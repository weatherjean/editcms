#!/usr/bin/env python3
"""Run real local servers against isolated files; never read installation data."""
import contextlib, http.client, json, os, shutil, signal, socket, subprocess, sys, tempfile, time, zipfile
from pathlib import Path
REPO = Path(__file__).resolve().parent.parent

def port():
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        return s.getsockname()[1]

def request(p, path):
    c = http.client.HTTPConnection('127.0.0.1', p, timeout=5)
    c.request('GET', path)
    r = c.getresponse()
    result = r.status, r.read(), dict(r.getheaders())
    c.close()
    return result

with tempfile.TemporaryDirectory(prefix='editcms-boundaries-') as directory:
    root = Path(directory).resolve()
    app = root / '_edit'
    if len(sys.argv)>1:
        with zipfile.ZipFile(sys.argv[1]) as z:
            assert all(not x.startswith('/') and '..' not in Path(x).parts for x in z.namelist())
            z.extractall(root)
    else:
        for folder in ('api','admin-api','core'):
            shutil.copytree(REPO/'_edit'/folder, app/folder)
        for filename in ('.htaccess','nginx.conf'):
            shutil.copyfile(REPO/'_edit'/filename, app/filename)
        for folder, source in [('uploads','uploads'),('data','data'),('admin','admin-source')]:
            (app/folder).mkdir(parents=True,exist_ok=True)
            shutil.copyfile(REPO/'_edit'/source/'.htaccess',app/folder/'.htaccess')
    shutil.copyfile(REPO/'router.php',root/'router.php')
    files = {
        'config.php':'PRIVATE SENTINEL', 'data/database/site.sqlite':'PRIVATE SENTINEL',
        'data/config/test.json':'PRIVATE SENTINEL', 'core/secret.txt':'PRIVATE SENTINEL',
        'admin-source/package.json':'PRIVATE SENTINEL', 'uploads/.secret':'PRIVATE SENTINEL',
        'uploads/2026/09/test.php':'<?php echo "EXECUTED";',
        'uploads/2026/09/test.php.jpg':'<?php echo "EXECUTED";',
        'uploads/2026/09/test.svg':'<svg/>',
        'uploads/2026/09/test.jpg':'SAFE IMAGE',
        'uploads/2026/09/test.pdf':'SAFE PDF',
        'admin/index.html':'ADMIN SPA', 'admin/assets/app.js':'ADMIN SCRIPT',
        'admin/altcha/LICENSE.txt':'MIT NOTICE',
        'api/index.php':'PUBLIC FRONT CONTROLLER', 'admin-api/index.php':'ADMIN FRONT CONTROLLER',
    }
    for name, value in files.items():
        p=app/name; p.parent.mkdir(parents=True,exist_ok=True); p.write_text(value)
    (app/'uploads/2026/09/link.jpg').symlink_to(app/'data/database/site.sqlite')
    denied=['/_edit/config.php','/_edit/data/database/site.sqlite','/_edit/data/config/test.json','/_edit/core/secret.txt','/_edit/admin-source/package.json','/_edit/.user.ini','/_edit/uploads/.secret','/_edit/uploads/2026/09/test.php','/_edit/uploads/2026/09/test.php.jpg','/_edit/uploads/2026/09/test.svg','/_edit/uploads/2026/09/link.jpg','/_edit/uploads/2026/09/test.php/extra.jpg','/_edit/uploads/%2e%2e/data/database/site.sqlite','/_edit/uploads/2026/09/%2e%2e/%2e%2e/%2e%2e/data/database/site.sqlite']
    for server in ('php','nginx','apache'):
        p=port()
        if server=='php':
            command=['php','-S',f'127.0.0.1:{p}',str(root/'router.php')]
        elif server=='nginx':
            config=root/'nginx-test.conf'
            nginx_dir = next((p for p in (Path('/etc/nginx'), Path('/opt/homebrew/etc/nginx')) if (p/'fastcgi_params').is_file()), None)
            if nginx_dir is None: raise RuntimeError('Install nginx to run deployment tests')
            shutil.copyfile(nginx_dir/'fastcgi_params',root/'fastcgi_params')
            config.write_text(f'''daemon off;
master_process off;
pid {root}/nginx.pid;
error_log {root}/nginx-error.log;
events {{ worker_connections 64; }}
http {{
 access_log off;
 client_body_temp_path {root}/client;
 proxy_temp_path {root}/proxy;
 fastcgi_temp_path {root}/fastcgi;
 uwsgi_temp_path {root}/uwsgi;
 scgi_temp_path {root}/scgi;
 include {nginx_dir}/mime.types;
 server {{
 listen 127.0.0.1:{p};
 root {root};
 include {app}/nginx.conf;
 location ~ \\.php {{ return 418; }}
 }}
}}
''')
            command=['nginx','-e',str(root/'nginx-error.log'),'-p',str(nginx_dir)+'/','-c',str(config)]
        else:
            config=root/'httpd.conf'
            modules=['mpm_prefork','authz_core','authz_host','unixd','dir','mime','rewrite','headers']
            module_dir = Path('/usr/lib/apache2/modules') if Path('/usr/lib/apache2/modules').is_dir() else Path('/usr/libexec/apache2')
            apache = shutil.which('apache2') or shutil.which('httpd') or '/usr/sbin/httpd'
            mime_types = '/etc/mime.types' if Path('/etc/mime.types').is_file() else '/etc/apache2/mime.types'
            # Some platforms compile unixd into the server instead of shipping a module.
            config.write_text('\n'.join(f'LoadModule {m}_module {module_dir}/mod_{m}.so' for m in modules if (module_dir/f'mod_{m}.so').is_file())+f'''
ServerRoot "{root}"
DefaultRuntimeDir "{root}"
ServerName localhost
Listen 127.0.0.1:{p}
PidFile "{root}/httpd.pid"
ErrorLog "{root}/httpd-error.log"
DocumentRoot "{root}"
TypesConfig {mime_types}
<Directory "{root}">
 Require all granted
 AllowOverride All
 Options FollowSymLinks
 DirectoryIndex index.html index.php
</Directory>
''')
            command=[apache,'-f',str(config),'-DFOREGROUND']
        log=open(root/(server+'.log'),'w+')
        proc=subprocess.Popen(command,cwd=root,stdout=log,stderr=log,start_new_session=True)
        try:
            for _ in range(60):
                if proc.poll() is not None:
                    log.seek(0); raise RuntimeError(f'{server} failed: '+log.read())
                try:
                    request(p,'/_edit/admin/index.html'); break
                except OSError: time.sleep(.05)
            for path in denied:
                status,body,_=request(p,path)
                assert status in (400,403,404), (server,path,status,body)
                assert b'PRIVATE SENTINEL' not in body, (server,path)
            for path,expected in [('/_edit/admin/index.html',b'ADMIN SPA'),('/_edit/admin/edit/property',b'ADMIN SPA'),('/_edit/admin/assets/app.js',b'ADMIN SCRIPT'),('/_edit/admin/altcha/LICENSE.txt',b'MIT NOTICE'),('/_edit/uploads/2026/09/test.jpg',b'SAFE IMAGE'),('/_edit/uploads/2026/09/test.pdf',b'SAFE PDF')]:
                status,body,headers=request(p,path)
                assert status==200 and body==expected,(server,path,status,body)
                if '/uploads/' in path:
                    assert headers.get('X-Content-Type-Options')=='nosniff',(server,headers)
            # Apache stubs prove rewrite destinations; nginx has no local FPM service.
            for api in ('api','admin-api'):
                status,body,_=request(p,f'/_edit/{api}/public/property?limit=2')
                if server=='nginx': assert status==502,(server,status,body)
                else: assert status==200 and b'FRONT CONTROLLER' in body,(server,status,body)
            print(f'PASS: {server} private paths, traversal, symlinks, uploads, SPA, assets and API routing')
        finally:
            if proc.poll() is None:
                os.killpg(proc.pid,signal.SIGTERM)
                proc.wait(timeout=10)
            log.close()
