<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$path=ROOT.'/tests/artifacts/admin-php-entry-fixture.json';
if(($argv[1]??'')==='cleanup') {
    $fixture=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    $targets=rows('SELECT id,label FROM xiu_navigation WHERE label=?',[$fixture['label']]);
    echo 'Cleanup targets: '.json_encode($targets,JSON_UNESCAPED_UNICODE)."\n";
    foreach($targets as $target) query('DELETE FROM xiu_navigation WHERE id=?',[$target['id']]);
    $user=row('SELECT username FROM xiu_users WHERE id=?',[$fixture['user']]);
    if(!$user || $user['username']!==$fixture['label']) throw new RuntimeException('Fixture identity mismatch.');
    echo 'Cleanup database targets: xiu_images id='.$fixture['image'].', xiu_user_verifications user_id='.$fixture['user'].', xiu_users id='.$fixture['user']."\n";
    query('DELETE FROM xiu_images WHERE id=?',[$fixture['image']]);
    query('DELETE FROM xiu_user_verifications WHERE user_id=?',[$fixture['user']]); query('DELETE FROM xiu_users WHERE id=?',[$fixture['user']]);
    foreach(['files','verification'] as $directory) { $file=ROOT.'/storage/'.$directory.'/'.$fixture['filename']; echo 'Cleanup file: '.$file."\n"; if(is_file($file)) unlink($file); }
    foreach(array_unique([$fixture['session'],...($fixture['extraSessions']??[])]) as $id) {
        if(!preg_match('/^[a-zA-Z0-9,-]{16,100}$/',$id)) throw new RuntimeException('Invalid fixture session.');
        $session=ROOT.'/storage/sessions/sess_'.$id;
        echo $session."\n";
        if(is_file($session)) unlink($session);
    }
    echo $path."\n";
    unlink($path);
    if(rows('SELECT * FROM xiu_navigation ORDER BY id')!==$fixture['original']) throw new RuntimeException('Original navigation changed.');
    echo "Temporary fixture cleaned; original navigation verified.\n"; exit;
}
if(is_file($path)) throw new RuntimeException('Previous fixture still exists.');
$session='admin-entry-qa-'.bin2hex(random_bytes(16));
$label='phpentryqa_'.bin2hex(random_bytes(5)); $filename=bin2hex(random_bytes(20)).'.png';
$image=imagecreatetruecolor(32,32); imagepng($image,ROOT.'/storage/verification/'.$filename); imagedestroy($image); copy(ROOT.'/storage/verification/'.$filename,ROOT.'/storage/files/'.$filename);
query('INSERT INTO xiu_users (username,password_hash) VALUES (?,?)',[$label,password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT)]); $user=(int)db()->lastInsertId();
query("INSERT INTO xiu_user_verifications (user_id,type,subject_name,status) VALUES (?,'personal',?,'pending')",[$user,$label]); $verification=(int)db()->lastInsertId();
query("INSERT INTO xiu_verification_files (verification_id,role,filename,mime,width,height) VALUES (?,'id_front',?,'image/png',32,32)",[$verification,$filename]); $document=(int)db()->lastInsertId();
query("INSERT INTO xiu_images (filename,mime,width,height) VALUES (?,'image/png',32,32)",[$filename]); $media=(int)db()->lastInsertId();
session_save_path(ROOT.'/storage/sessions'); session_name('xiu_session'); session_id($session); session_start();
$_SESSION['admin_id']=(int)scalar('SELECT id FROM xiu_admins ORDER BY id LIMIT 1'); session_write_close();
file_put_contents($path,json_encode(['label'=>$label,'user'=>$user,'document'=>$document,'image'=>$media,'filename'=>$filename,'original'=>rows('SELECT * FROM xiu_navigation ORDER BY id'),'session'=>$session],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
echo "Temporary admin session created.\n";
