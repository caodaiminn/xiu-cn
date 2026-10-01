<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$path=ROOT.'/tests/artifacts/verification-delete-fixture.json';
if(($argv[1]??'')==='cleanup') {
    $fixture=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    foreach($fixture['items'] as $item) {
        $user=row('SELECT username FROM xiu_users WHERE id=?',[$item['user_id']]);
        if(!$user || !str_starts_with($user['username'],$fixture['token'])) throw new RuntimeException('Test account identity changed.');
        echo 'Cleanup database targets: xiu_listings user_id='.$item['user_id'].', xiu_user_verifications user_id='.$item['user_id'].', xiu_users id='.$item['user_id']."\n";
        query('DELETE FROM xiu_listings WHERE user_id=?',[$item['user_id']]);
        query('DELETE FROM xiu_user_verifications WHERE user_id=?',[$item['user_id']]);
        query('DELETE FROM xiu_users WHERE id=?',[$item['user_id']]);
        foreach($item['files'] as $file) {
            $filePath=ROOT.'/storage/verification/'.$file['filename'];
            if(is_file($filePath)) { echo 'Cleanup file: '.$filePath."\n"; unlink($filePath); }
        }
    }
    $session=ROOT.'/storage/sessions/sess_'.$fixture['session'];
    echo 'Cleanup files: '.$session."\n".$path."\n";
    if(is_file($session)) unlink($session); unlink($path);
    echo "Isolated test records and temporary session cleaned up. Recovery backups retained.\n"; exit;
}
if(is_file($path)) throw new RuntimeException('Previous fixture exists.');
$token='verdelete_'.bin2hex(random_bytes(5)); $items=[];
foreach(['pending','approved','rejected'] as $status) {
    query('INSERT INTO xiu_users (username,password_hash) VALUES (?,?)',[$token.'_'.$status,password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT)]);
    $user=(int)db()->lastInsertId(); $type=$status==='approved'?'company':'personal';
    query('INSERT INTO xiu_user_verifications (user_id,type,subject_name,status) VALUES (?,?,?,?)',[$user,$type,$token.'_'.$status,$status]); $id=(int)db()->lastInsertId();$files=[];
    foreach(verificationRoles($type) as $role=>$label) {
        $filename=bin2hex(random_bytes(20)).'.png';$image=imagecreatetruecolor(360,180);$white=imagecolorallocate($image,245,245,245);imagefill($image,0,0,$white);$ink=imagecolorallocate($image,75,75,75);imagestring($image,5,90,80,'TEST DOCUMENT',$ink);imagepng($image,ROOT.'/storage/verification/'.$filename);imagedestroy($image);
        query("INSERT INTO xiu_verification_files (verification_id,role,filename,mime,width,height) VALUES (?,?,?,'image/png',360,180)",[$id,$role,$filename]);$files[]=['id'=>(int)db()->lastInsertId(),'filename'=>$filename];
    }
    $items[$status]=['id'=>$id,'user_id'=>$user,'files'=>$files];
}
$category=(int)scalar("SELECT id FROM xiu_categories WHERE kind='service' AND enabled=1 AND parent_id IS NULL LIMIT 1");$city=(int)scalar('SELECT id FROM xiu_cities WHERE enabled=1 LIMIT 1');
query("INSERT INTO xiu_listings (user_id,category_id,city_id,type,title,address,contact,phone,body,status,is_demo) VALUES (?,?,?,'service',?,'QA address','QA contact','00000000000','This is isolated verification deletion test data.','published',1)",[$items['approved']['user_id'],$category,$city,$token.'_listing']);$listing=(int)db()->lastInsertId();ensureSeoSlug('xiu_listings',$listing);
$session='verdelete-qa-'.bin2hex(random_bytes(16));session_save_path(ROOT.'/storage/sessions');session_name('xiu_session');session_id($session);session_start();$_SESSION['admin_id']=(int)scalar('SELECT id FROM xiu_admins ORDER BY id LIMIT 1');session_write_close();
file_put_contents($path,json_encode(['token'=>$token,'items'=>$items,'session'=>$session,'listing_id'=>$listing,'listing_url'=>listingUrl(row('SELECT * FROM xiu_listings WHERE id=?',[$listing]))],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo "Isolated verification deletion fixtures ready.\n";
