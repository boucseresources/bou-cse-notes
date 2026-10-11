<?php
return static function(PDO $db,string $driver): void {
    $text=$driver==='mysql'?'MEDIUMTEXT':'TEXT';
    $id=$driver==='mysql'?'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $engine=$driver==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $db->exec("CREATE TABLE IF NOT EXISTS cms_state (id INT PRIMARY KEY,data $text NOT NULL,revision INT NOT NULL DEFAULT 1,updated_at VARCHAR(30) NOT NULL)$engine");
    $db->exec("CREATE TABLE IF NOT EXISTS cms_pages (id $id,slug VARCHAR(100) NOT NULL UNIQUE,title VARCHAR(160) NOT NULL,body $text NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'draft',audience VARCHAR(20) NOT NULL DEFAULT 'public',revision INT NOT NULL DEFAULT 1,created_by BIGINT NOT NULL,updated_by BIGINT NOT NULL,created_at VARCHAR(30) NOT NULL,updated_at VARCHAR(30) NOT NULL)$engine");
    $db->exec("CREATE TABLE IF NOT EXISTS cms_media (id $id,stored_name VARCHAR(80) NOT NULL,mime VARCHAR(80) NOT NULL,original_name VARCHAR(160) NOT NULL,size_bytes BIGINT NOT NULL,created_by BIGINT NOT NULL,created_at VARCHAR(30) NOT NULL)$engine");
    $insert=$driver==='mysql'?'INSERT IGNORE':'INSERT OR IGNORE';
    $q=$db->prepare("$insert INTO cms_state(id,data,revision,updated_at) VALUES(1,?,1,?)");$q->execute([json_encode(cmsDefaults()),now()]);
};
