<?php
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$path=ROOT.'/tests/artifacts/fixtures.json'; if(!is_file($path)) exit("No QA fixtures.\n");
$fixtures=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR); $token=$fixtures['token']??'';
if(!preg_match('/^xiuqa_[0-9]+$/',$token)) exit("Unexpected fixture token; nothing removed.\n");
db()->beginTransaction(); $images=[]; $documents=[];
foreach($fixtures['listings'] as $id) {
    $listing=row('SELECT l.id,u.username FROM xiu_listings l JOIN xiu_users u ON u.id=l.user_id WHERE l.id=?',[(int)$id]);
    if($listing && in_array($listing['username'],$fixtures['users'],true) && str_starts_with($listing['username'],$token)) { $images=array_merge($images,rows('SELECT * FROM xiu_images WHERE listing_id=?',[$id])); query('DELETE FROM xiu_listings WHERE id=?',[$id]); }
}
foreach($fixtures['articles'] as $id) { $article=row('SELECT * FROM xiu_articles WHERE id=?',[(int)$id]); if($article && str_starts_with($article['title'],$token)) { $images=array_merge($images,rows('SELECT * FROM xiu_images WHERE article_id=?',[$id])); query('DELETE FROM xiu_articles WHERE id=?',[$id]); } }
foreach($fixtures['users'] as $username) if(str_starts_with($username,$token)) {
    $documents=array_merge($documents,rows('SELECT f.* FROM xiu_verification_files f JOIN xiu_user_verifications v ON v.id=f.verification_id JOIN xiu_users u ON u.id=v.user_id WHERE u.username=?',[$username]));
    query('DELETE FROM xiu_users WHERE username=?',[$username]);
}
foreach($fixtures['categories'] as $id) query('DELETE FROM xiu_categories WHERE id=? AND name=?',[(int)$id,$token.' category']);
foreach($fixtures['districts']??[] as $id) query('DELETE FROM xiu_districts WHERE id=? AND name=?',[(int)$id,$token.' district']);
foreach($fixtures['cities'] as $id) query('DELETE FROM xiu_cities WHERE id=? AND name=?',[(int)$id,$token.' city']);
db()->commit(); removeImages($images); removeImages($documents,'verification');
echo "Removed only recorded QA fixtures for {$token}.\n";
