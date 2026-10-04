<?php
function siteUrl(): string { static $url; return $url??=rtrim((string)(scalar("SELECT setting_value FROM xiu_settings WHERE setting_key='site_url'")?:'http://www.xiu.cn'),'/'); }
function absoluteUrl(string $path): string { return siteUrl().$path; }
function publicPath(string $name): string { return ['services'=>'/fen-lei-xin-xi/','cities'=>'/selectcity.html','knowledge'=>'/wei-xiu-zhi-shi-ku/','encyclopedia'=>'/wei-xiu-bai-ke/','login'=>'/deng-lu/','register'=>'/zhu-ce/','publish'=>'/fa-bu-xin-xi/','user'=>'/yong-hu-zhong-xin/','logout'=>'/tui-chu/','sitemap'=>'/wang-zhan-di-tu/'][$name]; }
function seoRecord(string $table,int $id): ?array {
    if(in_array($table,['xiu_categories','xiu_cities','xiu_districts'],true)) {
        $catalog=publicDataRemember('seo-catalog',[$table],60,fn()=>array_column(rows('SELECT * FROM '.$table),null,'id'));
        return $catalog[$id]??null;
    }
    static $cache=[]; $key=$table.':'.$id;
    if(!array_key_exists($key,$cache)) $cache[$key]=row('SELECT * FROM '.$table.' WHERE id=?',[$id]);
    return $cache[$key];
}
function recordSlug(array $record): string { return !empty($record['seo_slug'])?$record['seo_slug']:pinyinSlug($record['name']??$record['title']); }
function detailSlug(string $table,array $record): string { return $record['short_slug']??seoRecord($table,(int)$record['id'])['short_slug']??throw new LogicException('Run the SEO migration first.'); }
function listingUrl(array $listing): string { return '/wei-xiu-xin-xi/'.detailSlug('xiu_listings',$listing).'.html'; }
function cityUrl(array $city): string { return '/'.recordSlug($city).'/'; }
function listUrl(array $filters=[],int $page=1): string {
    $city=!empty($filters['city'])?seoRecord('xiu_cities',(int)$filters['city']):null;
    $category=!empty($filters['category'])?seoRecord('xiu_categories',(int)$filters['category']):null;
    $url=$city?cityUrl($city):publicPath('services');
    if($category) $url.=recordSlug($category).'/';
    if(!empty($filters['district'])) { $district=seoRecord('xiu_districts',(int)$filters['district']); if($district) $url.='qu-yu/'.recordSlug($district).'/'; }
    if(!empty($filters['type']) && in_array($filters['type'],['service','request'],true)) $url.='lei-xing/'.($filters['type']==='service'?'wei-xiu-fu-wu':'qiu-xiu-xu-qiu').'/';
    if($page>1) $url.='di-'.$page.'-ye/';
    $keyword=trim(mb_substr((string)($filters['q']??''),0,100));
    return $url.($keyword!==''?'?q='.rawurlencode($keyword):'');
}
function articleCollectionUrl(string $kind,array $filters=[],int $page=1): string {
    $url=publicPath($kind); $category=!empty($filters['category'])?seoRecord('xiu_categories',(int)$filters['category']):null;
    if($category) $url.=recordSlug($category).'/';
    if($page>1) $url.='di-'.$page.'-ye/';
    $keyword=trim(mb_substr((string)($filters['q']??''),0,100));
    return $url.($keyword!==''?'?q='.rawurlencode($keyword):'');
}
function enforceCanonical(string $url): void {
    $GLOBALS['canonical_path']=$url;
    if(in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true) && ($_SERVER['REQUEST_URI']??'/')!==$url) { header('Location: '.absoluteUrl($url),true,301); exit; }
}
function positiveId($value,bool $allowZero=true): int {
    if(!is_scalar($value) || !preg_match('/^[0-9]{1,9}$/',(string)$value)) abortPage(404,'筛选参数或页码无效。');
    $number=(int)$value; if(!$allowZero && $number<1) abortPage(404,'页码无效。'); return $number;
}
function publicFilters(array $routeFilters): array {
    $filters=['category'=>0,'city'=>0,'district'=>0,'type'=>'','q'=>'','page'=>1];
    foreach($filters as $key=>$default) {
        $value=array_key_exists($key,$_GET)?$_GET[$key]:($routeFilters[$key]??$default);
        if(in_array($key,['category','city','district','page'],true)) $filters[$key]=positiveId($value==='' && $key!=='page'?0:$value,$key!=='page');
        else { if(!is_scalar($value)) abortPage(404,'筛选参数无效。'); $filters[$key]=trim(mb_substr((string)$value,0,100)); }
    }
    if($filters['type']!=='' && !in_array($filters['type'],['service','request'],true)) abortPage(404,'信息类型不存在。');
    return $filters;
}
/** Resolve new public paths to controller routes. POST aliases remain usable. */
function resolveSeoRoute(string $path): string {
    $routeFilters=[]; $path=strtolower($path);
    $aliases=['/deng-lu'=>'/login','/zhu-ce'=>'/register','/fa-bu-xin-xi'=>'/publish','/yong-hu-zhong-xin'=>'/user','/yong-hu-zhong-xin/xia-jia'=>'/user/offline','/yong-hu-zhong-xin/wo-de-fa-bu'=>'/user/listings','/yong-hu-zhong-xin/shen-fen-ren-zheng'=>'/user/verification','/yong-hu-zhong-xin/shou-ji-hao-ma'=>'/user/phone','/tui-chu'=>'/logout','/index.php'=>'/'];
    if(isset($aliases[$path])) return $aliases[$path];
    if($path==='/wang-zhan-di-tu') return '/html-sitemap';
    if(in_array($path,['/cheng-shi-fu-wu','/xuan-ze-cheng-shi.html','/selectcity.html'],true)) return '/cities';
    if(preg_match('#^/(wei-xiu-xin-xi|wei-xiu-zhi-shi-ku|wei-xiu-bai-ke)/([a-z][a-z0-9]{0,7}[0-9]{4})\.html$#',$path,$match)) {
        $table=$match[1]==='wei-xiu-xin-xi'?'xiu_listings':'xiu_articles';
        $record=row('SELECT id, '.($table==='xiu_articles'?'kind':'type').' FROM '.$table.' WHERE short_slug=?',[$match[2]]);
        if(!$record && $table==='xiu_listings') {
            // Preserve shared URLs; the detail controller redirects to the canonical URL.
            static $hasAliases=null;
            $hasAliases??=(bool)scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='xiu_listing_slug_aliases'");
            if($hasAliases) $record=row('SELECT l.id,l.type FROM xiu_listing_slug_aliases a JOIN xiu_listings l ON l.id=a.listing_id WHERE a.slug=?',[$match[2]]);
        }
        if(!$record || ($table==='xiu_articles' && $record['kind']!==($match[1]==='wei-xiu-bai-ke'?'encyclopedia':'knowledge'))) abortPage(404,'内容不存在。');
        return $table==='xiu_listings'?'/info/'.$record['id']:($record['kind']==='encyclopedia'?'/baike/':'/baixiuquan/').$record['id'].'.html';
    }
    if(preg_match('#^/wei-xiu-xin-xi/[a-z0-9-]+-(\d+)\.html$#',$path,$match)) return '/info/'.$match[1];
    if(preg_match('#^/(wei-xiu-zhi-shi-ku|wei-xiu-bai-ke)/[a-z0-9-]+-(\d+)\.html$#',$path,$match)) return ($match[1]==='wei-xiu-bai-ke'?'/baike/':'/baixiuquan/').$match[2].'.html';
    // Interpret a city at the root using the existing city/category/district parser.
    $first=explode('/',ltrim($path,'/'))[0];
    $reserved=['dgadmin','assets','media','app','storage','backup','scripts','tests','database','services','cities','city','info','baixiuquan','baike','login','register','publish','user','logout','html-sitemap','fen-lei-xin-xi','cheng-shi-fu-wu','wei-xiu-xin-xi','wei-xiu-zhi-shi-ku','wei-xiu-bai-ke','deng-lu','zhu-ce','fa-bu-xin-xi','yong-hu-zhong-xin','tui-chu','wang-zhan-di-tu'];
    if(preg_match('/^[a-z][a-z0-9-]*$/',$first) && !in_array($first,$reserved,true) && scalar('SELECT id FROM xiu_cities WHERE seo_slug=?',[$first])) $path='/cheng-shi-fu-wu'.$path;
    if(preg_match('#^/(fen-lei-xin-xi|cheng-shi-fu-wu|wei-xiu-zhi-shi-ku|wei-xiu-bai-ke)(?:/(.*))?$#',$path,$match)) {
        $base=$match[1]; $tail=$match[2]??''; $parts=$tail===''?[]:explode('/',$tail);
        if($parts && preg_match('/^di-([0-9]+)-ye$/',end($parts),$page)) { $routeFilters['page']=positiveId($page[1],false); array_pop($parts); }
        $kind=in_array($base,['wei-xiu-zhi-shi-ku','wei-xiu-bai-ke'],true)?($base==='wei-xiu-bai-ke'?'encyclopedia':'knowledge'):'service';
        if($kind==='service' && count($parts)>=2 && $parts[count($parts)-2]==='lei-xing') {
            $type=array_pop($parts); array_pop($parts); $routeFilters['type']=['wei-xiu-fu-wu'=>'service','qiu-xiu-xu-qiu'=>'request'][$type]??'';
            if(!$routeFilters['type']) abortPage(404,'信息类型不存在。');
        }
        $districtSlug=null;
        if($kind==='service' && count($parts)>=2 && $parts[count($parts)-2]==='qu-yu') { $districtSlug=array_pop($parts); array_pop($parts); if($base!=='cheng-shi-fu-wu') abortPage(404,'请选择区域所属城市。'); }
        if($base==='cheng-shi-fu-wu') {
            if(!$parts) abortPage(404,'城市地址无效。');
            $slug=array_shift($parts); $city=row('SELECT * FROM xiu_cities WHERE seo_slug=? AND enabled=1',[$slug]);
            if(!$city) abortPage(404,'这个城市暂未开通。'); $routeFilters['city']=(int)$city['id'];
            if($districtSlug!==null) { $district=row('SELECT id FROM xiu_districts WHERE city_id=? AND seo_slug=? AND enabled=1',[$city['id'],$districtSlug]); if(!$district) abortPage(404,'这个区域暂未开通。'); $routeFilters['district']=(int)$district['id']; }
        }
        if($parts) {
            if(count($parts)!==1) abortPage(404,'分类地址不存在。');
            $category=row('SELECT * FROM xiu_categories WHERE seo_slug=? AND kind=?',[$parts[0],$kind]);
            if(!$category || !in_array((int)$category['id'],categoryIds($kind),true)) abortPage(404,'这个分类暂未开通。');
            $routeFilters['category']=(int)$category['id'];
        }
        $GLOBALS['route_filters']=$routeFilters;
        return $kind==='service'?'/services':($kind==='encyclopedia'?'/baike':'/baixiuquan');
    }
    return $path;
}
function seoDescription(string $text,int $limit=150): string { $text=trim(preg_replace('/\s+/u',' ',strip_tags($text))); return mb_strlen($text)>$limit?mb_substr($text,0,$limit-1).'…':$text; }
function pageSeo(string $template,array $data): array {
    $name=siteName(); $path=$GLOBALS['canonical_path']??($_SERVER['REQUEST_URI']??'/');
    $seo=['title'=>$data['title']??$name,'description'=>$name.'提供按城市和分类查找的维修服务、求修需求、维修知识与设备百科。','canonical'=>absoluteUrl($path),'robots'=>'index, follow, max-image-preview:large','schema'=>[]];
    $crumbs=[['name'=>'首页','url'=>absoluteUrl('/')]];
    $pageType='WebPage';
    if($template==='home') { $seo['title']=$name.' - 维修服务、求修需求、维修知识与百科'; $seo['description']='在'.$name.'按城市和维修分类查找家电、房屋、电脑数码、机械及生活服务，发布维修服务或求修需求，阅读维修知识与设备百科。'; }
    elseif($template==='listings') {
        $filters=$data['filters']; $city=$data['city']??null; $category=!empty($filters['category'])?seoRecord('xiu_categories',(int)$filters['category']):null;
        $district=!empty($filters['district'])?seoRecord('xiu_districts',(int)$filters['district']):null;
        $subject=($city?$city['name']:'全国').($district?$district['name']:'').($category?$category['name']:'维修').(['service'=>'服务信息','request'=>'求修需求'][$filters['type']??'']??'服务与求修信息');
        $seo['heading']=$subject;
        $keyword=($city?$city['name']:'全国').($district?$district['name']:'').($category?$category['name']:'维修');
        $typeSuffix=['service'=>' - 维修服务','request'=>' - 求修需求'][$filters['type']??'']??'';
        $seo['title']=$keyword.'|'.$keyword.'电话'.$typeSuffix.($data['result']['page']>1?' - 第'.$data['result']['page'].'页':'').' - '.$name;
        $seo['description']='浏览'.$subject.($data['result']['page']>1?'第'.$data['result']['page'].'页':'').'，了解服务内容、联系人、服务地址和联系电话，也可免费发布自己的维修服务或求修需求。';
        $crumbs[]=['name'=>'分类信息','url'=>absoluteUrl(publicPath('services'))];
        if($city) $crumbs[]=['name'=>$city['name'],'url'=>absoluteUrl(cityUrl($city))];
        if($category) foreach(array_reverse(categoryAncestors((int)$category['id'])) as $categoryId) { $crumbCategory=seoRecord('xiu_categories',$categoryId); $crumbs[]=['name'=>$crumbCategory['name'],'url'=>absoluteUrl(listUrl(array_merge($filters,['category'=>$categoryId,'district'=>0,'q'=>'','type'=>''])))]; }
        if($district) $crumbs[]=['name'=>$district['name'],'url'=>absoluteUrl(listUrl(array_merge($filters,['q'=>'','type'=>''])))];
        if(!empty($filters['q'])) { $seo['title']='搜索“'.seoDescription($filters['q'],30).'” - '.$name; $seo['description']='在'.$name.'查找与“'.$filters['q'].'”相关的维修服务和求修需求。'; $seo['robots']='noindex, follow'; }
        if(!empty($data['categoryContent'])) {
            $seo['description']=seoDescription($data['categoryContent']['intro']);
            if(!$data['result']['total']) $seo['title']=$data['categoryContent']['subject'].'服务信息与报修指南 - '.$name;
        }
        if(!$data['result']['total'] && empty($data['categoryContent'])) $seo['robots']='noindex, follow';
        $pageType='CollectionPage';
    }
    elseif($template==='cities') { $seo['title']='全国城市选择 - '.$name; $seo['description']='在'.$name.'选择所在城市，按省份浏览全国城市与地区，查找当地维修服务和求修需求。'; $crumbs[]=['name'=>'选择城市','url'=>absoluteUrl(publicPath('cities'))]; $pageType='CollectionPage'; }
    elseif($template==='repair-guide-index') {
        $seo['title']='维修与服务指南 - '.$name;
        $seo['description']='按维修分类阅读问题记录、报修准备、报价核对、完工验收和日常维护指南，查找当地服务前先了解需要确认的事项。';
        $crumbs[]=['name'=>'维修与服务指南','url'=>absoluteUrl('/wei-xiu-zhi-nan/')];$pageType='CollectionPage';
    }
    elseif($template==='repair-guide') {
        $guide=$data['guide'];$seo['title']=$guide['title'].' - '.$name;
        $seo['description']=seoDescription($guide['summary']);$seo['canonical']=absoluteUrl(repairGuideUrl($guide));
        $crumbs[]=['name'=>'维修与服务指南','url'=>absoluteUrl('/wei-xiu-zhi-nan/')];
        $crumbs[]=['name'=>$guide['category_name'],'url'=>absoluteUrl(listUrl(['category'=>$guide['category_id']]))];
        $crumbs[]=['name'=>$guide['title'],'url'=>$seo['canonical']];$pageType='Article';
        $seo['schema'][]=['@context'=>'https://schema.org','@type'=>'Article','headline'=>$guide['title'],'description'=>$seo['description'],'mainEntityOfPage'=>$seo['canonical'],'inLanguage'=>'zh-CN','datePublished'=>date(DATE_ATOM,strtotime($guide['created_at'])),'dateModified'=>date(DATE_ATOM,strtotime($guide['updated_at'])),'author'=>['@type'=>'Organization','name'=>$name.'内容整理'],'publisher'=>['@type'=>'Organization','name'=>$name,'url'=>siteUrl()]];
    }
    elseif($template==='detail') {
        $item=$data['item']; $seo['title']=seoDescription($item['title'],60).' - '.$item['city_name'].$item['category_name'].' - '.$name;
        if(!empty($item['district_name'])) $seo['title']=seoDescription($item['title'],60).' - '.$item['city_name'].$item['district_name'].$item['category_name'].' - '.$name;
        $seo['description']=seoDescription($item['city_name'].' '.$item['category_name'].'：'.$item['title'].'。'.$item['body']);
        $seo['canonical']=absoluteUrl(listingUrl($item));
        $crumbs[]=['name'=>'分类信息','url'=>absoluteUrl(publicPath('services'))]; $crumbs[]=['name'=>$item['city_name'],'url'=>absoluteUrl(listUrl(['city'=>$item['city_id']]))]; $crumbs[]=['name'=>$item['category_name'],'url'=>absoluteUrl(listUrl(['city'=>$item['city_id'],'category'=>$item['category_id']]))]; $crumbs[]=['name'=>$item['title'],'url'=>$seo['canonical']];
        if(!visibleListing($item) || $item['is_demo']) $seo['robots']='noindex, follow';
    }
    elseif($template==='articles') {
        $category=!empty($data['filters']['category'])?seoRecord('xiu_categories',(int)$data['filters']['category']):null;
        $subject=($category?$category['name']:'维修').kindLabel($data['kind']);
        $seo['heading']=$subject;
        $seo['title']=$subject.($data['result']['page']>1?' - 第'.$data['result']['page'].'页':'').' - '.$name;
        $seo['description']=$data['kind']==='encyclopedia'?'浏览'.$subject.'，了解设备原理、维修术语和日常维护基础知识。':'浏览'.$subject.'，了解常见故障、报修准备和日常维护知识。';
        if($data['result']['page']>1) $seo['description']='第'.$data['result']['page'].'页：'.$seo['description'];
        $crumbs[]=['name'=>'维修'.kindLabel($data['kind']),'url'=>absoluteUrl(publicPath($data['kind']))];
        if($category) $crumbs[]=['name'=>$category['name'],'url'=>absoluteUrl(articleCollectionUrl($data['kind'],['category'=>$category['id']]))];
        if(!empty($data['filters']['q'])) { $seo['title']='搜索“'.seoDescription($data['filters']['q'],30).'” - '.kindLabel($data['kind']).' - '.$name; $seo['robots']='noindex, follow'; }
        if(!$data['result']['total']) $seo['robots']='noindex, follow'; $pageType='CollectionPage';
    }
    elseif($template==='article') {
        $article=$data['article']; $seo['title']=seoDescription($article['title'],60).' - '.kindLabel($article['kind']).' - '.$name; $seo['description']=seoDescription($article['title'].'：'.($article['summary']?:articleBodyText($article))); $seo['canonical']=absoluteUrl(articleUrl($article));
        $crumbs[]=['name'=>'维修'.kindLabel($article['kind']),'url'=>absoluteUrl(publicPath($article['kind']))]; $crumbs[]=['name'=>$article['category_name'],'url'=>absoluteUrl(articleCollectionUrl($article['kind'],['category'=>$article['category_id']]))]; $crumbs[]=['name'=>$article['title'],'url'=>$seo['canonical']];
        if(!visibleArticle($article) || $article['is_demo']) $seo['robots']='noindex, follow';
        else {
            $schema=['@context'=>'https://schema.org','@type'=>'Article','headline'=>$article['title'],'description'=>$seo['description'],'mainEntityOfPage'=>$seo['canonical'],'inLanguage'=>'zh-CN','datePublished'=>date(DATE_ATOM,strtotime($article['created_at'])),'dateModified'=>date(DATE_ATOM,strtotime($article['updated_at'])),'author'=>['@type'=>'Organization','name'=>$name],'publisher'=>['@type'=>'Organization','name'=>$name,'url'=>siteUrl()]];
            if($data['images']) $schema['image']=array_map(fn($image)=>absoluteUrl('/media/'.$image['id'].'/'),$data['images']); $seo['schema'][]=$schema;
        }
    }
    elseif($template==='sitemap') { $seo['title']='网站地图 - '.$name; $seo['description']='浏览'.$name.'的城市、维修分类、知识库和百科导航，查找已公开的信息与文章。'; $crumbs[]=['name'=>'网站地图','url'=>absoluteUrl(publicPath('sitemap'))]; }
    else $seo['robots']='noindex, nofollow';
    if($_SERVER['REQUEST_METHOD']==='POST' || http_response_code()>=400) { $seo['robots']='noindex, nofollow'; if(http_response_code()>=400) $seo['canonical']=null; }
    if($seo['canonical'] && $seo['robots']==='index, follow, max-image-preview:large') {
        if($template!=='repair-guide') $seo['schema'][]=['@context'=>'https://schema.org','@type'=>$pageType,'name'=>$seo['title'],'description'=>$seo['description'],'url'=>$seo['canonical'],'inLanguage'=>'zh-CN'];
        if($template==='home') $seo['schema'][]=['@context'=>'https://schema.org','@type'=>'WebSite','name'=>$name,'url'=>absoluteUrl('/'),'inLanguage'=>'zh-CN'];
        if(count($crumbs)>1) $seo['schema'][]=['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>array_map(fn($item,$index)=>['@type'=>'ListItem','position'=>$index+1,'name'=>$item['name'],'item'=>$item['url']],$crumbs,array_keys($crumbs))];
    }
    $seo['crumbs']=$crumbs;
    return $seo;
}
function publicPaginationUrl(int $page): string {
    $context=$GLOBALS['collection_context']??null;
    if(!$context) return qs(array_merge($_GET,['page'=>$page]));
    return $context['kind']==='service'?listUrl($context['filters'],$page):articleCollectionUrl($context['kind'],$context['filters'],$page);
}
function renderBreadcrumbs(array $seo): void {
    if(count($seo['crumbs'])<2) return;
    echo '<nav class="breadcrumbs" aria-label="当前位置">';
    foreach($seo['crumbs'] as $index=>$crumb) { if($index) echo '<span aria-hidden="true">/</span>'; echo $index===count($seo['crumbs'])-1?'<span aria-current="page">'.e($crumb['name']).'</span>':'<a href="'.e($crumb['url']).'">'.e($crumb['name']).'</a>'; }
    echo '</nav>';
}
