#!/usr/bin/env python3
"""Exercise authenticated multipart uploads in a disposable installation."""
import base64, http.client, json, os, shutil, signal, socket, subprocess, sys, tempfile, time, zipfile
from pathlib import Path
REPO=Path(__file__).resolve().parent.parent
with tempfile.TemporaryDirectory(prefix='editcms-upload-') as directory:
    root=Path(directory).resolve(); app=root/'_edit'
    if len(sys.argv)>1:
        with zipfile.ZipFile(sys.argv[1]) as z:
            assert all(not x.startswith('/') and '..' not in Path(x).parts for x in z.namelist())
            z.extractall(root)
    else:
        for folder in ('core','api','admin-api'): shutil.copytree(REPO/'_edit'/folder,app/folder)
    shutil.copyfile(REPO/'router.php',root/'router.php')
    (app/'config.php').write_text("<?php\ndefine('EDIT_ENCRYPTION_KEY','temporary-upload-fixture');\ndefine('EDIT_DATABASE_PATH',EDIT_BASE_PATH.'/data/database/site.sqlite');\ndefine('EDIT_CORS_ORIGINS',[]);\ndefine('EDIT_DEBUG',false);\ndefine('EDIT_SESSION_EXPIRY_HOURS',24);\ndefine('EDIT_MAX_PAGE_LIMIT',100);\ndefine('EDIT_MAX_FILE_SIZE',52428800);\ndefine('EDIT_MAX_IMAGE_WIDTH',4000);\ndefine('EDIT_MAX_IMAGE_HEIGHT',4000);\n")
    subprocess.run(['php',str(REPO/'tests/seed-email-fixture.php'),str(root),'1'],check=True)
    with socket.socket() as s: s.bind(('127.0.0.1',0)); port=s.getsockname()[1]
    def request(path,body=None,headers={}):
        c=http.client.HTTPConnection('127.0.0.1',port,timeout=5)
        c.request('POST' if body is not None else 'GET',path,body,headers)
        r=c.getresponse(); result=(r.status,r.read(),dict(r.getheaders())); c.close(); return result
    with open(root/'server.log','w+') as log:
        proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','router.php'],cwd=root,stdout=log,stderr=log,start_new_session=True)
        try:
            for _ in range(60):
                try: request('/_edit/api/health'); break
                except OSError: time.sleep(.05)
            status,body,_=request('/_edit/admin-api/auth/login',json.dumps({'email':'admin@example.invalid','password':'FixturePassword789!'}),{'Content-Type':'application/json'})
            assert status==200,(status,body)
            token=json.loads(body)['token']
            png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')
            def upload(name,data,authorized=True):
                boundary='editcms-fixture-boundary'
                body=(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{name}"\r\nContent-Type: image/png\r\n\r\n').encode()+data+f'\r\n--{boundary}--\r\n'.encode()
                headers={'Content-Type':'multipart/form-data; boundary='+boundary}
                if authorized: headers['Authorization']='Bearer '+token
                return request('/_edit/admin-api/media',body,headers)
            assert upload('image.png',png,False)[0]==401
            for name in ('image.php','image.PHTML','image.php.jpg','image','../../image.php'):
                status,body,_=upload(name,png+b'<?php echo "EXECUTION_SENTINEL"; ?>')
                assert status==200,(status,body)
                media=json.loads(body)
                assert media['path'].endswith('.png') and len(Path(media['path']).stem)==32,media
                status,body,headers=request('/_edit/uploads/'+media['path'])
                assert status==200 and body.startswith(png) and b'<?php' in body,(status,body)
                assert headers['X-Content-Type-Options']=='nosniff'
            assert upload('image.png',b'<?php echo "bad"; ?>')[0]==400
            assert upload('image.svg',b'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')[0]==400
            print('PASS: authenticated uploads, spoofed extensions, random names, static polyglot serving, PHP and SVG rejection')
        except Exception:
            log.flush(); log.seek(0); print(log.read()); raise
        finally:
            os.killpg(proc.pid,signal.SIGTERM);proc.wait(timeout=10)
