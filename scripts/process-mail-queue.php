<?php
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$lock=fopen(mailDirectory().'/worker.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) exit;
try {
    do {
        $results=processMailQueue(20);
        echo json_encode($results,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
        $active=array_filter($results,fn($result)=>$result['status']!=='paused');
    } while(count($results)===20 && $active);
}
finally { flock($lock,LOCK_UN); fclose($lock); }
