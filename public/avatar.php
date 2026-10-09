<?php
require __DIR__.'/paths.php';
header('Cache-Control: private, no-store');
try {
    $viewer=user(true,false);$id=(int)($_GET['id']??$viewer['id']);
    if($id!==(int)$viewer['id']&&(!$viewer['verified_at']||$viewer['approval']!=='approved'))fail('Photo unavailable.',404);
    $owner=one('SELECT * FROM users WHERE id=?',[$id]);
    if(!$owner||$owner['status']!=='active'||!$owner['profile_photo']||($id!==(int)$viewer['id']&&(!$owner['verified_at']||accountAccess($owner)['approval']!=='approved')))fail('Photo unavailable.',404);
    $path=cfg('storage_path').'/avatars/'.basename($owner['profile_photo']);if(!is_file($path))fail('Photo unavailable.',404);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))fail('Photo unavailable.',404);
    session_write_close();header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));if($_SERVER['REQUEST_METHOD']!=='HEAD')readfile($path);
} catch(Throwable $e){error_log($e->getMessage());fail('Photo unavailable.',500);}
