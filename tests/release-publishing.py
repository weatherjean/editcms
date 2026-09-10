#!/usr/bin/env python3
"""Exercise release orchestration with a fake gh executable; never publish remotely."""
import hashlib, json, os, subprocess, tempfile
from pathlib import Path
SCRIPT = Path(__file__).resolve().parent.parent / 'scripts/publish-release.sh'
VERSION = 'v2026.09.10.1'
with tempfile.TemporaryDirectory(prefix='edit-release-test-') as folder:
    root = Path(folder)
    (root/'bin').mkdir()
    (root/'dist').mkdir()
    fake = root/'bin/gh'
    fake.write_text('''#!/usr/bin/env python3
import json, os, sys
from pathlib import Path
root=Path(os.environ['FIXTURE_ROOT'])
with (root/'calls').open('a') as f: f.write(json.dumps(sys.argv[1:])+'\\n')
a=sys.argv[1:]
state=root/'state'
if a[:2]==['release','view']:
    if not state.exists():sys.exit(1)
    print('false' if state.read_text()=='published' else 'true')
elif a[:2]==['release','create']:state.write_text('draft')
elif a[:2]==['release','upload']:
    if os.environ.get('FAIL_UPLOAD'):sys.exit(1)
elif a[:2]==['release','edit']:state.write_text('published')
else:sys.exit(2)
''')
    fake.chmod(0o755)
    data=b'verified fixture archive'
    archive=root/'dist'/f'_edit-{VERSION}.zip'
    archive.write_bytes(data)
    (root/'dist/SHA256SUMS').write_text(hashlib.sha256(data).hexdigest()+'  '+archive.name+'\n')
    env={**os.environ,'PATH':str(root/'bin')+os.pathsep+os.environ['PATH'],'FIXTURE_ROOT':str(root),'VERSION':VERSION,'GITHUB_SHA':'a'*40,'GH_REPO':'fixture/repo'}
    def run(**overrides):return subprocess.run(['bash',str(SCRIPT)],cwd=root,env={**env,**overrides},capture_output=True,text=True)
    def calls():return [json.loads(line) for line in (root/'calls').read_text().splitlines()]
    assert run().returncode==0
    assert (root/'state').read_text()=='published'
    assert '--draft' in calls()[1] and '--target' in calls()[1] and 'a'*40 in calls()[1]
    assert [a[1] for a in calls()]==['view','create','upload','edit']
    (root/'calls').unlink()
    assert run().returncode==0 and [a[1] for a in calls()]==['view']
    (root/'state').unlink();(root/'calls').unlink()
    assert run(FAIL_UPLOAD='1').returncode!=0 and (root/'state').read_text()=='draft'
    assert not any(a[1]=='edit' for a in calls())
    (root/'calls').unlink()
    assert run().returncode==0 and [a[1] for a in calls()]==['view','upload','edit']
    (root/'calls').unlink();archive.write_bytes(b'corrupted')
    assert run().returncode!=0 and not (root/'calls').exists()
    assert run(VERSION='../invalid').returncode!=0 and not (root/'calls').exists()
print('PASS: checksum gate, draft-first publication, pinned source SHA, interrupted draft recovery and immutable reruns')
