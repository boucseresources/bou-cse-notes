<?php
require __DIR__.'/paths.php';
header('Cache-Control: no-store');
session_write_close();
function assetUrl(string $path): string {
    $stamp=is_file(__DIR__.'/'.$path)?filemtime(__DIR__.'/'.$path):0;
    return htmlspecialchars($path.'?v=1.5.0-'.$stamp,ENT_QUOTES,'UTF-8');
}
function scriptEntry(): string {
    $manifestFile=__DIR__.'/assets/dist/manifest.json';
    if(is_file($manifestFile)){
        $m=json_decode(file_get_contents($manifestFile),true);$valid=is_array($m)&&!empty($m['sources']);
        foreach(($m['sources']??[]) as $path=>$sha){if(!is_file(__DIR__.'/../'.$path)||hash_file('sha256',__DIR__.'/../'.$path)!==$sha){$valid=false;break;}}
        if($valid&&preg_match('~^assets/dist/app-[A-Z0-9]+\.js$~',$m['entry']??'')&&is_file(__DIR__.'/'.$m['entry']))return htmlspecialchars($m['entry'],ENT_QUOTES,'UTF-8');
    }
    return assetUrl('assets/app.js');
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="bou-build" content="1.5.0"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="Your personal BOU CSE workspace for code, notes, files and study."><title>BOU CSE Notes — Student Workspace</title><link rel="icon" href="<?= assetUrl('assets/favicon.svg') ?>" type="image/svg+xml"><link rel="stylesheet" href="<?= assetUrl('assets/app.css') ?>"><link rel="stylesheet" href="<?= assetUrl('assets/material-symbols.css') ?>"><link rel="stylesheet" href="<?= assetUrl('assets/reference.css') ?>"><link rel="stylesheet" href="<?= assetUrl('assets/reference-ui.css') ?>"><link rel="stylesheet" href="<?= assetUrl('assets/gallery.css') ?>"><link rel="stylesheet" href="<?= assetUrl('assets/mobile-profile.css') ?>"></head><body>
<div id="app" data-build="1.5.0" data-csrf="<?= htmlspecialchars($_SESSION['csrf'],ENT_QUOTES,'UTF-8') ?>" data-base="<?= htmlspecialchars(cfg('app_url'),ENT_QUOTES,'UTF-8') ?>" data-local="<?= cfg('environment')==='local'?'1':'0' ?>" data-editor-js="<?= assetUrl('assets/editor.js') ?>" data-editor-css="<?= assetUrl('assets/editor.css') ?>" aria-live="polite"><div class="boot">Opening your workspace…</div></div><div id="toast" role="status"></div><dialog id="dialog"></dialog>
<script type="module" src="<?= scriptEntry() ?>"></script></body></html>

