#!/usr/bin/env python3
"""Disposable deployed sites only. No real site data, messages or credentials."""
import fcntl, http.client, json, os, shutil, signal, socket, sqlite3, subprocess, sys, tempfile, time, zipfile
from pathlib import Path
REPO=Path(__file__).resolve().parent.parent
with tempfile.TemporaryDirectory(prefix='editcms-recovery-') as temporary:
    root=Path(temporary).resolve(); site=root/'source';site.mkdir(); app=site/'_edit'
    archive_input=Path(sys.argv[1]) if len(sys.argv)>1 else REPO/'dist/_edit-content-fixes.zip'
    with zipfile.ZipFile(archive_input) as z:z.extractall(site)
    if len(sys.argv)==1:
        for folder in ('core','api','admin-api'):
            shutil.rmtree(app/folder);shutil.copytree(REPO/'_edit'/folder,app/folder)
    shutil.copyfile(REPO/'router.php',site/'router.php')
    (app/'config.php').write_text("<?php\ndefine('EDIT_ENCRYPTION_KEY','recovery-fixture-key');\ndefine('EDIT_DATABASE_PATH',EDIT_BASE_PATH.'/data/database/site.sqlite');\ndefine('EDIT_CORS_ORIGINS',[]);\ndefine('EDIT_DEBUG',false);\ndefine('EDIT_SESSION_EXPIRY_HOURS',24);\ndefine('EDIT_MAX_PAGE_LIMIT',100);\n")
    subprocess.run(['php',str(REPO/'tests/seed-email-fixture.php'),str(site),'1'],check=True)
    cfg={'post_types':[{'key':'property','label':'Property','label_plural':'Properties'}],'field_groups':[{'key':'details','title':'Details','locations':['property'],'fields':[{'key':'price','type':'number'}]}]}
    (app/'data/config/modules/property.json').write_text(json.dumps(cfg))
    subprocess.run(['php','-r',"require $argv[1].'/core/bootstrap.php'; (new Edit\\Core\\Configuration\\Store($argv[1].'/data/config'))->apply([]);",str(app)],check=True,capture_output=True)
    generation=json.loads((app/'data/config/.active.json').read_text())['generation']
    upload=app/'uploads/2026/09/photo.jpg';upload.parent.mkdir(parents=True);upload.write_bytes(b'fixture photo bytes')
    database=app/'data/database/site.sqlite'
    # Keep a live WAL connection: copying only the main database file would lose this row.
    db=sqlite3.connect(database);db.execute('PRAGMA journal_mode=WAL')
    db.execute("INSERT INTO content (type,slug,status) VALUES ('property','wal-property','published')")
    db.commit()
    assert Path(str(database)+'-wal').stat().st_size>0
    with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
    def request(path,data=None,token=None,method=None,raw=None):
        body=raw if raw is not None else (json.dumps(data) if data is not None else None)
        headers={'Content-Type':'application/json'}
        if token:headers['Authorization']='Bearer '+token
        conn=http.client.HTTPConnection('127.0.0.1',port,timeout=5)
        conn.request(method or ('POST' if body is not None else 'GET'),'/_edit/'+path,body,headers)
        response=conn.getresponse();value=response.read();status=response.status;conn.close()
        try:value=json.loads(value)
        except ValueError:pass
        return status,value
    def start(path):
        log=open(root/'http.log','a')
        proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','router.php'],cwd=path,stdout=log,stderr=log,start_new_session=True)
        for _ in range(60):
            try:request('api/health');break
            except OSError:time.sleep(.05)
        return proc,log
    def stop(proc,log):os.killpg(proc.pid,signal.SIGTERM);proc.wait(timeout=10);log.close()
    def command(*args,success=True):
        result=subprocess.run(['php',str(app/'core/Operations/backup.php'),*map(str,args)],capture_output=True,text=True)
        assert (result.returncode==0)==success,(args,result.stdout,result.stderr)
        return result
    proc,log=start(site)
    try:
        def login(password):
            status,value=request('admin-api/auth/login',{'email':'admin@example.invalid','password':password})
            return status,value
        status,first=login('FixturePassword789!');assert status==200
        status,second=login('FixturePassword789!');assert status==200
        token=first['token'];user=first['user']['id']
        assert request(f'admin-api/users/{user}',{'password':'weak'},token,'PUT')[0]==400
        assert request('admin-api/users',token=token)[0]==200
        for raw in ('null','"text"','{'):
            assert request(f'admin-api/users/{user}',token=token,method='PUT',raw=raw)[0]==400
        assert request('admin-api/users/999',{'password':'ReplacementPassword456!'},token,'PUT')[0]==404
        status,result=request(f'admin-api/users/{user}',{'password':'ReplacementPassword456!'},token,'PUT')
        assert status==200 and result['reauthenticate'],(status,result)
        for previous in (token,second['token']):assert request('admin-api/users',token=previous)[0]==401
        assert login('FixturePassword789!')[0]==401
        status,current=login('ReplacementPassword456!');assert status==200
        token=current['token']
        # Shared request locks enforce maintenance before any API writes.
        with open(app/'data/.operations.lock','r+') as lock:
            fcntl.flock(lock,fcntl.LOCK_EX)
            assert request('api/health')[0]==503
            assert request('admin-api/users',token=token)[0]==503
            fcntl.flock(lock,fcntl.LOCK_UN)
        backup=root/'backup.zip'
        command('backup',app,backup)
        assert backup.stat().st_mode & 0o777==0o600
        command('verify',backup)
        command('backup',app,backup,success=False)
        command('backup',app,site/'unsafe.zip',success=False)
        with zipfile.ZipFile(backup) as z:
            names=z.namelist();assert 'config.php' in names and 'uploads/2026/09/photo.jpg' in names
            assert not any(x.endswith(('-wal','-shm','-journal')) or x.endswith('.operations.lock') for x in names)
        restored_site=root/'restored';restored_site.mkdir();restored=restored_site/'_edit'
        command('restore',backup,restored)
        assert (restored/'config.php').read_bytes()==(app/'config.php').read_bytes()
        assert (restored/'uploads/2026/09/photo.jpg').read_bytes()==upload.read_bytes()
        assert (restored/'data/config/modules/property.json').read_bytes()==(app/'data/config/modules/property.json').read_bytes()
        assert (restored/'data/config/.active.json').read_bytes()==(app/'data/config/.active.json').read_bytes()
        assert (restored/f'data/config/.versions/{generation}/modules/property.json').read_bytes()==(app/'data/config/modules/property.json').read_bytes()
        assert not (restored/'data/config/.write.lock').exists()
        restored_db=sqlite3.connect(restored/'data/database/site.sqlite')
        assert restored_db.execute("SELECT slug FROM content").fetchone()[0]=='wal-property'
        assert restored_db.execute('SELECT count(*) FROM sessions').fetchone()[0]==0
        assert db.execute('SELECT count(*) FROM sessions').fetchone()[0]>0
        restored_db.close()
        command('restore',backup,restored,success=False)
        # Corruption and traversal must fail before creating the target.
        for kind in ('corrupt','traversal'):
            bad=root/(kind+'.zip')
            with zipfile.ZipFile(backup) as source,zipfile.ZipFile(bad,'w') as dest:
                for name in source.namelist():
                    content=source.read(name)
                    if kind=='corrupt' and name=='config.php':content+=b'changed'
                    dest.writestr(name,content)
                if kind=='traversal':dest.writestr('../escape',b'bad')
            target=root/('bad-'+kind)
            command('restore',bad,target,success=False);assert not target.exists()
        assert not (root/'escape').exists()
        shutil.copyfile(REPO/'router.php',restored_site/'router.php')
        stop(proc,log);proc=None
        proc,log=start(restored_site)
        assert request('admin-api/users',token=token)[0]==401
        status,new_login=login('ReplacementPassword456!');assert status==200,(status,new_login)
        status,settings=request('admin-api/email-settings',token=new_login['token']);assert status==200
        # Decrypt on restored site without exposing the credential in test output.
        verifier=root/'verify-secret.php'
        verifier.write_text("<?php require $argv[1].'/core/bootstrap.php'; $db=new Edit\\Core\\Database\\Database(EDIT_DATABASE_PATH); $r=$db->table('settings')->where('key','smtp_password')->first(); if(Edit\\Core\\Security\\Security::decrypt($r['value'])!=='fixture-password') exit(1); echo 'Restored secret decrypts';")
        subprocess.run(['php',str(verifier),str(restored)],check=True,capture_output=True)
        assert request('api/public/property/wal-property')[0]==200
        # A custom absolute database path must not silently write back into the source.
        portable=(restored/'config.php').read_text()
        (restored/'config.php').write_text(portable.replace("EDIT_BASE_PATH.'/data/database/site.sqlite'",repr(str(database))))
        assert request('api/health')[0]==503
        (restored/'config.php').write_text(portable)
        assert request('api/health')[0]==200

        print('PASS: HTTP password revocation, maintenance gate, WAL-safe full backup, checksums, clean restore, retained encryption key/content/uploads, revoked restored sessions, corruption/traversal/overwrite rejection')
    finally:
        if proc:stop(proc,log)
        db.close()
