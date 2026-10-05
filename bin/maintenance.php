<?php
if (PHP_SAPI!=='cli') exit('CLI only');
require __DIR__.'/../app/bootstrap.php';require ROOT.'/app/items.php';
$cutoff=gmdate('Y-m-d H:i:s',time()-(int)setting('trash_days',30)*86400);
$rows=all('SELECT * FROM items WHERE deleted_at IS NOT NULL AND deleted_at<? LIMIT 1000',[$cutoff]);
foreach ($rows as $r) deleteForever($r);
query('DELETE FROM tokens WHERE expires_at<?',[now()]);query('DELETE FROM rate_limits WHERE started_at<?',[time()-86400]);query('DELETE FROM activity WHERE created_at<?',[gmdate('Y-m-d H:i:s',time()-180*86400)]);
echo count($rows)." trashed items purged.\n";
