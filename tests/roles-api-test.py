"""Role integration regression. ISOLATED local mail-log/SQLite fixture only.
Seed six verified users: admin, student1, student2, outsider, teacher1, teacher2
at @example.test with password testing-password-123. Admin must be super_admin.
Never run against a live database: this changes fixture accounts and uploads.
"""
import urllib.request,urllib.error,urllib.parse,http.cookiejar,json,pathlib,re,secrets,os
BASE=os.environ.get('BOU_TEST_URL','http://127.0.0.1:8093')
ROOT=pathlib.Path(os.environ.get('BOU_TEST_ROOT','roles-fixture')).resolve()
if urllib.parse.urlparse(BASE).hostname not in ['localhost','127.0.0.1']:
 raise SystemExit('Only an isolated local fixture is supported.')
class Client:
 def __init__(self,name=None):
  self.jar=http.cookiejar.CookieJar();self.http=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar));self.csrf='';self.call('session')
  if name:self.call('login',{'email':name+'@example.test','password':'testing-password-123'})
 def call(self,action,data=None,status=200,params=None,bad=False):
  headers={};raw=None
  if data is not None:raw=json.dumps(data).encode();headers={'Content-Type':'application/json','X-CSRF-Token':'invalid' if bad else self.csrf}
  url=BASE+'/api.php?'+urllib.parse.urlencode({'action':action,**(params or {})})
  return self.send(urllib.request.Request(url,data=raw,headers=headers),status)
 def send(self,req,status=200,is_json=True):
  try:r=self.http.open(req)
  except urllib.error.HTTPError as e:r=e
  raw=r.read();assert r.status==status,(req.full_url,r.status,raw[:400])
  if r.headers.get('X-CSRF-Token'):self.csrf=r.headers['X-CSRF-Token']
  return json.loads(raw) if is_json else raw
 def upload(self,content,status=200,action='upload',fields=None):
  boundary='----'+secrets.token_hex(8);parts=[]
  for k,v in (fields or {}).items():parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n').encode())
  parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="lecture.txt"\r\nContent-Type: text/plain\r\n\r\n').encode()+content+f'\r\n--{boundary}--\r\n'.encode())
  return self.send(urllib.request.Request(BASE+'/api.php?action='+action,data=b''.join(parts),headers={'Content-Type':'multipart/form-data; boundary='+boundary,'X-CSRF-Token':self.csrf}),status)
admin=Client('admin');student=Client('student1');other=Client('student2');outsider=Client('outsider');teacher=Client('teacher1');foreign=Client('teacher2');guest=Client()
def uid(client):return int(client.call('session')['user']['id'])
aid,sid,oid,tid=uid(admin),uid(student),uid(other),uid(teacher)
def control(id,role='student',**extra):
 data={'id':id,'role':role,'status':'active','approval':'approved','allow_publish':1,'allow_groups':1,'quota_mb':'','max_upload_mb':''};data.update(extra);return data
student.call('control_data',status=403);teacher.call('control_data',status=403)
teacher.call('control_user',control(sid),403)
admin.call('control_user',control(aid),400)
admin.call('control_user',control(sid,role='super_admin'),400)
teacher.call('group_save',{'name':'Group A','members':[sid,oid]});groups=teacher.call('group_data')['groups'];g1=int(groups[0]['id'])
g2=teacher.call('group_save',{'name':'Group B','members':[sid]})['id']
foreign.call('group_save',{'id':g1,'name':'stolen','members':[]},403)
student.call('group_save',{'name':'unauthorized','members':[]},403)
item=teacher.call('save',{'kind':'code','title':'Teacher code','language':'C','content':'int main(){return 0;}'})
title='Group notification '+secrets.token_hex(4)
post=teacher.call('broadcast_publish',{'title':title,'message':'For selected groups','item_id':item['id'],'audience':'groups','groups':[g1,g2]})
assert post['recipients']==2
student.call('broadcast_get',params={'id':post['id']});other.call('broadcast_get',params={'id':post['id']});outsider.call('broadcast_get',status=404,params={'id':post['id']})
outsider.send(urllib.request.Request(BASE+'/download.php?broadcast='+str(post['id'])),404)
content=student.send(urllib.request.Request(BASE+'/download.php?broadcast='+str(post['id'])),is_json=False);assert b'return 0' in content
assert sum(x['title']==title for x in student.call('notifications'))==1
foreign.call('broadcast_publish',{'title':'Foreign groups','message':'bad','audience':'groups','groups':[g1]},403)
student.call('broadcast_publish',{'title':'Unauthorized','message':'bad','audience':'all'},403)
single=teacher.call('broadcast_publish',{'title':'Selected student','message':'Private update','audience':'members','members':[sid]})
other.call('broadcast_get',status=404,params={'id':single['id']})
global_post=teacher.call('broadcast_publish',{'title':'Global lesson','message':'All members','audience':'all'})
outsider.call('broadcast_get',params={'id':global_post['id']})
teacher.call('broadcast_revoke',{'id':post['id']});student.call('broadcast_get',status=404,params={'id':post['id']})
admin.call('control_user',control(tid,role='teacher',allow_publish=0));teacher.call('broadcast_publish',{'title':'Disabled','message':'no','audience':'all'},403)
admin.call('control_user',control(tid,role='teacher'))
admin.call('control_user',control(sid,quota_mb=1,max_upload_mb=1));assert student.call('dashboard')['quota']==1048576
student.upload((b'Lecture notes.\n'*60000)[:800*1024]);student.upload((b'Course notes.\n'*70000)[:800*1024],400)
admin.call('control_user',control(sid,quota_mb=2,max_upload_mb=1));student.upload((b'Course notes.\n'*70000)[:800*1024]);student.upload((b'Large lecture notes.\n'*70000)[:1100*1024],400)
student.call('storage_request',{'requested_mb':5,'reason':'Course materials'})
requests=admin.call('control_data')['requests'];request=next(r for r in requests if int(r['user_id'])==sid)
admin.call('control_request',{'id':request['id'],'status':'approved'});assert student.call('dashboard')['quota']==5*1048576
admin.call('control_request',{'id':request['id'],'status':'approved'},409)
email='pending'+secrets.token_hex(5)+'@example.test';new=Client();r=new.call('register',{'name':'Pending teacher','email':email,'password':'testing-password-123','role':'teacher'})
assert r['user']['role']=='teacher' and r['user']['approval']=='pending'
new.call('resend',{},200)
mail='\n'.join(p.read_text() for p in (ROOT/'storage/mail').glob('*.txt') if ('To: '+email+'\n') in p.read_text())
tokens=re.findall(r'#verify\?token=([a-f0-9]+)',mail)
# Resend invalidates the first token. Identify the live token from newest file.
latest=max((p for p in (ROOT/'storage/mail').glob('*.txt') if ('To: '+email+'\n') in p.read_text()),key=lambda p:p.stat().st_mtime_ns)
verify=re.search(r'#verify\?token=([a-f0-9]+)',latest.read_text()).group(1)
new.call('verify',{'token':verify});new.call('dashboard',status=403)
new_id=uid(new);admin.call('control_user',control(new_id,role='teacher'));new.call('dashboard')
limits={'enabled':1,'file_mb':1,'text_mb':1,'page_mb':2,'entries':1,'total_mb':5,'pages_hour':10,'max_hours':1}
admin.call('control_guest',limits);guest.call('temp_create',{'title':'Too long','duration':86400},400)
drop=guest.call('temp_create',{'title':'Guest code','duration':3600})
assert '#drop?token=' in drop['url'] and drop['delete_key']
guest.call('temp_add',{'token':drop['token'],'kind':'code','content':'print(1)','language':'Python'})
guest.call('temp_add',{'token':drop['token'],'kind':'text','content':'second'},400)
limits['enabled']=0;admin.call('control_guest',limits);guest.call('temp_create',{'title':'Disabled','duration':3600},403)
limits['enabled']=1;admin.call('control_guest',limits)
teacher.call('broadcast_publish',{'title':'CSRF rejected','message':'no','audience':'all'},419,bad=True)
admin.call('control_guest',{**limits,'file_mb':20},400)
print('PASS: Super Admin boundary, protected admins, pending teacher approval/resend/verification, teacher permission revocation, group ownership, overlapping recipients deduplicated, global and selected-member access, downloads/revocation, quota and per-file enforcement, storage request approval, guest limits/expiry/disable, CSRF.')
