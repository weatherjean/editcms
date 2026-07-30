#!/usr/bin/env python3
"""Isolated real HTTP CRUD, filtering, validation and revision checks."""
import contextlib, http.client, json, os, shutil, signal, socket, subprocess, sys, tempfile, time, zipfile
from pathlib import Path
REPO=Path(__file__).resolve().parent.parent
with tempfile.TemporaryDirectory(prefix='editcms-content-') as directory:
    root=Path(directory).resolve(); app=root/'_edit'
    if len(sys.argv)>1:
        with zipfile.ZipFile(sys.argv[1]) as z:
            assert all(not x.startswith('/') and '..' not in Path(x).parts for x in z.namelist())
            z.extractall(root)
    else:
        for folder in ('core','api','admin-api'): shutil.copytree(REPO/'_edit'/folder,app/folder)
    shutil.copyfile(REPO/'router.php',root/'router.php')
    (app/'config.php').write_text("<?php\ndefine('EDIT_ENCRYPTION_KEY','temporary-content-fixture');\ndefine('EDIT_DATABASE_PATH',EDIT_BASE_PATH.'/data/database/site.sqlite');\ndefine('EDIT_CORS_ORIGINS',[]);\ndefine('EDIT_DEBUG',false);\ndefine('EDIT_SESSION_EXPIRY_HOURS',24);\ndefine('EDIT_MAX_PAGE_LIMIT',100);\n")
    subprocess.run(['php',str(REPO/'tests/seed-email-fixture.php'),str(root),'1'],check=True)
    module={'post_types':[{'key':'property','label':'Property','label_plural':'Properties','allow_open':True},{'key':'other','label':'Other','label_plural':'Others'}], 'field_groups':[{'key':'details','locations':['property'],'fields':[{'key':'price','type':'number','required':True,'config':{'min':0}},{'key':'active','type':'boolean','required':True},{'key':'private','type':'text','public':False}]}]}
    config=app/'data/config/modules/fixture.json';config.parent.mkdir(parents=True,exist_ok=True);config.write_text(json.dumps(module))
    with socket.socket() as s: s.bind(('127.0.0.1',0));port=s.getsockname()[1]
    def request(path,data=None,token=None,method=None,raw=None):
        headers={}
        body=raw if raw is not None else (json.dumps(data) if data is not None else None)
        if body is not None: headers['Content-Type']='application/json'
        if token: headers['Authorization']='Bearer '+token
        c=http.client.HTTPConnection('127.0.0.1',port,timeout=5)
        c.request(method or ('POST' if body is not None else 'GET'),'/_edit/'+path,body,headers)
        r=c.getresponse();body=r.read();status=r.status;c.close()
        try: body=json.loads(body)
        except ValueError: pass
        return status,body
    with open(root/'server.log','w+') as log:
        proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','router.php'],cwd=root,stdout=log,stderr=log,start_new_session=True)
        try:
            for _ in range(60):
                try: request('api/health');break
                except OSError:time.sleep(.05)
            status,login=request('admin-api/auth/login',{'email':'admin@example.invalid','password':'FixturePassword789!'})
            assert status==200,(status,login)
            token=login['token']
            assert request('admin-api/property',{'slug':'unauthorized'})[0]==401
            for raw in ('{','null','[]','"text"'):
                assert request('admin-api/property',token=token,raw=raw)[0]==400
            assert request('admin-api/property',token=token,raw='x'*2097153)[0]==413
            status,error=request('admin-api/property',{'slug':'incomplete','status':'published'},token)
            assert status==422 and 'details.price' in error['error'],(status,error)
            ids=[]
            for price in (90000,1000000,200000,0):
                status,item=request('admin-api/property',{'slug':f'p-{price}','status':'published','fields':{'details':{'price':price,'active':False,'private':'PRIVATE SENTINEL'}}},token)
                assert status==201,(status,item)
                assert item['fields']['details']['price']==price and item['fields']['details']['active'] is False
                ids.append(item['id'])
            for alias in ('api','admin-api'):
                status,result=request(alias+'/public/property?fields[price_gte]=200000&order_by=details.price&order_dir=ASC&limit=1')
                assert status==200 and result['data'][0]['slug']=='p-200000' and result['meta']['total']==2 and result['meta']['has_more'],(status,result)
                assert 'PRIVATE SENTINEL' not in json.dumps(result)
                status,result=request(alias+'/public/property?fields[details.price_gte]=200000&order_by=price&order_dir=ASC&limit=1&offset=1')
                assert result['data'][0]['slug']=='p-1000000' and not result['meta']['has_more'],(status,result)
                for query in ('fields[private]=x','order_by=private','fields[price_gte][]=1','limit=1x','populate[]=x'):
                    assert request(alias+'/public/property?'+query)[0]==400,query
            # Admin queries use the same numeric contract and retain private fields.
            status,result=request('admin-api/property?fields[price_lte]=90000&order_by=price&order_dir=DESC',token=token)
            assert status==200 and [x['slug'] for x in result]==['p-90000','p-0'],(status,result)
            target=ids[0]
            status,_=request(f'admin-api/property/{target}',{'slug':'changed'},token,'PUT');assert status==200
            status,revisions=request(f'admin-api/property/{target}/revisions',token=token)
            assert status==200 and len(revisions)==1,(status,revisions)
            revision=revisions[0]['id']
            status,error=request(f'admin-api/property/{target}',{'fields':{}},token,'PUT');assert status==422,(status,error)
            assert len(request(f'admin-api/property/{target}/revisions',token=token)[1])==1
            status,item=request(f'admin-api/property/{target}/revisions/{revision}/restore',{},token)
            assert status==200 and item['slug']=='p-90000' and item['fields']['details']['price']==90000,(status,item)
            assert len(request(f'admin-api/property/{target}/revisions',token=token)[1])==2
            assert request(f'admin-api/other/{target}/revisions',token=token)[0]==404
            assert request(f'admin-api/property/{ids[1]}/revisions/{revision}/restore',{},token)[0]==422
            status,draft=request('admin-api/property',{'slug':'draft'},token);assert status==201
            assert request(f"admin-api/property/{draft['id']}",{'status':'published'},token,'PUT')[0]==422
            print('PASS: real HTTP numeric queries on both public aliases/admin, visibility, bounded inputs, 422 validation, draft/publish and atomic revisions')
        except Exception:
            log.flush();log.seek(0);print(log.read());raise
        finally:
            os.killpg(proc.pid,signal.SIGTERM);proc.wait(timeout=10)
