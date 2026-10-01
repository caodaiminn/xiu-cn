<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$path=ROOT.'/tests/artifacts/navigation-fixture.json';
if(($argv[1]??'')==='cleanup') {
    $fixture=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    $targets=rows('SELECT id,label FROM xiu_navigation WHERE id IN (?,?) OR label=?',[$fixture['ids'][0],$fixture['ids'][1],$fixture['newLabel']]);
    $session=ROOT.'/storage/sessions/sess_'.$fixture['session'];
    echo 'Cleanup targets: '.json_encode($targets,JSON_UNESCAPED_UNICODE)."\n".$session."\n".$path."\n";
    foreach($targets as $item) {
        if(!str_starts_with($item['label'],$fixture['token'])) throw new RuntimeException('Fixture identity mismatch.');
        query('DELETE FROM xiu_navigation WHERE id=?',[$item['id']]);
    }
    if(is_file($session)) unlink($session); unlink($path);
    $current=rows('SELECT * FROM xiu_navigation ORDER BY id');
    if($current!==$fixture['original']) throw new RuntimeException('Original navigation changed.');
    echo "Temporary navigation and session removed; original navigation verified.\n"; exit;
}
if(is_file($path)) throw new RuntimeException('Previous fixture still exists.');
$token='navqa_'.bin2hex(random_bytes(5)); $original=rows('SELECT * FROM xiu_navigation ORDER BY id'); $ids=[];
foreach([60,60] as $index=>$sort) {
    query("INSERT INTO xiu_navigation (label,url,sort_order) VALUES (?,?,?)",[$token.'_'.$index,'/wang-zhan-di-tu/?navqa='.$token,$sort]); $ids[]=(int)db()->lastInsertId();
}
$session='nav-qa-'.bin2hex(random_bytes(16));
session_save_path(ROOT.'/storage/sessions');session_name('xiu_session');session_id($session);session_start();
$_SESSION['admin_id']=(int)scalar('SELECT id FROM xiu_admins ORDER BY id LIMIT 1');session_write_close();
file_put_contents($path,json_encode(['token'=>$token,'original'=>$original,'ids'=>$ids,'newLabel'=>$token.'_new','session'=>$session],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo "Isolated navigation fixture created.\n";
