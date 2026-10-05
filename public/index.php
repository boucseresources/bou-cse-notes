<?php
require __DIR__.'/paths.php';
header('Cache-Control: no-store');
function assetUrl(string $path): string {
    $stamp=is_file(__DIR__.'/'.$path)?filemtime(__DIR__.'/'.$path):0;
    return htmlspecialchars($path.'?v=1.5.0-'.$stamp,ENT_QUOTES,'UTF-8');
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="bou-build" content="1.5.0"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="Your personal BOU CSE workspace for code, notes, files and study."><title>BOU CSE Notes — Student Workspace</title><link rel="icon" href="<?= assetUrl('assets/favicon.svg') ?>" type="image/svg+xml"><link rel="stylesheet" href="<?= assetUrl('assets/app.css') ?>"><link rel="stylesheet" href="<?= assetUrl('assets/editor.css') ?>"><link rel="stylesheet" href="<?= assetUrl('assets/material-symbols.css') ?>"><link rel="stylesheet" href="<?= assetUrl('assets/reference.css') ?>"><link rel="stylesheet" href="<?= assetUrl('assets/reference-ui.css') ?>"><link rel="stylesheet" href="<?= assetUrl('assets/gallery.css') ?>"></head><body>
<div id="app" data-build="1.5.0" aria-live="polite"><div class="boot">Opening your workspace…</div></div><div id="toast" role="status"></div><dialog id="dialog"></dialog>
<script src="bootstrap.php"></script><script src="<?= assetUrl('assets/editor.js') ?>"></script><script type="module" src="<?= assetUrl('assets/app.js') ?>"></script></body></html>
