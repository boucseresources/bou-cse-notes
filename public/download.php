<?php
require __DIR__.'/paths.php';require ROOT.'/app/media.php';require ROOT.'/app/preview.php';
header('Cache-Control: private, no-store');
try {
    $share=$_GET['share']??'';$direct=(int)($_GET['direct']??0);
    if (!empty($_GET['broadcast'])) {
        $u=user();$b=accessibleBroadcast((int)$_GET['broadcast'],$u);
        $x=$b['item_id']?one('SELECT * FROM items WHERE id=? AND user_id=? AND deleted_at IS NULL',[$b['item_id'],$b['author_id']]):null;
    } elseif ($share || $direct) {
        if ($direct) { $u=user();$s=one('SELECT * FROM shares WHERE id=? AND recipient_id=?',[$direct,$u['id']]); }
        else $s=one('SELECT * FROM shares WHERE token_hash=?',[hash('sha256',$share)]);
        if (!$s || $s['revoked_at'] || ($s['expires_at'] && $s['expires_at']<=now())) fail('Share unavailable.',404);
        if ($s['recipient_id'] && !$direct) {$u=user();if ((int)$s['recipient_id']!==(int)$u['id'] && (int)$s['user_id']!==(int)$u['id']) fail('Share unavailable.',404);}
        $owner=one('SELECT * FROM users WHERE id=?',[$s['user_id']]);if (!$owner || $owner['status']!=='active'||accountAccess($owner)['approval']!=='approved') fail('Share unavailable.',404);
        $x=one('SELECT * FROM items WHERE id=? AND deleted_at IS NULL',[$s['item_id']]);
    } else { $u=user();$x=one('SELECT * FROM items WHERE id=? AND user_id=? AND deleted_at IS NULL',[(int)($_GET['id']??0),$u['id']]); }
    if (!$x || !in_array($x['kind'],['code','note','file'],true)) fail('File not found.',404);
    $ext=['C'=>'c','C++'=>'cpp','Java'=>'java','Python'=>'py','JavaScript'=>'js','PHP'=>'php','SQL'=>'sql','HTML'=>'html','CSS'=>'css','8086 / Assembly'=>'asm'];
    $name=$x['kind']==='file'?$x['original_name']:preg_replace('/[^\pL\pN._ -]/u','_',$x['title']).'.'.($x['kind']==='note'?'md':($ext[$x['language']]??'txt'));
    // All downloads, including shared PDFs, are attachments. Never interpret user HTML/code.
    header('Content-Type: application/octet-stream');header("Content-Disposition: attachment; filename=\"download\"; filename*=UTF-8''".rawurlencode($name));
    if ($x['kind']==='file') { $p=cfg('storage_path').'/uploads/'.basename($x['stored_name']);if (!is_file($p)) fail('File bytes unavailable. Contact support.',404);if(isset($_GET['preview'])){if(!in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),['docx','odt','txt','md','csv']))fail('Preview unavailable.',415);respond(filePreviewText($p,$name));}streamMedia($p,$x['mime'],$name,isset($_GET['inline'])); }
    else echo $x['content'];
} catch (Throwable $e) {error_log($e->getMessage());fail('Download unavailable.',500);}

