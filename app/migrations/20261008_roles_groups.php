<?php
return static function (PDO $db, string $driver): void {
    $id=$driver==='sqlite'?'INTEGER PRIMARY KEY AUTOINCREMENT':'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
    $big=$driver==='sqlite'?'INTEGER':'BIGINT UNSIGNED';
    $engine=$driver==='sqlite'?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $sql=[
        "CREATE TABLE IF NOT EXISTS account_controls (user_id $big PRIMARY KEY, approval VARCHAR(20) NOT NULL DEFAULT 'approved', quota_mb INT NULL, max_upload_mb INT NULL, allow_publish INT NULL, allow_groups INT NULL, note TEXT NULL, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE)$engine",
        "CREATE TABLE IF NOT EXISTS member_groups (id $id, owner_id $big NOT NULL, name VARCHAR(120) NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY(owner_id) REFERENCES users(id) ON DELETE CASCADE)$engine",
        "CREATE TABLE IF NOT EXISTS group_members (group_id $big NOT NULL, user_id $big NOT NULL, PRIMARY KEY(group_id,user_id), FOREIGN KEY(group_id) REFERENCES member_groups(id) ON DELETE CASCADE, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE)$engine",
        "CREATE TABLE IF NOT EXISTS broadcasts (id $id, author_id $big NOT NULL, item_id $big NULL, title VARCHAR(255) NOT NULL, message TEXT NOT NULL, audience VARCHAR(20) NOT NULL, revoked_at DATETIME NULL, created_at DATETIME NOT NULL, FOREIGN KEY(author_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY(item_id) REFERENCES items(id) ON DELETE SET NULL)$engine",
        "CREATE TABLE IF NOT EXISTS broadcast_recipients (broadcast_id $big NOT NULL, user_id $big NOT NULL, PRIMARY KEY(broadcast_id,user_id), FOREIGN KEY(broadcast_id) REFERENCES broadcasts(id) ON DELETE CASCADE, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE)$engine",
        "CREATE TABLE IF NOT EXISTS storage_requests (id $id, user_id $big NOT NULL, requested_mb INT NOT NULL, reason TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', reviewed_by $big NULL, created_at DATETIME NOT NULL, reviewed_at DATETIME NULL, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL)$engine",
        "CREATE TABLE IF NOT EXISTS admin_audit (id $id, actor_id $big NOT NULL, action VARCHAR(80) NOT NULL, target_id $big NULL, detail TEXT NULL, created_at DATETIME NOT NULL, FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE CASCADE)$engine",
    ];
    foreach($sql as $statement)$db->exec($statement);
    // Preserve old account access, and retain all existing administrator privileges.
    $db->exec("UPDATE users SET role='super_admin' WHERE role='admin'");
};
