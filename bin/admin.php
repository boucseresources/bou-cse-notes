<?php
if (PHP_SAPI!=='cli') exit;
require __DIR__.'/../app/bootstrap.php';
$email=$argv[1]??'';
if (!filter_var($email,FILTER_VALIDATE_EMAIL)) exit("Usage: php bin/admin.php admin@example.com\n");
echo "Admin name: ";$name=trim(fgets(STDIN));echo "Password (visible in terminal; at least 12 characters): ";$password=trim(fgets(STDIN));
if (mb_strlen($password)<12 || !$name) exit("Name and 12-character password required.\n");
if (one('SELECT id FROM users WHERE email=?',[$email])) exit("That email exists. Use the existing admin or another email.\n");
query("INSERT INTO users(name,email,password_hash,verified_at,role,created_at,updated_at) VALUES(?,?,?,?,'admin',?,?)",[$name,strtolower($email),password_hash($password,PASSWORD_DEFAULT),now(),now(),now()]);echo "Admin created.\n";
