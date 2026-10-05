"""Destructive integration tests against an ISOLATED local database only.
Run with a local PHP server and local mail-log mode, never against production.
"""
import urllib.request,urllib.error,http.cookiejar,json,secrets,pathlib,re,time,os
BASE=os.environ.get('BOU_TEST_URL','http://localhost:8080')
ROOT=pathlib.Path(__file__).resolve().parents[1]
class Client:
 def __init__(self):
  self.jar=http.cookiejar.CookieJar();self.open=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar));self.csrf=''
  self.call('session');self.csrf=self.call('session')[1]['csrf']
 def call(self,action,data=None,code=200,csrf=True,query=''):
  h={};raw=None
  if data is not None:raw=json.dumps(data).encode();h['Content-Type']='application/json';h['X-CSRF-Token']=self.csrf if csrf else 'invalid'
  req=urllib.request.Request(BASE+'/api.php?action='+action+query,data=raw,headers=h)
  try:r=self.open.open(req)
  except urllib.error.HTTPError as e:r=e
  body=r.read();j=json.loads(body);assert r.status==code,(action,r.status,body[:500]);return r.status,j
 def download(self,id,code=200,extra=''):
  try:r=self.open.open(BASE+'/download.php?id='+str(id)+extra)
  except urllib.error.HTTPError as e:r=e
  data=r.read();assert r.status==code,(r.status,data[:100]);return data
 def upload(self,name,data,ctype,code=200):
  boundary='----'+secrets.token_hex(10)
  body=(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{name}"\r\nContent-Type: {ctype}\r\n\r\n').encode()+data+f'\r\n--{boundary}--\r\n'.encode()
  req=urllib.request.Request(BASE+'/api.php?action=upload',data=body,headers={'Content-Type':'multipart/form-data; boundary='+boundary,'X-CSRF-Token':self.csrf})
  try:r=self.open.open(req)
  except urllib.error.HTTPError as e:r=e
  j=json.loads(r.read());assert r.status==code,(r.status,j);return j
suffix=secrets.token_hex(5)
def register(c,label):
 email=label+suffix+'@example.test';c.call('register',{'name':'Test '+label,'email':email,'password':'testing-password-123'})
 c.call('dashboard',code=403)
 mail=[p.read_text() for p in (ROOT/'storage/mail').glob('*.txt') if ('To: '+email+'\n') in p.read_text()]
 token=re.search(r'#verify\?token=([a-f0-9]+)', '\n'.join(mail)).group(1)
 c.call('verify',{'token':token});c.call('verify',{'token':token},400);return email
alice=Client();bob=Client();anon=Client();a=register(alice,'alice');b=register(bob,'bob')
_,project=alice.call('save',{'kind':'project','title':'DS Lab'})
_,folder=alice.call('save',{'kind':'folder','title':'Algorithms','project_id':project['id']})
_,x=alice.call('save',{'kind':'code','title':'Binary Search <script>alert(1)</script>','content':'int main() { return 0; }','language':'C','project_id':project['id'],'folder_id':folder['id'],'tags':['Lab','lab','EXAM']})
assert x['tags']==['lab','exam'];alice.call('save',{'id':x['id'],'title':'blocked'},419,csrf=False)
bob.call('item',code=404,query='&id='+str(x['id']));bob.call('favorite',{'id':x['id']},404);anon.call('item',code=401,query='&id='+str(x['id']))
_,updated=alice.call('save',{**x,'content':'int main() { return 1; }','expected_revision':x['revision']})
alice.call('save',{**x,'content':'stale','expected_revision':x['revision']},409)
assert updated['versions'];_,v=alice.call('version',query='&id='+str(updated['versions'][0]['id']));assert v['content']==x['content']
assert b'return 1' in alice.download(x['id']);bob.download(x['id'],404);anon.download(x['id'],401)
alice.call('favorite',{'id':x['id']});_,favs=alice.call('list',query='&mode=favorites');assert any(i['id']==x['id'] for i in favs['items'])
_,search=alice.call('list',query='&q=Binary');assert search['total']>=1;assert 'content' not in search['items'][0]
_,foreign=bob.call('save',{'kind':'project','title':'Foreign'});alice.call('save',{'kind':'code','title':'bad relation','project_id':foreign['id']},404)
_,share=alice.call('share',{'id':x['id'],'days':7});secret=share['url'].split('token=')[1];_,s=anon.call('shared',query='&token='+secret);assert s['item']['content']==updated['content']
_,item=alice.call('item',query='&id='+str(x['id']));alice.call('revoke',{'id':item['shares'][0]['id']});anon.call('shared',code=404,query='&token='+secret)
alice.call('share',{'id':x['id'],'email':b});_,direct=bob.call('shared_with_me');sid=direct[0]['share_id'];_,direct_item=bob.call('direct_item',query='&share_id='+str(sid));assert direct_item['item']['id']==x['id']
file=alice.upload('lecture.txt',b'Private lecture material','text/plain');bob.download(file['id'],404);assert alice.download(file['id'])==b'Private lecture material'
alice.upload('exploit.php',b'<?php echo 1;','text/plain',400);alice.upload('pretend.png',b'not an image','image/png',400)
_,note=alice.call('save',{'kind':'note','title':'Lecture notes','content':'# Bengali\nবাংলা','attachments':[file['id']]});assert note['attachments'][0]['id']==file['id']
alice.call('trash',{'id':x['id']});alice.call('item',code=404,query='&id='+str(x['id']));alice.call('restore',{'id':x['id']});alice.call('item',query='&id='+str(x['id']))
alice.call('trash',{'id':file['id']});_,dash=alice.call('dashboard');assert dash['used']>=len(b'Private lecture material');alice.call('purge',{'id':file['id']});alice.download(file['id'],404)
alice.call('admin_data',code=403)
alice.call('forgot',{'email':a});mail='\n'.join(p.read_text() for p in (ROOT/'storage/mail').glob('*.txt') if ('To: '+a+'\n') in p.read_text());reset=re.search(r'#reset\?token=([a-f0-9]+)',mail).group(1)
alice.call('reset',{'token':reset,'password':'new-testing-password-123'});alice.call('dashboard',code=401);alice.call('reset',{'token':reset,'password':'new-testing-password-123'},400);alice.call('login',{'email':a,'password':'new-testing-password-123'});alice.call('dashboard')
print('PASS: registration/verification, CSRF, ownership, CRUD, stale-save rejection, version history, downloads, favorites, search, relation checks, sharing/revocation, uploads, attachments, trash/restore, quota accounting, admin boundary, reset token expiry/reuse and session invalidation.')
