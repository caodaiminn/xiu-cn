<?php
function pinyinSlug(string $text): string {
    static $dictionary, $lengths;
    if ($dictionary===null) {
        $dictionary=json_decode(file_get_contents(ROOT.'/app/data/pinyin.json'),true,512,JSON_THROW_ON_ERROR);
        $lengths=array_unique(array_map('mb_strlen',array_keys($dictionary['phrases']))); rsort($lengths);
    }
    $characters=mb_str_split(mb_strtolower($text)); $tokens=[]; $ascii='';
    $flush=function() use (&$tokens,&$ascii) { if($ascii!=='') { $tokens[]=$ascii; $ascii=''; } };
    for($i=0;$i<count($characters);$i++) {
        $character=$characters[$i];
        if(preg_match('/^[a-z0-9]$/',$character)) { $ascii.=$character; continue; }
        $flush(); $matched=false;
        foreach($lengths as $length) {
            $phrase=implode('',array_slice($characters,$i,$length));
            if(isset($dictionary['phrases'][$phrase])) { array_push($tokens,...$dictionary['phrases'][$phrase]); $i+=$length-1; $matched=true; break; }
        }
        if(!$matched && isset($dictionary['characters'][$character])) $tokens[]=$dictionary['characters'][$character];
        elseif(!$matched && preg_match('/\p{Han}/u',$character)) $tokens[]='u'.dechex(mb_ord($character));
    }
    $flush(); $slug=trim(preg_replace('/[^a-z0-9]+/','-',implode('-',$tokens)),'-');
    if(strlen($slug)>160) { $slug=substr($slug,0,160); $break=strrpos($slug,'-'); if($break!==false) $slug=substr($slug,0,$break); }
    return $slug?:'xin-xi';
}
function ensureSeoSlug(string $table,int $id): void {
    if(!in_array($table,['xiu_categories','xiu_cities','xiu_listings','xiu_articles'],true)) throw new LogicException('Invalid SEO table');
    $record=row('SELECT * FROM '.$table.' WHERE id=?',[$id]); if(!$record) return;
    if(in_array($table,['xiu_listings','xiu_articles'],true)) ensureShortSlug($table,$record);
    if(!empty($record['seo_slug'])) return;
    $slug=pinyinSlug($record['name']??$record['title']);
    if($table==='xiu_categories' || $table==='xiu_cities') {
        $params=[$slug,$id]; $kindClause='';
        if($table==='xiu_categories') { $kindClause=' AND kind=?'; $params[]=$record['kind']; }
        if(scalar('SELECT id FROM '.$table.' WHERE seo_slug=? AND id<>?'.$kindClause,$params)) $slug.='-'.$id;
    }
    query('UPDATE '.$table." SET seo_slug=? WHERE id=? AND seo_slug=''",[$slug,$id]);
}
/** Persist a maximum 12-character identifier; database uniqueness also guards concurrent inserts. */
function ensureShortSlug(string $table,array $record): void {
    if(!empty($record['short_slug'])) return;
    $spelling=pinyinSlug($record['title']);
    $letters=preg_replace('/[^a-z]/','',$spelling);
    $prefix=strlen($letters)<=8?$letters:implode('',array_map(fn($word)=>preg_replace('/[^a-z]/','', $word)[0]??'',explode('-',$spelling)));
    $prefix=substr($prefix?:'xinxi',0,8);
    for($attempt=0;$attempt<1000;$attempt++) {
        $slug=$prefix.sprintf('%04d',random_int(0,9999));
        try { query('UPDATE '.$table.' SET short_slug=? WHERE id=? AND short_slug IS NULL',[$slug,$record['id']]); return; }
        catch(PDOException $exception) { if((int)($exception->errorInfo[1]??0)!==1062) throw $exception; }
    }
    throw new RuntimeException('Short URL namespace exhausted for this title prefix.');
}
