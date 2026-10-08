"""Runs role API regressions against a throwaway local SQLite database.
Requires native PHP CLI with pdo_sqlite, mbstring, fileinfo and zip.
Never reads production config. No external services or production emails.
"""
import tempfile,pathlib,shutil,subprocess,socket,time,os,urllib.request
REPO=pathlib.Path(__file__).resolve().parents[1]
php=shutil.which('php')
if not php:raise SystemExit('PHP CLI required for role integration tests.')
with tempfile.TemporaryDirectory(prefix='bou-role-test-') as temporary:
 root=pathlib.Path(temporary)
 for name in ['app','bin','database']:shutil.copytree(REPO/name,root/name)
 (root/'public').mkdir();(root/'config').mkdir();(root/'storage').mkdir()
 for name in ['api.php','download.php','temp-file.php']:shutil.copy2(REPO/'public'/name,root/'public'/name)
 (root/'public/paths.php').write_text("<?php require __DIR__.'/../app/bootstrap.php';\n")
 with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
 url='http://127.0.0.1:'+str(port)
 config=(REPO/'config/config.example.php').read_text().replace("'driver' => 'mysql'","'driver' => 'sqlite'").replace('http://localhost:8080',url)
 (root/'config/config.php').write_text(config)
 subprocess.run([php,str(root/'bin/install.php')],check=True)
 shutil.copy2(REPO/'tests/roles-seed.php',root/'seed-roles.php')
 subprocess.run([php,str(root/'seed-roles.php')],check=True)
 subprocess.run([php,str(root/'bin/migrate.php')],check=True)
 subprocess.run([php,str(root/'bin/migrate.php')],check=True)
 with (root/'server.log').open('w') as log:
  process=subprocess.Popen([php,'-S','127.0.0.1:'+str(port),'-t',str(root/'public')],stdout=log,stderr=log)
  try:
   for i in range(100):
    try:urllib.request.urlopen(url+'/api.php?action=session',timeout=.3).close();break
    except Exception:time.sleep(.05)
   else:raise RuntimeError('Test server did not start.')
   env=os.environ.copy();env.update(BOU_TEST_URL=url,BOU_TEST_ROOT=str(root))
   subprocess.run(['python3',str(REPO/'tests/roles-api-test.py')],env=env,check=True)
  finally:
   process.terminate();process.wait(timeout=5)
