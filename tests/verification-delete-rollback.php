<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$path=ROOT.'/tests/artifacts/verification-delete-rollback-fixture.json';
$mode=$argv[1]??'';
if($mode==='setup') {
    if(is_file($path)) throw new RuntimeException('Previous rollback fixture exists.');
    $username='verrollback_'.bin2hex(random_bytes(5));
    query('INSERT INTO xiu_users (username,password_hash) VALUES (?,?)',[$username,password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT)]);$user=(int)db()->lastInsertId();
    query("INSERT INTO xiu_user_verifications (user_id,type,subject_name) VALUES (?,'personal',?)",[$user,$username]);$id=(int)db()->lastInsertId();$files=[];
    foreach(['id_front','id_back'] as $role) {
        $name=bin2hex(random_bytes(20)).'.png';$image=imagecreatetruecolor(2,2);imagepng($image,ROOT.'/storage/verification/'.$name);imagedestroy($image);
        query("INSERT INTO xiu_verification_files (verification_id,role,filename,mime,width,height) VALUES (?,?,?,'image/png',2,2)",[$id,$role,$name]);$files[]=ROOT.'/storage/verification/'.$name;
    }
    file_put_contents($path,json_encode(['id'=>$id,'user_id'=>$user,'username'=>$username,'files'=>$files],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo "Rollback fixture created.\n";exit;
}
$fixture=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
if($mode==='attempt') {
    $_SESSION['admin_id']=(int)scalar('SELECT id FROM xiu_admins ORDER BY id LIMIT 1');
    try { deleteVerification($fixture['id'],1,'pending'); throw new RuntimeException('Deletion unexpectedly succeeded with a locked material.'); }
    catch(DomainException $error) { if(!str_contains($error->getMessage(),'证件材料备份失败')) throw $error; echo "Locked material aborts deletion.\n"; }
    exit;
}
if($mode==='assert-cleanup') {
    if(!row('SELECT id FROM xiu_user_verifications WHERE id=?',[$fixture['id']]) || (int)scalar('SELECT COUNT(*) FROM xiu_verification_files WHERE verification_id=?',[$fixture['id']])!==2) throw new RuntimeException('Records not preserved after rollback.');
    foreach($fixture['files'] as $file) if(!is_file($file)) throw new RuntimeException('Material was not restored after rollback.');
    echo "Rollback verified: original record, both file rows and both material files retained.\n";
    $user=row('SELECT username FROM xiu_users WHERE id=?',[$fixture['user_id']]);
    if(!$user || $user['username']!==$fixture['username']) throw new RuntimeException('Fixture identity mismatch.');
    echo 'Cleanup targets: xiu_user_verifications id='.$fixture['id'].', xiu_users id='.$fixture['user_id']."\n".implode("\n",$fixture['files'])."\n".$path."\n";
    query('DELETE FROM xiu_user_verifications WHERE id=?',[$fixture['id']]);query('DELETE FROM xiu_users WHERE id=?',[$fixture['user_id']]);
    foreach($fixture['files'] as $file) unlink($file);unlink($path);
}
