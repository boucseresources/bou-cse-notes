<?php
require __DIR__.'/paths.php';
header('Cache-Control: public, max-age=86400');
$media=one('SELECT stored_name,mime,size_bytes FROM cms_media WHERE id=?',[(int)($_GET['id']??0)]);
if(!$media||!in_array($media['mime'],['image/png','image/jpeg','image/webp'],true)||basename($media['stored_name'])!==$media['stored_name']){header('Cache-Control: no-store');fail('Image unavailable.',404);}
$path=cfg('storage_path').'/cms/'.$media['stored_name'];if(!is_file($path)){header('Cache-Control: no-store');fail('Image unavailable.',404);}session_write_close();header('Content-Type: '.$media['mime']);header('Content-Length: '.filesize($path));if(($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD')readfile($path);
