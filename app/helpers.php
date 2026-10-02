<?php
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function field(string $key, $default='') { $value=$_POST[$key]??$default; return is_scalar($value)?$value:$default; }
function input(string $key, $default=''): string { return trim((string)field($key,$default)); }
function getValue(string $key, $default=''): string { $value=$_GET[$key]??$default; return is_scalar($value)?trim((string)$value):(string)$default; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrfField(): string { return '<input type="hidden" name="csrf" value="'.e(csrf()).'">'; }
function checkCsrf(): void { if (!hash_equals(csrf(),input('csrf'))) abortPage(403,'操作已过期，请刷新页面后重试。'); }
function redirect(string $url): void { header('Location: '.$url, true,303); exit; }
function flash(string $message,string $type='success'): void { $_SESSION['flash']=['message'=>$message,'type'=>$type]; }
function currentUser(): ?array { static $loaded=false,$user=null; if (!$loaded) { $loaded=true; $user=!empty($_SESSION['user_id'])?row('SELECT id,username,phone,enabled FROM xiu_users WHERE id=? AND enabled=1',[$_SESSION['user_id']]):null; } return $user; }
function currentAdmin(): ?array { static $loaded=false,$admin=null; if (!$loaded) { $loaded=true; $admin=!empty($_SESSION['admin_id'])?row('SELECT id,username FROM xiu_admins WHERE id=?',[$_SESSION['admin_id']]):null; } return $admin; }
function requireUser(): array { $user=currentUser(); if (!$user) { flash('请先登录后发布或管理信息。','notice'); redirect(publicPath('login')); } return $user; }
function adminUrl(string $section='dashboard',array $params=[]): string {
    unset($params['section']);
    $params=($section==='dashboard'?[]:['section'=>$section])+$params;
    return '/dgadmin/index.php'.($params?qs($params):'');
}
function requireAdmin(): array { $admin=currentAdmin(); if (!$admin) redirect(adminUrl('login')); return $admin; }
function siteName(): string { static $name; return $name ??= (string)(scalar("SELECT setting_value FROM xiu_settings WHERE setting_key='site_name'")?:'修网'); }
function render(string $template,array $data=[]): void {
    extract($data); $title=$data['title']??siteName(); $adminPage=$data['adminPage']??false;
    $seo=pageSeo($template,$data); $title=$seo['title'];
    header('X-Robots-Tag: '.$seo['robots']);
    $seoJson=$seo['schema']?json_encode($seo['schema'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT):'';
    header('Content-Security-Policy: '.pageContentSecurityPolicy($seoJson));
    $cacheStats=$GLOBALS['public_data_cache_stats']??[];
    header('X-Xiu-Data-Cache: hit='.($cacheStats['hit']??0).'; miss='.($cacheStats['miss']??0).'; bypass='.($cacheStats['bypass']??0));
    $notice=$_SESSION['flash']??null; unset($_SESSION['flash']);
    require ROOT.'/views/layout.php';
}
function abortPage(int $status,string $message): void { http_response_code($status); render('error',['title'=>$status.' · '.siteName(),'message'=>$message]); exit; }
function validateText(string $value,string $label,int $min,int $max): void { $length=mb_strlen($value); if ($length<$min || $length>$max) throw new DomainException($label.'须为 '.$min.'–'.$max.' 个字符。'); }
function validateContactPhone(string $phone): void {
    if(!preg_match('/^[0-9+()\- ]{6,32}$/',$phone) || strlen(preg_replace('/[^0-9]/','',$phone))<6) throw new DomainException('请填写有效的联系电话，允许数字、空格、加号、括号和短横线。');
}
function validateMobileNumber(string $phone): void {
    if(!preg_match('/^1[3-9][0-9]{9}$/',$phone)) throw new DomainException('请输入有效的11位手机号码。');
}
function statusLabel(string $status): string { return ['pending'=>'待审核','published'=>'已发布','rejected'=>'已驳回','offline'=>'已下架','draft'=>'草稿'][$status]??$status; }
function kindLabel(string $kind): string { return ['service'=>'维修分类','knowledge'=>'知识库','encyclopedia'=>'百科'][$kind]??'维修分类'; }
function listingType(string $type): string { return $type==='request'?'求修需求':'维修服务'; }
function relativeListingTime(string $date, ?int $now=null): string {
    $timestamp=strtotime($date); if($timestamp===false) return '时间待更新';
    $elapsed=max(0,($now??time())-$timestamp);
    if($elapsed<60) return '刚刚';
    foreach([[31536000,'年'],[2592000,'个月'],[86400,'天'],[3600,'小时'],[60,'分钟']] as [$seconds,$unit]) {
        if($elapsed>=$seconds) return (int)floor($elapsed/$seconds).$unit.'前';
    }
    return '刚刚';
}
function articleUrl(array $article): string { return publicPath($article['kind']).detailSlug('xiu_articles',$article).'.html'; }
function qs(array $values): string { return '?'.http_build_query(array_filter($values,fn($v)=>$v!=='' && $v!==null)); }
function pagination(array $result): void {
    if ($result['pages']<=1) return;
    echo '<nav class="pagination" aria-label="分页">';
    $start=max(1,$result['page']-2); $end=min($result['pages'],$result['page']+2);
    if ($result['page']>1) echo '<a href="'.e(publicPaginationUrl($result['page']-1)).'">上一页</a>';
    for($i=$start;$i<=$end;$i++) echo '<a '.($i===$result['page']?'aria-current="page" class="selected"':'').' href="'.e(publicPaginationUrl($i)).'">'.$i.'</a>';
    if ($result['page']<$result['pages']) echo '<a href="'.e(publicPaginationUrl($result['page']+1)).'">下一页</a>';
    echo '</nav>';
}
function loginAttempt(string $role): void {
    $file=ROOT.'/storage/limits/'.hash('sha256',$role.'|'.($_SERVER['REMOTE_ADDR']??'local')).'.json';
    $handle=fopen($file,'c+'); flock($handle,LOCK_EX); $attempts=json_decode(stream_get_contents($handle),true)?:[];
    $attempts=array_values(array_filter($attempts,fn($time)=>$time>time()-600));
    if(count($attempts)>=20) { flock($handle,LOCK_UN); fclose($handle); throw new DomainException('尝试次数过多，请 10 分钟后再试。'); }
    $attempts[]=time(); rewind($handle); ftruncate($handle,0); fwrite($handle,json_encode($attempts)); flock($handle,LOCK_UN); fclose($handle);
}
function uploadedImages(string $field='images',int $limit=5,string $directory='files'): array {
    if(!in_array($directory,['files','verification'],true)) throw new LogicException('Invalid image directory');
    $uploads=$_FILES[$field]??null; if (!$uploads) return [];
    if (!is_array($uploads['error'])) throw new DomainException('图片提交格式不正确。');
    $result=[];
    try {
        if (count($uploads['error'])>$limit) throw new DomainException('每次最多上传 '.$limit.' 张图片。');
        foreach ($uploads['error'] as $i=>$error) {
            if ($error===UPLOAD_ERR_NO_FILE) continue;
            if ($error!==UPLOAD_ERR_OK) throw new DomainException('图片上传失败，请检查图片大小并重试。');
            $tmp=$uploads['tmp_name'][$i];
            if (!is_uploaded_file($tmp) || $uploads['size'][$i]>5*1024*1024) throw new DomainException('每张图片不可超过 5MB。');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp); $dimensions=@getimagesize($tmp);
            $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if (!$dimensions || !isset($extensions[$mime]) || $dimensions[0]*$dimensions[1]>16000000 || $dimensions[0]>8000 || $dimensions[1]>8000) throw new DomainException('请上传有效的 JPEG、PNG 或 WebP 图片，最大 1600 万像素。');
            $image=@imagecreatefromstring(file_get_contents($tmp)); if(!$image) throw new DomainException('图片无法读取，请换一张图片。');
            $filename=bin2hex(random_bytes(20)).'.'.$extensions[$mime]; $path=ROOT.'/storage/'.$directory.'/'.$filename;
            if ($mime==='image/jpeg') $saved=imagejpeg($image,$path,88);
            elseif ($mime==='image/png') { imagesavealpha($image,true); $saved=imagepng($image,$path,6); }
            else $saved=imagewebp($image,$path,88);
            imagedestroy($image); if(!$saved) throw new RuntimeException('Image storage failed');
            $result[]=['filename'=>$filename,'mime'=>$mime,'width'=>$dimensions[0],'height'=>$dimensions[1]];
        }
        return $result;
    } catch(Throwable $exception) { removeImages($result,$directory); throw $exception; }
}
function saveImages(array $images,string $ownerType,int $id): void {
    $column=$ownerType==='listing'?'listing_id':'article_id';
    foreach($images as $image) query('INSERT INTO xiu_images ('.$column.',filename,mime,width,height) VALUES (?,?,?,?,?)',[$id,$image['filename'],$image['mime'],$image['width'],$image['height']]);
}
function removeImages(array $images,string $directory='files'): void { if(!in_array($directory,['files','verification'],true)) throw new LogicException('Invalid image directory'); foreach($images as $image) @unlink(ROOT.'/storage/'.$directory.'/'.$image['filename']); }
