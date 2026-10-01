<?php
declare(strict_types=1);
function publicLocationIp(string $ip): bool {
    if(str_starts_with(strtolower($ip),'::ffff:')) $ip=substr($ip,7);
    return (bool)filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE);
}
function locationClientIp(): string {
    $remote=$_SERVER['REMOTE_ADDR']??'';
    $trusted=$GLOBALS['config']['location']['trusted_proxies']??[];
    if(in_array($remote,$trusted,true)) {
        $chain=explode(',',$_SERVER['HTTP_X_FORWARDED_FOR']??''); $chain[]=$remote;
        foreach(array_reverse($chain) as $ip) { $ip=trim($ip); if(!filter_var($ip,FILTER_VALIDATE_IP)) return $remote; if(!in_array($ip,$trusted,true)) return $ip; }
    }
    return $remote;
}
function cityFromIp(string $ip): ?array {
    if(!publicLocationIp($ip)) return null;
    if(str_starts_with(strtolower($ip),'::ffff:')) $ip=substr($ip,7);
    require_once ROOT.'/app/vendor/ip2region/Searcher.class.php';
    $v4=(bool)filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4);
    $version=$v4?\ip2region\xdb\IPv4::default():\ip2region\xdb\IPv6::default();
    $file=ROOT.'/app/data/ip2region/ip2region_'.($v4?'v4':'v6').'.xdb';
    try {
        $searcher=\ip2region\xdb\Searcher::newWithFileOnly($version,$file);
        try { $region=$searcher->search($ip); } finally { $searcher->close(); }
        return cityFromRegion($region);
    } catch(Throwable $error) { error_log('City location lookup unavailable.'); return null; }
}
function cityFromRegion(string $region): ?array {
    $parts=explode('|',$region);
    // Current data: country|province|city|ISP|country-code; legacy data has an extra area field.
    if(count($parts)<4 || !in_array($parts[0],['中国','China'],true)) return null;
    $legacy=count($parts)>=5 && !preg_match('/^[A-Z]{2}$/',$parts[4]);
    $province=$legacy?$parts[2]:$parts[1]; $name=$legacy?$parts[3]:$parts[2];
    if($name==='' || $name==='0') return null;
    $normalize=fn($value)=>preg_replace('/(?:壮族自治区|回族自治区|维吾尔自治区|自治区|特别行政区|省|市)$/u','',$value);
    foreach(cityList() as $city) if($normalize($city['name'])===$normalize($name) && ($province==='0' || $normalize($city['province'])===$normalize($province))) return $city;
    return null;
}
function rememberedCityChoice(): ?array {
    $choice=$_SESSION['city_choice']??null;
    if(!$choice && isset($_COOKIE['xiu_city_choice']) && is_string($_COOKIE['xiu_city_choice']) && preg_match('/^(m|a):(\d{1,9}):(\d{10})$/',$_COOKIE['xiu_city_choice'],$match)) $choice=['source'=>$match[1],'id'=>(int)$match[2],'expires'=>(int)$match[3]];
    if(!$choice && !empty($_SESSION['selected_city_id'])) $choice=['source'=>'m','id'=>(int)$_SESSION['selected_city_id'],'expires'=>time()+365*86400];
    if(!$choice || ($choice['expires']??0)<=time()) { unset($_SESSION['city_choice'],$_SESSION['selected_city_id']); return null; }
    if($choice['id']!==0) {
        $city=seoRecord('xiu_cities',(int)$choice['id']);
        if(!$city || !$city['enabled']) { unset($_SESSION['city_choice'],$_SESSION['selected_city_id']); return null; }
    }
    $_SESSION['city_choice']=$choice; return $choice;
}
function selectedLocationCity(): ?array {
    $choice=rememberedCityChoice(); return !empty($choice['id'])?seoRecord('xiu_cities',(int)$choice['id']):null;
}
function rememberLocationCity(int $id,string $source='m'): void {
    $expires=time()+($source==='m'?365:1)*86400;
    $_SESSION['city_choice']=['id'=>$id,'source'=>$source,'expires'=>$expires]; $_SESSION['selected_city_id']=$id;
    if(PHP_SAPI!=='cli' && !headers_sent()) setcookie('xiu_city_choice',$source.':'.$id.':'.$expires,['expires'=>$expires,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
}
function initializeHomeLocation(): void {
    if(rememberedCityChoice()) return;
    if(($_SESSION['city_location_retry_at']??0)>time()) return;
    $ip=locationClientIp();
    if(!publicLocationIp($ip)) { $GLOBALS['browser_city_location_needed']=true; return; }
    $city=cityFromIp($ip);
    if($city) rememberLocationCity((int)$city['id'],'a');
    else $_SESSION['city_location_retry_at']=time()+1800;
}
function cityLocationController(string $path): void {
    header('X-Robots-Tag: noindex, nofollow');
    if($path==='/change-city') {
        if($_SERVER['REQUEST_METHOD']!=='GET') abortPage(405,'请选择城市后切换。');
        $id=positiveId(getValue('city')); $city=$id?seoRecord('xiu_cities',$id):null;
        if($id && (!$city || !$city['enabled'])) abortPage(404,'这个城市暂未开通。');
        rememberLocationCity($id); unset($_SESSION['city_location_retry_at']);
        redirect($city?cityUrl($city):publicPath('services')); return;
    }
    if($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); header('Allow: POST'); }
    else {
        $choice=rememberedCityChoice();
        if(!$choice) {
            $ip=locationClientIp();
            if(!publicLocationIp($ip)) $ip=input('ip');
            $city=cityFromIp($ip);
            if($city) rememberLocationCity((int)$city['id'],'a');
            else $_SESSION['city_location_retry_at']=time()+1800;
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    $city=selectedLocationCity();
    echo json_encode(['city'=>$city?['id'=>(int)$city['id'],'name'=>$city['name']]:null],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}
