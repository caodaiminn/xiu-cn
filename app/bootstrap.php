<?php
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
$config = require ROOT . '/app/config.php';
date_default_timezone_set($config['timezone']);
foreach (['files','sessions','logs','limits'] as $directory) {
    if (!is_dir(ROOT . '/storage/' . $directory)) mkdir(ROOT . '/storage/' . $directory, 0700, true);
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/php.log');
require ROOT . '/app/analytics.php';
require ROOT . '/app/public-cache.php';
if (PHP_SAPI !== 'cli') {
    session_save_path(ROOT . '/storage/sessions');
    session_name('xiu_session');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['httponly'=>true, 'samesite'=>'Lax', 'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'path'=>'/']);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Content-Security-Policy: '.pageContentSecurityPolicy());
    header('Cache-Control: no-store');
}
require ROOT . '/app/model.php';
require ROOT . '/app/helpers.php';
require ROOT . '/app/article-rich-text.php';
require ROOT . '/app/navigation.php';
require ROOT . '/app/city-location.php';
require ROOT . '/app/pinyin.php';
require ROOT . '/app/seo.php';
require ROOT . '/app/sitemap.php';
require ROOT . '/app/member.php';
require ROOT . '/app/admin-listings.php';
require ROOT . '/app/mail.php';
require ROOT . '/app/ai.php';
require ROOT . '/app/crawlers.php';
require ROOT . '/app/friend-links.php';
require ROOT . '/app/repair-guides.php';
registerCrawlerTracking();
