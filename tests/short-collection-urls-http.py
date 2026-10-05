"""Read-only HTTP redirect, canonical, pagination and sitemap verification."""
import html, json, os, re, sys, urllib.error, urllib.request
from pathlib import Path
BASE=os.environ.get('XIU_BASE_URL','http://www.xiu.cn').rstrip('/')
fixture=json.loads(Path(sys.argv[1]).read_text(encoding='utf-8'));checks=0
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args):return None
def request(path,method='GET'):
    try:r=urllib.request.build_opener(NoRedirect()).open(urllib.request.Request(BASE+path,method=method),timeout=30)
    except urllib.error.HTTPError as error:r=error
    return r.status,r.headers,r.read().decode('utf-8')
def check(ok,message):
    global checks
    checks+=1
    if not ok:raise AssertionError(message)
for case in fixture['http']:
    old,new=case['old'],case['new']
    status,headers,_=request(old)
    check(status==301 and headers.get('Location')==BASE+new,'One-hop 301 '+old)
    status,headers,body=request(new)
    check(status==200,'New route 200 '+new)
    check('rel="canonical" href="'+BASE+new+'"' in body,'Self canonical '+new)
    check('property="og:url" content="'+BASE+new+'"' in body,'OG URL '+new)
    check('/lei-xing/' not in body and '/qu-yu/' not in body and not re.search(r'href="[^"]*/di-\d+-ye/',body),'Short internal filters and pagination '+new)
    check(fixture['example_old'].split('/')[2] not in body,'No original long category link '+new)
old,new=fixture['example_old'],fixture['example_new']
for path in [old,old.upper().rstrip('/'),'/cheng-shi-fu-wu'+old,new.rstrip('/')]:
    status,headers,_=request(path,'HEAD')
    check(status==301 and headers.get('Location')==BASE+new,'HEAD normalization '+path)
status,headers,_=request(old+'?q=repair')
check(status==301 and headers.get('Location')==BASE+new+'?q=repair','Query survives redirect')
status,headers,body=request(new+'?q=repair')
check(status==200 and headers.get('X-Robots-Tag')=='noindex, follow','Search index policy preserved')
for path in ['/fen-lei-xin-xi/p/0/','/fen-lei-xin-xi/p/999999/','/qin-zhou/wronginitials-'+new.split('/')[2].rsplit('-',1)[1]+'/', '/qin-zhou/lx/unknown/', '/shang-hai/qy/tian-he/', '/fen-lei-xin-xi/qy/tian-he/', '/qin-zhou/lx/wxfw/lx/qxxq/']:
    status,headers,body=request(path)
    # Production Nginx intercepts 404 responses with its own plain error page.
    robots=headers.get('X-Robots-Tag')
    check(status==404 and (robots is None or 'noindex' in robots) and 'rel="canonical"' not in body,'Invalid route 404 '+path)
for path in ['/','/wang-zhan-di-tu/','/sitemap-dao-hang-1.xml','/sitemap-cheng-shi-fen-lei-1.xml']:
    status,_,body=request(path)
    check(status==200 and '/gong-gong-an-quan-she-bei-wei-xiu/' not in body,'Short global links '+path)
status,_,body=request('/wei-xiu-zhi-shi-ku/')
check(status==200,'Articles collection retained')
status,_,body=request('/dgadmin/index.php?section=login')
check(status==200,'Admin PHP entry retained')
print(json.dumps({'passed':True,'checks':checks,'base':BASE,'example':new}))
