<?php
// Isolated local integration checks; creates and removes its own users.
require __DIR__.'/../app/bootstrap.php';require ROOT.'/app/items.php';require ROOT.'/app/study.php';require ROOT.'/app/game.php';
if(cfg('environment')!=='local')exit('Local tests only.');gameInit();$ids=[];
function checkGame(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
try{
 foreach(['A','B'] as $n){query("INSERT INTO users(name,email,password_hash,verified_at,role,semester,created_at,updated_at) VALUES(?,?,?,?,'student',?,?,?)",['Game Test '.$n,'game-'.bin2hex(random_bytes(5)).'@example.test',password_hash('game-test-password-1234',PASSWORD_DEFAULT),now(),'Semester 5',now(),now()]);$ids[]=(int)db()->lastInsertId();}
 [$uid,$peer]=$ids;$x=saveItem(['kind'=>'code','title'=>'Original program','content'=>'int main() { return 123456789; }'], $uid);gameSync($uid);$first=gameStats($uid);checkGame($first['xp']===30,'Code + active day XP');gameSync($uid);checkGame(gameStats($uid)['xp']===30,'Repeated read must not earn XP');
 saveItem(['kind'=>'code','title'=>'Copy','content'=>'int main() { return 123456789; }'],$uid);gameSync($uid);checkGame(gameStats($uid)['xp']===30,'Duplicate content must not earn XP');
 saveItem([...$x,'content'=>'int main() { return 987654321; }'],$uid);gameSync($uid);checkGame(gameStats($uid)['xp']===30,'Editing same item must not earn XP');
 $g=studySave(['kind'=>'goal','title'=>'Finish lab','done'=>true],$uid);gameSync($uid);checkGame(gameStats($uid)['xp']===70,'Goal + daily sprint bonus');studySave(['kind'=>'goal','id'=>$g['id'],'title'=>'Finish lab','done'=>false],$uid);gameSync($uid);studySave(['kind'=>'goal','id'=>$g['id'],'title'=>'Finish lab','done'=>true],$uid);gameSync($uid);checkGame(gameStats($uid)['xp']===70,'Completion toggles must not earn repeat XP');
 for($i=0;$i<12;$i++)saveItem(['kind'=>'code','title'=>'Practice '.$i,'content'=>'int exercise_'.$i.'() { return '.$i.'; }'],$uid);gameSync($uid);checkGame(gameStats($uid)['sources']['code']===100,'Daily code cap');
 $d=gameLeaderboard(['period'=>'all'],$uid);checkGame(!in_array($uid,array_column($d['rows'],'id'),true),'Participation is private by default');query('UPDATE game_profiles SET participating=1,campus=? WHERE user_id=?',['Dhaka',$uid]);$d=gameLeaderboard(['period'=>'all','category'=>'code'],$uid);$self=$d['self'];checkGame($self['score']===100&&isset($self['rank']),'Category score/rank');
 query('INSERT INTO game_cheers(giver_id,receiver_id,created_at) VALUES(?,?,?)',[$peer,$uid,now()]);checkGame(gameLeaderboard(['period'=>'day'],$uid)['self']['cheers']===1,'Persistent cheer count');
 checkGame(gameTier(4000)['name']==='Grandmaster'&&gameTier(2900)['name']==='Diamond Scholar','Tier thresholds');
 echo "PASS: durable XP, original-content deduplication, editing/toggle protection, daily cap, bonus sprint, opt-in ranks, category score, cheers, tiers.\n";
}finally{foreach($ids as $id){query('DELETE FROM game_cheers WHERE giver_id=? OR receiver_id=?',[$id,$id]);query('DELETE FROM game_awards WHERE user_id=?',[$id]);query('DELETE FROM game_profiles WHERE user_id=?',[$id]);query('DELETE FROM game_state WHERE user_id=?',[$id]);query('DELETE FROM users WHERE id=?',[$id]);}}
