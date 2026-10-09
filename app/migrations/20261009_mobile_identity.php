<?php
return static function(PDO $db,string $driver): void {
    foreach(['username'=>'VARCHAR(24) NULL','profile_photo'=>'VARCHAR(80) NULL'] as $column=>$type){
        if($driver==='sqlite'){$columns=array_column($db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC),'name');$exists=in_array($column,$columns,true);}
        else{$q=$db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$q->execute(['users',$column]);$exists=(bool)$q->fetchColumn();}
        if(!$exists)$db->exec("ALTER TABLE users ADD COLUMN $column $type");
    }
    foreach($db->query('SELECT id,email FROM users WHERE username IS NULL ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $u){$name=availableUsername($u['email'],(int)$u['id']);$q=$db->prepare('UPDATE users SET username=? WHERE id=?');$q->execute([$name,$u['id']]);}
    if($driver==='sqlite')$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS users_username_unique ON users(username)');
    else{$q=$db->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');$q->execute(['users','users_username_unique']);if(!(int)$q->fetchColumn())$db->exec('CREATE UNIQUE INDEX users_username_unique ON users(username)');}
};
