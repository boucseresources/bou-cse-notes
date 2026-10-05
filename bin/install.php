<?php
if (PHP_SAPI !== 'cli') exit('CLI only.');
require __DIR__.'/../app/bootstrap.php';
$driver=cfg('database')['driver'];
$sql=file_get_contents(ROOT.'/database/schema.mysql.sql');
if ($driver==='sqlite') {
    $sql=str_replace('INSERT IGNORE','INSERT OR IGNORE',$sql);
    $sql=preg_replace('/BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY/','INTEGER PRIMARY KEY AUTOINCREMENT',$sql);
    $sql=str_replace(['BIGINT UNSIGNED','BIGINT','TINYINT','MEDIUMTEXT','DATETIME'],['INTEGER','INTEGER','INTEGER','TEXT','TEXT'],$sql);
    $sql=preg_replace('/,\s*INDEX \w+ \([^)]*\)/','',$sql);
    $sql=preg_replace('/\) ENGINE=InnoDB[^;]*;/',');',$sql);
}
db()->exec($sql);
foreach (['quota_mb'=>'500','max_upload_mb'=>'10','trash_days'=>'30','support_email'=>cfg('support_email')] as $k=>$v) if (!one('SELECT * FROM settings WHERE `key`=?',[$k])) putSetting($k,$v);
foreach (['uploads','mail'] as $d) if (!is_dir(cfg('storage_path').'/'.$d)) mkdir(cfg('storage_path').'/'.$d,0700,true);
echo "Database ready. Run php bin/admin.php admin@example.com to create an administrator.\n";
