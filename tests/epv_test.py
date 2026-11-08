"""Exercise the real EPV and ensure nonfatal findings fail our CI gate."""
import argparse
import importlib.util
from pathlib import Path
import subprocess
import tempfile
import unittest
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("package", ROOT / "scripts/package.py")
package = importlib.util.module_from_spec(spec)
spec.loader.exec_module(package)


class EPVGateTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.stage = Path(self.temporary.name) / "install"
        archive = package.build(Path(self.temporary.name) / "output")
        with ZipFile(archive) as bundle:
            bundle.extractall(self.stage)

    def run_gate(self):
        return subprocess.run(
            ["php", str(ROOT / "scripts/epv.php"), str(EPV_ROOT), str(self.stage)],
            capture_output=True, text=True,
        )

    def test_install_zip_has_no_findings(self):
        result = self.run_gate()
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn("Fatal: 0, Error: 0, Warning: 0, Notice: 0", result.stdout)

    def test_nonfatal_missing_license_fails_gate(self):
        (self.stage / "bca/antispam/license.txt").unlink()
        # Reproduce the upstream CLI behavior that would make CI falsely green.
        upstream = subprocess.run(
            ["php", str(EPV_ROOT / "src/EPV.php"), "run", "--dir=" + str(self.stage)],
            capture_output=True, text=True,
        )
        self.assertEqual(upstream.returncode, 0, upstream.stdout + upstream.stderr)
        result = self.run_gate()
        self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
        self.assertIn("Missing required license.txt", result.stdout)

    def test_sql_warning_fails_gate(self):
        scanner = self.stage / "bca/antispam/service/scanner.php"
        text = scanner.read_text()
        original = "WHERE forum_id = ' . (int) $forum_id"
        self.assertIn(original, text)
        scanner.write_text(text.replace(original, "WHERE forum_id = ' . $forum_id"))
        result = self.run_gate()
        self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
        self.assertIn("Found potential SQL injection", result.stdout)

    def test_incomplete_validation_fails_gate(self):
        (self.stage / "bca/antispam/composer.json").write_text("{ invalid")
        result = self.run_gate()
        self.assertNotEqual(result.returncode, 0, result.stdout + result.stderr)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--epv-root", type=Path, required=True)
    args, remaining = parser.parse_known_args()
    EPV_ROOT = args.epv_root.resolve()
    unittest.main(argv=[__file__, *remaining])
