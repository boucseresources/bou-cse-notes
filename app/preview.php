<?php
function documentXml(string $path,string $entry): string {
    try {$zip=new PharData($path);if(!isset($zip[$entry])||!$zip[$entry]->isFile()||$zip[$entry]->isLink()||$zip[$entry]->getSize()>2097152)throw new RuntimeException('Document content unavailable or too large to preview.');return $zip[$entry]->getContent();}
    catch(Throwable $e){throw new InvalidArgumentException('This document cannot be previewed. Download it to open in your document editor.');}
}
function filePreviewText(string $path,string $name): array {
    $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));$truncated=false;
    if(in_array($ext,['docx','odt'],true)){
        $xml=documentXml($path,$ext==='docx'?'word/document.xml':'content.xml');
        $xml=preg_replace('/<[^>]*(?:tab|line-break)\b[^>]*\/>/',' ',$xml);
        $xml=preg_replace('/<\/(?:w:p|text:p|text:h|table:table-row)>/',"\n",$xml);
        $text=html_entity_decode(strip_tags($xml),ENT_QUOTES|ENT_XML1,'UTF-8');
    }else{$text=file_get_contents($path,false,null,0,65537);$truncated=strlen($text)>65536;$text=substr($text,0,65536);}
    if(!mb_check_encoding($text,'UTF-8'))$text=mb_convert_encoding($text,'UTF-8','Windows-1252');
    return ['text'=>mb_substr(trim($text),0,60000),'truncated'=>$truncated||mb_strlen($text)>60000,'format'=>$ext];
}
function validOfficeDocument(string $path,string $ext): bool {
    $temporary=sys_get_temp_dir().'/bou-document-'.bin2hex(random_bytes(16)).'.zip';
    try{if(!copy($path,$temporary))return false;chmod($temporary,0600);$xml=documentXml($temporary,$ext==='docx'?'word/document.xml':'content.xml');return str_contains($xml,$ext==='docx'?'wordprocessingml':'office:document-content');}
    catch(Throwable $e){return false;}finally{if(is_file($temporary))unlink($temporary);}
}
