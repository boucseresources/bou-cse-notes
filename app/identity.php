<?php
function normalizedUsername(string $value): string {
    $value=strtolower(trim($value));
    if(!preg_match('/^[a-z][a-z0-9_]{2,23}$/D',$value))fail('Username: 3–24 characters, starting with a letter; use letters, numbers and underscores.');
    return $value;
}
function availableUsername(string $email,int $suffix=0): string {
    $base=strtolower(explode('@',$email)[0]);$base=preg_replace('/[^a-z0-9_]/','_',$base);$base=trim($base,'_');
    if(!preg_match('/^[a-z]/',$base))$base='user_'.$base;if(strlen($base)<3)$base='user_'.$base;$base=substr($base,0,24);
    $candidate=$base;$n=$suffix;
    while(one('SELECT id FROM users WHERE username=?',[$candidate])){$tail='_'.(++$n);$candidate=substr($base,0,24-strlen($tail)).$tail;}
    return $candidate;
}
function photoUrl(array $u): ?string {
    return empty($u['profile_photo'])?null:'avatar.php?id='.(int)$u['id'].'&v='.substr(hash('sha256',$u['profile_photo']),0,16);
}
function iniBytes(string $value): int {
    $value=trim($value);if($value==='0'||$value==='-1')return PHP_INT_MAX;
    $unit=strtolower(substr($value,-1));return (int)((float)$value*(match($unit){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1}));
}
function hostingFileLimit(): int {return max(0,min(iniBytes((string)ini_get('upload_max_filesize')),iniBytes((string)ini_get('post_max_size'))-65536));}
function receivedFile(string $key='file'): array {
    $f=$_FILES[$key]??null;if(!$f||is_array($f['error']??null))fail('Choose a file first. The hosting upload limit may also have been exceeded.');
    $message=match((int)$f['error']){UPLOAD_ERR_OK=>'',UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE=>'The file exceeds the hosting upload limit.',UPLOAD_ERR_PARTIAL=>'Only part of the file arrived. Keep this tab open and retry.',UPLOAD_ERR_NO_FILE=>'Choose a file first.',UPLOAD_ERR_NO_TMP_DIR=>'Hosting temporary upload folder is missing.',UPLOAD_ERR_CANT_WRITE=>'Hosting could not write this file. Check available disk space.',UPLOAD_ERR_EXTENSION=>'A hosting extension blocked this upload.',default=>'Upload failed. Retry when connected.'};
    if($message!=='')fail($message,(int)$f['error']===UPLOAD_ERR_INI_SIZE?413:400);return $f;
}
function photoAction(array $u): never {
    $uid=(int)$u['id'];limit('photo-'.$uid,20,3600);$old=$u['profile_photo']??null;
    if($_GET['action']==='profile_photo_remove'){query('UPDATE users SET profile_photo=NULL,updated_at=? WHERE id=?',[now(),$uid]);if($old&&is_file(cfg('storage_path').'/avatars/'.basename($old)))unlink(cfg('storage_path').'/avatars/'.basename($old));respond(['user'=>safeUser(one('SELECT * FROM users WHERE id=?',[$uid]))]);}
    $f=receivedFile();if($f['size']<1||$f['size']>min(2*1048576,hostingFileLimit()))fail('Profile photo must fit 2 MB and the hosting upload limit.',413);
    $info=@getimagesize($f['tmp_name']);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!$info||!isset($types[$mime])||($info['mime']??'')!==$mime||$info[0]>2048||$info[1]>2048||$info[0]*$info[1]>4194304)fail('Use a JPG, PNG or WebP photo up to 2048 × 2048 pixels.');
    $dir=cfg('storage_path').'/avatars';if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Profile storage unavailable.');
    $stored=bin2hex(random_bytes(24)).'.'.$types[$mime];$path=$dir.'/'.$stored;
    if(!move_uploaded_file($f['tmp_name'],$path))throw new RuntimeException('Unable to store profile photo.');chmod($path,0600);
    try{query('UPDATE users SET profile_photo=?,updated_at=? WHERE id=?',[$stored,now(),$uid]);}catch(Throwable $e){unlink($path);throw $e;}
    if($old&&is_file($dir.'/'.basename($old)))unlink($dir.'/'.basename($old));respond(['user'=>safeUser(one('SELECT * FROM users WHERE id=?',[$uid]))]);
}
