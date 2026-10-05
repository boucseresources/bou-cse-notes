"""Additional admin/quota integration checks. Requires tests/seed.php in local mode."""
import runpy,pathlib
n=runpy.run_path(str(pathlib.Path(__file__).with_name('api_test.py')))
Client=n['Client'];alice=n['alice'];bob=n['bob'];admin=Client()
admin.call('login',{'email':'qa@example.test','password':'qa-password-1234'})
_,original=admin.call('admin_data');settings={x['key']:x['value'] for x in original['settings']}
try:
 admin.call('admin_config',{'quota_mb':1,'max_upload_mb':10,'trash_days':30,'support_email':'support@example.test'})
 alice.upload('over-quota.txt',b'a'*1100000,'text/plain',400)
 _,u=bob.call('session');uid=u['user']['id']
 admin.call('admin_update',{'id':uid,'status':'suspended'});bob.call('dashboard',code=401)
 admin.call('admin_update',{'id':uid,'status':'active'})
 _,share=alice.call('share',{'id':n['x']['id']});secret=share['url'].split('token=')[1]
 _,u=alice.call('session');uid=u['user']['id']
 admin.call('admin_update',{'id':uid,'status':'suspended'});n['anon'].call('shared',code=404,query='&token='+secret)
 admin.call('admin_update',{'id':uid,'status':'active'})
 admin.call('announce',{'title':'Integration announcement','content':'Testing notifications'})
 print('PASS: administrator updates, over-quota upload rejection, suspended-user session invalidation, suspended-owner share denial and announcement publication.')
finally:admin.call('admin_config',settings)
