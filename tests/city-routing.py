"""Read-only city directory and migration checks."""
import html,json,re,urllib.request,urllib.error
from pathlib import Path
BASE='http://www.xiu.cn';checks=[]
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args):return None
def request(path,follow=True):
    opener=urllib.request.build_opener() if follow else urllib.request.build_opener(NoRedirect())
    try:r=opener.open(BASE+path,timeout=20)
    except urllib.error.HTTPError as error:r=error
    return r.status,r.read().decode('utf-8'),r.geturl(),r.headers
def check(value,label):
    if not value:raise AssertionError(label)
    checks.append(label)
status,source,final,headers=request('/selectcity.html')
check(status==200 and final==BASE+'/selectcity.html','Selector is canonical single page')
check('rel="canonical" href="'+final+'"' in source and 'noindex' not in headers.get('X-Robots-Tag',''),'Selector SEO and index policy')
cities=re.findall(r'<a href="([^"]+)" data-city-item data-city-key="[^"]+">([^<]+)</a>',source)
check(len(cities)==393 and len({url for url,_ in cities})==393,'Nationwide directory has 393 unique city/region links')
check(source.count('data-city-group')==34,'Directory has 34 province groups')
check('/cheng-shi-fu-wu/' not in source,'Selector contains no old city URLs')
for url,name in cities:
    status,page,final,_=request(url,False)
    check(status==200 and 'rel="canonical" href="'+BASE+url+'"' in page,'City canonical '+name)
    old=request('/cheng-shi-fu-wu'+url,False)
    check(old[0]==301 and old[3].get('Location')==BASE+url,'Old city redirect '+name)
for old,new in [('/cheng-shi-fu-wu/','/selectcity.html'),('/cities/','/selectcity.html'),('/xuan-ze-cheng-shi.html','/selectcity.html'),('/city/shanghai/','/shang-hai/'),('/SHANG-HAI','/shang-hai/'),('/shang-hai/?utm_source=old','/shang-hai/'),('/cheng-shi-fu-wu/guang-zhou/kong-tiao-qing-xi/qu-yu/tian-he/','/guang-zhou/kong-tiao-qing-xi/qu-yu/tian-he/'),('/cheng-shi-fu-wu/guang-zhou/kong-tiao-qing-xi/qu-yu/tian-he/lei-xing/wei-xiu-fu-wu/?q=abc','/guang-zhou/kong-tiao-qing-xi/qu-yu/tian-he/lei-xing/wei-xiu-fu-wu/?q=abc')]:
    r=request(old,False);check(r[0]==301 and r[3].get('Location')==BASE+new,'Compatibility '+old)
for url in ['/unknown-city/','/shang-hai/unknown-category/','/shang-hai/qu-yu/tian-he/','/guang-zhou/di-999999-ye/','/guang-zhou/?city[]=3']:
    r=request(url);check(r[0]==404 and 'noindex' in r[3].get('X-Robots-Tag',''),'Invalid route '+url)
map_source=request('/sitemap-dao-hang-1.xml')[1]
check('/selectcity.html' in map_source and '/cheng-shi-fu-wu/' not in map_source,'Sitemap uses new city paths')
for url in ['/','/fen-lei-xin-xi/','/shang-hai/','/wang-zhan-di-tu/']:
    page=request(url)[1];check('/cheng-shi-fu-wu/' not in page,'No old internal city link '+url)
Path('tests/artifacts/city-routing-results.json').write_text(json.dumps({'passed':len(checks),'cities':len(cities),'checks':checks},ensure_ascii=False,indent=2),encoding='utf-8')
print('SUCCESS',len(checks),'city directory/routing checks.')
