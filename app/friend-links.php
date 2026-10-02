<?php
declare(strict_types=1);
function friendLinks(bool $enabledOnly=true): array {
    $items=publicDataRemember('friend-links',[],60,fn()=>rows('SELECT * FROM xiu_friend_links ORDER BY sort_order,id'));
    return $enabledOnly?array_values(array_filter($items,fn($item)=>(bool)$item['enabled'])):$items;
}
function saveFriendLink(): void {
    $id=(int)input('id'); $action=input('action','save');
    if(!in_array($action,['save','delete','toggle','up','down'],true)) throw new DomainException('无效的友情链接操作。');
    $params=[];
    if($action==='save') {
        $label=input('label'); $url=input('url'); $sort=input('sort_order','0');
        validateText($label,'网站名称',1,40);
        try { validateNavigationUrl($url); } catch(DomainException $error) { throw new DomainException('网址格式无效，请填写完整的 http:// 或 https:// 地址。'); }
        if(!in_array(strtolower(parse_url($url,PHP_URL_SCHEME)??''),['http','https'],true)) throw new DomainException('网址请填写完整的 http:// 或 https:// 地址。');
        if(!preg_match('/^[0-9]{1,6}$/',$sort)) throw new DomainException('排序须为 0–999999 的整数。');
        $params=[$label,$url,(int)$sort,input('enabled')==='1'?1:0,input('new_tab')==='1'?1:0];
    }
    db()->beginTransaction();
    try {
        $before=rows('SELECT * FROM xiu_friend_links ORDER BY sort_order,id FOR UPDATE');
        $map=array_column($before,null,'id'); $old=$map[$id]??null;
        if(($id && !$old) || ($action!=='save' && !$old)) throw new DomainException('友情链接不存在，请刷新后重试。');
        $directory=ROOT.'/backup/'.date('Y-m-d').'-friend-links/records';
        if(!is_dir($directory) && !mkdir($directory,0700,true)) throw new RuntimeException('友情链接备份目录不可写。');
        $backup=$directory.'/'.date('His').'-'.bin2hex(random_bytes(6)).'.json';
        if(file_put_contents($backup,json_encode(['action'=>$action,'id'=>$id,'records'=>$before],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('友情链接备份失败。');
        if($action==='delete') query('DELETE FROM xiu_friend_links WHERE id=?',[$id]);
        elseif($action==='toggle') query('UPDATE xiu_friend_links SET enabled=1-enabled WHERE id=?',[$id]);
        elseif(in_array($action,['up','down'],true)) {
            $ids=array_map('intval',array_column($before,'id')); $index=array_search($id,$ids,true); $neighbor=$index+($action==='up'?-1:1);
            if(isset($ids[$neighbor])) { [$ids[$index],$ids[$neighbor]]=[$ids[$neighbor],$ids[$index]]; foreach($ids as $order=>$linkId) query('UPDATE xiu_friend_links SET sort_order=? WHERE id=?',[($order+1)*10,$linkId]); }
        } elseif($old) query('UPDATE xiu_friend_links SET label=?,url=?,sort_order=?,enabled=?,new_tab=? WHERE id=?',[...$params,$id]);
        else query('INSERT INTO xiu_friend_links (label,url,sort_order,enabled,new_tab) VALUES (?,?,?,?,?)',$params);
        db()->commit();
    } catch(Throwable $error) { if(db()->inTransaction()) db()->rollBack(); throw $error; }
}
function adminFriendLinksController(): void {
    $error='';
    if($_SERVER['REQUEST_METHOD']==='POST') {
        try { saveFriendLink(); flash('友情链接已保存，前台已生效。'); redirect(adminUrl('friendlinks')); }
        catch(DomainException $exception) { $error=$exception->getMessage(); }
    }
    $id=(int)getValue('edit'); $record=$id?row('SELECT * FROM xiu_friend_links WHERE id=?',[$id]):null;
    if($id && !$record) abortPage(404,'友情链接不存在。');
    render('admin',['title'=>'友情链接 · '.siteName(),'adminPage'=>true,'section'=>'friendlinks','error'=>$error,'record'=>$record,'items'=>friendLinks(false)]);
}
