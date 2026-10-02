<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$path=ROOT.'/tests/artifacts/member-phone-fixture.json';
$fixture=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
if(!preg_match('/^mobileqa_[0-9]{13}$/',$fixture['token'])) throw new RuntimeException('Invalid fixture token.');
function memberPhoneUserHash(): string { return hash('sha256',json_encode(rows('SELECT * FROM xiu_users ORDER BY id'),JSON_THROW_ON_ERROR)); }
$mode=$argv[1]??'';
if($mode==='setup') {
    $fixture['baseline']=memberPhoneUserHash(); $fixture['old_username']=$fixture['token'].'_old';
    if(scalar('SELECT id FROM xiu_users WHERE username=?',[$fixture['old_username']])) throw new RuntimeException('Fixture already exists.');
    $numbers=[];
    while(count($numbers)<3) {
        $phone='138'.sprintf('%08d',random_int(0,99999999));
        if(!in_array($phone,$numbers,true) && !scalar('SELECT id FROM xiu_users WHERE username=? OR phone=?',[$phone,$phone])) $numbers[]=$phone;
    }
    [$fixture['occupied'],$fixture['first'],$fixture['second']]=$numbers;
    db()->beginTransaction();
    try {
        query('INSERT INTO xiu_users (username,password_hash) VALUES (?,?)',[$fixture['old_username'],password_hash($fixture['password'],PASSWORD_DEFAULT)]); $fixture['owner_id']=(int)db()->lastInsertId();
        query('INSERT INTO xiu_users (username,password_hash,phone) VALUES (?,?,?)',[$fixture['occupied'],password_hash($fixture['password'],PASSWORD_DEFAULT),$fixture['occupied']]); $fixture['other_id']=(int)db()->lastInsertId();
        query("INSERT INTO xiu_listings (user_id,category_id,city_id,type,title,address,contact,phone,body,status,is_demo) VALUES (?,2,1,'service',?,'示例地址','示例联系人','0735-0000000','用于验证手机号码修改与已有发布信息的归属和联系方式。','pending',1)",[$fixture['owner_id'],$fixture['token'].'手机管理验收']); $fixture['listing_id']=(int)db()->lastInsertId();
        if(file_put_contents($path,json_encode($fixture,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Fixture journal failed.');
        db()->commit();
    } catch(Throwable $error) { if(db()->inTransaction()) db()->rollBack(); throw $error; }
    echo json_encode($fixture).PHP_EOL; exit;
}
if(str_starts_with($mode,'verify-')) {
    $expected=['verify-original'=>[$fixture['old_username'],''],'verify-first'=>[$fixture['first'],$fixture['first']],'verify-second'=>[$fixture['second'],$fixture['second']]][$mode]??null;
    if(!$expected) throw new RuntimeException('Unknown verification mode.');
    $owner=row('SELECT id,username,phone,password_hash FROM xiu_users WHERE id=?',[$fixture['owner_id']]);
    $other=row('SELECT username,phone FROM xiu_users WHERE id=?',[$fixture['other_id']]);
    $listing=row('SELECT user_id,phone FROM xiu_listings WHERE id=?',[$fixture['listing_id']]);
    if(!$owner || [$owner['username'],$owner['phone']]!==$expected || !password_verify($fixture['password'],$owner['password_hash']) || !$other || [$other['username'],$other['phone']]!==[$fixture['occupied'],$fixture['occupied']] || !$listing || (int)$listing['user_id']!==$fixture['owner_id'] || $listing['phone']!=='0735-0000000') throw new RuntimeException('Phone, password, other account or listing changed unexpectedly.');
    echo json_encode(['verified'=>true,'owner_id'=>$fixture['owner_id'],'mode'=>$mode]).PHP_EOL; exit;
}
if($mode!=='cleanup') throw new RuntimeException('Unknown fixture mode.');
$owner=row('SELECT id,username,password_hash FROM xiu_users WHERE id=?',[$fixture['owner_id']]);
$other=row('SELECT id,username,password_hash FROM xiu_users WHERE id=?',[$fixture['other_id']]);
if(!$owner || !$other || !in_array($owner['username'],[$fixture['old_username'],$fixture['first'],$fixture['second']],true) || $other['username']!==$fixture['occupied'] || !password_verify($fixture['password'],$owner['password_hash']) || !password_verify($fixture['password'],$other['password_hash'])) throw new RuntimeException('Fixture account ownership mismatch.');
$listing=row('SELECT user_id,title,is_demo FROM xiu_listings WHERE id=?',[$fixture['listing_id']]);
if(!$listing || (int)$listing['user_id']!==$fixture['owner_id'] || !$listing['is_demo'] || $listing['title']!==$fixture['token'].'手机管理验收') throw new RuntimeException('Fixture listing ownership mismatch.');
echo 'Cleanup database listing ID: '.$fixture['listing_id'].PHP_EOL;
query('DELETE FROM xiu_listings WHERE id=? AND user_id=?',[$fixture['listing_id'],$fixture['owner_id']]);
foreach([$owner,$other] as $account) { echo 'Cleanup database user ID: '.$account['id'].PHP_EOL; query('DELETE FROM xiu_users WHERE id=? AND username=?',[$account['id'],$account['username']]); }
if(memberPhoneUserHash()!==$fixture['baseline']) throw new RuntimeException('Original account data changed.');
foreach(array_unique($fixture['sessions']??[]) as $session) {
    if(!preg_match('/^[a-zA-Z0-9,-]{16,100}$/',$session)) throw new RuntimeException('Invalid fixture session.');
    $target=ROOT.'/storage/sessions/sess_'.$session; if(is_file($target)) { echo 'Cleanup file: '.$target.PHP_EOL; unlink($target); }
}
echo 'Cleanup file: '.$path.PHP_EOL; unlink($path);
echo "Original accounts verified unchanged.\n";
