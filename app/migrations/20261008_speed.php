<?php
return static function(PDO $db,string $driver): void {
    // Preserve the existing game tables and ledger. Runtime page reads no longer run DDL.
    $tables=[
        "CREATE TABLE IF NOT EXISTS game_awards (user_id INTEGER NOT NULL, award_key VARCHAR(100) NOT NULL, category VARCHAR(20) NOT NULL, points INTEGER NOT NULL, created_at VARCHAR(19) NOT NULL, PRIMARY KEY(user_id,award_key))",
        "CREATE TABLE IF NOT EXISTS game_profiles (user_id INTEGER PRIMARY KEY, campus VARCHAR(100) NOT NULL DEFAULT '', participating INTEGER NOT NULL DEFAULT 0)",
        "CREATE TABLE IF NOT EXISTS game_state (user_id INTEGER PRIMARY KEY, fingerprint VARCHAR(64) NOT NULL)",
        "CREATE TABLE IF NOT EXISTS game_cheers (giver_id INTEGER NOT NULL, receiver_id INTEGER NOT NULL, created_at VARCHAR(19) NOT NULL, PRIMARY KEY(giver_id,receiver_id))",
    ];
    foreach($tables as $sql)$db->exec($sql);
    $indexes=[
        ['items','speed_items_recent','user_id,deleted_at,updated_at,id'],
        ['items','speed_items_kind_recent','user_id,kind,deleted_at,updated_at,id'],
        ['activity','speed_activity_day','user_id,created_at'],
        ['notifications','speed_notifications_recent','user_id,id'],
        ['game_awards','speed_awards_period','user_id,created_at'],
        ['game_cheers','speed_cheers_receiver','receiver_id'],
        ['broadcast_recipients','speed_broadcast_inbox','user_id,broadcast_id'],
    ];
    foreach($indexes as [$table,$name,$columns]){
        if($driver==='sqlite')$db->exec("CREATE INDEX IF NOT EXISTS $name ON $table($columns)");
        else {
            $q=$db->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');$q->execute([$table,$name]);
            if(!(int)$q->fetchColumn())$db->exec("CREATE INDEX $name ON $table($columns)");
        }
    }
};
