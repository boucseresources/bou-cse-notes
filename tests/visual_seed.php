<?php
// Optional screenshot fixture, isolated local development only. Never run on production.
if (PHP_SAPI!=='cli') exit;
require __DIR__.'/../app/bootstrap.php';require ROOT.'/app/items.php';require ROOT.'/app/study.php';
if (cfg('environment')!=='local') exit("Local development only.\n");
$email='visual@example.test';$u=one('SELECT * FROM users WHERE email=?',[$email]);if($u) exit("Visual fixture already exists.\n");
query("INSERT INTO users(name,email,password_hash,verified_at,role,semester,created_at,updated_at) VALUES(?,?,?,?,'student',?,?,?)",['Rakib Hasan',$email,password_hash('visual-password-1234',PASSWORD_DEFAULT),now(),'Semester 5',now(),now()]);$uid=(int)db()->lastInsertId();
foreach ([['Binary Search','Data Structure Lab','C',['array','search','recursion']],['Student Management System','OOP Lab','Java',['swing','class','oop']],['JOIN Practice & Subqueries','DBMS Practice','SQL',['join','mysql','queries']],['8086 Addition & Overflow Check','Microprocessor Lab','8086 / Assembly',['8086','registers']]] as [$title,$subject,$language,$tags]) saveItem(['kind'=>'code','title'=>$title,'subject'=>$subject,'language'=>$language,'tags'=>$tags,'content'=>'// Your lab solution goes here.'],$uid);
foreach ([['Stack vs Queue','LIFO vs FIFO principle with dynamic array memory diagrams'],['Normalization Rules (1NF to 3NF)','Functional dependency and candidate key cheat sheet'],['8086 Register Summary','AX, BX, CX, DX and pointer offset registers'],['OOP Constructor Notes','Default, parameterized, and copy constructors in C++']] as $i=>[$title,$description]) saveItem(['kind'=>'note','title'=>$title,'description'=>$description,'content'=>'# '.$title,'pinned'=>$i===0],$uid);
studySave(['kind'=>'course','title'=>'Basic Web & Java Development','subject'=>'Object Oriented Programming','code'=>'CSE-214','total'=>28,'done'=>12,'hours'=>4],$uid);
studySave(['kind'=>'course','title'=>'Advance UI/UX & Algorithm Design','subject'=>'Data Structures & Lab','code'=>'CSE-212','total'=>40,'done'=>18,'hours'=>2],$uid);
for ($i=1;$i<=6;$i++) studySave(['kind'=>'goal','title'=>'Complete Lab '.$i,'done'=>false],$uid);
$stored=bin2hex(random_bytes(24)).'.png';$source=ROOT.'/public/assets/reference-logo.png';copy($source,cfg('storage_path').'/uploads/'.$stored);
query("INSERT INTO items(user_id,kind,title,original_name,stored_name,mime,size_bytes,created_at,updated_at) VALUES(?,'file',?,?,?,'image/png',?,?,?)",[$uid,'BOU-CSE-logo.png','BOU-CSE-logo.png',$stored,filesize($source),now(),now()]);
echo "Isolated local fixture ready: visual@example.test / visual-password-1234\n";
