<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$fixture=json_decode(file_get_contents(ROOT.'/tests/artifacts/verification-delete-fixture.json'),true,512,JSON_THROW_ON_ERROR);$checks=[];
$check=function(bool $ok,string $name) use(&$checks):void { if(!$ok) throw new RuntimeException($name);$checks[]=$name; };
foreach($fixture['items'] as $status=>$item) {
    $check(!row('SELECT id FROM xiu_user_verifications WHERE id=?',[$item['id']]),$status.' verification deleted');
    $check(!scalar('SELECT COUNT(*) FROM xiu_verification_files WHERE verification_id=?',[$item['id']]),$status.' file metadata cascades');
    $check((bool)row('SELECT id FROM xiu_users WHERE id=?',[$item['user_id']]),$status.' account remains');
    $paths=glob(ROOT.'/backup/'.date('Y-m-d').'-verification-delete/'.$item['id'].'-*/manifest.json');
    $check(count($paths)===1,$status.' recovery manifest exists');
    $manifest=json_decode(file_get_contents($paths[0]),true,512,JSON_THROW_ON_ERROR);
    $check((int)$manifest['verification']['id']===$item['id'] && count($manifest['files'])===count($item['files']),$status.' record and file metadata backed up');
    foreach($item['files'] as $file) {
        $check(!is_file(ROOT.'/storage/verification/'.$file['filename']),$status.' material no longer stored at original path');
        $check(is_file(dirname($paths[0]).'/files/'.$file['filename']),$status.' material preserved in recovery backup');
    }
}
$check((bool)row('SELECT id FROM xiu_listings WHERE id=? AND user_id=?',[$fixture['listing_id'],$fixture['items']['approved']['user_id']]),'published listing remains');
echo json_encode(['passed'=>count($checks),'checks'=>$checks],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),"\n";
