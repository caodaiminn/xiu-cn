<?php
function dispatch(): void {
    $method=$_SERVER['REQUEST_METHOD'];
    if(!in_array($method,['GET','HEAD','POST'],true)) { header('Allow: GET, HEAD, POST'); abortPage(405,'不支持此请求方式。'); }
    $requestPath=rtrim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/','/')?:'/';
    if($requestPath==='/api/ai-description') {
        if((int)($_SERVER['CONTENT_LENGTH']??0)>4096) { http_response_code(413); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'message'=>'提交内容过大，请缩短标题。'],JSON_UNESCAPED_UNICODE); return; }
        aiDescriptionController(); return;
    }
    if($method==='POST') {
        if((int)($_SERVER['CONTENT_LENGTH']??0)>32*1024*1024) abortPage(413,'提交内容过大，请减少图片或压缩后重试。');
        checkCsrf();
    }
    $path=rtrim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/','/')?:'/';
    if(defined('ADMIN_ENTRY') || preg_match('#^/dgadmin(?:/([a-z]+))?$#',$path,$adminMatch)) {
        $section=defined('ADMIN_ENTRY')?getValue('section','dashboard'):($adminMatch[1]??'dashboard');
        $GLOBALS['path']='/dgadmin'.($section==='dashboard'?'':'/'.$section);
        header('X-Robots-Tag: noindex, nofollow');
        if(!in_array($section,['dashboard','login','logout','categories','cities','districts','users','listings','articles','settings','verifications','navigation','friendlinks','notifications','ai','document','media'],true)) abortPage(404,'后台页面不存在。');
        if(!defined('ADMIN_ENTRY') && in_array($method,['GET','HEAD'],true)) { header('Location: '.adminUrl($section,$_GET),true,301); return; }
        if($section==='login') { authController('/dgadmin/login'); return; }
        if($section==='logout') {
            if($method!=='POST') abortPage(405,'请使用退出按钮。');
            unset($_SESSION['admin_id']); session_regenerate_id(true); redirect(adminUrl('login'));
        }
        if(in_array($section,['document','media'],true)) {
            requireAdmin(); if(!in_array($method,['GET','HEAD'],true)) abortPage(405,'请使用查看链接。');
            if($section==='document') verificationDocumentController((int)getValue('id')); else mediaController((int)getValue('id'));
            return;
        }
        adminController($section); return;
    }
    if(in_array($path,['/api/city-location','/change-city'],true)) { cityLocationController($path); return; }
    if(in_array($method,['GET','HEAD'],true) && sitemapController($path)) return;
    if(repairGuideController($path)) return;
    $path=resolveSeoRoute($path);
    $GLOBALS['path']=$path;
    if(str_starts_with($path,'/dgadmin') || in_array($path,['/login','/register','/publish','/user','/logout','/user/offline'],true)) header('X-Robots-Tag: noindex, nofollow');
    if(in_array($path,['/login','/register','/publish','/user','/logout','/user/offline'],true)) {
        $names=['/login'=>'login','/register'=>'register','/publish'=>'publish','/user'=>'user','/logout'=>'logout'];
        if(isset($names[$path])) {
            $url=publicPath($names[$path]);
            if($path==='/publish' && getValue('id')!=='') $url.=qs(['id'=>positiveId(getValue('id'))]);
            if($path==='/user' && (int)getValue('page')>1) $url.=qs(['page'=>(int)getValue('page')]);
            enforceCanonical($url);
        }
    }
    if($path==='/html-sitemap') {
        enforceCanonical(publicPath('sitemap'));
        render('sitemap',['title'=>'网站地图 · '.siteName(),'listings'=>sitemapContent('xin-xi',1),'knowledge'=>sitemapContent('zhi-shi',1),'encyclopedia'=>sitemapContent('bai-ke',1)]); return;
    }
    if($path==='/') {
        if($method==='POST') abortPage(405,'请通过发布表单提交信息。');
        enforceCanonical('/');
        initializeHomeLocation(); $homeCity=selectedLocationCity(); $homeCityId=(int)($homeCity['id']??0);
        $groups=array_values(array_filter(categories(),fn($c)=>$c['depth']===1));
        $sections=[]; foreach(array_slice($groups,0,6) as $category) $sections[]=['category'=>$category,'result'=>listingPreview(['category'=>$category['id'],'city'=>$homeCityId],5)];
        render('home',['title'=>siteName().' · 维修服务与求修信息','homeCity'=>$homeCity,'homeCityId'=>$homeCityId,'groups'=>$groups,'sections'=>$sections,'latest'=>listingPreview(['city'=>$homeCityId],6),'knowledge'=>articleSearch('knowledge',[],1),'encyclopedia'=>articleSearch('encyclopedia',[],1)]); return;
    }
    if($path==='/services' || preg_match('#^/city/([a-z0-9-]+)$#',$path,$match)) {
        $routeFilters=$GLOBALS['route_filters']??[];
        if($path!=='/services') { $legacyCity=row('SELECT * FROM xiu_cities WHERE slug=? AND enabled=1',[$match[1]]); if(!$legacyCity) abortPage(404,'这个城市暂未开通。'); $routeFilters['city']=(int)$legacyCity['id']; }
        $filters=publicFilters($routeFilters); $requestedPage=$filters['page']; unset($filters['page']);
        $city=null;
        if($filters['district']) { $district=seoRecord('xiu_districts',$filters['district']); if(!$district || !$district['enabled'] || ($filters['city'] && $filters['city']!==(int)$district['city_id'])) abortPage(404,'区域不存在或不属于该城市。'); $filters['city']=(int)$district['city_id']; }
        if($filters['city']) { $city=seoRecord('xiu_cities',$filters['city']); if(!$city || !$city['enabled']) abortPage(404,'这个城市暂未开通。'); }
        if($filters['category'] && !in_array($filters['category'],categoryIds('service'),true)) abortPage(404,'这个分类暂未开通。');
        $result=listingSearch($filters,$requestedPage); if($requestedPage>$result['pages']) abortPage(404,'这一页没有信息。');
        enforceCanonical(listUrl($filters,$result['page'])); $GLOBALS['collection_context']=['kind'=>'service','filters'=>$filters];
        if($city && (int)(rememberedCityChoice()['id']??-1)!==(int)$city['id']) rememberLocationCity((int)$city['id']);
        render('listings',['title'=>($city?$city['name'].'维修服务':'分类信息').' · '.siteName(),'filters'=>$filters,'city'=>$city,'result'=>$result,'navigation'=>serviceNavigation($filters['category'])]); return;
    }
    if($path==='/cities') { enforceCanonical(publicPath('cities')); render('cities',['title'=>'城市服务 · '.siteName()]); return; }
    if(preg_match('#^/info/(\d+)$#',$path,$match)) {
        $item=row("SELECT l.*,k.name category_name,c.name city_name,c.slug city_slug,u.username,d.name district_name,v.type verification_type FROM xiu_listings l JOIN xiu_categories k ON k.id=l.category_id JOIN xiu_cities c ON c.id=l.city_id JOIN xiu_users u ON u.id=l.user_id LEFT JOIN xiu_districts d ON d.id=l.district_id LEFT JOIN xiu_user_verifications v ON v.user_id=l.user_id AND v.status='approved' WHERE l.id=?",[$match[1]]);
        if(!$item || (!visibleListing($item) && !currentAdmin() && (int)(currentUser()['id']??0)!==(int)$item['user_id'])) abortPage(404,'信息不存在或暂未发布。');
        enforceCanonical(listingUrl($item));
        render('detail',['title'=>$item['title'].' · '.siteName(),'item'=>$item,'images'=>rows('SELECT * FROM xiu_images WHERE listing_id=? ORDER BY id',[$item['id']]),'related'=>listingSearch(['category'=>$item['category_id'],'city'=>$item['city_id']],1,5)]); return;
    }
    if($path==='/login' || $path==='/register') { authController($path); return; }
    if($path==='/logout') {
        if($method!=='POST') abortPage(405,'请使用退出按钮。');
        unset($_SESSION['user_id']); session_regenerate_id(true); redirect('/');
    }
    if($path==='/publish') { publishController(); return; }
    if($path==='/yong-hu-zhong-xin/shua-xin') { memberRefreshController(); return; }
    if(in_array($path,['/user','/user/listings','/user/verification','/user/phone'],true)) { memberController(['/user'=>'dashboard','/user/listings'=>'listings','/user/verification'=>'verification','/user/phone'=>'phone'][$path]); return; }
    if(preg_match('#^/yong-hu-zhong-xin/zheng-jian/(\d+)$#',$path,$match)) { verificationDocumentController((int)$match[1]); return; }
    if($path==='/user/offline') {
        $user=requireUser(); if($method!=='POST') abortPage(405,'请使用下架按钮。');
        query("UPDATE xiu_listings SET status='offline',updated_at=NOW() WHERE id=? AND user_id=? AND status IN ('pending','published')",[(int)input('id'),$user['id']]); clearAdminListingCountCache(); flash('信息已下架。'); redirect(publicPath('user'));
    }
    if($path==='/baixiuquan' || $path==='/baike') {
        $kind=$path==='/baike'?'encyclopedia':'knowledge'; $parameters=publicFilters($GLOBALS['route_filters']??[]); $filters=['q'=>$parameters['q'],'category'=>$parameters['category']];
        if($filters['category'] && !in_array($filters['category'],categoryIds($kind),true)) abortPage(404,'这个文章分类暂未开通。');
        $result=articleSearch($kind,$filters,$parameters['page']); if($parameters['page']>$result['pages']) abortPage(404,'这一页没有文章。');
        enforceCanonical(articleCollectionUrl($kind,$filters,$result['page'])); $GLOBALS['collection_context']=['kind'=>$kind,'filters'=>$filters];
        render('articles',['title'=>kindLabel($kind).' · '.siteName(),'kind'=>$kind,'filters'=>$filters,'result'=>$result]); return;
    }
    if(preg_match('#^/(baixiuquan|baike)/(\d+)\.html$#',$path,$match)) {
        $kind=$match[1]==='baike'?'encyclopedia':'knowledge';
        $article=row('SELECT a.*,k.name category_name FROM xiu_articles a JOIN xiu_categories k ON k.id=a.category_id WHERE a.id=? AND a.kind=?',[$match[2],$kind]);
        if(!$article || (!visibleArticle($article) && !currentAdmin())) abortPage(404,'文章不存在或暂未发布。');
        enforceCanonical(articleUrl($article));
        render('article',['title'=>$article['title'].' · '.siteName(),'article'=>$article,'images'=>rows('SELECT * FROM xiu_images WHERE article_id=?',[$article['id']]),'related'=>articleSearch($kind,['category'=>$article['category_id']],1)]); return;
    }
    if(preg_match('#^/media/(\d+)$#',$path,$match)) { mediaController((int)$match[1]); return; }
    abortPage(404,'页面不存在，请返回首页继续浏览。');
}
function authController(string $path): void {
    $admin=$path==='/dgadmin/login'; $register=$path==='/register'; $error='';
    if($_SERVER['REQUEST_METHOD']==='POST') {
        try {
            loginAttempt($admin?'admin':($register?'register':'user'));
            $username=input('username'); $password=(string)field('password');
            if($register) {
                validateMobileNumber($username);
            } elseif(!preg_match('/^[a-zA-Z0-9_]{3,32}$/',$username)) throw new DomainException('账号须为 3–32 位英文字母、数字或下划线。');
            if($register) {
                if(strlen($password)<8 || strlen($password)>72) throw new DomainException('密码须为 8–72 个字节。');
                if(!hash_equals($password,(string)field('confirm_password'))) throw new DomainException('两次输入的密码不一致。');
                $phone=$username;
                if(scalar('SELECT id FROM xiu_users WHERE username=?',[$username])) throw new DomainException('这个手机号码已注册，请直接登录。');
                query('INSERT INTO xiu_users (username,password_hash,phone) VALUES (?,?,?)',[$username,password_hash($password,PASSWORD_DEFAULT),$phone]);
                session_regenerate_id(true); $_SESSION['user_id']=(int)db()->lastInsertId(); flash('注册成功，可以发布信息了。'); redirect(publicPath('user'));
            }
            $account=row('SELECT * FROM '.($admin?'xiu_admins':'xiu_users').' WHERE username=?',[$username]);
            if(!$account || !$password || !password_verify($password,$account['password_hash']) || (!$admin && !$account['enabled'])) throw new DomainException('账号或密码不正确，或账号已停用。');
            session_regenerate_id(true); $_SESSION[$admin?'admin_id':'user_id']=(int)$account['id']; redirect($admin?adminUrl():publicPath('user'));
        } catch(DomainException $exception) { $error=$exception->getMessage(); }
        catch(PDOException $exception) { if($exception->getCode()==='23000') $error=$register?'这个手机号码已注册，请直接登录。':'这个账号已被使用，请换一个。'; else throw $exception; }
    }
    render('auth',['title'=>($admin?'后台登录':($register?'注册账号':'登录')).' · '.siteName(),'admin'=>$admin,'register'=>$register,'error'=>$error]);
}
function publishController(): void {
    $user=requireUser(); $id=(int)($_SERVER['REQUEST_METHOD']==='POST'?input('id'):getValue('id')); $item=null; $error='';
    if($id) { $item=row('SELECT * FROM xiu_listings WHERE id=? AND user_id=?',[$id,$user['id']]); if(!$item) abortPage(404,'找不到你要编辑的信息。'); }
    $images=$item?rows('SELECT * FROM xiu_images WHERE listing_id=?',[$id]):[];
    if($_SERVER['REQUEST_METHOD']==='POST') {
        $newImages=[];
        try {
            $title=input('title'); $body=input('body'); $address=input('address'); $contact=input('contact'); $phone=input('phone'); $type=input('type'); $category=(int)input('category_id'); $city=(int)input('city_id');
            $district=(int)input('district_id');
            validateText($title,'标题',5,120); validateText($body,'详细描述',10,20000); validateText($address,'服务地址',2,255); validateText($contact,'联系人',1,64);
            validateContactPhone($phone);
            if(!in_array($type,['service','request'],true)) throw new DomainException('请选择信息类型。');
            $selectedCity=row('SELECT id,province FROM xiu_cities WHERE id=? AND enabled=1',[$city]);
            if(!in_array($category,categoryIds('service'),true) || !$selectedCity) throw new DomainException('请选择已开通的分类和城市。');
            if(input('province')!=='' && input('province')!==$selectedCity['province']) throw new DomainException('请选择所选省份下的城市。');
            if($district && !scalar('SELECT id FROM xiu_districts WHERE id=? AND city_id=? AND enabled=1',[$district,$city])) throw new DomainException('请选择所选城市下已开通的区域。');
            $remove=is_array($_POST['remove_images']??null)?array_map('intval',$_POST['remove_images']):[];
            $deleted=array_values(array_filter($images,fn($image)=>in_array((int)$image['id'],$remove,true)));
            $newImages=uploadedImages();
            if(count($images)-count($deleted)+count($newImages)>5) throw new DomainException('每条信息最多保留 5 张图片。');
            db()->beginTransaction();
            if($item) query("UPDATE xiu_listings SET category_id=?,city_id=?,district_id=?,type=?,title=?,address=?,contact=?,phone=?,body=?,status='pending',rejection_reason='',updated_at=NOW() WHERE id=? AND user_id=?",[$category,$city,$district?:null,$type,$title,$address,$contact,$phone,$body,$id,$user['id']]);
            else { query('INSERT INTO xiu_listings (user_id,category_id,city_id,district_id,type,title,address,contact,phone,body) VALUES (?,?,?,?,?,?,?,?,?,?)',[$user['id'],$category,$city,$district?:null,$type,$title,$address,$contact,$phone,$body]); $id=(int)db()->lastInsertId(); }
            ensureSeoSlug('xiu_listings',$id);
            foreach($deleted as $image) query('DELETE FROM xiu_images WHERE id=? AND listing_id=?',[$image['id'],$id]);
            saveImages($newImages,'listing',$id); $mailJob=queueListingReviewNotification($id); db()->commit(); clearAdminListingCountCache(); removeImages($deleted);
            if($mailJob) startMailWorker();
            flash('信息已提交，审核通过后展示。'); redirect(publicPath('user'));
        } catch(Throwable $exception) {
            if(db()->inTransaction()) db()->rollBack(); removeImages($newImages);
            if($exception instanceof DomainException) $error=$exception->getMessage(); else throw $exception;
        }
    }
    render('publish',['title'=>($item?'编辑信息':'免费发布信息').' · '.siteName(),'item'=>$item,'images'=>$images,'error'=>$error]);
}
function mediaController(int $id): void {
    $image=row('SELECT * FROM xiu_images WHERE id=?',[$id]); if(!$image) abortPage(404,'图片不存在。');
    $allowed=(bool)currentAdmin();
    if($image['listing_id']) { $listing=row('SELECT * FROM xiu_listings WHERE id=?',[$image['listing_id']]); $allowed=$allowed || ($listing && (visibleListing($listing) || (int)(currentUser()['id']??0)===(int)$listing['user_id'])); }
    elseif($image['article_id']) { $article=row('SELECT * FROM xiu_articles WHERE id=?',[$image['article_id']]); $allowed=$allowed || ($article && visibleArticle($article)); }
    $path=ROOT.'/storage/files/'.$image['filename'];
    if(!$allowed || !is_file($path)) abortPage(404,'图片不存在或暂不可查看。');
    header('Content-Type: '.$image['mime']); header('Content-Length: '.filesize($path)); header('Content-Disposition: inline');
    readfile($path);
}
function adminController(string $section): void {
    requireAdmin();
    if($section==='ai') { adminAiController(); return; }
    if($section==='navigation') { adminNavigationController(); return; }
    if($section==='friendlinks') { adminFriendLinksController(); return; }
    if($section==='notifications') { adminMailController(); return; }
    if(!in_array($section,['dashboard','categories','cities','districts','users','listings','articles','settings','verifications'],true)) abortPage(404,'后台页面不存在。');
    $error='';
    if($_SERVER['REQUEST_METHOD']==='POST') {
        try {
            $categoryBefore=$section==='categories'?row('SELECT kind FROM xiu_categories WHERE id=?',[(int)input('id')]):null;
            $savedId=adminMutation($section); flash($section==='verifications' && input('action')==='delete'?'认证已删除，账号和发布信息已保留，恢复备份已保存。':'操作已保存。');
            $suffix=$section==='articles'?qs(['kind'=>input('kind','knowledge')]):'';
            if($section==='categories') $suffix=qs(['kind'=>input('kind',$categoryBefore['kind']??'service'),'category'=>$savedId]);
            if($section==='listings') $suffix=qs(['status'=>in_array(getValue('status'),['pending','published','rejected','offline'],true)?getValue('status'):'','page'=>max(1,(int)getValue('page',1)),'per_page'=>adminListingPerPage(getValue('per_page',20))]);
            if($section==='verifications') $suffix=qs(['status'=>in_array(getValue('status'),['pending','approved','rejected'],true)?getValue('status'):'','page'=>max(1,(int)getValue('page',1))>1?(int)getValue('page'):null]);
            $params=[]; parse_str(ltrim($suffix,'?'),$params);
            redirect(adminUrl($section,$params));
        }
        catch(DomainException $exception) { $error=$exception->getMessage(); }
        catch(PDOException $exception) { if($exception->getCode()==='23000') $error='标识重复或此条目仍被引用，请修改后重试。'; else throw $exception; }
    }
    $id=(int)getValue('edit'); $record=null; $items=[]; $kind=getValue('kind','knowledge'); if(!in_array($kind,['knowledge','encyclopedia'],true)) $kind='knowledge';
    $tables=['categories'=>'xiu_categories','cities'=>'xiu_cities','districts'=>'xiu_districts','articles'=>'xiu_articles'];
    if(isset($tables[$section]) && $id) { $record=row('SELECT * FROM '.$tables[$section].' WHERE id=?',[$id]); if(!$record) abortPage(404,'记录不存在。'); if($section==='articles') $kind=$record['kind']; }
    if($section==='categories') $items=rows('SELECT k.*,p.name parent_name FROM xiu_categories k LEFT JOIN xiu_categories p ON p.id=k.parent_id ORDER BY k.kind,k.sort_order,k.id');
    if($section==='cities') $items=cityList(false);
    if($section==='districts') $items=districtList((int)getValue('city'),false);
    $page=max(1,(int)getValue('page',1)); $total=0; $pages=1;
    if($section==='verifications') {
        $reviewStatus=getValue('status'); $where=''; $params=[];
        if(in_array($reviewStatus,['pending','approved','rejected'],true)) { $where=' WHERE v.status=?'; $params[]=$reviewStatus; }
        $total=(int)scalar('SELECT COUNT(*) FROM xiu_user_verifications v'.$where,$params); $pages=max(1,(int)ceil($total/20)); $page=min($page,$pages);
        $items=rows('SELECT v.*,u.username,u.enabled user_enabled FROM xiu_user_verifications v JOIN xiu_users u ON u.id=v.user_id'.$where.' ORDER BY v.id DESC LIMIT 20 OFFSET '.(($page-1)*20),$params);
    }
    if($section==='users') { $total=(int)scalar('SELECT COUNT(*) FROM xiu_users'); $pages=max(1,(int)ceil($total/30)); $page=min($page,$pages); $items=rows('SELECT u.*,(SELECT COUNT(*) FROM xiu_listings WHERE user_id=u.id) listing_count FROM xiu_users u ORDER BY id DESC LIMIT 30 OFFSET '.(($page-1)*30)); }
    if($section==='listings') {
        $listingResult=adminListingResults(getValue('status'),$page,adminListingPerPage(getValue('per_page',20)));
        $items=$listingResult['items']; $total=$listingResult['total']; $page=$listingResult['page']; $pages=$listingResult['pages'];
    }
    if($section==='articles') { $total=(int)scalar('SELECT COUNT(*) FROM xiu_articles WHERE kind=?',[$kind]); $pages=max(1,(int)ceil($total/30)); $page=min($page,$pages); $items=rows('SELECT a.*,k.name category_name FROM xiu_articles a JOIN xiu_categories k ON k.id=a.category_id WHERE a.kind=? ORDER BY a.id DESC LIMIT 30 OFFSET '.(($page-1)*30),[$kind]); }
    $stats=[];
    if($section==='dashboard') $stats=['pending'=>(int)scalar("SELECT COUNT(*) FROM xiu_listings WHERE status='pending'"),'listings'=>(int)scalar('SELECT COUNT(*) FROM xiu_listings'),'users'=>(int)scalar('SELECT COUNT(*) FROM xiu_users'),'articles'=>(int)scalar('SELECT COUNT(*) FROM xiu_articles')];
    $crawlerStats=$section==='dashboard'?crawlerDashboard(getValue('period','today'),getValue('bot'),(int)getValue('crawler_page',1)):null;
    render('admin',['title'=>'管理后台 · '.siteName(),'adminPage'=>true,'section'=>$section,'items'=>$items,'record'=>$record,'kind'=>$kind,'error'=>$error,'stats'=>$stats,'crawlerStats'=>$crawlerStats,'result'=>$listingResult??compact('items','total','page','pages')]);
}
function adminMutation(string $section) {
    $id=(int)input('id'); $action=input('action','save');
    if($section==='verifications') { reviewVerification(); return; }
    if($section==='settings') {
        $name=input('site_name'); validateText($name,'站点名称',1,24); $url=rtrim(input('site_url',siteUrl()),'/'); $parts=parse_url($url);
        if(!filter_var($url,FILTER_VALIDATE_URL) || !$parts || !in_array($parts['scheme']??'',['http','https'],true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !empty($parts['path'])) throw new DomainException('网站地址必须是完整的 http:// 或 https:// 域名地址，不含路径、参数和账号密码。');
        query("INSERT INTO xiu_settings (setting_key,setting_value) VALUES ('site_name',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",[$name]);
        query("INSERT INTO xiu_settings (setting_key,setting_value) VALUES ('site_url',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",[$url]); return;
    }
    if($section==='users') {
        if($action!=='toggle' || !row('SELECT id FROM xiu_users WHERE id=?',[$id])) throw new DomainException('用户不存在。');
        query('UPDATE xiu_users SET enabled=1-enabled WHERE id=?',[$id]); return;
    }
    if($section==='listings') {
        if($action==='approve') { $updated=query("UPDATE xiu_listings SET status='published',rejection_reason='',updated_at=NOW() WHERE id=? AND status='pending'",[$id])->rowCount(); }
        elseif($action==='reject') { $reason=input('reason'); validateText($reason,'驳回原因',2,500); $updated=query("UPDATE xiu_listings SET status='rejected',rejection_reason=?,updated_at=NOW() WHERE id=? AND status='pending'",[$reason,$id])->rowCount(); }
        elseif($action==='offline') $updated=query("UPDATE xiu_listings SET status='offline',updated_at=NOW() WHERE id=? AND status='published'",[$id])->rowCount();
        else throw new DomainException('无效的审核操作。');
        if(!$updated) throw new DomainException('信息状态已改变，请刷新后再操作。'); clearAdminListingCountCache(); return;
    }
    if($section==='categories') {
        if($action==='delete') { $deleted=row('SELECT parent_id FROM xiu_categories WHERE id=?',[$id]); if(!$deleted) throw new DomainException('请选择分类。'); query('DELETE FROM xiu_categories WHERE id=?',[$id]); return (int)$deleted['parent_id']; }
        $name=input('name'); $slug=input('slug'); $kind=input('kind'); $parent=(int)input('parent_id');
        validateText($name,'分类名称',1,64);
        if(!in_array($kind,['service','knowledge','encyclopedia'],true)) throw new DomainException('请选择分类用途。');
        $old=$id?row('SELECT * FROM xiu_categories WHERE id=?',[$id]):null;
        if($id && !$old) throw new DomainException('分类不存在。');
        if($slug==='') {
            $slug=$old['slug']??rtrim(substr(pinyinSlug($name),0,70),'-');
            if(!preg_match('/^[a-z]/',$slug)) $slug='category-'.$slug;
            $slug=substr($slug,0,70); $base=$slug;
            while(scalar('SELECT id FROM xiu_categories WHERE slug=? AND id<>?',[$slug,$id])) $slug=$base.'-'.bin2hex(random_bytes(4));
        }
        validateSlug($slug);
        if($old && $old['kind']!==$kind && (scalar('SELECT COUNT(*) FROM xiu_categories WHERE parent_id=?',[$id]) || scalar('SELECT COUNT(*) FROM xiu_listings WHERE category_id=?',[$id]) || scalar('SELECT COUNT(*) FROM xiu_articles WHERE category_id=?',[$id]))) throw new DomainException('已被引用的分类不能修改用途。');
        $depth=1; $ancestor=$parent; $visited=[$id];
        while($ancestor) { if(in_array($ancestor,$visited,true)) throw new DomainException('不能把分类移到自身或其子分类下。'); $visited[]=$ancestor; $parentRow=row('SELECT * FROM xiu_categories WHERE id=? AND kind=?',[$ancestor,$kind]); if(!$parentRow) throw new DomainException('父分类用途必须一致。'); $depth++; if($depth>3) throw new DomainException('分类最多支持三级。'); $ancestor=(int)$parentRow['parent_id']; }
        $height=function($categoryId) use (&$height) { $max=1; foreach(rows('SELECT id FROM xiu_categories WHERE parent_id=?',[$categoryId]) as $child) $max=max($max,1+$height($child['id'])); return $max; };
        if($id && $depth+$height($id)-1>3) throw new DomainException('此移动会使子分类超过三级。');
        $params=[$parent?:null,$kind,$name,$slug,(int)input('sort_order'),input('enabled')==='1'?1:0,input('show_in_filter',(string)($old['show_in_filter']??1))==='1'?1:0];
        if($id) { $params[]=$id; query('UPDATE xiu_categories SET parent_id=?,kind=?,name=?,slug=?,sort_order=?,enabled=?,show_in_filter=? WHERE id=?',$params); }
        else { query('INSERT INTO xiu_categories (parent_id,kind,name,slug,sort_order,enabled,show_in_filter) VALUES (?,?,?,?,?,?,?)',$params); $id=(int)db()->lastInsertId(); }
        ensureSeoSlug('xiu_categories',$id); return $id;
    }
    if($section==='cities') {
        if($action==='delete') { if(!$id) throw new DomainException('请选择城市。'); query('DELETE FROM xiu_cities WHERE id=?',[$id]); return; }
        $province=input('province'); $name=input('name'); $slug=input('slug'); validateText($province,'省份',1,32); validateText($name,'城市名称',1,32); validateSlug($slug);
        $params=[$province,$name,$slug,input('is_hot')==='1'?1:0,(int)input('sort_order'),input('enabled')==='1'?1:0];
        if($id) { if(!row('SELECT id FROM xiu_cities WHERE id=?',[$id])) throw new DomainException('城市不存在。'); $params[]=$id; query('UPDATE xiu_cities SET province=?,name=?,slug=?,is_hot=?,sort_order=?,enabled=? WHERE id=?',$params); }
        else { query('INSERT INTO xiu_cities (province,name,slug,is_hot,sort_order,enabled) VALUES (?,?,?,?,?,?)',$params); $id=(int)db()->lastInsertId(); }
        ensureSeoSlug('xiu_cities',$id); return;
    }
    if($section==='articles') { saveArticle($id,$action); return; }
    if($section==='districts') {
        $old=$id?row('SELECT * FROM xiu_districts WHERE id=?',[$id]):null;
        if($id && !$old) throw new DomainException('区域不存在。');
        if($action==='delete') { if(!$old) throw new DomainException('请选择区域。'); query('DELETE FROM xiu_districts WHERE id=?',[$id]); return; }
        $city=(int)input('city_id'); $name=input('name'); validateText($name,'区域名称',1,32);
        if(!scalar('SELECT id FROM xiu_cities WHERE id=?',[$city])) throw new DomainException('请选择区域所属城市。');
        if($old && $city!==(int)$old['city_id']) throw new DomainException('已创建区域的所属城市不能更改，请在目标城市新增区域。');
        if(scalar('SELECT id FROM xiu_districts WHERE city_id=? AND name=? AND id<>?',[$city,$name,$id])) throw new DomainException('该城市已有同名区域。');
        if($old) query('UPDATE xiu_districts SET name=?,sort_order=?,enabled=? WHERE id=?',[$name,(int)input('sort_order'),input('enabled')==='1'?1:0,$id]);
        else {
            $slug=pinyinSlug($name); if(strlen($slug)>64) $slug=substr($slug,0,64);
            if(scalar('SELECT id FROM xiu_districts WHERE city_id=? AND seo_slug=?',[$city,$slug])) $slug.='-'.bin2hex(random_bytes(3));
            query('INSERT INTO xiu_districts (city_id,name,seo_slug,sort_order,enabled) VALUES (?,?,?,?,?)',[$city,$name,$slug,(int)input('sort_order'),input('enabled')==='1'?1:0]);
        }
        return;
    }
    throw new DomainException('无效的操作。');
}
function validateSlug(string $slug): void { if(!preg_match('/^[a-z][a-z0-9-]{0,79}$/',$slug)) throw new DomainException('英文标识须以字母开头，只能包含小写字母、数字和短横线。'); }
function saveArticle(int $id,string $action): void {
    $old=$id?row('SELECT * FROM xiu_articles WHERE id=?',[$id]):null;
    if($id && !$old) throw new DomainException('文章不存在。');
    if($action==='delete') {
        if(!$old) throw new DomainException('请选择文章。');
        $images=rows('SELECT * FROM xiu_images WHERE article_id=?',[$id]); query('DELETE FROM xiu_articles WHERE id=?',[$id]); removeImages($images); return;
    }
    $kind=input('kind'); $title=input('title'); $summary=input('summary'); $body=input('body'); $status=input('status'); $category=(int)input('category_id');
    if(!in_array($kind,['knowledge','encyclopedia'],true) || !in_array($status,['draft','published'],true)) throw new DomainException('请选择文章类型和状态。');
    validateText($title,'标题',2,120); validateText($summary,'摘要',0,500);
    $bodyFormat=input('body_format','text');
    if(!in_array($bodyFormat,['text','html'],true)) throw new DomainException('正文格式无效。');
    if(mb_strlen($body)>400000) throw new DomainException('正文排版过多，请精简后保存。');
    if($bodyFormat==='html') $body=sanitizeArticleHtml($body);
    validateText(articleBodyText(['body'=>$body,'body_format'=>$bodyFormat]),'正文',10,100000);
    if(!scalar('SELECT id FROM xiu_categories WHERE id=? AND kind=?',[$category,$kind])) throw new DomainException('请选择对应类型的分类。');
    $newImages=[];
    try {
        $newImages=uploadedImages(); if(count($newImages)>1) throw new DomainException('文章最多上传一张封面。');
        $deleted=($newImages || input('remove_cover')==='1') && $old?rows('SELECT * FROM xiu_images WHERE article_id=?',[$id]):[];
        db()->beginTransaction();
        if($old) query('UPDATE xiu_articles SET category_id=?,kind=?,title=?,summary=?,body=?,body_format=?,status=?,updated_at=NOW() WHERE id=?',[$category,$kind,$title,$summary,$body,$bodyFormat,$status,$id]);
        else { query('INSERT INTO xiu_articles (category_id,kind,title,summary,body,body_format,status) VALUES (?,?,?,?,?,?,?)',[$category,$kind,$title,$summary,$body,$bodyFormat,$status]); $id=(int)db()->lastInsertId(); }
        ensureSeoSlug('xiu_articles',$id);
        foreach($deleted as $image) query('DELETE FROM xiu_images WHERE id=? AND article_id=?',[$image['id'],$id]);
        saveImages($newImages,'article',$id); db()->commit(); removeImages($deleted);
    } catch(Throwable $exception) { if(db()->inTransaction()) db()->rollBack(); removeImages($newImages); throw $exception; }
}
