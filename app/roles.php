<?php
declare(strict_types=1);

function isSuper(array $u): bool {return in_array($u['role'],['super_admin','admin'],true);}
function accountAccess(array $u): array {
    $c=one('SELECT * FROM account_controls WHERE user_id=?',[$u['id']])??[];
    $teacher=$u['role']==='teacher';$super=isSuper($u);
    $u['approval']=$super?'approved':($c['approval']??'approved');
    $u['quota_mb']=(int)($c['quota_mb']??setting('quota_mb',500));
    $u['max_upload_mb']=(int)($c['max_upload_mb']??setting('max_upload_mb',10));
    $u['permissions']=['publish'=>$super||($teacher&&(bool)($c['allow_publish']??1)), 'groups'=>$super||($teacher&&(bool)($c['allow_groups']??1))];
    $u['approval_note']=$c['note']??'';
    return $u;
}
function accountLimit(int $uid,string $key,int $fallback): int {
    if(!in_array($key,['quota_mb','max_upload_mb'],true))throw new InvalidArgumentException('Unknown account limit');
    $row=one("SELECT $key FROM account_controls WHERE user_id=?",[$uid]);
    return (int)($row[$key]??setting($key,$fallback));
}
function guestLimits(): array {
    $defaults=['enabled'=>1,'file_mb'=>10,'text_mb'=>1,'page_mb'=>50,'entries'=>20,'total_mb'=>500,'pages_hour'=>10,'max_hours'=>168];$result=[];
    foreach($defaults as $k=>$v)$result[$k]=(int)setting('guest_'.$k,$v);
    $result['max_file_bytes']=min($result['file_mb']*1048576,hostingFileLimit());return $result;
}
function auditAdmin(int $actor,string $action,?int $target,array $detail=[]): void {
    query('INSERT INTO admin_audit(actor_id,action,target_id,detail,created_at) VALUES(?,?,?,?,?)',[$actor,$action,$target,json_encode($detail),now()]);
}
function controlRecord(int $uid): void {
    $insert=cfg('database')['driver']==='mysql'?'INSERT IGNORE':'INSERT OR IGNORE';query($insert.' INTO account_controls(user_id) VALUES(?)',[$uid]);
}
function approvedMembers(array $ids): array {
    $ids=array_values(array_unique(array_map('intval',$ids)));
    if(count($ids)>1000||in_array(0,$ids,true))fail('Select up to 1000 valid members.');
    if(!$ids)return [];
    $rows=all("SELECT u.id FROM users u LEFT JOIN account_controls c ON c.user_id=u.id WHERE u.id IN (".implode(',',array_fill(0,count($ids),'?')).") AND u.status='active' AND u.verified_at IS NOT NULL AND COALESCE(c.approval,'approved')='approved'",$ids);
    if(count($rows)!==count($ids))fail('Some selected members are not approved and verified.');
    return array_map(fn($x)=>(int)$x['id'],$rows);
}
function managedGroup(int $id,array $u): array {
    $g=one('SELECT * FROM member_groups WHERE id=?',[$id]);
    if(!$g||(!isSuper($u)&&(int)$g['owner_id']!==(int)$u['id']))fail('You cannot manage this group.',403);
    return $g;
}
function accessibleBroadcast(int $id,array $u): array {
    $b=one("SELECT b.*,u.name AS author,u.status AS author_status,COALESCE(c.approval,'approved') AS author_approval FROM broadcasts b JOIN users u ON u.id=b.author_id LEFT JOIN account_controls c ON c.user_id=u.id WHERE b.id=? AND b.revoked_at IS NULL",[$id]);
    if(!$b||$b['author_status']!=='active'||$b['author_approval']!=='approved')fail('Shared resource unavailable.',404);
    if((int)$b['author_id']!==(int)$u['id']&&!isSuper($u)&&$b['audience']!=='all'&&!one('SELECT user_id FROM broadcast_recipients WHERE broadcast_id=? AND user_id=?',[$id,$u['id']]))fail('Shared resource unavailable.',404);
    return $b;
}
function roleAction(string $action,array $in,array $u): never {
    $uid=(int)$u['id'];
    if(str_starts_with($action,'control_')){
        if(!isSuper($u))fail('Super Admin access required.',403);
        if($action==='control_data'){
            $q=mb_substr(trim((string)($in['q']??'')),0,120);$page=max(1,(int)($in['page']??1));$offset=($page-1)*100;
            $where="(u.name LIKE ? OR u.email LIKE ?)";$params=['%'.$q.'%','%'.$q.'%'];
            $users=all("SELECT u.*,COALESCE(c.approval,'approved') AS approval,c.quota_mb AS custom_quota,c.max_upload_mb AS custom_upload,c.allow_publish,c.allow_groups,c.note,(SELECT COALESCE(SUM(size_bytes),0) FROM items i WHERE i.user_id=u.id AND i.kind='file') AS used FROM users u LEFT JOIN account_controls c ON c.user_id=u.id WHERE $where ORDER BY u.id DESC LIMIT 100 OFFSET $offset",$params);
            foreach($users as &$x){unset($x['password_hash']);$x['effective_quota']=accountLimit((int)$x['id'],'quota_mb',500);$x['effective_upload']=accountLimit((int)$x['id'],'max_upload_mb',10);}
            respond(['users'=>$users,'total'=>(int)one("SELECT COUNT(*) AS n FROM users u WHERE $where",$params)['n'],'page'=>$page,'settings'=>all('SELECT * FROM settings'),'guest'=>guestLimits(),'requests'=>all("SELECT r.*,u.name,u.email FROM storage_requests r JOIN users u ON u.id=r.user_id WHERE r.status='pending' ORDER BY r.id LIMIT 200"),'audit'=>all('SELECT a.*,u.name AS actor FROM admin_audit a JOIN users u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT 50')]);
        }
        if($action==='control_user'){
            $id=(int)($in['id']??0);$target=one('SELECT * FROM users WHERE id=?',[$id]);
            if(!$target||$id===$uid||isSuper($target))fail('Super Admin accounts are protected.');
            $role=$in['role']??$target['role'];$status=$in['status']??$target['status'];$approval=$in['approval']??'approved';
            if(!in_array($role,['student','teacher'],true)||!in_array($status,['active','suspended'],true)||!in_array($approval,['pending','approved','rejected'],true))fail('Invalid role, approval or account status.');
            $quota=nullableLimit($in['quota_mb']??null,1,100000);$upload=nullableLimit($in['max_upload_mb']??null,1,500);
            $note=mb_substr(trim((string)($in['note']??'')),0,1000);
            $publish=empty($in['allow_publish'])?0:1;$groups=empty($in['allow_groups'])?0:1;
            db()->beginTransaction();controlRecord($id);
            query('UPDATE account_controls SET approval=?,quota_mb=?,max_upload_mb=?,allow_publish=?,allow_groups=?,note=? WHERE user_id=?',[$approval,$quota,$upload,$publish,$groups,$note,$id]);
            query('UPDATE users SET role=?,status=?,updated_at=? WHERE id=?',[$role,$status,now(),$id]);
            if($status!=='active'||$approval!=='approved') {query('UPDATE users SET session_version=session_version+1 WHERE id=?',[$id]);query("DELETE FROM tokens WHERE user_id=? AND kind='remember'",[$id]);}
            notify($id,'Account permissions updated','Approval: '.$approval.'. Role: '.$role.'. '.$note);auditAdmin($uid,'account_updated',$id,['role'=>$role,'status'=>$status,'approval'=>$approval,'quota_mb'=>$quota,'max_upload_mb'=>$upload]);db()->commit();respond(['ok'=>true]);
        }
        if($action==='control_guest'){
            $ranges=['enabled'=>[0,1],'file_mb'=>[1,100],'text_mb'=>[1,5],'page_mb'=>[1,500],'entries'=>[1,100],'total_mb'=>[1,50000],'pages_hour'=>[1,100],'max_hours'=>[1,168]];$values=[];
            foreach($ranges as $k=>$range){$v=filter_var($in[$k]??null,FILTER_VALIDATE_INT);if($v===false||$v===null||$v<$range[0]||$v>$range[1])fail('Anonymous sharing limit is out of range: '.$k);$values[$k]=$v;}
            if($values['file_mb']>$values['page_mb']||$values['page_mb']>$values['total_mb'])fail('File limit must fit page limit, and page limit must fit total storage.');
            db()->beginTransaction();foreach($values as $k=>$v)putSetting('guest_'.$k,(string)$v);auditAdmin($uid,'guest_limits',null,$values);db()->commit();respond(['ok'=>true]);
        }
        if($action==='control_request'){
            $id=(int)($in['id']??0);$status=$in['status']??'';if(!in_array($status,['approved','rejected'],true))fail('Choose approve or reject.');
            db()->beginTransaction();$suffix=cfg('database')['driver']==='mysql'?' FOR UPDATE':'';$r=one('SELECT * FROM storage_requests WHERE id=?'.$suffix,[$id]);
            if(!$r||$r['status']!=='pending'){db()->rollBack();fail('Request already processed or unavailable.',409);}
            if($status==='approved'){controlRecord((int)$r['user_id']);query('UPDATE account_controls SET quota_mb=? WHERE user_id=?',[max((int)$r['requested_mb'],accountLimit((int)$r['user_id'],'quota_mb',500)),$r['user_id']]);}
            query('UPDATE storage_requests SET status=?,reviewed_by=?,reviewed_at=? WHERE id=?',[$status,$uid,now(),$id]);notify((int)$r['user_id'],'Storage request '.$status,$status==='approved'?'Your total storage quota is now '.accountLimit((int)$r['user_id'],'quota_mb',500).' MB.':'Your storage request was declined. Contact your administrator.');auditAdmin($uid,'storage_request_'.$status,(int)$r['user_id'],['request_id'=>$id]);db()->commit();respond(['ok'=>true]);
        }
    }
    if($action==='storage_request'){
        limit('storage-request-'.$uid,3,86400);$mb=nullableLimit($in['requested_mb']??null,1,100000);$reason=trim((string)($in['reason']??''));
        if(!$mb||$mb<=accountLimit($uid,'quota_mb',500)||!$reason||mb_strlen($reason)>2000)fail('Request a larger total quota and explain why.');
        if(one("SELECT id FROM storage_requests WHERE user_id=? AND status='pending'",[$uid]))fail('You already have a pending storage request.');
        query('INSERT INTO storage_requests(user_id,requested_mb,reason,created_at) VALUES(?,?,?,?)',[$uid,$mb,$reason,now()]);foreach(all("SELECT id FROM users WHERE role IN ('admin','super_admin') AND status='active'") as $a)notify((int)$a['id'],'Storage request',$u['name'].' requested '.$mb.' MB. Open Administration to review.');respond(['ok'=>true]);
    }
    if($action==='group_data'){
        $manage=$u['permissions']['groups'];$groups=isSuper($u)?all('SELECT * FROM member_groups ORDER BY name'):all('SELECT DISTINCT g.* FROM member_groups g LEFT JOIN group_members m ON m.group_id=g.id WHERE g.owner_id=? OR m.user_id=? ORDER BY g.name',[$uid,$uid]);
        foreach($groups as &$g){$g['can_manage']=$manage&&(isSuper($u)||(int)$g['owner_id']===$uid);$g['members']=all('SELECT u.id,u.name FROM group_members m JOIN users u ON u.id=m.user_id WHERE m.group_id=? ORDER BY u.name',[$g['id']]);}
        $members=($manage||$u['permissions']['publish'])?all("SELECT u.id,u.name,u.email FROM users u LEFT JOIN account_controls c ON c.user_id=u.id WHERE u.status='active' AND u.verified_at IS NOT NULL AND COALESCE(c.approval,'approved')='approved' ORDER BY u.name LIMIT 1000"):[];
        $items=$u['permissions']['publish']?all("SELECT id,title,kind FROM items WHERE user_id=? AND deleted_at IS NULL AND kind IN ('code','note','file') ORDER BY updated_at DESC LIMIT 1000",[$uid]):[];
        respond(['groups'=>$groups,'members'=>$members,'items'=>$items,'permissions'=>$u['permissions']]);
    }
    if(in_array($action,['group_save','group_delete'],true)){
        if(!$u['permissions']['groups'])fail('Group management permission required.',403);
        $id=(int)($in['id']??0);if($id)managedGroup($id,$u);
        if($action==='group_delete'){if(!$id)fail('Select a group.');query('DELETE FROM member_groups WHERE id=?',[$id]);respond(['ok'=>true]);}
        $name=trim((string)($in['name']??''));if(!$name||mb_strlen($name)>120)fail('Use a group name of up to 120 characters.');
        $ids=approvedMembers(is_array($in['members']??null)?$in['members']:[]);
        db()->beginTransaction();
        if(!$id){query('INSERT INTO member_groups(owner_id,name,created_at) VALUES(?,?,?)',[$uid,$name,now()]);$id=(int)db()->lastInsertId();}else query('UPDATE member_groups SET name=? WHERE id=?',[$name,$id]);
        query('DELETE FROM group_members WHERE group_id=?',[$id]);foreach($ids as $member)query('INSERT INTO group_members(group_id,user_id) VALUES(?,?)',[$id,$member]);db()->commit();respond(['id'=>$id]);
    }
    if($action==='broadcast_publish'){
        if(!$u['permissions']['publish'])fail('Teacher publishing permission required.',403);
        limit('broadcast-'.$uid,20,3600);$title=trim((string)($in['title']??''));$message=trim((string)($in['message']??''));$audience=$in['audience']??'';$item=(int)($in['item_id']??0);
        if(!$title||mb_strlen($title)>255||mb_strlen($message)>4000||(!$message&&!$item))fail('Add a title and message or resource.');
        if($item&&!in_array(owned($item,$uid)['kind'],['code','note','file'],true))fail('Share a code, note or file.');
        $ids=[];
        if($audience==='all')$ids=array_column(all("SELECT u.id FROM users u LEFT JOIN account_controls c ON c.user_id=u.id WHERE u.status='active' AND u.verified_at IS NOT NULL AND COALESCE(c.approval,'approved')='approved'"),'id');
        elseif($audience==='groups'){
            $selected=$in['groups']??[];if(!is_array($selected)||!$selected||count($selected)>100)fail('Select one or more managed groups.');
            foreach(array_unique(array_map('intval',$selected)) as $gid){managedGroup($gid,$u);foreach(all("SELECT u.id FROM group_members m JOIN users u ON u.id=m.user_id LEFT JOIN account_controls c ON c.user_id=u.id WHERE m.group_id=? AND u.status='active' AND u.verified_at IS NOT NULL AND COALESCE(c.approval,'approved')='approved'",[$gid]) as $member)$ids[]=(int)$member['id'];}
        }elseif($audience==='members')$ids=approvedMembers(is_array($in['members']??null)?$in['members']:[]);else fail('Choose all members, groups or selected members.');
        $ids=array_values(array_unique(array_map('intval',$ids)));if(!$ids)fail('The selected audience has no approved members.');
        db()->beginTransaction();query('INSERT INTO broadcasts(author_id,item_id,title,message,audience,created_at) VALUES(?,?,?,?,?,?)',[$uid,$item?:null,$title,$message,$audience,now()]);$id=(int)db()->lastInsertId();
        foreach($ids as $recipient){query('INSERT INTO broadcast_recipients(broadcast_id,user_id) VALUES(?,?)',[$id,$recipient]);if($recipient!==$uid)notify($recipient,$title,$u['name'].' shared an update. Open Shared Resources.');}
        db()->commit();respond(['id'=>$id,'recipients'=>count($ids),'url'=>cfg('app_url').'/#resources?post='.$id]);
    }
    if($action==='broadcast_list'){
        $page=max(1,min(100000,(int)($in['page']??1)));$offset=($page-1)*30;
        $where="b.revoked_at IS NULL AND u.status='active' AND COALESCE(c.approval,'approved')='approved' AND (b.author_id=? OR b.audience='all' OR EXISTS(SELECT 1 FROM broadcast_recipients r WHERE r.broadcast_id=b.id AND r.user_id=?)".(isSuper($u)?' OR 1=1':'').")";
        $rows=all("SELECT b.*,u.name AS author,i.title AS resource_title,i.kind AS resource_kind FROM broadcasts b JOIN users u ON u.id=b.author_id LEFT JOIN account_controls c ON c.user_id=u.id LEFT JOIN items i ON i.id=b.item_id WHERE $where ORDER BY b.id DESC LIMIT 30 OFFSET $offset",[$uid,$uid]);
        respond(['posts'=>$rows,'page'=>$page,'has_more'=>count($rows)===30]);
    }
    if($action==='broadcast_get'){
        $b=accessibleBroadcast((int)($in['id']??0),$u);$x=$b['item_id']?one('SELECT * FROM items WHERE id=? AND user_id=? AND deleted_at IS NULL',[$b['item_id'],$b['author_id']]):null;
        respond(['post'=>$b,'item'=>$x?itemData($x,true):null]);
    }
    if($action==='broadcast_revoke'){
        $b=accessibleBroadcast((int)($in['id']??0),$u);if(!isSuper($u)&&(int)$b['author_id']!==$uid)fail('Only the publisher may revoke this share.',403);
        query('UPDATE broadcasts SET revoked_at=? WHERE id=?',[now(),$b['id']]);respond(['ok'=>true]);
    }
    fail('Action not found.',404);
}
function nullableLimit(mixed $value,int $min,int $max): ?int {
    if($value===null||$value==='')return null;$v=filter_var($value,FILTER_VALIDATE_INT);
    if($v===false||$v<$min||$v>$max)fail('Limit must be between '.$min.' and '.$max.'.');return $v;
}
