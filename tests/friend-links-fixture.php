<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$path=ROOT.'/tests/artifacts/friend-links-fixture.json';$mode=$argv[1]??'';
if($mode==='setup') {
    if(is_file($path)) throw new RuntimeException('Prior friend link fixture requires cleanup.');
    $fixture=['token'=>'flqa'.bin2hex(random_bytes(5)),'session'=>'friendlinks-qa-'.bin2hex(random_bytes(16)),'before'=>rows('SELECT * FROM xiu_friend_links ORDER BY id')];
    $admin=(int)scalar('SELECT id FROM xiu_admins ORDER BY id LIMIT 1');if(!$admin) throw new RuntimeException('Admin missing.');
    if(file_put_contents($path,json_encode($fixture,JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Fixture journal failed.');
    session_save_path(ROOT.'/storage/sessions');session_name('xiu_session');session_id($fixture['session']);session_start();$_SESSION['admin_id']=$admin;session_write_close();exit;
}
$fixture=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
if(!preg_match('/^flqa[a-f0-9]{10}$/',$fixture['token'])||!preg_match('/^friendlinks-qa-[a-f0-9]{32}$/',$fixture['session'])) throw new RuntimeException('Invalid fixture marker.');
if($mode==='records') { echo json_encode(rows('SELECT * FROM xiu_friend_links WHERE label LIKE ? ORDER BY sort_order,id',[$fixture['token'].'%']),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit; }
if($mode!=='cleanup') throw new RuntimeException('Unknown fixture mode.');
$ids=array_column(rows('SELECT id FROM xiu_friend_links WHERE label LIKE ?',[$fixture['token'].'%']),'id');
if($ids) { echo 'Cleanup friend link IDs: '.implode(',',$ids).PHP_EOL;query('DELETE FROM xiu_friend_links WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).') AND label LIKE ?',[...$ids,$fixture['token'].'%']); }
$current=array_column(rows('SELECT * FROM xiu_friend_links ORDER BY id'),null,'id');
foreach($fixture['before'] as $record) if(($current[$record['id']]??null)!==$record) throw new RuntimeException('An original friend link changed.');
$session=ROOT.'/storage/sessions/sess_'.$fixture['session'];if(is_file($session)){echo 'Cleanup file: '.$session.PHP_EOL;unlink($session);}
echo 'Cleanup file: '.$path.PHP_EOL;unlink($path);
echo 'Original friend links verified unchanged.'.PHP_EOL;
