<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/bootstrap.php';
db()->exec(file_get_contents(ROOT.'/database/listing-slug-alias-schema.sql'));
echo "Listing URL alias schema ready.\n";
