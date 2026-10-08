<?php
require_once __DIR__.'/media.php';require_once __DIR__.'/preview.php';
function validateRelations(array $in,int $uid,?int $self=null,string $kind='code'): array {
    $project=empty($in['project_id'])?null:(int)$in['project_id'];$folder=empty($in['folder_id'])?null:(int)$in['folder_id'];
    if ($project) { $p=owned($project,$uid);if ($p['kind']!=='project' || $project===$self) fail('Choose a valid project.'); }
    if ($folder) {
        $f=owned($folder,$uid);if ($f['kind']!=='folder' || $folder===$self) fail('Choose a valid folder.');
        if ((int)($f['project_id']??0)!==(int)($project??0)) fail('Folder must belong to the selected project.');
        $cursor=$f;$depth=0;
        while ($cursor) { if (++$depth>5 || (int)$cursor['id']===$self) fail('Folders can be nested up to five levels, without cycles.');$cursor=$cursor['folder_id']?owned((int)$cursor['folder_id'],$uid):null; }
    }
    if ($kind==='project' && ($project || $folder)) fail('Projects cannot be nested.');
    return [$project,$folder];
}
function saveItem(array $in,int $uid): array {
    $id=(int)($in['id']??0);$old=$id?owned($id,$uid):null;$kind=$old['kind']??($in['kind']??'code');
    if (!in_array($kind,['code','note','project','folder','file','course','goal'],true) || (!$old && $kind==='file')) fail('Invalid item type.');
    $title=trim($in['title']??'');if (!$title || mb_strlen($title)>255) fail('Title is required (up to 255 characters).');
    $content=$in['content']??'';$description=$in['description']??'';
    if (!is_string($content) || strlen($content)>1000000 || !is_string($description) || strlen($description)>20000) fail('Content is too large.');
    $subject=trim($in['subject']??'');$language=trim($in['language']??'Plain Text');if (mb_strlen($subject)>120 || mb_strlen($language)>40) fail('Subject or language is too long.');
    $tags=$in['tags']??[];if (!is_array($tags) || count($tags)>20) fail('Use up to 20 tags.');
    $tags=array_values(array_unique(array_filter(array_map(fn($t)=>mb_strtolower(trim(mb_substr((string)$t,0,40))),$tags))));
    [$project,$folder]=validateRelations($in,$uid,$id?:null,$kind);
    if ($old && isset($in['expected_revision']) && (int)$in['expected_revision'] !== (int)$old['revision']) fail('This item changed in another tab. Copy your edits before reloading.',409);
    $vals=[$title,$content,$description,$language,$subject,json_encode($tags,JSON_UNESCAPED_UNICODE),$project,$folder,empty($in['pinned'])?0:1,now()];
    db()->beginTransaction();
    try {
        if ($old) {
            if ($old['kind']==='code' && ($old['content']??'')!==$content) {
                if (isset($in['expected_updated_at']) && $in['expected_updated_at']!==$old['updated_at']) { db()->rollBack();fail('This item changed in another tab. Copy your edits before reloading.',409); }
                query('INSERT INTO versions(item_id,content,created_at) VALUES(?,?,?)',[$id,$old['content']??'',now()]);
                $v=all('SELECT id FROM versions WHERE item_id=? ORDER BY id DESC',[$id]);
                foreach (array_slice($v,20) as $x) query('DELETE FROM versions WHERE id=?',[$x['id']]);
            }
            $statement=query('UPDATE items SET title=?,content=?,description=?,language=?,subject=?,tags=?,project_id=?,folder_id=?,pinned=?,updated_at=?,revision=revision+1 WHERE id=? AND user_id=? AND revision=?',[...$vals,$id,$uid,$old['revision']]);
            if ($statement->rowCount()!==1) { db()->rollBack();fail('This item changed in another tab. Copy your edits before reloading.',409); }
        } else {
            query('INSERT INTO items(title,content,description,language,subject,tags,project_id,folder_id,pinned,updated_at,user_id,kind,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',[...$vals,$uid,$kind,now()]);$id=(int)db()->lastInsertId();
        }
        if (isset($in['attachments'])) {
            if (!in_array($kind,['code','note'],true) || !is_array($in['attachments']) || count($in['attachments'])>30) throw new InvalidArgumentException('Invalid attachments.');
            $files=array_unique(array_map('intval',$in['attachments']));
            foreach ($files as $fid) { $f=owned($fid,$uid);if ($f['kind']!=='file') throw new InvalidArgumentException('Choose a file attachment.'); }
            query('DELETE FROM attachments WHERE item_id=?',[$id]);foreach ($files as $fid) query('INSERT INTO attachments(item_id,file_id) VALUES(?,?)',[$id,$fid]);
        }
        activity($uid,'save_'.$kind);db()->commit();
    } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack();throw $e; }
    return fullItem($id,$uid);
}
function fullItem(int $id,int $uid): array {
    $x=itemData(owned($id,$uid),true);
    $x['attachments']=array_map(fn($a)=>itemData($a),all('SELECT i.* FROM items i INNER JOIN attachments a ON a.file_id=i.id WHERE a.item_id=? AND i.user_id=? AND i.deleted_at IS NULL',[$id,$uid]));
    $x['versions']=all('SELECT id,created_at FROM versions WHERE item_id=? ORDER BY id DESC',[$id]);
    $x['shares']=all('SELECT s.id,s.recipient_id,s.expires_at,s.created_at,u.email FROM shares s LEFT JOIN users u ON s.recipient_id=u.id WHERE s.item_id=? AND s.user_id=? AND s.revoked_at IS NULL',[$id,$uid]);return $x;
}
function upload(int $uid): array {
    $f=$_FILES['file']??null;
    if (!$f || $f['error']!==UPLOAD_ERR_OK) fail('Upload failed. Check file size and hosting upload limits.');
    if ($f['size']<1 || $f['size']>accountLimit($uid,'max_upload_mb',10)*1048576) fail('File exceeds the upload limit or is empty.');
    $name=basename(str_replace('\\','/',$f['name']));$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
    $allow=mediaTypes();
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($allow[$ext]) || !in_array($mime,$allow[$ext],true) || mb_strlen($name)>255) fail('Allowed: images, videos, audio, PDF, DOCX, ODT, TXT, Markdown, CSV. File content must match its type.');
    if(in_array($ext,['docx','odt'])&&!validOfficeDocument($f['tmp_name'],$ext))fail('Invalid document structure.');
    $raw=file_get_contents($f['tmp_name'],false,null,0,8192);
    if (preg_match('/<\?(?:php|=)|<script\b|<html\b/i',$raw)) fail('Executable or HTML uploads are not allowed.');
    [$project,$folder]=validateRelations($_POST,$uid);
    $stored=bin2hex(random_bytes(24)).'.'.$ext;$dest=cfg('storage_path').'/uploads/'.$stored;
    db()->beginTransaction();
    try {
        if (cfg('database')['driver']==='mysql') one('SELECT id FROM users WHERE id=? FOR UPDATE',[$uid]);
        $used=(int)one("SELECT COALESCE(SUM(size_bytes),0) AS used FROM items WHERE user_id=? AND kind='file'",[$uid])['used'];
        if ($used+$f['size']>accountLimit($uid,'quota_mb',500)*1048576) {db()->rollBack();fail('Storage is full. Permanently delete files from Trash before uploading.');}
        if (!move_uploaded_file($f['tmp_name'],$dest)) throw new RuntimeException('Unable to store upload.');chmod($dest,0600);
        query("INSERT INTO items(user_id,kind,title,original_name,stored_name,mime,size_bytes,project_id,folder_id,created_at,updated_at) VALUES(?,'file',?,?,?,?,?,?,?,?,?)",[$uid,$name,$name,$stored,$mime,$f['size'],$project,$folder,now(),now()]);$id=(int)db()->lastInsertId();
        activity($uid,'upload');
        if ($used+$f['size']>accountLimit($uid,'quota_mb',500)*1048576*0.9) notify($uid,'Storage almost full','Your workspace is using more than 90% of its storage quota.');
        db()->commit();return itemData(owned($id,$uid),true);
    } catch (Throwable $e) {if (db()->inTransaction()) db()->rollBack();if (is_file($dest)) unlink($dest);throw $e;}
}
function deleteForever(array $x): void {
    if ($x['kind']==='file' && $x['stored_name']) { $p=cfg('storage_path').'/uploads/'.basename($x['stored_name']);if (is_file($p) && !unlink($p)) throw new RuntimeException('Unable to remove file bytes.'); }
    query('DELETE FROM items WHERE id=?',[$x['id']]);
}

