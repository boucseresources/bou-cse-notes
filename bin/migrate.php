<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
$lockPath=cfg('storage_path').'/migration.lock';
if(!is_dir(cfg('storage_path')))mkdir(cfg('storage_path'),0700,true);
$lock=fopen($lockPath,'c');
if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Cannot lock migrations.');
$driver=cfg('database')['driver'];$db=db();
if($driver==='mysql'){
    $key='bou-migrate-'.substr(hash('sha256',cfg('database')['name']),0,32);
    if((int)$db->query("SELECT GET_LOCK(".$db->quote($key).",30)")->fetchColumn()!==1)throw new RuntimeException('Migration database lock unavailable.');
}
try{
    $db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(190) PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at VARCHAR(30) NOT NULL)');
    $files=glob(ROOT.'/app/migrations/*.php');sort($files,SORT_STRING);
    foreach($files as $file){
        $version=basename($file);$checksum=hash_file('sha256',$file);
        $existing=one('SELECT checksum FROM schema_migrations WHERE version=?',[$version]);
        if($existing){if(!hash_equals($existing['checksum'],$checksum))throw new RuntimeException('An applied migration was edited: '.$version);continue;}
        // Migrations must be additive/restart-safe: MySQL DDL may commit implicitly.
        $migration=require $file;$migration($db,$driver);
        query('INSERT INTO schema_migrations(version,checksum,applied_at) VALUES(?,?,?)',[$version,$checksum,now()]);
        echo 'Applied '.$version."\n";
    }
    echo "Database migrations up to date.\n";
}finally{
    if($driver==='mysql')$db->query("SELECT RELEASE_LOCK(".$db->quote($key).")");
    flock($lock,LOCK_UN);fclose($lock);
}
