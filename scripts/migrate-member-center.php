<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/bootstrap.php';
foreach(explode(';',file_get_contents(ROOT.'/database/member-schema.sql')) as $statement) if(trim($statement)) db()->exec(trim($statement));
if(!is_dir(ROOT.'/storage/verification') && !mkdir(ROOT.'/storage/verification',0700,true)) throw new RuntimeException('Cannot create private document directory.');
echo "Member center and private verification tables ready.\n";
