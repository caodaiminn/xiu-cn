<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/dgadmin/index.php' || $path==='/dgadmin/' || $path==='/dgadmin') { require __DIR__.'/dgadmin/index.php'; return; }
if(preg_match('#^/assets/[a-zA-Z0-9_./-]+$#',$path) && !str_contains($path,'..') && is_file(__DIR__.$path)) return false;
require __DIR__.'/index.php';
