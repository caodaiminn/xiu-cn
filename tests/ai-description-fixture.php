<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$path=ROOT.'/tests/artifacts/ai-description-fixture.json';
if(($argv[1]??'')==='cleanup') {
    $fixture=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    $user=row('SELECT username FROM xiu_users WHERE id=?',[$fixture['user']]);
    if(!$user || $user['username']!==$fixture['token']) throw new RuntimeException('Fixture identity mismatch.');
    echo 'Cleanup database target: xiu_users id='.$fixture['user']."\n";
    query('DELETE FROM xiu_users WHERE id=?',[$fixture['user']]);
    foreach(['settings.json','secret.key','settings.lock'] as $name) {
        $target=ROOT.'/storage/ai/'.$name; $original=$fixture['original'][$name]??null;
        if($original!==null) { echo 'Restore file: '.$target."\n"; copy($original,$target); }
        elseif(is_file($target)) { echo 'Cleanup file: '.$target."\n"; unlink($target); }
    }
    $usage=ROOT.'/storage/ai/usage-'.$fixture['user'].'.json'; if(is_file($usage)) { echo 'Cleanup file: '.$usage."\n"; unlink($usage); }
    foreach(array_unique([$fixture['admin_session'],$fixture['user_session'],...($fixture['extraSessions']??[])]) as $session) {
        if(!preg_match('/^[a-zA-Z0-9,-]{16,100}$/',$session)) throw new RuntimeException('Invalid fixture session.');
        $target=ROOT.'/storage/sessions/sess_'.$session; echo 'Cleanup file: '.$target."\n"; if(is_file($target)) unlink($target);
    }
    echo 'Cleanup file: '.$path."\n"; unlink($path); echo "AI configuration restored; temporary user and sessions removed.\n"; exit;
}
if(is_file($path)) throw new RuntimeException('Previous fixture still exists.');
$token='aiqa_'.bin2hex(random_bytes(6)); $original=[];
$backup=ROOT.'/backup/'.date('Y-m-d').'-zhipu-ai-description/test-config-original'; if(!is_dir($backup)) mkdir($backup,0700,true);
foreach(['settings.json','secret.key','settings.lock'] as $name) { $source=ROOT.'/storage/ai/'.$name; $original[$name]=null; if(is_file($source)) { $target=$backup.'/'.$name; if(!copy($source,$target)) throw new RuntimeException('Config backup failed.'); $original[$name]=$target; } }
query('INSERT INTO xiu_users (username,password_hash) VALUES (?,?)',[$token,password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT)]); $user=(int)db()->lastInsertId();
$adminSession='ai-admin-qa-'.bin2hex(random_bytes(16)); $userSession='ai-user-qa-'.bin2hex(random_bytes(16));
session_save_path(ROOT.'/storage/sessions');session_name('xiu_session');
session_id($adminSession);session_start();$_SESSION['admin_id']=(int)scalar('SELECT id FROM xiu_admins ORDER BY id LIMIT 1');session_write_close();
session_id($userSession);session_start();$_SESSION['user_id']=$user;session_write_close();
$category=(int)scalar("SELECT c.id FROM xiu_categories c WHERE c.kind='service' AND c.enabled=1 AND c.parent_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM xiu_categories k WHERE k.parent_id=c.id) ORDER BY c.id LIMIT 1");
file_put_contents($path,json_encode(['token'=>$token,'user'=>$user,'category'=>$category,'admin_session'=>$adminSession,'user_session'=>$userSession,'original'=>$original],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo "AI description isolated fixture ready.\n";
