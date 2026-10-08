<?php
require_once __DIR__.'/media.php';require_once __DIR__.'/preview.php';
function tempDir(): string {$d=cfg('storage_path').'/temporary';if(!is_dir($d)&&!mkdir($d,0700,true))throw new RuntimeException('Temporary storage unavailable.');return $d;}
function tempRemove(string $path,array $page): void {foreach($page['entries']??[] as $e)if(isset($e['stored'])){ $f=tempDir().'/'.basename($e['stored']);if(is_file($f)&&!unlink($f))throw new RuntimeException('Temporary file cleanup failed.');}if(is_file($path)&&!unlink($path))throw new RuntimeException('Temporary page cleanup failed.');}
function tempCleanup(): int {$count=0;foreach(glob(tempDir().'/*.json') as $f){$p=json_decode(file_get_contents($f),true);if($p&&$p['expires']<=time()){tempRemove($f,$p);$count++;}}return $count;}
function tempLock(){ $f=fopen(tempDir().'/.lock','c');if(!$f||!flock($f,LOCK_EX))throw new RuntimeException('Temporary storage busy.');return $f; }
function tempPage(string $token): array {if(!preg_match('/^[a-f0-9]{48}$/',$token))fail('Page expired or unavailable.',404);$f=tempDir().'/'.$token.'.json';$p=is_file($f)?json_decode(file_get_contents($f),true):null;if(!$p||$p['expires']<=time()){if($p)tempRemove($f,$p);fail('Page expired or unavailable.',410);}return $p;}
function tempPublic(array $p): array {unset($p['delete_hash']);foreach($p['entries'] as &$e)unset($e['stored']);return $p;}
function tempSave(string $token,array $p): void {if(file_put_contents(tempDir().'/'.$token.'.json',json_encode($p,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),LOCK_EX)===false)throw new RuntimeException('Unable to save temporary page.');}
function tempAction(string $action,array $in): never {
    $limits=guestLimits();if(!$limits['enabled'])fail('Anonymous sharing is disabled by the administrator.',403);
    $lock=tempLock();tempCleanup();$ip=$_SERVER['REMOTE_ADDR']??'cli';
    if($action==='temp_create'){
        limit('temp-create-'.$ip,$limits['pages_hour'],3600);if(count(glob(tempDir().'/*.json'))>=1000)fail('Temporary pages are at capacity. Try later.',429);
        $seconds=(int)($in['duration']??3600);if(!in_array($seconds,[900,3600,21600,86400,259200,604800],true)||$seconds>$limits['max_hours']*3600)fail('Choose a supported expiry.');
        $title=trim((string)($in['title']??'Temporary drop'));if(!$title||mb_strlen($title)>120)fail('Use a title of up to 120 characters.');
        $token=bin2hex(random_bytes(24));$secret=bin2hex(random_bytes(32));$p=['title'=>$title,'expires'=>time()+$seconds,'created'=>time(),'entries'=>[],'size'=>0,'delete_hash'=>hash('sha256',$secret)];tempSave($token,$p);
        respond(['token'=>$token,'delete_key'=>$secret,'page'=>tempPublic($p),'url'=>rtrim(cfg('app_url'),'/').'/#drop?token='.$token]);
    }
    $token=(string)($in['token']??'');$p=tempPage($token);
    if($action==='temp_get')respond(tempPublic($p));
    if($action==='temp_delete'){if(!hash_equals($p['delete_hash'],hash('sha256',(string)($in['delete_key']??''))))fail('Only the creator can delete this page.',403);tempRemove(tempDir().'/'.$token.'.json',$p);respond(['ok'=>true]);}
    if($action==='temp_add'){
        limit('temp-add-'.$ip,30,3600);if(count($p['entries'])>=$limits['entries'])fail('This page has reached its entry limit.');
        $kind=$in['kind']??'text';if(!in_array($kind,['text','code','file'],true))fail('Choose text, code or file.');$entry=['id'=>bin2hex(random_bytes(8)),'kind'=>$kind,'created'=>time()];$dest=null;
        if($kind==='file'){
            $f=$_FILES['file']??null;if(!$f||$f['error']!==UPLOAD_ERR_OK||$f['size']<1||$f['size']>$limits['file_mb']*1048576)fail('File exceeds the anonymous upload limit.');
            $name=basename(str_replace('\\','/',$f['name']));$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));$mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$types=mediaTypes();
            if(mb_strlen($name)>255||!isset($types[$ext])||!in_array($mime,$types[$ext],true))fail('Use images, video, audio, PDF, DOCX, ODT, TXT, MD or CSV.');
            if(in_array($ext,['docx','odt'])&&!validOfficeDocument($f['tmp_name'],$ext))fail('Invalid document structure.');
            $entry+=['title'=>$name,'mime'=>$mime,'size'=>$f['size'],'stored'=>bin2hex(random_bytes(24)).'.'.$ext];$dest=tempDir().'/'.$entry['stored'];
        }else{
            $text=(string)($in['content']??'');if(!trim($text)||strlen($text)>$limits['text_mb']*1048576)fail('Text or code exceeds the anonymous content limit.');$title=trim((string)($in['title']??''));if(mb_strlen($title)>120)fail('Title is too long.');$entry+=['title'=>$title?:($kind==='code'?'Code snippet':'Text note'),'content'=>$text,'size'=>strlen($text),'language'=>mb_substr((string)($in['language']??'Plain Text'),0,40)];
        }
        if($p['size']+$entry['size']>$limits['page_mb']*1048576)fail('This page has reached its storage limit.');$used=0;foreach(glob(tempDir().'/*.json') as $f){$v=json_decode(file_get_contents($f),true);$used+=(int)($v['size']??0);}if($used+$entry['size']>$limits['total_mb']*1048576)fail('Temporary storage is full. Try later.',429);
        if($dest&&!move_uploaded_file($_FILES['file']['tmp_name'],$dest))throw new RuntimeException('Unable to store file.');
        $p['entries'][]=$entry;$p['size']+=$entry['size'];try{tempSave($token,$p);}catch(Throwable $e){if($dest&&is_file($dest))unlink($dest);throw $e;}respond(tempPublic($p));
    }
    fail('Unknown temporary action.',404);
}

