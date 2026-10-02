<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
$source=$argv[1]??'';
if(!$source || !is_file($source)) throw new RuntimeException('Pass the saved rollback-url-repair.sql path.');
$apply=in_array('--apply',$argv,true);
$lock=fopen(ROOT.'/storage/limits/brand-listing-url-aliases.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('URL alias import already running.');
try {
    // Read recovery statements as data; never execute the rollback SQL.
    $pairs=[]; $stream=fopen($source,'rb');
    while(($line=fgets($stream))!==false) {
        if(!preg_match("/^UPDATE xiu_listings SET short_slug='([a-z][a-z0-9]{0,7}[0-9]{4})',seo_slug='\\1' WHERE id=([0-9]+) AND short_slug='([a-z]{1,8}[0-9]{4})';\\s*$/",$line,$match)) throw new RuntimeException('Unexpected recovery record.');
        if(isset($pairs[$match[1]])) throw new RuntimeException('Duplicate recovery alias.');
        $pairs[$match[1]]=['id'=>(int)$match[2],'current'=>$match[3]];
    }
    fclose($stream);
    $listings=array_column(rows("SELECT l.id,l.short_slug FROM xiu_listings l JOIN xiu_local_test_seeds s ON s.listing_id=l.id WHERE s.seed_version='ac-brands-v1' AND l.is_demo=1"),null,'id');
    if(count($pairs)!==count($listings) || !$pairs) throw new RuntimeException('Recovery file does not cover all brand sample listings.');
    foreach($pairs as $pair) if(($listings[$pair['id']]['short_slug']??null)!==$pair['current']) throw new RuntimeException('Listing no longer matches its recovery record.');
    echo json_encode(['mode'=>$apply?'apply':'dry-run','verified_aliases'=>count($pairs)]).PHP_EOL;
    if(!$apply) exit;
    $backup=ROOT.'/backup/'.date('Y-m-d').'-brand-listing-old-links/'.date('His').'-'.bin2hex(random_bytes(3));
    if(!mkdir($backup,0700,true)) throw new RuntimeException('Cannot create alias recovery directory.');
    if(!copy($source,$backup.'/source-url-recovery.sql')) throw new RuntimeException('Cannot back up source recovery records.');
    $exists=(bool)scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='xiu_listing_slug_aliases'");
    $before=$exists?rows('SELECT * FROM xiu_listing_slug_aliases ORDER BY slug'):[];
    if(file_put_contents($backup.'/aliases-before.json',json_encode($before,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Alias backup failed.');
    db()->exec(file_get_contents(ROOT.'/database/listing-slug-alias-schema.sql'));
    $existing=array_column($before,'listing_id','slug');
    unset($before);
    $used=[]; db()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
    $statement=query('SELECT id,short_slug FROM xiu_listings WHERE short_slug IS NOT NULL');
    while($record=$statement->fetch()) $used[$record['short_slug']]=(int)$record['id'];
    $statement->closeCursor(); db()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);
    $insert=db()->prepare('INSERT INTO xiu_listing_slug_aliases (slug,listing_id) VALUES (?,?)');
    $rollback=fopen($backup.'/rollback-added-aliases.sql','xb'); if(!$rollback) throw new RuntimeException('Cannot create alias rollback file.');
    $created=0; db()->beginTransaction();
    try {
        foreach($pairs as $slug=>$pair) {
            if(isset($used[$slug]) && (int)$used[$slug]!==$pair['id']) throw new RuntimeException('Alias conflicts with an existing detail URL.');
            if(isset($existing[$slug])) { if((int)$existing[$slug]!==$pair['id']) throw new RuntimeException('Alias points at another listing.'); continue; }
            $insert->execute([$slug,$pair['id']]);
            if(fwrite($rollback,'DELETE FROM xiu_listing_slug_aliases WHERE slug='.db()->quote($slug).' AND listing_id='.$pair['id'].";\n")===false) throw new RuntimeException('Alias rollback write failed.');
            $created++;
        }
        if(!fflush($rollback)) throw new RuntimeException('Alias rollback flush failed.');
        db()->commit(); fclose($rollback);
    } catch(Throwable $error) { if(db()->inTransaction()) db()->rollBack(); throw $error; }
    echo json_encode(['created'=>$created,'aliases'=>count($pairs),'backup'=>$backup],JSON_UNESCAPED_UNICODE).PHP_EOL;
} finally { flock($lock,LOCK_UN); fclose($lock); }
