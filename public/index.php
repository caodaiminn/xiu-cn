<?php
require dirname(__DIR__).'/app/bootstrap.php';
try {
    require ROOT.'/app/controller.php';
    dispatch();
} catch (Throwable $exception) {
    error_log((string)$exception);
    http_response_code(500);
    header('X-Robots-Tag: noindex, nofollow');
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Security-Policy: '.pageContentSecurityPolicy());
    echo '<!doctype html><html lang="zh-CN"><meta charset="utf-8"><title>服务暂时不可用</title><body><h1>服务暂时不可用</h1><p>请稍后重试，你提交的内容尚未确认保存。</p><a href="/">返回首页</a>'.analyticsScripts().'</body></html>';
}
