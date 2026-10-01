<?php
declare(strict_types=1);
function navigationPresets(): array {
    return [
        ['label'=>'首页','url'=>'/','match_mode'=>'exact'],
        ['label'=>'分类信息','url'=>'/fen-lei-xin-xi/','match_mode'=>'prefix'],
        ['label'=>'选择城市','url'=>'/selectcity.html','match_mode'=>'exact'],
        ['label'=>'维修知识库','url'=>'/wei-xiu-zhi-shi-ku/','match_mode'=>'prefix'],
        ['label'=>'维修百科','url'=>'/wei-xiu-bai-ke/','match_mode'=>'prefix'],
        ['label'=>'免费发布信息','url'=>'/fa-bu-xin-xi/','match_mode'=>'exact'],
        ['label'=>'会员中心','url'=>'/yong-hu-zhong-xin/','match_mode'=>'prefix'],
        ['label'=>'网站地图','url'=>'/wang-zhan-di-tu/','match_mode'=>'exact'],
        ['label'=>'登录','url'=>'/deng-lu/','match_mode'=>'exact'],
        ['label'=>'注册','url'=>'/zhu-ce/','match_mode'=>'exact'],
    ];
}
function validateNavigationUrl(string $url): void {
    validateText($url,'导航链接',1,1000);
    $parts=parse_url($url);
    if(!$parts || preg_match('/[\x00-\x20\x7f\\\\]/u',$url) || preg_match('/%(?:0[0-9a-f]|1[0-9a-f]|7f|5c)/i',$url) || isset($parts['user']) || isset($parts['pass'])) throw new DomainException('链接格式无效，请填写站内路径或完整 HTTP/HTTPS 地址。');
    $local=str_starts_with($url,'/') && !str_starts_with($url,'//') && !isset($parts['host']) && !isset($parts['scheme']);
    $external=in_array(strtolower($parts['scheme']??''),['http','https'],true) && !empty($parts['host']) && filter_var($url,FILTER_VALIDATE_URL);
    if(!$local && !$external) throw new DomainException('链接须以 / 开头，或使用完整的 http://、https:// 地址。');
}
function navigationItems(bool $enabledOnly=true): array {
    $items=publicDataRemember('primary-navigation',[],60,fn()=>rows('SELECT * FROM xiu_navigation ORDER BY sort_order,id'));
    return $enabledOnly?array_values(array_filter($items,fn($item)=>(bool)$item['enabled'])):$items;
}
function activeNavigationId(array $items,string $current): ?int {
    $currentParts=parse_url($current); $site=parse_url(siteUrl());
    $path=rtrim($currentParts['path']??'/','/')?:'/'; $currentQuery=[];
    parse_str($currentParts['query']??'',$currentQuery);
    $winner=null; $best=-1;
    foreach($items as $item) {
        $parts=parse_url($item['url']); if(!$parts) continue;
        if(isset($parts['host']) && (strcasecmp($parts['host'],$site['host']??'')!==0 || ($parts['port']??null)!==($site['port']??null))) continue;
        if(!empty($parts['fragment'])) continue;
        $target=rtrim($parts['path']??'/','/')?:'/'; $query=[]; parse_str($parts['query']??'',$query);
        foreach($query as $key=>$value) if(!array_key_exists($key,$currentQuery) || $currentQuery[$key]!==$value) continue 2;
        $match=$path===$target || ($item['match_mode']==='prefix' && $target!=='/' && str_starts_with($path,$target.'/'));
        $score=strlen($target)+count($query)*1000;
        if($match && $score>$best) { $winner=(int)$item['id']; $best=$score; }
    }
    return $winner;
}
function saveNavigation(): void {
    $id=(int)input('id'); $action=input('action','save');
    $old=$id?row('SELECT * FROM xiu_navigation WHERE id=?',[$id]):null;
    if($id && !$old) throw new DomainException('导航项不存在，请刷新后重试。');
    if(in_array($action,['delete','toggle','up','down'],true)) {
        if(!$old) throw new DomainException('请选择导航项。');
        if($action==='delete') { query('DELETE FROM xiu_navigation WHERE id=?',[$id]); return; }
        if($action==='toggle') { query('UPDATE xiu_navigation SET enabled=1-enabled WHERE id=?',[$id]); return; }
        db()->beginTransaction();
        try {
            $ids=array_map('intval',array_column(rows('SELECT id FROM xiu_navigation ORDER BY sort_order,id FOR UPDATE'),'id'));
            $position=array_search($id,$ids,true); if($position===false) throw new DomainException('导航项不存在。');
            $next=$position+($action==='up'?-1:1);
            if(isset($ids[$next])) { [$ids[$position],$ids[$next]]=[$ids[$next],$ids[$position]]; foreach($ids as $index=>$navId) query('UPDATE xiu_navigation SET sort_order=? WHERE id=?',[($index+1)*10,$navId]); }
            db()->commit();
        } catch(Throwable $error) { if(db()->inTransaction()) db()->rollBack(); throw $error; }
        return;
    }
    if($action!=='save') throw new DomainException('无效的导航操作。');
    $label=input('label'); $url=input('url'); $sort=input('sort_order','0'); $mode=input('match_mode','exact');
    validateText($label,'导航名称',1,40); validateNavigationUrl($url);
    if(!preg_match('/^[0-9]{1,6}$/',$sort)) throw new DomainException('排序须为 0–999999 的整数。');
    if(!in_array($mode,['exact','prefix'],true)) throw new DomainException('请选择选中规则。');
    $params=[$label,$url,(int)$sort,input('enabled')==='1'?1:0,input('new_tab')==='1'?1:0,$mode];
    if($old) { $params[]=$id; query('UPDATE xiu_navigation SET label=?,url=?,sort_order=?,enabled=?,new_tab=?,match_mode=? WHERE id=?',$params); }
    else query('INSERT INTO xiu_navigation (label,url,sort_order,enabled,new_tab,match_mode) VALUES (?,?,?,?,?,?)',$params);
}
function adminNavigationController(): void {
    $error='';
    if($_SERVER['REQUEST_METHOD']==='POST') {
        try { saveNavigation(); flash('导航设置已保存，前台已生效。'); redirect(adminUrl('navigation')); }
        catch(DomainException $exception) { $error=$exception->getMessage(); }
    }
    $id=(int)getValue('edit'); $record=$id?row('SELECT * FROM xiu_navigation WHERE id=?',[$id]):null;
    if($id && !$record) abortPage(404,'导航项不存在。');
    render('admin',['title'=>'导航栏管理 · '.siteName(),'adminPage'=>true,'section'=>'navigation','error'=>$error,'record'=>$record,'items'=>navigationItems(false)]);
}
