<?php
function mediaTypes(): array {return ['jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'png'=>['image/png'],'webp'=>['image/webp'],'gif'=>['image/gif'],'mp4'=>['video/mp4'],'mov'=>['video/quicktime','video/mp4'],'m4a'=>['audio/mp4','audio/x-m4a'],'aac'=>['audio/aac','audio/x-hx-aac-adts'],'webm'=>['video/webm'],'pdf'=>['application/pdf'],'txt'=>['text/plain'],'md'=>['text/plain'],'csv'=>['text/plain','text/csv','application/csv'],'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip'],'odt'=>['application/vnd.oasis.opendocument.text','application/zip'],'mp3'=>['audio/mpeg'],'wav'=>['audio/wav','audio/x-wav'],'ogg'=>['audio/ogg','application/ogg']];}
function streamMedia(string $path,string $mime,string $name,bool $inline=false): never {
    if(!is_file($path)) fail('File unavailable.',404);
    $size=filesize($path);$start=0;$end=$size-1;
    $safe=$inline&&(str_starts_with($mime,'image/')||str_starts_with($mime,'video/')||str_starts_with($mime,'audio/')||$mime==='application/pdf'||$mime==='application/ogg');
    header('Content-Type: '.($safe?$mime:'application/octet-stream'));
    header("Content-Disposition: ".($safe?'inline':'attachment')."; filename=\"download\"; filename*=UTF-8''".rawurlencode($name));
    header('Accept-Ranges: bytes');
    if(isset($_SERVER['HTTP_RANGE'])) {
        if(!preg_match('/^bytes=(\d*)-(\d*)$/',$_SERVER['HTTP_RANGE'],$m)||($m[1]===''&&$m[2]==='')) {header('Content-Range: bytes */'.$size);http_response_code(416);exit;}
        if($m[1]===''){$start=max(0,$size-(int)$m[2]);}else{$start=(int)$m[1];if($m[2]!=='')$end=min($end,(int)$m[2]);}
        if($start>$end||$start>=$size){header('Content-Range: bytes */'.$size);http_response_code(416);exit;}
        http_response_code(206);header("Content-Range: bytes $start-$end/$size");
    }
    header('Content-Length: '.($end-$start+1));session_write_close();if($_SERVER['REQUEST_METHOD']==='HEAD')exit;
    $f=fopen($path,'rb');fseek($f,$start);$left=$end-$start+1;while($left>0&&!feof($f)){ $chunk=fread($f,min(65536,$left));echo $chunk;$left-=strlen($chunk);}fclose($f);exit;
}

