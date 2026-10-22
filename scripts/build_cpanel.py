#!/usr/bin/env python3
"""Create two cPanel install archives; both reflect current checkout exactly."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED

ROOT = Path(__file__).resolve().parent.parent
DIST = ROOT / 'dist'
DIST.mkdir(exist_ok=True)
with ZipFile(DIST / 'zohal-cpanel-private.zip', 'w', ZIP_DEFLATED) as z:
    for relative in ('src/app.php', 'src/catalog.php', 'public/index.php', '.env.example', 'README.md'):
        z.write(ROOT / relative, 'zohal-services/' + relative)
    z.writestr('zohal-services/storage/.gitkeep', '')
with ZipFile(DIST / 'zohal-cpanel-public_html.zip', 'w', ZIP_DEFLATED) as z:
    for relative in ('public/.htaccess', 'public/assets/app.css', 'public/assets/app.js'):
        z.write(ROOT / relative, relative.removeprefix('public/'))
    z.write(ROOT / 'hosting/public_html/index.php', 'index.php')
print('Built cPanel archives:')
for path in sorted(DIST.glob('*.zip')):
    print(path.name, path.stat().st_size, 'bytes')
