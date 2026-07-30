#!/usr/bin/env python3
"""Fresh-install and role HTTP tests, including competing first-account claims."""
import concurrent.futures, http.client, json, os, re, shutil, signal, socket, sqlite3, subprocess, sys, tempfile, time, zipfile
from pathlib import Path
REPO=Path(__file__).resolve().parent.parent
with tempfile.TemporaryDirectory(prefix='editcms-setup-') as directory:
    root=Path(directory).resolve();app=root/'_edit'
    archive=Path(sys.argv[1]) if len(sys.argv)>1 else REPO/'dist/_edit-recovery.zip'
    with zipfile.ZipFile(archive) as z:z.extractall(root)
    if len(sys.argv)==1:
        for folder in ('core','api','admin-api'):
            shutil.rmtree(app/folder);shutil.copytree(REPO/'_edit'/folder,app/folder)
    shutil.copyfile(REPO/'router.php',root/'router.php')
    # Competing first bootstraps must retain one encryption key without printing it.
    def bootstrap(_):
        result=subprocess.run(['php','-r',"require $argv[1]; echo hash('sha256', EDIT_ENCRYPTION_KEY);",str(app/'core/bootstrap.php')],check=True,capture_output=True,text=True)
        return result.stdout
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool: hashes=list(pool.map(bootstrap,range(4)))
    assert len(set(hashes))==1 and len(hashes[0])==64
    assert (app/'config.php').stat().st_mode & 0o777==0o600
    # Only this local CLI can generate the setup code; do not print the code.
    issued=subprocess.run(['php',str(app/'core/Operations/setup.php')],check=True,capture_output=True,text=True)
    code=re.search(r'\b[a-f0-9]{64}\b',issued.stdout).group()
    cfg={'post_types':[{'key':'property','label':'Property','label_plural':'Properties'}],'field_groups':[{'key':'details','locations':['property'],'fields':[{'key':'title','type':'text'}]}]}
    (app/'data/config/modules/property.json').write_text(json.dumps(cfg))
    with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
    def request(path,data=None,token=None,method=None):
        headers={'Content-Type':'application/json'}
        if token:headers['Authorization']='Bearer '+token
        conn=http.client.HTTPConnection('127.0.0.1',port,timeout=10)
        conn.request(method or ('POST' if data is not None else 'GET'),'/_edit/'+path,json.dumps(data) if data is not None else None,headers)
        response=conn.getresponse();body=response.read();status=response.status;conn.close()
        try:body=json.loads(body)
        except ValueError:pass
        return status,body
    with open(root/'server.log','w+') as log:
        proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','router.php'],cwd=root,env={**os.environ,'PHP_CLI_SERVER_WORKERS':'4'},stdout=log,stderr=log,start_new_session=True)
        try:
            for _ in range(80):
                try:request('api/health');break
                except OSError:time.sleep(.05)
            base={'name':'Fixture Admin','email':'admin@example.invalid','password':'FixturePassword789!'}
            assert request('admin-api/auth/register',base)[0]==403
            def claim(i):return request('admin-api/auth/register',{**base,'email':f'admin{i}@example.invalid','setup_code':code})
            with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:results=list(pool.map(claim,(1,2)))
            assert sorted(x[0] for x in results)==[200,403],results
            admin=next(value for status,value in results if status==200);token=admin['token'];admin_id=admin['user']['id']
            assert admin['user']['role']=='admin'
            db=sqlite3.connect(app/'data/database/site.sqlite')
            assert db.execute('SELECT count(*) FROM users').fetchone()[0]==1
            assert db.execute("SELECT count(*) FROM settings WHERE key='setup_token_hash'").fetchone()[0]==0
            db.close()
            assert subprocess.run(['php',str(app/'core/Operations/setup.php')],capture_output=True).returncode!=0
            # The administrator creates a default editor and a second administrator.
            status,editor=request('admin-api/users',{'name':'Editor','email':'editor@example.invalid','password':'FixturePassword789!'},token)
            assert status==200 and editor['role']=='editor',(status,editor)
            status,editor_login=request('admin-api/auth/login',{'email':'editor@example.invalid','password':'FixturePassword789!'})
            assert status==200
            editorial=editor_login['token']
            for method,path in [('GET','users'),('POST','users'),('PUT',f'users/{admin_id}'),('PUT',f'users/{editor["id"]}/role'),('DELETE',f'users/{admin_id}'),('GET','config'),('GET','config/export'),('POST','config/import'),('GET','email-settings'),('PUT','email-settings'),('GET','email-logs'),('POST','email-test')]:
                status,body=request('admin-api/'+path,{} if method!='GET' else None,editorial,method)
                assert status==403,(method,path,status,body)
            for path in ('auth/me','post-types','field-groups','blocks','media'):
                assert request('admin-api/'+path,token=editorial)[0]==200,path
            status,item=request('admin-api/property',{'slug':'editor-created','status':'published','fields':{'details':{'title':'Created by editor'}}},editorial)
            assert status==201,(status,item)
            assert request(f'admin-api/property/{item["id"]}',{'slug':'editor-updated'},editorial,'PUT')[0]==200
            assert request('api/public/property/editor-updated')[0]==200
            assert request(f'admin-api/users/{admin_id}/role',{'role':'editor'},token,'PUT')[0]==409
            assert request(f'admin-api/users/{admin_id}',token=token,method='DELETE')[0]==400
            status,other=request('admin-api/users',{'name':'Other admin','email':'other@example.invalid','password':'FixturePassword789!','role':'admin'},token)
            assert status==200
            status,other_login=request('admin-api/auth/login',{'email':'other@example.invalid','password':'FixturePassword789!'})
            assert status==200
            status,_=request(f'admin-api/users/{other["id"]}/role',{'role':'editor'},token,'PUT');assert status==200
            assert request('admin-api/users',token=other_login['token'])[0]==401
            assert request(f'admin-api/users/{other["id"]}',token=token,method='DELETE')[0]==200
            print('PASS: CLI-only setup code, competing first-account claims, default editor, server-side role denial, editor workflow, last-admin protection and role-change session revocation')
        except Exception:
            log.flush();log.seek(0);print(log.read());raise
        finally:
            os.killpg(proc.pid,signal.SIGTERM);proc.wait(timeout=10)
