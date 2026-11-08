#!/usr/bin/env python3
"""Install and test the real phpBB package on an empty disposable MariaDB database."""
import argparse
import importlib.util
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
import uuid
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parents[2]
HERE = Path(__file__).resolve().parent
PHPBB_REVISION = "10356c2c3be3804402c7616e18bb53d54c02f143"  # phpBB 3.3.19
LEGACY_REVISION = "ad84ce8faa647be6cfd0183f726e44cad5f6d338"  # pre-context 0.1.0-beta
spec = importlib.util.spec_from_file_location("package", ROOT / "scripts/package.py")
package = importlib.util.module_from_spec(spec)
spec.loader.exec_module(package)


def command(args, cwd, environment, log):
    result = subprocess.run(args, cwd=cwd, env=environment, capture_output=True, text=True)
    log.write_text(result.stdout + result.stderr)
    if result.returncode:
        # The retained local log has details; never dump installer config or environment.
        raise RuntimeError("Command failed; see " + str(log))
    return result


def replace_extension(board, archive):
    if not (board / ".antispam-integration").is_file():
        raise RuntimeError("Missing disposable board marker")
    extension = board / "ext/bca/antispam"
    if extension.exists():
        shutil.rmtree(extension)
    with ZipFile(archive) as bundle:
        bundle.extractall(board / "ext")


def legacy_package(output):
    names = subprocess.check_output(
        ["git", "ls-tree", "-r", "--name-only", LEGACY_REVISION], cwd=ROOT, text=True
    ).splitlines()
    with ZipFile(output, "w") as bundle:
        for name in names:
            if name in package.RUNTIME_FILES or any(name.startswith(folder + "/") for folder in package.RUNTIME_DIRS):
                content = subprocess.check_output(["git", "show", LEGACY_REVISION + ":" + name], cwd=ROOT)
                bundle.writestr("bca/antispam/" + name, content)
    return output


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--phpbb-checkout", type=Path, required=True)
    parser.add_argument("--php", default="php")
    parser.add_argument("--db-host", default="127.0.0.1")
    parser.add_argument("--db-port", type=int, default=3306)
    parser.add_argument("--db-user", default="root")
    parser.add_argument("--db-name", default="antispam_integration")
    parser.add_argument("--results", type=Path, default=ROOT / "test-results/integration")
    parser.add_argument("--keep-board", action="store_true")
    args = parser.parse_args()
    if not re.fullmatch(r"antispam_(test|integration)(_[a-z0-9]+)?", args.db_name):
        parser.error("Use a disposable antispam_test or antispam_integration database")
    if args.db_host not in {"127.0.0.1", "localhost", "mariadb"}:
        parser.error("Only local/container test database hosts are accepted")
    checkout = args.phpbb_checkout.resolve()
    revision = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=checkout, text=True).strip()
    if revision != PHPBB_REVISION:
        parser.error("phpBB checkout must match the pinned 3.3.19 revision: " + PHPBB_REVISION)
    source = checkout / "phpBB"
    if not (source / "vendor/autoload.php").is_file():
        parser.error("Install phpBB's locked production Composer dependencies first")
    if (source / "config.php").exists():
        parser.error("Pass an uninstalled phpBB source checkout, not an existing forum")
    args.results = args.results.resolve()
    args.results.mkdir(parents=True, exist_ok=True)
    nonce = uuid.uuid4().hex
    output = args.results / nonce
    output.mkdir()
    environment = dict(os.environ)
    environment.pop("BCA_ANTISPAM_OPENAI_API_KEY", None)
    environment.update(
        ANTISPAM_TEST_DB_HOST=args.db_host, ANTISPAM_TEST_DB_PORT=str(args.db_port),
        ANTISPAM_TEST_DB_USER=args.db_user, ANTISPAM_TEST_DB_NAME=args.db_name,
        ANTISPAM_TEST_DB_PASSWORD=os.environ.get("ANTISPAM_TEST_DB_PASSWORD", ""),
    )
    # Fail before copying/installing if the test database contains existing data.
    command([args.php, str(HERE / "check_database.php")], ROOT, environment, output / "database-check.log")
    work = Path(tempfile.mkdtemp(prefix="antispam-integration-"))
    board = work / "board"
    completed = False
    reports = []
    try:
        shutil.copytree(source, board, ignore=lambda path, names: {".git"})
        (board / ".antispam-integration").write_text("Disposable phpBB integration board\n")
        settings = {
            "admin": {"name": "integration_admin", "password": "Integration-test-only-33", "email": "admin@example.invalid"},
            "board": {"lang": "en", "name": "Anti-Spam Integration", "description": "Disposable integration board"},
            "database": {"dbms": "mysqli", "dbhost": args.db_host, "dbport": args.db_port,
                         "dbuser": args.db_user, "dbpasswd": environment["ANTISPAM_TEST_DB_PASSWORD"],
                         "dbname": args.db_name, "table_prefix": "phpbb_"},
            "email": {"enabled": False},
            "server": {"cookie_secure": False, "server_protocol": "http://", "force_server_vars": False,
                       "server_name": "localhost", "server_port": 80, "script_path": "/"},
        }
        yaml = ["installer:"]
        for section, values in settings.items():
            yaml.append("    " + section + ":")
            yaml.extend("        " + key + ": " + json.dumps(value) for key, value in values.items())
        yaml.append("    extensions: []")
        installer_config = work / "installer.yml"
        installer_config.write_text("\n".join(yaml) + "\n")
        installer_config.chmod(0o600)
        result = command([args.php, "install/phpbbcli.php", "install", str(installer_config), "--no-interaction"],
                         board, environment, output / "installer.log")
        if "installer has finished successfully" not in result.stdout:
            raise RuntimeError("Installer did not confirm completion; see " + str(output / "installer.log"))
        installer_config.unlink()
        shutil.rmtree(board / "install")  # Only the marked temporary board is modified.
        archive = package.build(work)
        replace_extension(board, archive)
        counter = 0

        def cli(operation):
            nonlocal counter
            counter += 1
            command([args.php, "bin/phpbbcli.php", "extension:" + operation, "bca/antispam", "--no-interaction"],
                    board, environment, output / (str(counter) + "-" + operation + ".log"))

        expected_cases = json.loads((HERE / "cases.json").read_text())

        def suite(phase, snapshot=None):
            report = output / (phase + ".json")
            invocation = [args.php, str(HERE / "suite.php"), str(board), str(report), nonce, phase]
            if snapshot:
                invocation.append(str(snapshot))
            result = command(invocation, ROOT, environment, output / (phase + ".log"))
            if not report.is_file():
                raise RuntimeError("phpBB stopped without a fresh completion report: " + str(output / (phase + ".log")))
            data = json.loads(report.read_text())
            if (data["run_id"] != nonce or data["phase"] != phase or not data["completed"]
                    or [case["name"] for case in data["cases"]] != expected_cases[phase]
                    or any(case["status"] != "passed" for case in data["cases"])
                    or data["assertions"] < len(expected_cases[phase])):
                raise RuntimeError("Incomplete or failed integration outcomes: " + str(report))
            print(result.stdout, end="")
            reports.append(data)
            return report

        cli("enable")
        current = suite("current")
        cli("disable")
        cli("enable")
        suite("verify-current", current)
        cli("disable")
        cli("purge")  # Tests only: removes extension fixtures, never an existing user's data.
        replace_extension(board, legacy_package(work / "legacy.zip"))
        cli("enable")
        legacy = suite("legacy")
        cli("disable")
        replace_extension(board, archive)
        cli("enable")
        suite("verify-upgrade", legacy)
        summary = {"run_id": nonce, "completed": True, "phpbb_revision": revision,
                   "legacy_revision": LEGACY_REVISION,
                   "cases": sum(len(report["cases"]) for report in reports),
                   "assertions": sum(report["assertions"] for report in reports)}
        (output / "summary.json").write_text(json.dumps(summary, indent=2))
        print("PASS: {cases} integration cases, {assertions} assertions.".format(**summary))
        print("Results: " + str(output))
        completed = True
    finally:
        if completed and not args.keep_board:
            shutil.rmtree(work)
        else:
            print("Disposable board retained: " + str(board), file=sys.stderr)
            print("Diagnostic logs: " + str(output), file=sys.stderr)
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (RuntimeError, subprocess.CalledProcessError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
