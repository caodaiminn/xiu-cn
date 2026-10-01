<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$path=ROOT.'/tests/artifacts/article-rich-text-fixture.json';
if(($argv[1]??'')==='cleanup') {
    $fixture=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    $article=row('SELECT * FROM xiu_articles WHERE id=?',[$fixture['id']]);
    $session=ROOT.'/storage/sessions/sess_'.$fixture['session'];
    echo 'Cleanup targets: xiu_articles id='.(int)$fixture['id']."\n".$session."\n".$path."\n";
    if($article && $article['title']!==$fixture['title']) throw new RuntimeException('Fixture identity changed; preserving data.');
    if($article) query('DELETE FROM xiu_articles WHERE id=?',[$fixture['id']]);
    if(is_file($session)) unlink($session);
    unlink($path);
    echo "Isolated article and session cleaned up.\n"; exit;
}
if(is_file($path)) throw new RuntimeException('Previous fixture manifest exists.');
$title='article_rich_qa_'.bin2hex(random_bytes(6));
$category=(int)scalar("SELECT id FROM xiu_categories WHERE kind='encyclopedia' AND enabled=1 LIMIT 1");
query("INSERT INTO xiu_articles (category_id,kind,title,summary,body,status) VALUES (?,'encyclopedia',?,'','这是一篇仅用于检查富文本编辑器的文章。','draft')",[$category,$title]);
$id=(int)db()->lastInsertId(); ensureSeoSlug('xiu_articles',$id);
$session='article-rich-qa-'.bin2hex(random_bytes(16));
session_save_path(ROOT.'/storage/sessions'); session_name('xiu_session'); session_id($session); session_start();
$_SESSION['admin_id']=(int)scalar('SELECT id FROM xiu_admins ORDER BY id LIMIT 1'); session_write_close();
$fixture=['id'=>$id,'title'=>$title,'category_id'=>$category,'session'=>$session,'url'=>articleUrl(row('SELECT * FROM xiu_articles WHERE id=?',[$id]))];
file_put_contents($path,json_encode($fixture,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo 'Isolated rich text fixture created: id='.$id."\n";
