<?php
require __DIR__.'/../app/bootstrap.php';require ROOT.'/app/temp.php';$lock=tempLock();echo 'Removed '.tempCleanup()." expired temporary pages.\n";
