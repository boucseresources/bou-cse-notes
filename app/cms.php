<?php
declare(strict_types=1);
function cmsDefaults(): array {
    return ['site_name'=>'BOU CSE Notes','tagline'=>'Student Workspace','login_title'=>'Your next great idea belongs here.','login_description'=>'One private workspace for your lab programs, lecture notes and everything you learn along the way.','support_email'=>'','logo_id'=>0,'notice_title'=>'','notice_body'=>'','notice_enabled'=>false,'menus'=>[]];
}
function cmsState(): array {
    static $state=null;
    if($state===null){$row=one('SELECT * FROM cms_state WHERE id=1');$state=['revision'=>(int)($row['revision']??1),'data'=>array_replace(cmsDefaults(),json_decode($row['data']??'{}',true)?:[])];}
    return $state;
}
function cmsMember(?array $u): bool {return $u && $u['status']==='active' && !empty($u['verified_at']) && ($u['approval']??'approved')==='approved';}
function cmsMediaUrl(int $id): string {return 'cms-media.php?id='.$id;}
function cmsPublic(?array $u=null): array {
    $s=cmsState();$data=$s['data'];$data['revision']=$s['revision'];$data['logo_url']=$data['logo_id']?cmsMediaUrl((int)$data['logo_id']):'assets/reference-logo.png';
    $member=cmsMember($u);if(!$member){$data['notice_enabled']=false;$data['notice_title']=$data['notice_body']='';}$pages=all("SELECT slug,title,audience FROM cms_pages WHERE status='published' ORDER BY title,id");
    $available=[];foreach($pages as $p)if($p['audience']==='public'||$member)$available[$p['slug']]=$p;
    $data['pages']=array_values($available);
    $data['menus']=array_values(array_filter($data['menus'],function($m)use($available,$member){if(($m['audience']??'public')==='members'&&!$member)return false;if(str_starts_with($m['url'],'#page?slug='))return isset($available[substr($m['url'],11)]);return true;}));
    return $data;
}
function cmsText(mixed $value,int $max,string $label,bool $required=false): string {
    if(!is_string($value))fail('Invalid '.$label.'.');$value=trim($value);
    if(mb_strlen($value)>$max||($required&&$value===''))fail('Enter a valid '.$label.' (up to '.$max.' characters).');return $value;
}
function cmsPageData(array $page): array {$page['id']=(int)$page['id'];$page['revision']=(int)$page['revision'];return $page;}
function cmsReadPage(array $in,?array $u): never {
    $slug=(string)($in['slug']??'');$page=one('SELECT * FROM cms_pages WHERE slug=?',[$slug]);$preview=!empty($in['preview']);
    if($preview&&(!$u||!cmsMember($u)||!isSuper($u)))fail('Super Admin preview required.',403);
    if(!$page||(!$preview&&$page['status']!=='published')||$page['status']==='archived')fail('Page unavailable.',404);
    if(!$preview&&$page['audience']==='members'&&!cmsMember($u))fail('Sign in with an approved account to view this page.',401);
    respond(['page'=>cmsPageData($page)]);
}
function cmsAction(string $action,array $in,array $u): never {
    if(!isSuper($u))fail('Super Admin access required.',403);$uid=(int)$u['id'];
    if($action==='cms_page_edit'){ $page=one('SELECT * FROM cms_pages WHERE id=?',[(int)($in['id']??0)]);if(!$page)fail('Page unavailable.',404);respond(['page'=>cmsPageData($page)]); }
    if($action==='cms_data'){
        $s=cmsState();respond(['state'=>$s,'pages'=>array_map('cmsPageData',all('SELECT id,slug,title,status,audience,revision,updated_at FROM cms_pages ORDER BY updated_at DESC,id DESC LIMIT 500')),'media'=>array_map(fn($m)=>[...$m,'url'=>cmsMediaUrl((int)$m['id'])],all('SELECT * FROM cms_media ORDER BY id DESC LIMIT 200')),'stats'=>['users'=>(int)one('SELECT COUNT(*) AS n FROM users')['n'],'pending'=>(int)one("SELECT COUNT(*) AS n FROM account_controls WHERE approval='pending'")['n'],'published'=>(int)one("SELECT COUNT(*) AS n FROM cms_pages WHERE status='published'")['n'],'media_bytes'=>(int)one('SELECT COALESCE(SUM(size_bytes),0) AS n FROM cms_media')['n']],'audit'=>all("SELECT a.*,u.name AS actor FROM admin_audit a JOIN users u ON u.id=a.actor_id WHERE a.action LIKE 'cms_%' ORDER BY a.id DESC LIMIT 30")]);
    }
    limit('cms-write-'.$uid,80,3600);
    if($action==='cms_config'){
        $d=$in['data']??null;if(!is_array($d))fail('Invalid site settings.');$clean=cmsDefaults();
        foreach(['site_name'=>100,'tagline'=>120,'login_title'=>180,'login_description'=>1000,'support_email'=>190,'notice_title'=>160,'notice_body'=>2000] as $k=>$max)$clean[$k]=cmsText($d[$k]??$clean[$k],$max,str_replace('_',' ',$k),$k==='site_name');
        if($clean['support_email']!==''&&!filter_var($clean['support_email'],FILTER_VALIDATE_EMAIL))fail('Enter a valid support email.');
        $clean['logo_id']=(int)($d['logo_id']??0);if($clean['logo_id']&&!one('SELECT id FROM cms_media WHERE id=?',[$clean['logo_id']]))fail('Select a logo from the image library.');
        $clean['notice_enabled']=!empty($d['notice_enabled']);$menus=$d['menus']??[];if(!is_array($menus)||count($menus)>20)fail('Use up to 20 menu links.');
        foreach($menus as $m){if(!is_array($m))fail('Invalid menu link.');$label=cmsText($m['label']??'',60,'menu label',true);$url=cmsText($m['url']??'',2000,'menu URL',true);$audience=$m['audience']??'public';
            if(!in_array($audience,['public','members'],true))fail('Invalid menu audience.');
            if(str_starts_with($url,'#page?slug=')){if(!preg_match('/^#page\?slug=[a-z0-9]+(?:-[a-z0-9]+)*$/D',$url)||!one('SELECT id FROM cms_pages WHERE slug=?',[substr($url,11)]))fail('Select an existing CMS page.');}
            elseif(str_starts_with($url,'#')){if(!in_array($url,['#overview','#courses','#goals','#resources','#groups','#tools','#features','#drop','#help','#login'],true))fail('Choose a supported workspace route.');}
            elseif(!filter_var($url,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true)||parse_url($url,PHP_URL_USER)||parse_url($url,PHP_URL_PASS))fail('Menu links must use HTTP/HTTPS or a supported page route.');
            $clean['menus'][]=['label'=>$label,'url'=>$url,'audience'=>$audience];
        }
        $rev=(int)($in['revision']??0);db()->beginTransaction();$q=query('UPDATE cms_state SET data=?,revision=revision+1,updated_at=? WHERE id=1 AND revision=?',[json_encode($clean,JSON_UNESCAPED_UNICODE),now(),$rev]);
        if(!$q->rowCount()){db()->rollBack();fail('Site settings changed in another tab. Reload before saving.',409);}auditAdmin($uid,'cms_config',null,['revision'=>$rev+1]);db()->commit();respond(['ok'=>true,'revision'=>$rev+1]);
    }
    if($action==='cms_page_save'){
        $id=(int)($in['id']??0);$title=cmsText($in['title']??'',160,'page title',true);$slug=cmsText($in['slug']??'',100,'page slug',true);$body=cmsText($in['body']??'',50000,'page content');$status=$in['status']??'draft';$audience=$in['audience']??'public';
        if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug)||!in_array($status,['draft','published','archived'],true)||!in_array($audience,['public','members'],true))fail('Use a lowercase page slug and valid publishing options.');
        if($status==='published'&&$body==='')fail('Add content before publishing.');
        db()->beginTransaction();
        try{
            if($id){$rev=(int)($in['revision']??0);$q=query('UPDATE cms_pages SET title=?,slug=?,body=?,status=?,audience=?,revision=revision+1,updated_by=?,updated_at=? WHERE id=? AND revision=?',[$title,$slug,$body,$status,$audience,$uid,now(),$id,$rev]);if(!$q->rowCount()){db()->rollBack();fail('Page changed or was removed. Reload before saving.',409);}}
            else{query('UPDATE cms_state SET updated_at=updated_at WHERE id=1');if((int)one('SELECT COUNT(*) AS n FROM cms_pages')['n']>=500){db()->rollBack();fail('CMS supports up to 500 pages. Reuse an existing page.');}query('INSERT INTO cms_pages(slug,title,body,status,audience,created_by,updated_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)',[$slug,$title,$body,$status,$audience,$uid,$uid,now(),now()]);$id=(int)db()->lastInsertId();}
            auditAdmin($uid,'cms_page_save',$id,['slug'=>$slug,'status'=>$status]);db()->commit();respond(['page'=>cmsPageData(one('SELECT * FROM cms_pages WHERE id=?',[$id]))]);
        }catch(PDOException $e){if(db()->inTransaction())db()->rollBack();if((string)$e->getCode()==='23000')fail('This page slug is already used.');throw $e;}
    }
    if($action==='cms_media_upload'){
        $file=receivedFile();$cap=min(5*1048576,hostingFileLimit());if((int)$file['size']>$cap)fail('Image exceeds the 5 MB or hosting limit.',413);
        $info=@getimagesize($file['tmp_name']);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
        if(!$info||!isset($ext[$mime])||($info['mime']??'')!==$mime||$info[0]>4096||$info[1]>4096||$info[0]*$info[1]>16000000)fail('Choose a JPG, PNG or WebP image up to 4096 pixels.');
        $quota=100*1048576;$dir=cfg('storage_path').'/cms';if(!is_dir($dir)&&!mkdir($dir,0700,true))fail('Cannot prepare image storage.',500);
        $name=bin2hex(random_bytes(24)).'.'.$ext[$mime];$path=$dir.'/'.$name;db()->beginTransaction();
        // Serialize CMS writes so two uploads cannot exceed the media budget together.
        query('UPDATE cms_state SET updated_at=updated_at WHERE id=1');
        if((int)one('SELECT COALESCE(SUM(size_bytes),0) AS n FROM cms_media')['n']+(int)$file['size']>$quota){db()->rollBack();fail('CMS image library is limited to 100 MB. Reuse an existing image.');}
        if(!move_uploaded_file($file['tmp_name'],$path)){db()->rollBack();fail('Image could not be stored.',500);}@chmod($path,0600);
        try{query('INSERT INTO cms_media(stored_name,mime,original_name,size_bytes,created_by,created_at) VALUES(?,?,?,?,?,?)',[$name,$mime,mb_substr(basename((string)$file['name']),0,160),(int)$file['size'],$uid,now()]);$id=(int)db()->lastInsertId();auditAdmin($uid,'cms_media_upload',$id);db()->commit();respond(['id'=>$id,'url'=>cmsMediaUrl($id)]);}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();@unlink($path);throw $e;}
    }
    if($action==='cms_media_delete'){
        $id=(int)($in['id']??0);db()->beginTransaction();query('UPDATE cms_state SET updated_at=updated_at WHERE id=1');
        $state=one('SELECT data FROM cms_state WHERE id=1');$config=json_decode($state['data'],true);
        if((int)($config['logo_id']??0)===$id||one('SELECT id FROM cms_pages WHERE body LIKE ?',['%cms-media.php?id='.$id.')%'])){db()->rollBack();fail('This image is used by the logo or a page. Remove those references first.');}
        $image=one('SELECT * FROM cms_media WHERE id=?',[$id]);if(!$image){db()->rollBack();fail('Image unavailable.',404);}query('DELETE FROM cms_media WHERE id=?',[$id]);auditAdmin($uid,'cms_media_delete',$id);db()->commit();@unlink(cfg('storage_path').'/cms/'.basename($image['stored_name']));respond(['ok'=>true]);
    }
    if($action==='cms_announce'){
        limit('cms-announce-'.$uid,3,3600);$title=cmsText($in['title']??'',160,'announcement title',true);$body=cmsText($in['body']??'',4000,'announcement message',true);
        db()->beginTransaction();$q=query("INSERT INTO notifications(user_id,title,content,created_at) SELECT u.id,?,?,? FROM users u LEFT JOIN account_controls c ON c.user_id=u.id WHERE u.status='active' AND u.verified_at IS NOT NULL AND (u.role IN ('admin','super_admin') OR COALESCE(c.approval,'approved')='approved')",[$title,$body,now()]);$count=$q->rowCount();auditAdmin($uid,'cms_announce',null,['title'=>$title,'recipients'=>$count]);db()->commit();respond(['ok'=>true,'recipients'=>$count]);
    }
    fail('Unknown CMS action.',404);
}
