<?php
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$data=['cities'=>cityList(),'categories'=>categories(),'baseline'=>adminListingCounts()];
$path=ROOT.'/reports/imports/baixiu-browser/local-catalog.json';
file_put_contents($path,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo json_encode(['cities'=>count($data['cities']),'categories'=>count($data['categories']),'baseline'=>$data['baseline']],JSON_UNESCAPED_UNICODE).PHP_EOL;
