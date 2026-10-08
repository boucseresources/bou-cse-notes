<?php
// Only the isolated test runner copies/runs this file; never run on production.
require __DIR__.'/app/bootstrap.php';
foreach([['admin','admin'],['student1','student'],['student2','student'],['outsider','student'],['teacher1','teacher'],['teacher2','teacher']] as [$name,$role]){
    query('INSERT INTO users(name,email,password_hash,verified_at,role,created_at,updated_at) VALUES(?,?,?,?,?,?,?)',[$name,$name.'@example.test',password_hash('testing-password-123',PASSWORD_DEFAULT),now(),$role,now(),now()]);
}
echo "Isolated test accounts seeded.\n";
