<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$path=ROOT.'/tests/artifacts/house-repair-category-fixture.json';
$mode=$argv[1]??'verify';
if($mode==='cleanup') {
    $fixture=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    foreach([$fixture['user_session'],$fixture['admin_session']] as $session) {
        if(!preg_match('/^house-category-(user|admin)-[a-f0-9]{32}$/',$session)) throw new RuntimeException('Invalid category fixture session.');
        $target=ROOT.'/storage/sessions/sess_'.$session;
        if(is_file($target)) { echo 'Cleanup file: '.$target.PHP_EOL; unlink($target); }
    }
    echo 'Cleanup file: '.$path.PHP_EOL; unlink($path); exit;
}
$report=json_decode(file_get_contents(ROOT.'/tests/artifacts/house-repair-categories.json'),true,512,JSON_THROW_ON_ERROR);
$before=json_decode(file_get_contents($report['backup'].'/categories-before.json'),true,512,JSON_THROW_ON_ERROR);
$all=array_column(rows('SELECT * FROM xiu_categories ORDER BY id'),null,'id');
foreach($before as $category) if(($all[$category['id']]??null)!==$category) throw new RuntimeException('An original category changed.');
$tree=array_column(categories('service',false),null,'id');
foreach($report['items'] as $item) {
    $category=$tree[$item['id']]??null;
    if(!$category || (int)$category['parent_id']!==$report['parent_id'] || $category['depth']!==2 || !$category['active'] || !$category['show_in_filter']) throw new RuntimeException('Category missing, hidden or under another parent.');
}
if(count($all)!==count($before)+21 || count($report['items'])!==23) throw new RuntimeException('Category coverage mismatch.');
$verification=['passed'=>true,'covered'=>23,'created'=>21,'original_categories_unchanged'=>count($before),'total'=>count($all)];
if($mode==='verify') { echo json_encode($verification,JSON_UNESCAPED_UNICODE).PHP_EOL; exit; }
if($mode!=='setup' || is_file($path)) throw new RuntimeException('Unexpected mode or prior fixture exists.');
$user=(int)scalar("SELECT id FROM xiu_users WHERE username='demo' AND enabled=1");
$admin=(int)scalar('SELECT id FROM xiu_admins ORDER BY id LIMIT 1');
if(!$user || !$admin) throw new RuntimeException('Local QA accounts missing.');
$fixture=['user_session'=>'house-category-user-'.bin2hex(random_bytes(16)),'admin_session'=>'house-category-admin-'.bin2hex(random_bytes(16))];
session_save_path(ROOT.'/storage/sessions');session_name('xiu_session');
session_id($fixture['user_session']);session_start();$_SESSION['user_id']=$user;session_write_close();
session_id($fixture['admin_session']);session_start();$_SESSION['admin_id']=$admin;session_write_close();
if(file_put_contents($path,json_encode($fixture,JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Fixture file write failed.');
echo json_encode($verification,JSON_UNESCAPED_UNICODE).PHP_EOL;
