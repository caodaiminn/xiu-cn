<?php
declare(strict_types=1);

/** Publish invalidation after a transaction commits, never while its data is uncommitted. */
class SitePDO extends PDO {
    public bool $publicCacheDirty=false;
    public function commit(): bool {
        $result=parent::commit();
        if($result && $this->publicCacheDirty) { $this->publicCacheDirty=false; invalidatePublicDataCache(); }
        return $result;
    }
    public function rollBack(): bool {
        $result=parent::rollBack();
        if($result) $this->publicCacheDirty=false;
        return $result;
    }
    public function exec(string $statement): int|false {
        $result=parent::exec($statement);
        if($result!==false) publicCacheDatabaseWrite($statement);
        return $result;
    }
}
function publicCacheDirectory(): ?string {
    $path=ROOT.'/storage/cache/public-data';
    return is_dir($path) || @mkdir($path,0700,true)?$path:null;
}
function publicCacheRevision(bool $invalidate=false,bool $fresh=false): ?string {
    if(!$invalidate && !$fresh && isset($GLOBALS['public_data_cache_revision'])) return $GLOBALS['public_data_cache_revision'];
    $directory=publicCacheDirectory();
    if(!$directory || !($stream=@fopen($directory.'/revision','c+'))) return null;
    if(!flock($stream,$invalidate?LOCK_EX:LOCK_SH)) { fclose($stream); return null; }
    $revision=trim((string)stream_get_contents($stream));
    if($invalidate || !preg_match('/^[a-f0-9]{32}$/',$revision)) {
        if(!$invalidate) {
            flock($stream,LOCK_UN); flock($stream,LOCK_EX); rewind($stream);
            $revision=trim((string)stream_get_contents($stream));
        }
        if($invalidate || !preg_match('/^[a-f0-9]{32}$/',$revision)) {
            $revision=bin2hex(random_bytes(16)); rewind($stream);
            if(!ftruncate($stream,0) || fwrite($stream,$revision)!==32) $revision=null;
            fflush($stream);
        }
    }
    flock($stream,LOCK_UN); fclose($stream);
    $GLOBALS['public_data_cache_revision']=$revision;
    return $revision;
}
function invalidatePublicDataCache(): void {
    publicCacheRevision(true);
    $GLOBALS['public_data_cache_memo']=[];
}
function publicCacheDatabaseWrite(string $sql): void {
    if(!preg_match('/^\s*(?:INSERT(?:\s+IGNORE)?\s+INTO|REPLACE(?:\s+INTO)?|UPDATE|DELETE\s+FROM|TRUNCATE(?:\s+TABLE)?)\s+`?(xiu_(?:listings|categories|cities|districts|users|user_verifications|images|articles|settings|navigation))\b/i',$sql)) return;
    $connection=db();
    if($connection->inTransaction()) $connection->publicCacheDirty=true;
    else invalidatePublicDataCache();
}
function publicCacheStat(string $name): void { $GLOBALS['public_data_cache_stats'][$name]=($GLOBALS['public_data_cache_stats'][$name]??0)+1; }
function publicDataRemember(string $namespace,array $key,int $ttl,callable $load) {
    // Private transactions and keyword searches must never publish intermediate or user-specific data.
    if(db()->inTransaction() || !($revision=publicCacheRevision())) { publicCacheStat('bypass'); return $load(); }
    $id=hash('sha256',$namespace.'|'.json_encode($key,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    $memo=$GLOBALS['public_data_cache_memo'][$id]??null;
    $valid=function($entry) use ($revision,$ttl): bool {
        $now=time();
        return is_array($entry) && ($entry['revision']??null)===$revision && ($entry['expires_at']??0)>$now && ($entry['expires_at']??0)<=$now+$ttl && array_key_exists('data',$entry);
    };
    if($valid($memo)) { publicCacheStat('hit'); return $memo['data']; }
    $path=publicCacheDirectory().'/'.$id.'.json'; $stream=@fopen($path,'c+');
    if(!$stream) { publicCacheStat('bypass'); return $load(); }
    try {
        if(!flock($stream,LOCK_SH)) { publicCacheStat('bypass'); return $load(); }
        $cached=json_decode((string)stream_get_contents($stream),true);
        if($valid($cached)) { $GLOBALS['public_data_cache_memo'][$id]=$cached; publicCacheStat('hit'); return $cached['data']; }
        flock($stream,LOCK_UN); flock($stream,LOCK_EX); rewind($stream);
        $cached=json_decode((string)stream_get_contents($stream),true);
        if($valid($cached)) { $GLOBALS['public_data_cache_memo'][$id]=$cached; publicCacheStat('hit'); return $cached['data']; }
        publicCacheStat('miss'); $data=$load();
        if(publicCacheRevision(false,true)===$revision) {
            $entry=['revision'=>$revision,'expires_at'=>time()+$ttl,'data'=>$data];
            $json=json_encode($entry,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
            rewind($stream); if(ftruncate($stream,0)) { fwrite($stream,$json); fflush($stream); }
            $GLOBALS['public_data_cache_memo'][$id]=$entry;
        }
        return $data;
    } finally { flock($stream,LOCK_UN); fclose($stream); }
}
