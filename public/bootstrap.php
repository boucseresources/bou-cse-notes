<?php
require __DIR__.'/paths.php';
header('Content-Type: application/javascript');header('Cache-Control: no-store');
echo 'window.BOU = '.json_encode(['csrf'=>$_SESSION['csrf'],'base'=>cfg('app_url'),'local'=>cfg('environment')==='local'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';';
