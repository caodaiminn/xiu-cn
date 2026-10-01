<?php
if(PHP_SAPI!=='cli') exit;
require_once dirname(__DIR__).'/app/bootstrap.php';
db()->exec(file_get_contents(ROOT.'/database/mail-notifications.sql'));
echo "Mail notification queue ready.\n";
