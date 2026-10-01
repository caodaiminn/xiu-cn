<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$checks=[];
$check=function(bool $condition,string $name) use(&$checks): void { if(!$condition) throw new RuntimeException($name); $checks[]=$name; };
$html='<h2 onclick="alert(1)">设备维护</h2><p class="ql-align-center evil" style="color:red"><strong>中文加粗</strong><em>斜体</em><u>下划线</u><s>删除线</s></p><ul><li>第一项</li></ul><blockquote>引用</blockquote><a href="https://example.com" target="_blank">安全链接</a><a href="javascript:alert(1)">危险链接</a><a href="&#106;avascript:alert(1)">编码危险链接</a><script>alert(1)</script><svg onload="alert(1)"></svg><iframe src="https://example.com"></iframe><img src=x onerror=alert(1)>';
$safe=sanitizeArticleHtml($html);
$check(str_contains($safe,'<h2>设备维护</h2>') && str_contains($safe,'<strong>中文加粗</strong>'),'headings and inline formatting');
$check(str_contains($safe,'<ul><li>第一项</li></ul>') && str_contains($safe,'<blockquote>引用</blockquote>'),'lists and quotes');
$check(str_contains($safe,'class="ql-align-center"') && !str_contains($safe,'evil'),'supported alignment only');
$check(str_contains($safe,'href="https://example.com"') && !str_contains($safe,'javascript:'),'safe links and encoded dangerous URL removal');
$check(!preg_match('/onclick|onload|onerror|<script|<svg|<iframe|<img|style=|alert\(/i',$safe),'executable markup and attributes removed');
$check(sanitizeArticleHtml($safe)===$safe,'sanitization remains stable when rendering');
$text=articleBodyText(['body'=>'<p>第一段 &amp; 内容</p><p>第二段<br>下一行</p>','body_format'=>'html']);
$check($text==="第一段 & 内容\n第二段\n下一行",'plain excerpts decode entities and separate paragraphs');
$plain=['body'=>"原有纯文本 <b>保留原文</b>\n第二段",'body_format'=>'text'];
$check(str_contains(articleBodyHtml($plain),'&lt;b&gt;保留原文&lt;/b&gt;') && str_contains(articleBodyHtml($plain),'<br'),'existing literal markup stays escaped');
$snapshot=json_decode(file_get_contents(ROOT.'/backup/2026-10-02-article-rich-editor/articles-before.json'),true,512,JSON_THROW_ON_ERROR);
foreach($snapshot['articles'] as $before) {
    $current=row('SELECT * FROM xiu_articles WHERE id=?',[$before['id']]);
    $check($current['body']===$before['body'] && $current['short_slug']===$before['short_slug'] && $current['body_format']==='text','original article '.$before['id'].' content and URL retained');
}
echo json_encode(['passed'=>count($checks),'checks'=>$checks],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),"\n";
