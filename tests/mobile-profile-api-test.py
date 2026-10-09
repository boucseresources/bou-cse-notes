"""Identity/photo/mobile upload behavior in the runner's isolated local database."""
import urllib.request,urllib.error,urllib.parse,http.cookiejar,json,os,base64,secrets
BASE=os.environ['BOU_TEST_URL']
if urllib.parse.urlparse(BASE).hostname not in ['localhost','127.0.0.1']:raise SystemExit('Local fixture required.')
PNG=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/nL8AAAAASUVORK5CYII=')
class Client:
 def __init__(self):self.http=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf='';self.call('session')
 def send(self,r,status=200,raw=False):
  try:x=self.http.open(r)
  except urllib.error.HTTPError as e:x=e
  body=x.read();assert x.status==status,(r.full_url,x.status,body[:200]);self.csrf=x.headers.get('X-CSRF-Token') or self.csrf;return (body,x.headers) if raw else json.loads(body)
 def call(self,action,data=None,status=200):
  r=urllib.request.Request(BASE+'/api.php?action='+action,data=None if data is None else json.dumps(data).encode(),headers={'Content-Type':'application/json','X-CSRF-Token':self.csrf});v=self.send(r,status);self.csrf=v.get('csrf') or self.csrf;return v
 def upload(self,action,body,name='photo.png',mime='image/png',status=200):
  boundary='Mobile'+secrets.token_hex(12);data=(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+body+f'\r\n--{boundary}--\r\n'.encode());return self.send(urllib.request.Request(BASE+'/api.php?action='+action,data=data,headers={'Content-Type':'multipart/form-data; boundary='+boundary,'X-CSRF-Token':self.csrf}),status)
c=Client();u=c.call('login',{'identifier':'STUDENT1','password':'testing-password-123'})['user'];assert u['username']=='student1';assert 'profile_photo' not in u
c.call('settings',{'name':u['name'],'username':'admin'},400)
c.call('settings',{'name':u['name'],'username':'bad@username'},400)
u=c.call('settings',{'name':u['name'],'username':'mobile_student'})['user'];assert u['username']=='mobile_student'
c.call('logout',{});c.call('login',{'identifier':'MOBILE_STUDENT','password':'testing-password-123'});c.call('logout',{});c.call('login',{'email':'student1@example.test','password':'testing-password-123'})
policy=c.call('upload_policy');assert policy['max_file_bytes']>0
photo=c.upload('profile_photo',PNG)['user'];assert photo['photo_url'] and 'profile_photo' not in photo
body,h=c.send(urllib.request.Request(BASE+'/'+photo['photo_url']),raw=True);assert body==PNG;assert h['Content-Type']=='image/png';assert h['Cache-Control']=='private, no-store'
anon=Client();anon.send(urllib.request.Request(BASE+'/'+photo['photo_url']),401)
c.upload('profile_photo',b'<svg><script>alert(1)</script></svg>',name='fake.png',status=400)
c.upload('profile_photo',PNG+b'x'*(2*1048576),status=413)
first=c.upload('upload',PNG,name='phone-photo.png');second=c.upload('upload',PNG,name='phone-photo-again.png');assert first['id']!=second['id']
u=c.call('profile_photo_remove',{})['user'];assert not u['photo_url'];c.send(urllib.request.Request(BASE+'/'+photo['photo_url']),404)
c.call('settings',{'name':u['name'],'username':'student1'})
print('PASS: generated usernames, case-insensitive username/email login, username updates/conflicts, photo upload/private serving/removal, invalid/oversized images rejected, repeated phone-file uploads.')
