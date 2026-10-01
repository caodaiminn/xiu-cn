<?php
declare(strict_types=1);
function analyticsInitScript(): string {
    return '(function(){var sdk=document.getElementById("LA_COLLECT");function start(){LA.init({id:"LKvmGacoqwoNTRyG",ck:"LKvmGacoqwoNTRyG",autoTrack:true,hashMode:true})}if(window.LA&&typeof LA.init==="function"){start()}else if(sdk){sdk.addEventListener("load",start,{once:true})}})();';
}
function analyticsScripts(): string {
    return '<script charset="UTF-8" id="LA_COLLECT" src="//sdk.51.la/js-sdk-pro.min.js" async></script>'."\n".
        '<script>'.analyticsInitScript().'</script>';
}
/** Keep inline execution limited to the exact analytics initializer and page JSON-LD. */
function pageContentSecurityPolicy(string $seoJson=''): string {
    $hashes=" 'sha256-".base64_encode(hash('sha256',analyticsInitScript(),true))."'";
    if($seoJson!=='') $hashes.=" 'sha256-".base64_encode(hash('sha256',$seoJson,true))."'";
    return "default-src 'self'; img-src 'self' data: https://collect-v6.51.la http://collect-v6.51.la; style-src 'self'; ".
        "script-src 'self' https://sdk.51.la http://sdk.51.la".$hashes."; ".
        "connect-src 'self' https://collect-v6.51.la http://collect-v6.51.la https://api.ipify.org https://api64.ipify.org; frame-ancestors 'none'; base-uri 'self'; form-action 'self'";
}
