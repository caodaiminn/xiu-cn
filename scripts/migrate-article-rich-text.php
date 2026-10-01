<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/bootstrap.php';
if(!scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='xiu_articles' AND column_name='body_format'")) {
    db()->exec("ALTER TABLE xiu_articles ADD body_format ENUM('text','html') NOT NULL DEFAULT 'text' AFTER body");
}
echo "Article rich text schema ready.\n";
