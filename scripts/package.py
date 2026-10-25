#!/usr/bin/env python3
"""Build a reproducible phpBB install ZIP without development files."""
import argparse
import json
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo

ROOT = Path(__file__).resolve().parents[1]
RUNTIME_DIRS = ("acp", "adm", "config", "cron", "language", "migrations", "service")
RUNTIME_FILES = ("composer.json", "LICENSE", "README.md", "SECURITY.md", "ROADMAP.md")


def build(output_dir):
    metadata = json.loads((ROOT / "composer.json").read_text())
    name = metadata["name"]
    if name != "bca/antispam" or metadata["type"] != "phpbb-extension":
        raise ValueError("Unexpected extension identity")
    version = metadata["version"]
    if not version or any(c not in "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.-" for c in version):
        raise ValueError("Unsafe package version")
    files = [ROOT / name for name in RUNTIME_FILES]
    for directory in RUNTIME_DIRS:
        files.extend(path for path in (ROOT / directory).rglob("*") if path.is_file())
    output_dir.mkdir(parents=True, exist_ok=True)
    output = output_dir / ("bca-antispam-" + version + ".zip")
    with ZipFile(output, "w", compression=ZIP_DEFLATED) as archive:
        for path in sorted(files):
            if path.is_symlink() or not path.is_file():
                raise ValueError("Missing or unsafe package file: " + str(path))
            entry = ZipInfo(name + "/" + path.relative_to(ROOT).as_posix(), (1980, 1, 1, 0, 0, 0))
            entry.compress_type = ZIP_DEFLATED
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            archive.writestr(entry, path.read_bytes())
    return output


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output-dir", type=Path, default=ROOT / "dist")
    args = parser.parse_args()
    print(build(args.output_dir))
