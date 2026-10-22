import unittest
from pathlib import Path
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parent.parent

class DeploymentTest(unittest.TestCase):
    def test_bundles_have_current_sources(self):
        with ZipFile(ROOT / 'dist/zohal-cpanel-private.zip') as private:
            for path in ('src/app.php', 'src/catalog.php', 'public/index.php', '.env.example', 'README.md'):
                self.assertEqual(private.read('zohal-services/' + path), (ROOT / path).read_bytes())
            self.assertNotIn('zohal-services/.env', private.namelist())
            self.assertNotIn('zohal-services/storage/app.sqlite', private.namelist())
        with ZipFile(ROOT / 'dist/zohal-cpanel-public_html.zip') as public:
            self.assertEqual(public.read('index.php'), (ROOT / 'hosting/public_html/index.php').read_bytes())
            for path in ('assets/app.js','assets/app.css','.htaccess'):
                self.assertEqual(public.read(path),(ROOT / 'public' / path).read_bytes())
            self.assertNotIn('.env', public.namelist())
            self.assertNotIn('src/app.php', public.namelist())

if __name__ == '__main__':
    unittest.main()
