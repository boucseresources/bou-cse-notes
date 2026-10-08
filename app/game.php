<?php
// Durable server-owned XP ledger. No client may choose an XP amount.
function gameInit(): void {
 // Tables are installed by the versioned performance migration before this code is deployed.
}
function gameAward(int $uid,string $key,string $category,int $points,string $stamp=''): void {
 gameInit();$stamp=$stamp?:now();$day=substr($stamp,0,10);$caps=['code'=>100,'note'=>50,'share'=>25,'goal'=>60,'course'=>100,'activity'=>10,'challenge'=>20];
 $insert=cfg('database')['driver']==='mysql'?'INSERT IGNORE':'INSERT OR IGNORE';
 // A dedicated per-user row serializes concurrent awards (and persists participation separately).
 query($insert.' INTO game_profiles(user_id,campus,participating) VALUES(?,\'\',0)',[$uid]);
 if(cfg('database')['driver']==='mysql')one('SELECT user_id FROM game_profiles WHERE user_id=? FOR UPDATE',[$uid]);
 if(one('SELECT award_key FROM game_awards WHERE user_id=? AND award_key=?',[$uid,$key]))return;
 $earned=(int)one('SELECT COALESCE(SUM(points),0) AS n FROM game_awards WHERE user_id=? AND category=? AND SUBSTR(created_at,1,10)=?',[$uid,$category,$day])['n'];$points=min($points,max(0,($caps[$category]??0)-$earned));
 // Record capped events too; repeat saves cannot move them into a different day.
 query($insert.' INTO game_awards(user_id,award_key,category,points,created_at) VALUES(?,?,?,?,?)',[$uid,$key,$category,$points,$stamp]);
}
function gameSync(int $uid): void {
 gameInit();$state=hash('sha256',json_encode([one('SELECT COUNT(*) AS n,COALESCE(SUM(revision),0) AS rev,COALESCE(MAX(id),0) AS last_id FROM items WHERE user_id=? AND deleted_at IS NULL',[$uid]),one('SELECT COALESCE(MAX(id),0) AS id FROM activity WHERE user_id=?',[$uid]),one('SELECT COUNT(*) AS n,COALESCE(MAX(id),0) AS id FROM shares WHERE user_id=?',[$uid])]));if((one('SELECT fingerprint FROM game_state WHERE user_id=?',[$uid])['fingerprint']??'')===$state)return;
 db()->beginTransaction();try{
 $insert=cfg('database')['driver']==='mysql'?'INSERT IGNORE':'INSERT OR IGNORE';query($insert." INTO game_profiles(user_id,campus,participating) VALUES(?,'',0)",[$uid]);if(cfg('database')['driver']==='mysql')one('SELECT user_id FROM game_profiles WHERE user_id=? FOR UPDATE',[$uid]);
 $events=all("SELECT SUBSTR(created_at,1,10) AS day,MIN(created_at) AS stamp FROM activity WHERE user_id=? AND (action LIKE 'save_%' OR action='upload' OR action='coding_30s') GROUP BY SUBSTR(created_at,1,10) ORDER BY day",[$uid]);foreach($events as $e)gameAward($uid,'day:'.$e['day'],'activity',10,$e['stamp']);
 $items=all('SELECT * FROM items WHERE user_id=? AND deleted_at IS NULL ORDER BY created_at,id',[$uid]);foreach($items as $x){$kind=$x['kind'];$content=trim($x['content']??'');$stamp=$x['created_at'];
 if(in_array($kind,['code','note'],true)&&strlen($content)>=($kind==='code'?20:40)){$hash='hash:'.$kind.':'.hash('sha256',preg_replace('/\s+/u',' ',$content));$seen=one('SELECT award_key FROM game_awards WHERE user_id=? AND award_key=?',[$uid,$hash]);gameAward($uid,$kind.':'.$x['id'],$kind,$seen?0:($kind==='code'?20:10),$stamp);gameAward($uid,$hash,$kind,0,$stamp);}
 if(in_array($kind,['goal','course'],true)){$p=json_decode($content,true)?:[];$done=$kind==='goal'?!empty($p['done']):isset($p['total'])&&$p['total']>0&&($p['done']??0)>=$p['total'];if($done)gameAward($uid,$kind.':'.$x['id'],$kind,$kind==='goal'?20:50,$x['updated_at']);}}
 $shares=all('SELECT item_id,MIN(created_at) AS stamp FROM shares WHERE user_id=? GROUP BY item_id ORDER BY stamp',[$uid]);foreach($shares as $x)gameAward($uid,'share:'.$x['item_id'],'share',5,$x['stamp']);
 $days=all("SELECT SUBSTR(created_at,1,10) AS day FROM game_awards WHERE user_id=? AND points>0 AND category IN ('code','goal') GROUP BY SUBSTR(created_at,1,10) HAVING COUNT(DISTINCT category)=2",[$uid]);foreach($days as $e)gameAward($uid,'challenge:'.$e['day'],'challenge',20,$e['day'].' 00:00:00');
 query('DELETE FROM game_state WHERE user_id=?',[$uid]);query('INSERT INTO game_state(user_id,fingerprint) VALUES(?,?)',[$uid,$state]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function gameTier(int $xp): array {foreach([[4000,'Grandmaster'],[2900,'Diamond Scholar'],[2500,'Gold Scholar'],[2000,'Silver Scholar'],[1000,'Bronze Scholar'],[0,'Rising Scholar']] as [$at,$name])if($xp>=$at)return ['name'=>$name,'floor'=>$at,'next'=>match($at){0=>1000,1000=>2000,2000=>2500,2500=>2900,2900=>4000,default=>null}];return [];}
function gameStats(int $uid): array {
 $awards=all('SELECT category,points,created_at FROM game_awards WHERE user_id=? AND points>0',[$uid]);$xp=0;$categories=[];$days=[];$challenge=[];foreach($awards as $a){$xp+=$a['points'];$categories[$a['category']]=($categories[$a['category']]??0)+$a['points'];if($a['category']==='activity')$days[substr($a['created_at'],0,10)]=true;if(substr($a['created_at'],0,10)===gmdate('Y-m-d'))$challenge[$a['category']]=true;}
 $streak=0;$cursor=gmdate('Y-m-d');if(!isset($days[$cursor]))$cursor=gmdate('Y-m-d',strtotime($cursor.' -1 day'));while(isset($days[$cursor])){$streak++;$cursor=gmdate('Y-m-d',strtotime($cursor.' -1 day'));}
 $max=0;$run=0;$last='';ksort($days);foreach(array_keys($days) as $d){$run=$last&&strtotime($d)-strtotime($last)===86400?$run+1:1;$max=max($max,$run);$last=$d;}
 $counts=one("SELECT SUM(CASE WHEN category='code' AND points>0 THEN 1 ELSE 0 END) AS codes,SUM(CASE WHEN category='note' AND points>0 THEN 1 ELSE 0 END) AS notes,SUM(CASE WHEN category='share' AND points>0 THEN 1 ELSE 0 END) AS shares,SUM(CASE WHEN category='goal' AND points>0 THEN 1 ELSE 0 END) AS goals,SUM(CASE WHEN category='course' AND points>0 THEN 1 ELSE 0 END) AS courses FROM game_awards WHERE user_id=?",[$uid]);
 $badges=[];foreach([['First Code','code',20],['Note Keeper','note',100],['Helping Hand','share',25],['Lab Builder','goal',100],['Course Finisher','course',50]] as [$name,$cat,$target])$badges[]=['name'=>$name,'earned'=>($categories[$cat]??0)>=$target,'progress'=>min($target,$categories[$cat]??0),'target'=>$target];$badges[]=['name'=>'Week of Focus','earned'=>$max>=7,'progress'=>min(7,$max),'target'=>7];
 return ['xp'=>$xp,'level'=>1+intdiv($xp,250),'level_progress'=>$xp%250,'tier'=>gameTier($xp),'streak'=>$streak,'record'=>$max,'sources'=>$categories,'counts'=>$counts,'badges'=>$badges,'challenge'=>['code'=>isset($challenge['code']),'goal'=>isset($challenge['goal']),'earned'=>isset($challenge['challenge'])]];
}
function gameLeaderboard(array $in,int $uid): array {
 gameInit();$users=all("SELECT u.id,u.name,u.avatar,u.semester,p.campus,p.participating FROM users u LEFT JOIN game_profiles p ON p.user_id=u.id WHERE u.status='active' AND u.verified_at IS NOT NULL ORDER BY u.id");
 // Sync retained items and historical activity once; award keys prevent repeated XP.
 foreach($users as $u)gameSync((int)$u['id']);
 $period=$in['period']??'week';$start=match($period){'day'=>gmdate('Y-m-d').' 00:00:00','week'=>gmdate('Y-m-d',strtotime('monday this week')).' 00:00:00','month'=>gmdate('Y-m-01').' 00:00:00','cohort','all'=>'0000-00-00 00:00:00',default=>null};if($start===null)fail('Invalid period.');
 $category=$in['category']??'all';if(!in_array($category,['all','code','note','share','study'],true))fail('Invalid category.');$rows=[];$self=null;
 foreach($users as $u){$id=(int)$u['id'];$profile=one('SELECT campus,participating FROM game_profiles WHERE user_id=?',[$id]);$u['campus']=$profile['campus']??'';$u['participating']=(bool)($profile['participating']??false);$stats=gameStats($id);$score=0;$awards=all('SELECT category,points FROM game_awards WHERE user_id=? AND created_at>=?',[$id,$start]);foreach($awards as $a)if($category==='all'||$category===$a['category']||$category==='study'&&in_array($a['category'],['goal','course','activity','challenge'],true))$score+=(int)$a['points'];$row=[...$u,'score'=>$score,'stats'=>$stats,'cheers'=>(int)one('SELECT COUNT(*) AS n FROM game_cheers WHERE receiver_id=?',[$id])['n'],'cheered'=>(bool)one('SELECT giver_id FROM game_cheers WHERE giver_id=? AND receiver_id=?',[$uid,$id])];if($id===$uid)$self=$row;if($u['participating'])$rows[]=$row;}
 if($period==='cohort')$rows=array_values(array_filter($rows,fn($r)=>$r['semester']===($self['semester']??'')));
 usort($rows,fn($a,$b)=>$b['score']<=>$a['score']?:$a['id']<=>$b['id']);$rank=0;$previous=null;foreach($rows as $i=>&$r){if($previous!==$r['score'])$rank=$i+1;$r['rank']=$rank;$previous=$r['score'];if($r['id']===$uid)$self=$r;}unset($r);
 $campuses=[];foreach($rows as $r)if($r['campus'])$campuses[$r['campus']]=($campuses[$r['campus']]??0)+$r['score'];arsort($campuses);
 return ['rows'=>$rows,'self'=>$self,'period'=>$period,'category'=>$category,'campuses'=>$campuses,'updated_at'=>now(),'day'=>gmdate('Y-m-d')];
}

