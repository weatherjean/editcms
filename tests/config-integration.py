#!/usr/bin/env python3
"""Exercise actual multipart imports, concurrent publication and legacy HTML on an isolated build."""
import concurrent.futures, http.client, io, json, os, re, shutil, signal, socket, sqlite3, subprocess, sys, tempfile, time, zipfile
from pathlib import Path
REPO=Path(__file__).resolve().parent.parent
with tempfile.TemporaryDirectory(prefix='edit-config-http-') as directory:
    root=Path(directory);app=root/'_edit'
    with zipfile.ZipFile(sys.argv[1]) as z:z.extractall(root)
    shutil.copyfile(REPO/'router.php',root/'router.php')
    issued=subprocess.run(['php',str(app/'core/Operations/setup.php')],check=True,capture_output=True,text=True)
    code=re.search(r'\b[a-f0-9]{64}\b',issued.stdout).group()
    with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
    def request(path,data=None,token=None,method=None,filename=None):
        headers={}
        if filename:
            boundary='edit-fixture-boundary'
            headers['Content-Type']='multipart/form-data; boundary='+boundary
            body=(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{filename}"\r\nContent-Type: application/octet-stream\r\n\r\n').encode()+data+f'\r\n--{boundary}--\r\n'.encode()
        else:
            headers['Content-Type']='application/json';body=json.dumps(data) if data is not None else None
        if token:headers['Authorization']='Bearer '+token
        conn=http.client.HTTPConnection('127.0.0.1',port,timeout=15)
        conn.request(method or ('POST' if data is not None else 'GET'),'/_edit/'+path,body,headers)
        response=conn.getresponse();body=response.read();status=response.status;conn.close()
        try:body=json.loads(body)
        except ValueError:pass
        return status,body
    def archive(files):
        out=io.BytesIO()
        with zipfile.ZipFile(out,'w') as z:
            for name,value in files.items():z.writestr(name,value)
        return out.getvalue()
    with open(root/'server.log','w+') as log:
        proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','router.php'],cwd=root,env={**os.environ,'PHP_CLI_SERVER_WORKERS':'4'},stdout=log,stderr=log,start_new_session=True)
        try:
            for _ in range(80):
                try:request('api/health');break
                except OSError:time.sleep(.05)
            status,admin=request('admin-api/auth/register',{'name':'Fixture','email':'admin@example.invalid','password':'FixturePassword789!','setup_code':code});assert status==200,admin
            token=admin['token']
            cfg={'post_types':[{'key':'property','label':'Property','label_plural':'Properties'}],'field_groups':[{'key':'details','title':'Details','locations':['property'],'fields':[{'key':'body','type':'html'}]}]}
            status,result=request('admin-api/config/modules',json.dumps(cfg).encode(),token,filename='property.json');assert status==200,result
            pointer=(app/'data/config/.active.json').read_bytes()
            bad=archive({'modules/property.json':json.dumps(cfg),'blocks/bad.json':'{'})
            assert request('admin-api/config/import',bad,token,filename='config.zip')[0]==400
            assert (app/'data/config/.active.json').read_bytes()==pointer
            def upload(i):
                block={'key':f'block{i}','label':'Block','fields':[{'key':'body','type':'wysiwyg'}]}
                return request('admin-api/config/blocks',json.dumps(block).encode(),token,filename=f'block{i}.json')
            with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
                results=list(pool.map(upload,(1,2)))
            assert all(status==200 for status,_ in results),results
            status,export=request('admin-api/config/export',token=token);assert status==200
            with zipfile.ZipFile(io.BytesIO(export)) as z:
                assert all(name in z.namelist() for name in ('modules/property.json','blocks/block1.json','blocks/block2.json'))
            status,item=request('admin-api/property',{'slug':'safe','status':'published','fields':{'details':{'body':'<p onclick="evil()">Hello<script>evil()</script></p>'}}},token);assert status==201,item
            assert item['fields']['details']['body']=='<p>Hello</p>',item
            db=sqlite3.connect(app/'data/database/site.sqlite')
            db.execute('UPDATE content_meta SET meta_value=? WHERE content_id=? AND meta_key=?',('<p onclick="evil()">Legacy<script>evil()</script></p>',item['id'],'details.body'));db.commit();db.close()
            for path in ('admin-api/property/'+str(item['id']),'api/public/property/safe'):
                status,body=request(path,token=token);assert status==200,(status,body)
                assert body['fields']['details']['body']=='<p>Legacy</p>',body
            assert request('admin-api/config/import',archive({'../escape.json':'{}'}),token,filename='bad.zip')[0]==400
            print('PASS: multipart upload/import/export, invalid import rollback, concurrent writers retain both updates, stored and legacy HTML sanitized through authenticated/public HTTP')
        except Exception:
            log.flush();log.seek(0);print(log.read());raise
        finally:
            os.killpg(proc.pid,signal.SIGTERM);proc.wait(timeout=10)
