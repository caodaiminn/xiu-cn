<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
function listingDataFingerprint(): array {
    $hash=hash_init('sha256'); $count=0;
    db()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
    $statement=query('SELECT * FROM xiu_listings ORDER BY id');
    while($record=$statement->fetch()) { hash_update($hash,json_encode($record,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n"); $count++; }
    $statement->closeCursor(); db()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);
    return ['count'=>$count,'sha256'=>hash_final($hash)];
}
$path=ROOT.'/tests/artifacts/brand-listing-aliases-before.json';
if(in_array('--snapshot',$argv,true)) {
    if(is_file($path)) throw new RuntimeException('Preserve the original snapshot before rerunning.');
    file_put_contents($path,json_encode(listingDataFingerprint(),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo "Listing content snapshot saved.\n"; exit;
}
$before=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
$after=listingDataFingerprint();
if($before!==$after) throw new RuntimeException('Listing content changed during URL repair.');
$bad=(int)scalar("SELECT COUNT(*) FROM (SELECT city_id,category_id,COUNT(*) amount FROM xiu_local_test_seeds WHERE seed_version='ac-brands-v1' GROUP BY city_id,category_id HAVING COUNT(*)<>8) counts");
$totals=row("SELECT COUNT(*) total,COUNT(DISTINCT s.city_id) cities,COUNT(DISTINCT s.category_id) brands,SUM(l.type='service') services,SUM(l.type='request') requests,SUM(l.status<>'published' OR u.enabled<>1 OR c.enabled<>1 OR k.enabled<>1 OR p.enabled<>1 OR r.enabled<>1) hidden FROM xiu_local_test_seeds s JOIN xiu_listings l ON l.id=s.listing_id JOIN xiu_users u ON u.id=l.user_id JOIN xiu_cities c ON c.id=l.city_id JOIN xiu_categories k ON k.id=l.category_id JOIN xiu_categories p ON p.id=k.parent_id JOIN xiu_categories r ON r.id=p.parent_id WHERE s.seed_version='ac-brands-v1'");
if($bad || (int)$totals['total']!==78600 || (int)$totals['cities']!==393 || (int)$totals['brands']!==25 || (int)$totals['hidden']!==0) throw new RuntimeException('Brand listing coverage failed.');
$items=rows("SELECT a.slug,l.id,l.short_slug,l.title,l.city_id,l.category_id,l.type FROM xiu_listing_slug_aliases a JOIN xiu_listings l ON l.id=a.listing_id JOIN xiu_local_test_seeds s ON s.listing_id=l.id WHERE s.seed_version='ac-brands-v1' ORDER BY l.id");
if(count($items)!==78600) throw new RuntimeException('Legacy alias coverage failed.');
$verified=0; $samples=[]; $brands=[];
foreach($items as $item) {
    $old='/wei-xiu-xin-xi/'.$item['slug'].'.html'; $current=listingUrl($item);
    if(resolveSeoRoute($old)!=='/info/'.$item['id'] || resolveSeoRoute($current)!=='/info/'.$item['id']) throw new RuntimeException('Old or new detail route mismatch.');
    $verified++;
    if(!isset($brands[$item['category_id']]) || $verified%2000===0 || $item['slug']==='ab0b406a2025') $samples[]=['old'=>$old,'current'=>$current,'id'=>$item['id'],'title'=>$item['title'],'type'=>$item['type']];
    $brands[$item['category_id']]=true;
    if($verified%20000===0) echo json_encode(['verified'=>$verified]).PHP_EOL;
}
$result=['passed'=>true,'verified_old_and_new_routes'=>$verified,'unchanged_listings'=>$after['count'],'totals'=>$totals,'bad_pairs'=>$bad,'samples'=>$samples];
file_put_contents(ROOT.'/tests/artifacts/brand-listing-aliases-results.json',json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
unset($result['samples']); echo json_encode($result,JSON_UNESCAPED_UNICODE).PHP_EOL;
