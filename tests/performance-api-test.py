"""Behavior checks for optimized reads. Used only by the isolated role test runner."""
import urllib.request,urllib.error,urllib.parse,http.cookiejar,json,os
BASE=os.environ['BOU_TEST_URL']
if urllib.parse.urlparse(BASE).hostname not in ['localhost','127.0.0.1']:raise SystemExit('Isolated localhost fixture required.')
http=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));csrf=''
def call(action,data=None,params=None):
 global csrf
 headers={};body=None
 if data is not None:body=json.dumps(data).encode();headers={'Content-Type':'application/json','X-CSRF-Token':csrf}
 r=http.open(urllib.request.Request(BASE+'/api.php?'+urllib.parse.urlencode({'action':action,**(params or {})}),data=body,headers=headers));result=json.loads(r.read());csrf=r.headers.get('X-CSRF-Token') or result.get('csrf') or csrf
 assert r.headers.get('Cache-Control')=='no-store'
 assert 'queries;' in r.headers.get('Server-Timing','')
 return result
call('session');call('login',{'email':'teacher1@example.test','password':'testing-password-123'})
first=call('notification_state');assert first['items']
unchanged=call('notification_state',params={'since':first['revision']});assert unchanged['items'] is None
unread=next(x for x in first['items'] if not x['read_at']);call('mark_read',{'id':unread['id']})
read=call('notification_state',params={'since':first['revision']});assert read['revision']!=first['revision'];assert next(x for x in read['items'] if x['id']==unread['id'])['read_at']
call('delete_notification',{'id':unread['id']});deleted=call('notification_state',params={'since':read['revision']});assert deleted['revision']!=read['revision'];assert all(x['id']!=unread['id'] for x in deleted['items'])
storage=call('storage');dashboard=call('dashboard')
for key in ['used','quota','max_upload_mb']:assert storage[key]==dashboard[key]
for key in ['recent','recent_codes','recent_notes','files']:assert all('content' not in x and 'stored_name' not in x for x in dashboard[key])
print('PASS: optimized notification polling detects read/delete, unchanged polls omit bodies; storage summary matches dashboard; private metadata stays body-free and uncached.')
