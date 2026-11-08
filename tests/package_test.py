"""Verify the downloadable archive can be installed without renaming folders."""
import importlib.util
import json
import shutil
import subprocess
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("package", ROOT / "scripts/package.py")
package = importlib.util.module_from_spec(spec)
spec.loader.exec_module(package)


class InstallPackageTest(unittest.TestCase):
    def test_local_server_files_are_not_shipped(self):
        with tempfile.TemporaryDirectory() as directory:
            fixture = Path(directory) / "checkout"
            fixture.mkdir()
            for folder in package.RUNTIME_DIRS:
                shutil.copytree(ROOT / folder, fixture / folder)
            for name in package.RUNTIME_FILES:
                shutil.copyfile(ROOT / name, fixture / name)
            subprocess.run(["git", "init", "-q", str(fixture)], check=True)
            subprocess.run(["git", "-C", str(fixture), "add", "."], check=True)
            # Fixtures contain no real credentials. Neither ignored-looking files
            # nor an arbitrary local PHP helper may enter the release.
            for relative in ["service/.env", "config/local.php", "service/backup.sql"]:
                (fixture / relative).write_text("local fixture")
            with patch.object(package, "ROOT", fixture):
                archive = package.build(Path(directory) / "output")
                with ZipFile(archive) as bundle:
                    names = bundle.namelist()
                    for relative in ["service/.env", "config/local.php", "service/backup.sql"]:
                        self.assertNotIn("bca/antispam/" + relative, names)
                # Even accidentally force-staged server files must be rejected.
                subprocess.run(["git", "-C", str(fixture), "add", "-f", "service/.env"], check=True)
                with self.assertRaisesRegex(ValueError, "deployment-only file"):
                    package.build(Path(directory) / "output")

    def test_install_layout_and_reproducibility(self):
        with tempfile.TemporaryDirectory() as directory:
            output = Path(directory)
            archive = package.build(output)
            original = archive.read_bytes()
            self.assertEqual(original, package.build(output).read_bytes())
            with ZipFile(archive) as bundle:
                self.assertIsNone(bundle.testzip())
                names = bundle.namelist()
                self.assertTrue(all(name.startswith("bca/antispam/") for name in names))
                self.assertFalse(any(part in {"tests", "scripts", ".github", ".git", "__pycache__"}
                                     for name in names for part in Path(name).parts))
                bundle.extractall(output / "ext")
            installed = output / "ext/bca/antispam"
            metadata = json.loads((installed / "composer.json").read_text())
            self.assertEqual(metadata["name"], "bca/antispam")
            self.assertEqual(metadata["type"], "phpbb-extension")
            for folder in package.RUNTIME_DIRS:
                for source in (ROOT / folder).rglob("*"):
                    if source.is_file():
                        self.assertEqual(source.read_bytes(), (installed / source.relative_to(ROOT)).read_bytes())
            for name in package.RUNTIME_FILES:
                self.assertEqual((ROOT / name).read_bytes(), (installed / name).read_bytes())


if __name__ == "__main__":
    unittest.main()
