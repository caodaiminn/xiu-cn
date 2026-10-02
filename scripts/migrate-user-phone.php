<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/bootstrap.php';
$lock=fopen(ROOT.'/storage/limits/user-phone-migration.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('User phone migration already running.');
try {
    $exists=(bool)scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='xiu_users' AND column_name='phone'");
    if(!$exists) {
        $backup=ROOT.'/backup/'.date('Y-m-d').'-register-contact-phone';
        if(!is_dir($backup) && !mkdir($backup,0700,true)) throw new RuntimeException('Cannot create user backup directory.');
        $path=$backup.'/users-before-'.date('His').'-'.bin2hex(random_bytes(3)).'.sql';
        $stream=fopen($path,'xb'); if(!$stream) throw new RuntimeException('Cannot back up user table.');
        $create=row('SHOW CREATE TABLE xiu_users');
        fwrite($stream,"SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nDROP TABLE IF EXISTS xiu_users;\n".$create['Create Table'].";\n");
        foreach(rows('SELECT * FROM xiu_users ORDER BY id') as $record) {
            $columns=implode(',',array_map(fn($key)=>'`'.$key.'`',array_keys($record)));
            $values=implode(',',array_map(fn($value)=>$value===null?'NULL':db()->quote((string)$value),array_values($record)));
            if(fwrite($stream,'INSERT INTO xiu_users ('.$columns.') VALUES ('.$values.");\n")===false) throw new RuntimeException('User backup write failed.');
        }
        fwrite($stream,"SET FOREIGN_KEY_CHECKS=1;\n");
        if(!fflush($stream) || !fclose($stream)) throw new RuntimeException('User backup flush failed.');
        db()->exec("ALTER TABLE xiu_users ADD COLUMN phone VARCHAR(32) NOT NULL DEFAULT '' AFTER password_hash");
        invalidatePublicDataCache();
        echo "User phone column added; existing accounts retain an empty phone.\n";
    } else echo "User phone column already exists; no account changes.\n";
} finally { flock($lock,LOCK_UN); fclose($lock); }
