# Build the installable plugin zip: dist/kenakata-kit-<version>.zip with a "kenakata-kit/" root folder and
# forward-slash paths (Windows PowerShell's Compress-Archive writes backslashes, which break on Linux servers).
# Usage: python dev/build-zip.py
import os
import re
import sys
import zipfile

here = os.path.dirname(os.path.abspath(__file__))
src = os.path.join(here, '..', 'kenakata-kit')
dist = os.path.join(here, '..', 'dist')
main = open(os.path.join(src, 'kenakata-kit.php'), encoding='utf-8').read()
version = re.search(r"define\( 'KK_VERSION', '([^']+)' \)", main).group(1)
header = re.search(r'\* Version:\s+(\S+)', main).group(1)
if version != header:
    sys.exit(f'Version mismatch: header {header}, KK_VERSION {version}')
os.makedirs(dist, exist_ok=True)
out = os.path.join(dist, f'kenakata-kit-{version}.zip')
skip_dirs = {'.git', 'node_modules', 'tools', 'dev'}
count = 0
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for root, dirs, files in os.walk(src):
        dirs[:] = [d for d in dirs if d not in skip_dirs and not d.startswith('.')]
        for f in files:
            if f.startswith('.'):
                continue
            p = os.path.join(root, f)
            z.write(p, 'kenakata-kit/' + os.path.relpath(p, src).replace(os.sep, '/'))
            count += 1
print(f'{out} ({count} files, {os.path.getsize(out) // 1024} KB)')
