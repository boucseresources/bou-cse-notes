<?php
// Optional browser-test fixture. Never run against real student data.
if (PHP_SAPI!=='cli') exit;
require __DIR__.'/../app/bootstrap.php';require ROOT.'/app/items.php';
if (cfg('environment')!=='local') exit("Refusing to seed outside local development.\n");
$email='qa@example.test';$u=one('SELECT * FROM users WHERE email=?',[$email]);
if (!$u) {
 query("INSERT INTO users(name,email,password_hash,verified_at,role,semester,created_at,updated_at) VALUES(?,?,?,?,'admin',?,?,?)",['Rakib Hasan',$email,password_hash('qa-password-1234',PASSWORD_DEFAULT),now(),'Semester 5',now(),now()]);$uid=(int)db()->lastInsertId();
 $p=saveItem(['kind'=>'project','title'=>'Data Structures Lab','description'=>'Semester 5 lab programs'], $uid);
 saveItem(['kind'=>'code','title'=>'Binary Search Implementation','content'=>"int binarySearch(int a[], int n, int key) {\n    int low = 0, high = n - 1;\n    while (low <= high) {\n        int mid = low + (high - low) / 2;\n        if (a[mid] == key) return mid;\n        if (a[mid] < key) low = mid + 1;\n        else high = mid - 1;\n    }\n    return -1;\n}",'language'=>'C','project_id'=>$p['id'],'subject'=>'Data Structures','tags'=>['array','search','lab']],$uid);
 saveItem(['kind'=>'note','title'=>'DBMS — Normalization','content'=>"# Normalization\n\n- 1NF: atomic values\n- 2NF: no partial dependency\n- 3NF: no transitive dependency\n\nবাংলা নোট এখানে লেখা যায়।",'subject'=>'DBMS','pinned'=>true,'tags'=>['exam']],$uid);
 notify($uid,'Your test workspace is ready','These are development fixtures. Remove them before hosting.');
}
echo "Local browser-test account: qa@example.test / qa-password-1234\nOnly use it in an isolated test database.\n";
