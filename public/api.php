<?php
require __DIR__.'/paths.php';
require ROOT.'/app/auth.php';require ROOT.'/app/items.php';require ROOT.'/app/study.php';require ROOT.'/app/temp.php';require ROOT.'/app/game.php';
header('Cache-Control: no-store');
try {
    $action=$_GET['action']??'session';$method=$_SERVER['REQUEST_METHOD'];
    $in=$method==='POST'?(str_contains($_SERVER['CONTENT_TYPE']??'','application/json')?json_decode(file_get_contents('php://input'),true):$_POST):$_GET;
    if (!is_array($in)) fail('Invalid request.');
    $writes=['game_profile','game_cheer','temp_create','temp_add','temp_delete','study_save','coding_tick','register','login','logout','forgot','verify','reset','resend','save','upload','favorite','trash','restore','purge','duplicate','version_restore','share','revoke','settings','password','email','logout_all','mark_read','delete_notification','admin_update','admin_config','announce','report','delete_request'];
    if (in_array($action,$writes,true)) { if ($method!=='POST') fail('POST required.',405);csrf(); }
    if (in_array($action,['register','login','logout','forgot','verify','reset','resend'],true)) authAction($action,$in);
    if ($action==='session') { $u=user(false,false);respond(['user'=>$u?safeUser($u):null,'csrf'=>$_SESSION['csrf']]); }
    if ($action==='shared') {
        $token=$in['token']??'';$s=one('SELECT s.*,u.status FROM shares s JOIN users u ON u.id=s.user_id WHERE s.token_hash=? AND s.revoked_at IS NULL AND (s.expires_at IS NULL OR s.expires_at>?)',[hash('sha256',$token),now()]);
        if (!$s || $s['status']!=='active') fail('Share link is unavailable.',404);
        if ($s['recipient_id']) { $v=user();if ((int)$s['recipient_id']!==(int)$v['id'] && (int)$s['user_id']!==(int)$v['id']) fail('Share link is unavailable.',404); }
        $item=one('SELECT * FROM items WHERE id=? AND deleted_at IS NULL',[$s['item_id']]);if (!$item) fail('Share link is unavailable.',404);
        // Attachments stay private. Sharing a parent never grants file access.
        respond(['item'=>itemData($item,true),'owner'=>one('SELECT name FROM users WHERE id=?',[$s['user_id']])['name']]);
    }
    if(in_array($action,['temp_create','temp_get','temp_add','temp_delete'],true))tempAction($action,$in);
    $u=user();$uid=(int)$u['id'];
    if($action==='leaderboard')respond(gameLeaderboard($in,$uid));
    if($action==='game_profile'){gameInit();$campus=trim((string)($in['campus']??''));if(mb_strlen($campus)>100)fail('Campus name is too long.');$insert=cfg('database')['driver']==='mysql'?'INSERT IGNORE':'INSERT OR IGNORE';query($insert.' INTO game_profiles(user_id,campus,participating) VALUES(?,\'\',0)',[$uid]);query('UPDATE game_profiles SET campus=?,participating=? WHERE user_id=?',[$campus,empty($in['participating'])?0:1,$uid]);respond(['ok'=>true]);}
    if($action==='game_cheer'){gameInit();$id=(int)($in['id']??0);if($id===$uid)fail('Cheer for another student.');if(!one("SELECT u.id FROM users u JOIN game_profiles p ON p.user_id=u.id WHERE u.id=? AND u.status='active' AND u.verified_at IS NOT NULL AND p.participating=1",[$id]))fail('Student unavailable.',404);$insert=cfg('database')['driver']==='mysql'?'INSERT IGNORE':'INSERT OR IGNORE';query($insert.' INTO game_cheers(giver_id,receiver_id,created_at) VALUES(?,?,?)',[$uid,$id,now()]);respond(['ok'=>true]);}
    if ($action==='study') respond(studySummary($uid,$u));
    if ($action==='study_save') {$result=studySave($in,$uid);gameSync($uid);respond($result);}
    if ($action==='coding_tick') {
        $stamp=time();$previous=(int)($_SESSION['coding_tick_at']??0);$activeId=(int)($in['id']??0);
        if (!$activeId || owned($activeId,$uid)['kind']!=='code') fail('Open a saved code before tracking coding time.');
        if ($previous && $stamp-$previous>=28 && $stamp-$previous<=90) activity($uid,'coding_30s');
        if (!$previous || $stamp-$previous>=28) $_SESSION['coding_tick_at']=$stamp;
        respond(['recorded'=>true]);
    }
    if ($action==='dashboard') {
        $counts=all('SELECT kind,COUNT(*) AS total FROM items WHERE user_id=? AND deleted_at IS NULL GROUP BY kind',[$uid]);
        $used=(int)one("SELECT COALESCE(SUM(size_bytes),0) AS used FROM items WHERE user_id=? AND kind='file'",[$uid])['used'];
        respond(['counts'=>$counts,'used'=>$used,'quota'=>(int)setting('quota_mb',500)*1048576,'max_upload_mb'=>(int)setting('max_upload_mb',10),
            'recent'=>array_map(fn($x)=>itemData($x),all("SELECT * FROM items WHERE user_id=? AND deleted_at IS NULL AND kind IN ('code','note','project') ORDER BY updated_at DESC,id DESC LIMIT 8",[$uid])),
            'recent_codes'=>array_map(fn($x)=>itemData($x),all("SELECT * FROM items WHERE user_id=? AND kind='code' AND deleted_at IS NULL ORDER BY updated_at DESC,id DESC LIMIT 20",[$uid])),
            'recent_notes'=>array_map(fn($x)=>itemData($x),all("SELECT * FROM items WHERE user_id=? AND kind='note' AND deleted_at IS NULL ORDER BY pinned DESC,updated_at DESC,id DESC LIMIT 4",[$uid])),
            'files'=>array_map(fn($x)=>itemData($x),all("SELECT * FROM items WHERE user_id=? AND kind='file' AND deleted_at IS NULL ORDER BY id DESC LIMIT 4",[$uid])),
            'activity'=>all('SELECT SUBSTR(created_at,1,10) AS day,COUNT(*) AS total FROM activity WHERE user_id=? AND created_at>? GROUP BY SUBSTR(created_at,1,10)',[$uid,gmdate('Y-m-d H:i:s',time()-7*86400)]),
            'study'=>studySummary($uid,$u),
            'unread'=>(int)one('SELECT COUNT(*) AS n FROM notifications WHERE user_id=? AND read_at IS NULL',[$uid])['n']]);
    }
    if ($action==='list') {
        $where=['user_id=?'];$params=[$uid];$kind=$in['kind']??'';$mode=$in['mode']??'';
        $where[]=$mode==='trash'?'deleted_at IS NOT NULL':'deleted_at IS NULL';
        if ($kind==='project') $where[]="kind IN ('project','folder')";
        elseif ($kind) {$where[]='kind=?';$params[]=$kind;}
        if ($mode==='favorites') $where[]='favorite=1';
        foreach (['project_id','folder_id','language'] as $k) if (!empty($in[$k])) { $where[]="$k=?";$params[]=$in[$k]; }
        if (!empty($in['q'])) { $q='%'.str_replace(['!','%','_'],['!!','!%','!_'],mb_substr($in['q'],0,150)).'%';$where[]="(title LIKE ? ESCAPE '!' OR content LIKE ? ESCAPE '!' OR description LIKE ? ESCAPE '!' OR tags LIKE ? ESCAPE '!' OR subject LIKE ? ESCAPE '!')";array_push($params,$q,$q,$q,$q,$q); }
        $fileTypes=['images'=>"mime LIKE 'image/%'",'videos'=>"mime LIKE 'video/%'",'audio'=>"(mime LIKE 'audio/%' OR mime='application/ogg')",'pdf'=>"mime='application/pdf'",'documents'=>"(original_name LIKE '%.docx' OR original_name LIKE '%.odt')",'text'=>"(original_name LIKE '%.txt' OR original_name LIKE '%.md' OR original_name LIKE '%.csv')"];
        if(isset($fileTypes[$in['file_type']??'']))$where[]="kind='file' AND (".$fileTypes[$in['file_type']].")";
        $page=max(1,min(100000,(int)($in['page']??1)));$offset=($page-1)*30;$sql=implode(' AND ',$where);
        $sorts=['title'=>'title ASC,id ASC','title_desc'=>'title DESC,id DESC','oldest'=>'updated_at ASC,id ASC','largest'=>'size_bytes DESC,id DESC','smallest'=>'size_bytes ASC,id ASC','type'=>'mime ASC,title ASC,id ASC','recent'=>'pinned DESC,updated_at DESC,id DESC'];$sort=$sorts[$in['sort']??'recent']??$sorts['recent'];
        $total=(int)one("SELECT COUNT(*) AS n FROM items WHERE $sql",$params)['n'];
        // List only metadata, never full code bodies.
        $cols='id,user_id,kind,title,description,language,subject,tags,project_id,folder_id,favorite,pinned,original_name,mime,size_bytes,created_at,updated_at,deleted_at';
        respond(['items'=>array_map(fn($x)=>itemData($x),all("SELECT $cols FROM items WHERE $sql ORDER BY $sort LIMIT 30 OFFSET $offset",$params)),'total'=>$total,'page'=>$page,'pages'=>(int)ceil($total/30)]);
    }
    if ($action==='options') {
        respond(['projects'=>all("SELECT id,title FROM items WHERE user_id=? AND kind='project' AND deleted_at IS NULL ORDER BY title",[$uid]),'folders'=>all("SELECT id,title,project_id,folder_id FROM items WHERE user_id=? AND kind='folder' AND deleted_at IS NULL ORDER BY title",[$uid]),'tags'=>all("SELECT tags FROM items WHERE user_id=? AND deleted_at IS NULL",[$uid])]);
    }
    if ($action==='item') respond(fullItem((int)($in['id']??0),$uid));
    if ($action==='save') { limit('save-'.$uid,120,60);$result=saveItem($in,$uid);gameSync($uid);respond($result); }
    if ($action==='upload') { limit('upload-'.$uid,30,60);$result=upload($uid);gameSync($uid);respond($result); }
    if (in_array($action,['favorite','trash','restore','purge','duplicate'],true)) {
        $x=owned((int)($in['id']??0),$uid,true);
        if ($action==='favorite') query('UPDATE items SET favorite=? WHERE id=?',[empty($x['favorite'])?1:0,$x['id']]);
        if ($action==='trash') {
            if (in_array($x['kind'],['project','folder'],true)) {
                $column=$x['kind']==='project'?'project_id':'folder_id';
                $n=(int)one("SELECT COUNT(*) AS n FROM items WHERE user_id=? AND $column=?",[$uid,$x['id']])['n'];
                if ($n && ($in['contents']??'')!=='keep') fail('Choose “Keep contents” to move contained items to the workspace.');
                query("UPDATE items SET $column=NULL,folder_id=NULL WHERE user_id=? AND $column=?",[$uid,$x['id']]);
            }
            query('UPDATE items SET deleted_at=?,updated_at=? WHERE id=?',[now(),now(),$x['id']]);query('UPDATE shares SET revoked_at=? WHERE item_id=?',[now(),$x['id']]);
        }
        if ($action==='restore') query('UPDATE items SET deleted_at=NULL,updated_at=? WHERE id=?',[now(),$x['id']]);
        if ($action==='purge') {if (!$x['deleted_at']) fail('Move the item to Trash first.');deleteForever($x);}
        if ($action==='duplicate') {
            if (!in_array($x['kind'],['code','note'],true) || $x['deleted_at']) fail('Only active codes and notes can be duplicated.');
            $x['id']=0;$x['title']=mb_substr($x['title'],0,245).' (copy)';$x['tags']=json_decode($x['tags']??'[]',true)?:[];respond(saveItem($x,$uid));
        }
        respond(['ok'=>true]);
    }
    if ($action==='version' || $action==='version_restore') {
        $v=one('SELECT * FROM versions WHERE id=?',[(int)($in['id']??0)]);if (!$v) fail('Version not found.',404);$x=owned((int)$v['item_id'],$uid);
        if ($action==='version') respond($v);
        $x['content']=$v['content'];$x['tags']=json_decode($x['tags']??'[]',true)?:[];respond(saveItem($x,$uid));
    }
    if ($action==='share') {
        limit('share-'.$uid,20,60);$x=owned((int)($in['id']??0),$uid);if (!in_array($x['kind'],['code','note','file'],true)) fail('Share a code, note, or file.');
        $recipient=null;$e=strtolower(trim($in['email']??''));
        if ($e) { $r=one("SELECT id FROM users WHERE email=? AND verified_at IS NOT NULL AND status='active'",[$e]);if (!$r || (int)$r['id']===$uid) fail('Choose another verified workspace account.');$recipient=(int)$r['id']; }
        $days=max(0,min(365,(int)($in['days']??0)));$raw=bin2hex(random_bytes(32));
        query('INSERT INTO shares(item_id,user_id,token_hash,recipient_id,expires_at,created_at) VALUES(?,?,?,?,?,?)',[$x['id'],$uid,hash('sha256',$raw),$recipient,$days?gmdate('Y-m-d H:i:s',time()+$days*86400):null,now()]);
        if ($recipient) {notify($recipient,'Content shared with you',$u['name'].' shared “'.$x['title'].'”.');$r=one('SELECT * FROM users WHERE id=?',[$recipient]);$prefs=json_decode($r['preferences']??'{}',true)?:[];if ($prefs['sharing_email']??true) sendMail($r['email'],'shared','Content shared with you',$u['name'].' shared '.$x['title'].'. Open Shared With Me in your workspace.');}
        gameSync($uid);respond(['url'=>cfg('app_url').'/#share?token='.$raw,'download'=>cfg('app_url').'/download.php?share='.$raw]);
    }
    if ($action==='revoke') {query('UPDATE shares SET revoked_at=? WHERE id=? AND user_id=?',[now(),(int)($in['id']??0),$uid]);respond(['ok'=>true]);}
    if ($action==='shared_with_me') {
        respond(all('SELECT s.id AS share_id,s.user_id AS owner_id,i.id,i.kind,i.title,i.mime,i.size_bytes,s.created_at,u.name AS owner FROM shares s JOIN items i ON i.id=s.item_id JOIN users u ON u.id=s.user_id WHERE s.recipient_id=? AND s.revoked_at IS NULL AND (s.expires_at IS NULL OR s.expires_at>?) AND i.deleted_at IS NULL AND u.status=\'active\' ORDER BY s.id DESC',[$uid,now()]));
    }
    if ($action==='direct_item') {
        $s=one('SELECT * FROM shares WHERE id=? AND recipient_id=? AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at>?)',[(int)($in['share_id']??0),$uid,now()]);
        if (!$s) fail('Share unavailable.',404);$owner=one('SELECT status FROM users WHERE id=?',[$s['user_id']]);if (!$owner || $owner['status']!=='active') fail('Share unavailable.',404);
        respond(['item'=>itemData(owned((int)$s['item_id'],(int)$s['user_id']),true),'share_id'=>$s['id']]);
    }
    if ($action==='notification_state') {
        $counts=one('SELECT COUNT(*) AS total,COALESCE(SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END),0) AS unread,COALESCE(MAX(id),0) AS latest_id FROM notifications WHERE user_id=?',[$uid]);
        $xs=all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 100',[$uid]);
        $revision=hash('sha256',json_encode([$counts,$xs]));
        respond(['total'=>(int)$counts['total'],'unread'=>(int)$counts['unread'],'latest_id'=>(int)$counts['latest_id'],'revision'=>$revision,'items'=>($in['since']??'')===$revision?null:$xs]);
    }
    if ($action==='delete_notification'){query('DELETE FROM notifications WHERE id=? AND user_id=?',[(int)($in['id']??0),$uid]);respond(['ok'=>true]);}
    if ($action==='notifications') respond(all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 100',[$uid]));
    if ($action==='mark_read') {
        if (!empty($in['id'])) query('UPDATE notifications SET read_at=? WHERE user_id=? AND id=? AND read_at IS NULL',[now(),$uid,(int)$in['id']]);
        else query('UPDATE notifications SET read_at=? WHERE user_id=? AND id<=? AND read_at IS NULL',[now(),$uid,(int)($in['up_to']??PHP_INT_MAX)]);
        respond(['ok'=>true]);
    }
    if ($action==='settings') {
        $name=trim($in['name']??'');$semester=trim($in['semester']??'');if (mb_strlen($name)<2 || mb_strlen($name)>120 || mb_strlen($semester)>60) fail('Enter a name and a valid semester.');
        $prefs=['default_language'=>mb_substr($in['default_language']??'C',0,40),'timezone'=>in_array($in['timezone']??'',timezone_identifiers_list(),true)?$in['timezone']:'Asia/Dhaka','security_email'=>!empty($in['security_email']),'sharing_email'=>!empty($in['sharing_email']),'announcement_email'=>!empty($in['announcement_email'])];
        $avatar=trim($in['avatar']??'');if ($avatar && !preg_match('/^[A-Za-z0-9]{1,3}$/',$avatar)) fail('Avatar initials must be 1–3 letters or numbers.');
        query('UPDATE users SET name=?,semester=?,avatar=?,preferences=?,updated_at=? WHERE id=?',[$name,$semester,$avatar,json_encode($prefs),now(),$uid]);respond(['user'=>safeUser(one('SELECT * FROM users WHERE id=?',[$uid]))]);
    }
    if ($action==='password') {
        if (!password_verify($in['current_password']??'',$u['password_hash'])) fail('Current password is incorrect.');
        $pw=$in['password']??'';if (mb_strlen($pw)<12 || strlen($pw)>72) fail('Use a password of 12–72 characters.');
        query('UPDATE users SET password_hash=?,session_version=session_version+1,updated_at=? WHERE id=?',[password_hash($pw,PASSWORD_DEFAULT),now(),$uid]);query('DELETE FROM tokens WHERE user_id=?',[$uid]);$_SESSION['version']=$u['session_version']+1;session_regenerate_id(true);cookie('bou_remember','',time()-3600);
        sendMail($u['email'],'password_changed','Password changed','Your password was changed. Other sessions have been revoked.');respond(['ok'=>true]);
    }
    if ($action==='email') {
        limit('change-email-'.$uid,2,3600);if (!password_verify($in['password']??'',$u['password_hash'])) fail('Password is incorrect.');$e=strtolower(trim($in['email']??''));
        if (!filter_var($e,FILTER_VALIDATE_EMAIL) || mb_strlen($e)>190 || one('SELECT id FROM users WHERE email=?',[$e])) fail('Choose an available, valid email.');
        $raw=token($uid,'verify',86400,$e);$sent=sendMail($e,'email_change','Verify your new email','Verify your new email: '.cfg('app_url').'/#verify?token='.$raw);sendMail($u['email'],'email_change','Email change requested','An email change was requested on your account. Your address remains unchanged until verification.');respond(['message'=>$sent?'Verification sent to your new email.':'Delivery failed. Your current email remains unchanged.']);
    }
    if ($action==='logout_all') {query('UPDATE users SET session_version=session_version+1 WHERE id=?',[$uid]);query('DELETE FROM tokens WHERE user_id=?',[$uid]);$_SESSION=[];session_destroy();cookie('bou_remember','',time()-3600);respond(['ok'=>true]);}
    if ($action==='export') {
        header('Content-Type: application/json');header('Content-Disposition: attachment; filename="bou-workspace.json"');
        echo json_encode(['user'=>safeUser($u),'items'=>array_map(fn($x)=>itemData($x,true),all('SELECT * FROM items WHERE user_id=?',[$uid]))],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);exit;
    }
    if ($action==='delete_request') {limit('delete-request-'.$uid,1,86400);if (!password_verify($in['password']??'',$u['password_hash'])) fail('Password is incorrect.');foreach (all("SELECT id FROM users WHERE role='admin' AND status='active'") as $a) notify((int)$a['id'],'Account deletion request',$u['email'].' has requested account deletion. Contact the student and follow the backup retention policy.');notify($uid,'Deletion request received','An administrator will contact you to confirm and process your request.');respond(['ok'=>true]);}
    if ($action==='help') respond(['support_email'=>setting('support_email',cfg('support_email'))]);
    if ($action==='report') {limit('report-'.$uid,5,3600);$content=trim($in['content']??'');if (!$content || mb_strlen($content)>4000) fail('Write a message of up to 4000 characters.');foreach (all("SELECT id FROM users WHERE role='admin' AND status='active'") as $a) notify((int)$a['id'],'Feedback / content report',$u['email'].': '.$content);respond(['ok'=>true]);}
    if (str_starts_with($action,'admin_') || $action==='announce') {
        if ($u['role']!=='admin') fail('Administrator access required.',403);
        if ($action==='admin_data') respond(['users'=>all('SELECT u.id,u.name,u.email,u.role,u.status,u.verified_at,u.created_at,COALESCE(SUM(i.size_bytes),0) AS used FROM users u LEFT JOIN items i ON i.user_id=u.id GROUP BY u.id,u.name,u.email,u.role,u.status,u.verified_at,u.created_at ORDER BY u.id DESC LIMIT 200'),
            'settings'=>all('SELECT * FROM settings'),'emails'=>all('SELECT * FROM email_logs ORDER BY id DESC LIMIT 100'),'shares'=>all('SELECT s.id,i.title,u.email,s.created_at FROM shares s JOIN items i ON i.id=s.item_id JOIN users u ON u.id=s.user_id WHERE s.revoked_at IS NULL ORDER BY s.id DESC LIMIT 100')]);
        if ($action==='admin_update') { $id=(int)($in['id']??0);$target=one('SELECT * FROM users WHERE id=?',[$id]);if (!$target || $id===$uid || $target['role']==='admin') fail('Cannot change this administrator.');$status=($in['status']??'')==='suspended'?'suspended':'active';query('UPDATE users SET status=?,session_version=session_version+1 WHERE id=?',[$status,$id]);query("DELETE FROM tokens WHERE user_id=? AND kind='remember'",[$id]);respond(['ok'=>true]); }
        if ($action==='admin_config') {
            $previousSettings=all('SELECT * FROM settings');db()->beginTransaction();foreach (['quota_mb'=>[1,10000],'max_upload_mb'=>[1,50],'trash_days'=>[1,365]] as $k=>$range) {$v=(int)($in[$k]??0);if ($v<$range[0] || $v>$range[1]) {db()->rollBack();fail('Configuration values are out of range.');}putSetting($k,(string)$v);}
            if (!filter_var($in['support_email']??'',FILTER_VALIDATE_EMAIL)) {db()->rollBack();fail('Enter a valid support email.');}putSetting('support_email',$in['support_email']);$changes=[];foreach($previousSettings as $settingRow){$key=$settingRow['key'];if(isset($in[$key])&&(string)$in[$key]!==$settingRow['value'])$changes[]=$key;}if($changes){$labels=['quota_mb'=>'Storage quota','max_upload_mb'=>'Maximum upload size','trash_days'=>'Trash retention','support_email'=>'Support email'];$message=implode("\n",array_map(fn($key)=>($labels[$key]??$key).': '.$in[$key].(in_array($key,['quota_mb','max_upload_mb'])?' MB':($key==='trash_days'?' days':'')),$changes));foreach(all("SELECT id FROM users WHERE status='active'") as $account)notify((int)$account['id'],'Workspace settings updated',$message);}db()->commit();respond(['ok'=>true]);
        }
        if ($action==='admin_revoke') {csrf();if ($method!=='POST') fail('POST required.',405);query('UPDATE shares SET revoked_at=? WHERE id=?',[now(),(int)($in['id']??0)]);respond(['ok'=>true]);}
        if ($action==='announce') {
            limit('announce-'.$uid,3,3600);$title=trim($in['title']??'');$content=trim($in['content']??'');if (!$title || mb_strlen($title)>255 || mb_strlen($content)>4000) fail('Enter a title and message.');
            foreach (all("SELECT id FROM users WHERE status='active'") as $a) notify((int)$a['id'],$title,$content);respond(['ok'=>true]);
        }
    }
    fail('Action not found.',404);
} catch (InvalidArgumentException $e) {fail($e->getMessage());} catch (Throwable $e) {
    try { if (db()->inTransaction()) db()->rollBack(); } catch (Throwable $ignored) {} error_log('BOU error: '.$e->getMessage());fail('Unable to complete the request. Please try again or contact support.',500);
}
