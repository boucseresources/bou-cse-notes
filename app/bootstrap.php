<?php
declare(strict_types=1);
const ROOT = __DIR__ . '/..';
require_once __DIR__.'/email-template.php';
require_once __DIR__.'/roles.php';
if (!is_file(ROOT.'/config/config.php')) {
    http_response_code(503); exit('Setup required: copy config/config.example.php to config/config.php and follow README.md.');
}
$config = require ROOT.'/config/config.php';
if (is_file(ROOT.'/vendor/autoload.php')) require ROOT.'/vendor/autoload.php';
date_default_timezone_set('UTC');
$production = $config['environment'] === 'production';
ini_set('display_errors', '0'); ini_set('log_errors', '1');
if ($production && (parse_url($config['app_url'], PHP_URL_SCHEME) !== 'https' || $config['mail']['driver'] !== 'smtp')) {
    http_response_code(503); exit('Production requires an HTTPS app_url and SMTP mail configuration.');
}
header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY'); header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' blob:; font-src 'self'; connect-src 'self'; frame-src 'self' blob:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
if ($production) header('Strict-Transport-Security: max-age=31536000');
ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', (string)$config['session_lifetime']);
$cookiePath = (parse_url($config['app_url'],PHP_URL_PATH) ?: '') . '/';
session_name('bou_session');
session_set_cookie_params(['lifetime'=>0,'path'=>$cookiePath,'secure'=>$production,'httponly'=>true,'samesite'=>'Lax']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
function cfg(string $key): mixed { global $config; return $config[$key]; }
function now(): string { return gmdate('Y-m-d H:i:s'); }
function db(): PDO {
    static $db;
    if (!$db) {
        $c=cfg('database');
        $dsn=$c['driver']==='sqlite' ? 'sqlite:'.$c['sqlite_path'] : "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4";
        $db=new PDO($dsn, $c['driver']==='sqlite'?null:$c['user'], $c['driver']==='sqlite'?null:$c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        if ($c['driver']==='sqlite') { $db->exec('PRAGMA foreign_keys=ON'); $db->exec('PRAGMA busy_timeout=5000'); }
        else $db->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
    }
    return $db;
}
function query(string $sql,array $params=[]): PDOStatement {$started=microtime(true);$s=db()->prepare($sql);$s->execute($params);$GLOBALS['bou_db_ms']=($GLOBALS['bou_db_ms']??0)+(microtime(true)-$started)*1000;$GLOBALS['bou_db_queries']=($GLOBALS['bou_db_queries']??0)+1;return $s;}
function one(string $sql,array $params=[]): ?array { return query($sql,$params)->fetch() ?: null; }
function all(string $sql,array $params=[]): array { return query($sql,$params)->fetchAll(); }
function fail(string $message,int $status=400): never { http_response_code($status);header('Content-Type: application/json');echo json_encode(['error'=>$message]);exit; }
function respond(mixed $data): never {header('Server-Timing: db;dur='.round($GLOBALS['bou_db_ms']??0,2).', queries;desc="'.($GLOBALS['bou_db_queries']??0).'"'); header('X-CSRF-Token: '.($_SESSION['csrf']??''));header('X-BOU-User: '.($_SESSION['user_id']??''));header('Content-Type: application/json');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit; }
function csrf(): void {
    $expected=$_SESSION['csrf']??'';$provided=$_SERVER['HTTP_X_CSRF_TOKEN']??$_POST['_csrf']??'';
    if (!is_string($provided) || !is_string($expected) || $expected==='' || !hash_equals($expected,$provided)) fail('Your session needs to be renewed. Please try again.',419);
}
function rotateCsrf(): void { $_SESSION['csrf']=bin2hex(random_bytes(32)); }
function anonymousSession(): void {
    $_SESSION=[];session_regenerate_id(true);rotateCsrf();cookie('bou_remember','',time()-3600);
}
function cookie(string $name,string $value,int $expires): void { global $production,$cookiePath;setcookie($name,$value,['expires'=>$expires,'path'=>$cookiePath,'secure'=>$production,'httponly'=>true,'samesite'=>'Lax']); }
function user(bool $required=true,bool $verified=true): ?array {
    if (!isset($_SESSION['user_id']) && !empty($_COOKIE['bou_remember'])) {
        $t=one("SELECT * FROM tokens WHERE kind='remember' AND token_hash=? AND expires_at>?",[hash('sha256',$_COOKIE['bou_remember']),now()]);
        if ($t) { $u=one('SELECT * FROM users WHERE id=?',[$t['user_id']]);if ($u && $u['status']==='active') { session_regenerate_id(true);$_SESSION['user_id']=$u['id'];$_SESSION['version']=$u['session_version'];$_SESSION['last_seen']=time(); } }
    }
    $u=isset($_SESSION['user_id'])?one('SELECT * FROM users WHERE id=?',[$_SESSION['user_id']]):null;
    if ($u && ($u['status']!=='active' || (int)$u['session_version']!==(int)($_SESSION['version']??0) || time()-($_SESSION['last_seen']??0)>cfg('session_lifetime'))) {
        unset($_SESSION['user_id']);cookie('bou_remember','',time()-3600);$u=null;
    }
    if ($u) {$_SESSION['last_seen']=time();$u=accountAccess($u);}
    if (!$u && $required) fail('Please sign in.',401);
    if ($u && $required && $verified && ($u['approval']??'approved')!=='approved') fail('Your account is awaiting approval or was declined. Contact an administrator.',403);
    if ($u && $verified && !$u['verified_at']) fail('Verify your email to open your workspace.',403);
    return $u;
}
function safeUser(array $u): array { if(!isset($u['permissions']))$u=accountAccess($u);unset($u['password_hash']);$u['preferences']=json_decode($u['preferences']??'{}',true) ?: new stdClass();return $u; }
function limit(string $key,int $max,int $seconds): void {
    $bucket=hash('sha256',$key);$t=time();$driver=cfg('database')['driver'];
    if ($driver==='sqlite') query('INSERT OR IGNORE INTO rate_limits(bucket,attempts,started_at) VALUES(?,0,?)',[$bucket,$t]);
    else query('INSERT IGNORE INTO rate_limits(bucket,attempts,started_at) VALUES(?,0,?)',[$bucket,$t]);
    query('UPDATE rate_limits SET attempts=0,started_at=? WHERE bucket=? AND started_at<?',[$t,$bucket,$t-$seconds]);
    query('UPDATE rate_limits SET attempts=attempts+1 WHERE bucket=?',[$bucket]);
    if ((int)one('SELECT attempts FROM rate_limits WHERE bucket=?',[$bucket])['attempts']>$max) fail('Too many attempts. Please try again later.',429);
}
function &requestSettings(): array {static $values=null;if($values===null)$values=array_column(all('SELECT `key`,value FROM settings'),'value','key');return $values;}
function setting(string $key,int|string $default): int|string {return requestSettings()[$key]??$default;}
function putSetting(string $key,string $value): void { query('DELETE FROM settings WHERE `key`=?',[$key]);query('INSERT INTO settings (`key`,value) VALUES (?,?)',[$key,$value]);$values=&requestSettings();$values[$key]=$value; }
function notify(int $uid,string $title,string $content=''): void { query('INSERT INTO notifications(user_id,title,content,created_at) VALUES(?,?,?,?)',[$uid,$title,$content,now()]); }
function activity(int $uid,string $action): void { query('INSERT INTO activity(user_id,action,created_at) VALUES(?,?,?)',[$uid,$action,now()]); }
function token(int $uid,string $kind,int $seconds,?string $payload=null): string {
    $raw=bin2hex(random_bytes(32));
    query('DELETE FROM tokens WHERE user_id=? AND kind=?',[$uid,$kind]);
    query('INSERT INTO tokens(user_id,kind,token_hash,payload,expires_at,created_at) VALUES(?,?,?,?,?,?)',[$uid,$kind,hash('sha256',$raw),$payload,gmdate('Y-m-d H:i:s',time()+$seconds),now()]);return $raw;
}
function sendMail(string $to,string $kind,string $subject,string $body): bool {
    $m=cfg('mail');$status='sent';$detail='';$sent=true;
    try {
        if ($m['driver']==='log' && cfg('environment')==='local') {
            $dir=cfg('storage_path').'/mail';if (!is_dir($dir)) mkdir($dir,0700,true);
            file_put_contents($dir.'/'.bin2hex(random_bytes(8)).'.txt',"To: $to\nSubject: $subject\n\n$body",LOCK_EX);$status='local';
        } else {
            if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) throw new RuntimeException('Mail dependency unavailable');
            $mail=new \PHPMailer\PHPMailer\PHPMailer(true);$mail->isSMTP();$mail->Host=$m['host'];$mail->Port=$m['port'];$mail->SMTPAuth=true;$mail->Username=$m['username'];$mail->Password=$m['password'];$mail->SMTPSecure=$m['encryption'];$mail->Timeout=12;
            $mail->setFrom($m['from'],$m['from_name']);$mail->addAddress($to);$mail->CharSet='UTF-8';$mail->isHTML(true);$mail->Subject=$subject;
            $mail->Body=renderBrandedEmail($kind,$subject,$body,[
                'name'=>'BOU CSE Notes',
                'url'=>cfg('app_url'),
                'support'=>cfg('support_email') ?? '',
                'logo'=>$m['logo_url'] ?? '',
            ]);$mail->AltBody=$body;$mail->send();
        }
    } catch (Throwable $e) { $status='failed';$detail='SMTP delivery failed; inspect SMTP settings and provider logs.';$sent=false;error_log('BOU mail delivery failed: '.$kind); }
    query('INSERT INTO email_logs(recipient,kind,status,detail,created_at) VALUES(?,?,?,?,?)',[$to,$kind,$status,$detail,now()]);return $sent;
}
function owned(int $id,int $uid,bool $deleted=false): array { $x=one('SELECT * FROM items WHERE id=? AND user_id=?',[$id,$uid]);if (!$x || (!$deleted && $x['deleted_at'])) fail('Item not found.',404);return $x; }
function itemData(array $x,bool $full=false): array {
    foreach (['id','user_id','favorite','pinned','size_bytes'] as $k) $x[$k]=(int)$x[$k];
    $x['tags']=json_decode($x['tags']??'[]',true) ?: [];
    unset($x['stored_name']);if (!$full) unset($x['content']);return $x;
}



