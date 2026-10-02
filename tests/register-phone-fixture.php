<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$path=ROOT.'/tests/artifacts/register-phone-fixture.json';
$fixture=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
if(!preg_match('/^phoneqa_[0-9]{13}$/',$fixture['token'])) throw new RuntimeException('Invalid fixture token.');
$names=[$fixture['token'],$fixture['token'].'_old',$fixture['token'].'_bad'];
$mode=$argv[1]??'';
if($mode==='setup') {
    if(scalar('SELECT id FROM xiu_users WHERE username IN (?,?,?)',$names)) throw new RuntimeException('Fixture account already exists.');
    query('INSERT INTO xiu_users (username,password_hash) VALUES (?,?)',[$names[1],password_hash($fixture['password'],PASSWORD_DEFAULT)]);
    echo json_encode(['legacy_id'=>(int)db()->lastInsertId()]).PHP_EOL;
} elseif($mode==='verify') {
    $user=row('SELECT id,phone FROM xiu_users WHERE username=?',[$names[0]]);
    if(!$user || $user['phone']!==$fixture['phone'] || scalar('SELECT id FROM xiu_users WHERE username=?',[$names[2]])) throw new RuntimeException('Registration phone persistence or validation failed.');
    query("INSERT INTO xiu_listings (user_id,category_id,city_id,type,title,address,contact,phone,body,status,is_demo) VALUES (?,2,1,'service','联系电话回填验收信息','示例地址','示例联系人',?,'用于核对编辑已有信息时联系电话保持原值。','pending',1)",[$user['id'],$fixture['edit_phone']]);
    echo json_encode(['registered_id'=>(int)$user['id'],'listing_id'=>(int)db()->lastInsertId(),'phone_saved'=>true]).PHP_EOL;
} elseif($mode==='cleanup') {
    foreach($names as $name) {
        $user=row('SELECT id FROM xiu_users WHERE username=?',[$name]); if(!$user) continue;
        $items=rows('SELECT id,title,is_demo,status FROM xiu_listings WHERE user_id=?',[$user['id']]);
        foreach($items as $item) {
            if(!$item['is_demo'] || $item['status']!=='pending' || $item['title']!=='联系电话回填验收信息') throw new RuntimeException('Unexpected fixture listing.');
            echo 'Cleanup database listing ID: '.$item['id'].PHP_EOL;
            query('DELETE FROM xiu_listings WHERE id=? AND user_id=?',[$item['id'],$user['id']]);
        }
        echo 'Cleanup database user ID: '.$user['id'].PHP_EOL;
        query('DELETE FROM xiu_users WHERE id=? AND username=?',[$user['id'],$name]);
    }
    foreach(array_unique($fixture['sessions']??[]) as $session) {
        if(!preg_match('/^[a-zA-Z0-9,-]{16,100}$/',$session)) throw new RuntimeException('Invalid fixture session.');
        $target=ROOT.'/storage/sessions/sess_'.$session;
        if(is_file($target)) { echo 'Cleanup file: '.$target.PHP_EOL; unlink($target); }
    }
    echo 'Cleanup file: '.$path.PHP_EOL; unlink($path);
} else throw new RuntimeException('Unknown fixture mode.');
