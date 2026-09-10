#!/usr/bin/env python3
"""Verify that a release contains runtime files and excludes development/site data."""
import sys, zipfile
from pathlib import PurePosixPath
with zipfile.ZipFile(sys.argv[1]) as archive:
    names = set(archive.namelist())
    required = {
        '_edit/admin/index.html', '_edit/admin-api/index.php', '_edit/api/index.php',
        '_edit/core/bootstrap.php', '_edit/nginx.conf', '_edit/.htaccess',
        '_edit/uploads/.htaccess', '_edit/data/.htaccess', '_edit/LICENSE',
        '_edit/DEPLOYMENT.md',
        '_edit/core/ThirdParty/HTMLPurifier/LICENSE', '_edit/core/ThirdParty/Altcha/LICENSE.txt',
        '_edit/admin/licenses/DOMPurify-LICENSE.txt', '_edit/admin/altcha/altcha.min.js',
    }
    assert required <= names, 'Missing release files: ' + str(required - names)
    for name in names:
        path = PurePosixPath(name)
        assert not path.is_absolute() and '..' not in path.parts, name
        assert name != '_edit/config.php', name
        if len(path.parts) == 2 and path.suffix == '.md':
            assert name == '_edit/DEPLOYMENT.md', 'Internal report in release: ' + name
        assert not any(part in ('node_modules', 'admin-source', '.git', '.versions', '__pycache__') for part in path.parts), name
        assert not name.endswith(('.sqlite', '.db', '.pyc', '.active.json', '.lock')), name
        assert not (name.startswith('_edit/data/config/') and name.endswith('.json')), name
    health = archive.read('_edit/api/routes/health.php')
    assert b'{{HASH_' not in health, 'Unresolved server-rule hashes'
print('PASS: complete release archive, licenses, server-rule hashes and no local runtime data')
