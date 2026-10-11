"""CMS integration regression for an isolated local SQLite fixture only."""
import os,pathlib,urllib.request,base64,secrets
source=pathlib.Path(__file__).with_name('roles-api-test.py').read_text()
ns={};exec(source.split("admin=Client('admin')")[0],ns)
Client,BASE=ns['Client'],ns['BASE'];admin=Client('admin');student=Client('student1');teacher=Client('teacher1');guest=Client()
student.call('cms_data',status=403);teacher.call('cms_data',status=403);guest.call('cms_data',status=401)
d=admin.call('cms_data');state=d['state'];initial=state['data'];suffix=secrets.token_hex(4)
config={**initial,'site_name':'CMS test '+suffix,'login_title':'Learn at your own pace','support_email':'help@example.test','notice_title':'Exam notice','notice_body':'Check your timetable','notice_enabled':True}
admin.call('cms_config',{'data':config,'revision':state['revision']},bad=True,status=419)
student.call('cms_config',{'data':config,'revision':state['revision']},status=403)
admin.call('cms_config',{'data':config,'revision':state['revision']})
admin.call('cms_config',{'data':config,'revision':state['revision']},status=409)
assert guest.call('cms_site')['site_name']==config['site_name'];assert guest.call('cms_site')['notice_body']==''
assert student.call('cms_site')['notice_body']==config['notice_body']
def save(**values):
 data={'title':'CMS guide','slug':'guide-'+suffix,'body':'# Hello\nA useful guide.','status':'draft','audience':'public'};data.update(values);return admin.call('cms_page_save',data)['page']
p=save();guest.call('cms_page',params={'slug':p['slug']},status=404);student.call('cms_page',params={'slug':p['slug'],'preview':1},status=403)
assert admin.call('cms_page',params={'slug':p['slug'],'preview':1})['page']['body']==p['body']
admin.call('cms_page_save',{**p,'status':'published'});p=admin.call('cms_page_edit',params={'id':p['id']})['page'];assert guest.call('cms_page',params={'slug':p['slug']})['page']['title']==p['title']
admin.call('cms_page_save',{**p,'revision':p['revision']-1},status=409)
admin.call('cms_page_save',{**p,'id':0},status=400)
member=save(slug='member-'+suffix,status='published',audience='members');guest.call('cms_page',params={'slug':member['slug']},status=401);student.call('cms_page',params={'slug':member['slug']})
draft=save(slug='draft-'+suffix)
menus=[{'label':'Guide','url':'#page?slug='+p['slug'],'audience':'public'},{'label':'Members','url':'#page?slug='+member['slug'],'audience':'members'},{'label':'Draft','url':'#page?slug='+draft['slug'],'audience':'public'},{'label':'Video','url':'https://youtu.be/dQw4w9WgXcQ','audience':'public'}]
state=admin.call('cms_data')['state'];config={**config,'menus':menus};admin.call('cms_config',{'data':config,'revision':state['revision']})
assert [x['label'] for x in guest.call('cms_site')['menus']]==['Guide','Video']
assert [x['label'] for x in student.call('cms_site')['menus']]==['Guide','Members','Video']
state=admin.call('cms_data')['state'];admin.call('cms_config',{'data':{**config,'menus':[{'label':'Bad','url':'javascript:alert(1)','audience':'public'}]},'revision':state['revision']},status=400)
png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/nL8AAAAASUVORK5CYII=')
image=admin.upload(png,action='cms_media_upload');raw=guest.send(urllib.request.Request(BASE+'/'+image['url']),is_json=False);assert raw==png
student.upload(png,action='cms_media_upload',status=403)
admin.upload(b'<svg onload="alert(1)"></svg>',action='cms_media_upload',status=400)
state=admin.call('cms_data')['state'];config={**config,'logo_id':image['id']};admin.call('cms_config',{'data':config,'revision':state['revision']});assert guest.call('cms_site')['logo_url']==image['url']
admin.call('cms_media_delete',{'id':image['id']},status=400)
state=admin.call('cms_data')['state'];config['logo_id']=0;admin.call('cms_config',{'data':config,'revision':state['revision']})
with_image=save(slug='image-'+suffix,status='published',body='![A photo]('+image['url']+')');admin.call('cms_media_delete',{'id':image['id']},status=400)
admin.call('cms_page_save',{**with_image,'body':'Image removed','status':'archived'});admin.call('cms_media_delete',{'id':image['id']});guest.send(urllib.request.Request(BASE+'/'+image['url']),status=404)
announce=admin.call('cms_announce',{'title':'CMS news '+suffix,'body':'New study guide ready'});assert announce['recipients']>=6
assert any(x['title']=='CMS news '+suffix for x in student.call('notifications'))
admin.call('cms_page_save',{**p,'status':'archived'});guest.call('cms_page',params={'slug':p['slug']},status=404)
assert not any(x['label']=='Guide' for x in guest.call('cms_site')['menus'])
state=admin.call('cms_data')['state'];admin.call('cms_config',{'data':initial,'revision':state['revision']})
assert any(x['action']=='cms_page_save' for x in admin.call('cms_data')['audit'])
print('PASS CMS roles/CSRF; site config and concurrent edit conflicts; drafts, publishing, member-only pages and archive; menu filtering and unsafe URL rejection; image upload/read/delete/reference protection; announcements and audit log.')
