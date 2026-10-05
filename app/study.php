<?php
// Personal study tracking reuses owned workspace items; no database reset or new tables.
function studyData(array $x): array {
    $data=json_decode($x['content']??'{}',true);if (!is_array($data)) $data=[];
    return [...itemData($x), 'progress'=>$data];
}
function studySave(array $in,int $uid): array {
    $kind=$in['kind']??'course';if (!in_array($kind,['course','goal'],true)) fail('Invalid study type.');
    if (!empty($in['id']) && owned((int)$in['id'],$uid)['kind']!==$kind) fail('Invalid study item.');
    if ($kind==='course') {
        $total=filter_var($in['total']??1,FILTER_VALIDATE_INT);$done=filter_var($in['done']??0,FILTER_VALIDATE_INT);
        if ($total===false || $total<1 || $total>10000 || $done===false || $done<0 || $done>$total) fail('Use valid module counts (completed cannot exceed total).');
        $hours=filter_var($in['hours']??0,FILTER_VALIDATE_FLOAT);if ($hours===false || $hours<0 || $hours>10000) fail('Enter valid remaining hours.');
        $url=trim($in['url']??'');if ($url && (!filter_var($url,FILTER_VALIDATE_URL)||!in_array(strtolower(parse_url($url,PHP_URL_SCHEME)??''),['https','http'],true))) fail('Course link must use HTTP or HTTPS.');
        if (strlen($url)>2000) fail('Course link is too long.');
        $code=trim($in['code']??'');if (mb_strlen($code)>30) fail('Course code is too long.');
        $progress=['total'=>$total,'done'=>$done,'hours'=>$hours,'url'=>$url,'code'=>$code];
    } else {
        $due=$in['due']??'';
        if ($due && (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$due) || !checkdate((int)substr($due,5,2),(int)substr($due,8,2),(int)substr($due,0,4)))) fail('Enter a valid target date.');
        $progress=['done'=>!empty($in['done']),'due'=>$due];
    }
    unset($in['attachments']);
    $x=saveItem([...$in,'kind'=>$kind,'content'=>json_encode($progress)],$uid);
    return studyData(owned((int)$x['id'],$uid));
}
function studySummary(int $uid,array $u): array {
    $courses=array_map('studyData',all("SELECT * FROM items WHERE user_id=? AND kind='course' AND deleted_at IS NULL ORDER BY updated_at DESC,id DESC",[$uid]));
    $goals=array_map('studyData',all("SELECT * FROM items WHERE user_id=? AND kind='goal' AND deleted_at IS NULL ORDER BY updated_at DESC,id DESC",[$uid]));
    $prefs=json_decode($u['preferences']??'{}',true)?:[];$tz=new DateTimeZone($prefs['timezone']??'Asia/Dhaka');
    $day=(new DateTimeImmutable('now',$tz))->format('Y-m-d');$days=[];$coding=[];
    $events=all('SELECT action,created_at FROM activity WHERE user_id=? AND created_at>=? ORDER BY id DESC LIMIT 20000',[$uid,gmdate('Y-m-d H:i:s',time()-90*86400)]);
    foreach ($events as $event) {
        $key=(new DateTimeImmutable($event['created_at'],new DateTimeZone('UTC')))->setTimezone($tz)->format('Y-m-d');
        if ($event['action']==='coding_30s') $coding[$key]=($coding[$key]??0)+30;
        if (str_starts_with($event['action'],'save_')||$event['action']==='upload'||$event['action']==='coding_30s') $days[$key]=true;
    }
    $streak=0;$cursor=new DateTimeImmutable($day,$tz);if (!isset($days[$day])) $cursor=$cursor->modify('-1 day');
    while (isset($days[$cursor->format('Y-m-d')])) {$streak++;$cursor=$cursor->modify('-1 day');}
    $completed=count(array_filter($courses,fn($x)=>($x['progress']['done']??0)>=($x['progress']['total']??1)));
    $doneGoals=count(array_filter($goals,fn($x)=>!empty($x['progress']['done'])));
    $points=count($days)*10+$completed*50+$doneGoals*20;if(function_exists('gameSync')){gameSync($uid);$game=gameStats($uid);$points=$game['xp'];}
    return ['courses'=>$courses,'goals'=>$goals,'days'=>array_keys($days),'coding'=>$coding,'today'=>$day,'streak'=>$streak,'points'=>$points,'tier'=>$points>=1000?3:($points>=250?2:1),'level'=>$game['level']??1,'completed'=>$completed,'in_progress'=>count($courses)-$completed,'open_goals'=>count($goals)-$doneGoals];
}
