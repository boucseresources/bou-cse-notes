import requests, pathlib, json
base='http://localhost:8080/'
s=requests.Session(); csrf=s.get(base+'api.php?action=session').json()['csrf'];headers={'X-CSRF-Token':csrf}
def post(action,data=None,files=None):return s.post(base+'api.php?action='+action,json=data if files is None else None,data=data if files else None,files=files,headers=headers)
r=post('temp_create',{'title':'QA collaborative drop','duration':900});assert r.status_code==200,r.text
out=r.json();token=out['token'];key=out['delete_key']
assert post('temp_add',{'token':token,'kind':'code','content':'<script>safe as code</script>','title':'Code'}).status_code==200
png=pathlib.Path('public/assets/reference-logo.png').read_bytes()
r=post('temp_add',{'token':token,'kind':'file'},files={'file':('test.png',png,'image/png')});assert r.status_code==200,r.text
page=r.json();image=page['entries'][-1];assert 'stored' not in image and 'delete_hash' not in page
u=base+'temp-file.php?token='+token+'&id='+image['id']+'&inline=1';r=requests.get(u);assert r.status_code==200 and r.headers['Content-Type']=='image/png' and r.content==png
r=requests.get(u,headers={'Range':'bytes=0-15'});assert r.status_code==206 and len(r.content)==16
r=requests.get(u,headers={'Range':'bytes=999999999-'});assert r.status_code==416
assert post('temp_add',{'token':token,'kind':'file'},files={'file':('evil.php',b'<?php echo 1;','application/octet-stream')}).status_code==400
assert post('temp_add',{'token':token,'kind':'text','content':'a'*1048577}).status_code==400
assert post('temp_delete',{'token':token,'delete_key':'wrong'}).status_code==403
# A second guest can add to the same page.
guest=requests.Session();c=guest.get(base+'api.php?action=session').json()['csrf'];r=guest.post(base+'api.php?action=temp_add',json={'token':token,'kind':'text','content':'Guest contribution'},headers={'X-CSRF-Token':c});assert r.status_code==200
assert post('temp_add',{'token':token,'kind':'file'},files={'file':('video.webm',pathlib.Path('tests/fixtures/video.webm').read_bytes(),'video/webm')}).status_code==200
# Expiry blocks access and removes bytes.
f=pathlib.Path('storage/temporary')/(token+'.json');p=json.loads(f.read_text());stored=[pathlib.Path('storage/temporary')/e['stored'] for e in p['entries'] if 'stored' in e];p['expires']=0;f.write_text(json.dumps(p));r=s.get(base+'api.php?action=temp_get&token='+token);assert r.status_code==410 and not f.exists() and all(not x.exists() for x in stored)
assert requests.get(u).status_code in [404,410]
print('PASS: guest creation/contributions, safe files/code, limits, deletion secret, inline media/ranges, expiry and physical cleanup')
