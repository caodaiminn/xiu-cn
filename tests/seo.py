"""Read-only SEO crawl; run after smoke.py and before fixture cleanup for real article coverage."""
import base64, hashlib, html, json, os, re, urllib.error, urllib.parse, urllib.request
from html.parser import HTMLParser
from pathlib import Path
from xml.etree import ElementTree as ET

BASE=os.environ.get('XIU_BASE_URL','http://www.xiu.cn').rstrip('/')
OUT=Path(__file__).parent/'artifacts'
checks=[]
def check(condition,label):
    if not condition: raise AssertionError(label)
    checks.append(label)
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args): return None
def request(path,follow=True,method='GET',headers=None):
    opener=urllib.request.build_opener() if follow else urllib.request.build_opener(NoRedirect())
    try: r=opener.open(urllib.request.Request(path if path.startswith('http') else BASE+path,headers=headers or {},method=method),timeout=20)
    except urllib.error.HTTPError as error: r=error
    return r.status,r.read().decode('utf-8'),r.geturl(),r.headers
class Page(HTMLParser):
    def __init__(self,source):
        super().__init__(); self.meta={};self.canonical=[];self.title='';self.h1=0;self.links=[];self.scripts=[];self.tag='';self.json_script=False
        self.feed(source)
    def handle_starttag(self,tag,attrs):
        d=dict(attrs); self.tag=tag
        if tag=='meta': self.meta[d.get('name',d.get('property'))]=d.get('content','')
        if tag=='link' and d.get('rel')=='canonical': self.canonical.append(d.get('href'))
        if tag=='h1': self.h1+=1
        if tag=='a' and 'href' in d: self.links.append(d['href'])
        if tag=='script': self.json_script=d.get('type')=='application/ld+json'
    def handle_endtag(self,tag):
        self.tag=''
        if tag=='script': self.json_script=False
    def handle_data(self,data):
        if self.tag=='title': self.title+=data
        if self.json_script: self.scripts.append(data)
def audit(url,indexable=None):
    status,source,final,headers=request(url)
    check(status==200 and final==url,'Canonical HTTP 200 '+url)
    page=Page(source)
    check(page.canonical==[url],'Self canonical '+url)
    check(page.h1==1 and page.title and page.meta.get('description'),'Title description H1 '+url)
    check(page.meta.get('robots')==headers.get('X-Robots-Tag'),'Robots meta/header agree '+url)
    check(page.meta.get('og:url')==url,'OG canonical '+url)
    if indexable is not None: check(('noindex' not in page.meta['robots'])==indexable,'Index policy '+url)
    for script in page.scripts:
        schemas=json.loads(script)
        check(all(s.get('@context')=='https://schema.org' for s in schemas),'Valid JSON-LD '+url)
        digest=base64.b64encode(hashlib.sha256(script.encode()).digest()).decode()
        check("'sha256-"+digest+"'" in headers.get('Content-Security-Policy',''),'CSP permits JSON-LD '+url)
    return page

for old,new in [('/cheng-shi-fu-wu/','/selectcity.html'),('/cheng-shi-fu-wu/shang-hai/','/shang-hai/'),('/cheng-shi-fu-wu/guang-zhou/kong-tiao-qing-xi/','/guang-zhou/kong-tiao-qing-xi/'),('/services/','/fen-lei-xin-xi/'),('/cities/','/selectcity.html'),('/city/hangzhou/','/hang-zhou/'),('/baixiuquan/','/wei-xiu-zhi-shi-ku/'),('/baike/','/wei-xiu-bai-ke/'),('/login/','/deng-lu/'),('/register/','/zhu-ce/'),('/index.php','/'),('/?utm_source=test','/')]:
    response=request(old,False)
    check(response[0]==301 and response[3]['Location']==BASE+new,'Legacy 301 '+old)
for old in ['/wei-xiu-xin-xi/shen-zhen-ban-gong-shi-tou-ying-yi-wei-xiu-xu-qiu-28.html','/wei-xiu-xin-xi/dong-guan-jia-dian-qing-xi-yu-ri-chang-wei-hu-fu-wu-13.html','/info/28/','/baixiuquan/1.html','/baike/5.html']:
    response=request(old,False)
    check(response[0]==301,'Old detail 301 '+old)
    new=response[3]['Location']; slug=urllib.parse.urlparse(new).path.rsplit('/',1)[-1][:-5]
    check(len(slug)<=12 and re.fullmatch('[a-z]{1,8}[0-9]{4}',slug),'12 character slug '+old)
    audit(new,False)
    uppercase=new.replace(slug,slug.upper()); check(request(uppercase,False)[0]==301,'Case normalization '+old)
    check(request(new+'?utm_source=legacy',False)[3].get('Location')==new,'Detail query normalization '+old)
for path in ['/deng-lu/','/zhu-ce/','/dgadmin/login/','/fen-lei-xin-xi/?q=test','/wei-xiu-zhi-shi-ku/?q=test']:
    audit(BASE+path,False)
for path in ['/fen-lei-xin-xi/di-0-ye/','/fen-lei-xin-xi/di-999999-ye/','/fen-lei-xin-xi/?category[]=1','/fen-lei-xin-xi/?type=invalid','/wei-xiu-zhi-shi-ku/di-99-ye/','/wei-xiu-xin-xi/unknown0000.html','/nonexistent-page/','/sitemap-xin-xi-999999.xml']:
    status,source,_,headers=request(path)
    p=Page(source)
    check(status==404 and 'noindex' in headers.get('X-Robots-Tag','') and not p.canonical,'True 404 no canonical '+path)
robot=request('/robots.txt'); check(robot[0]==200 and 'Sitemap: '+BASE+'/sitemap.xml' in robot[1] and 'Disallow: /dgadmin/' in robot[1] and 'Disallow: /*?' not in robot[1],'Robots discovery and crawlable noindex searches')
ns={'s':'http://www.sitemaps.org/schemas/sitemap/0.9'}
index=ET.fromstring(request('/sitemap.xml')[1]); urls=[]
for location in index.findall('s:sitemap/s:loc',ns):
    response=request(location.text); check(response[0]==200,'XML chunk '+location.text)
    chunk=ET.fromstring(response[1]); urls.extend(node.text for node in chunk.findall('s:url/s:loc',ns))
check(len(urls)==len(set(urls)) and len(urls)>10,'Sitemap URLs unique and populated')
titles={};descriptions={};article_schema=False
for url in urls:
    check(url.startswith(BASE+'/') and '?' not in url and '/dgadmin/' not in url,'Only canonical public URLs '+url)
    page=audit(url,True)
    check(page.title not in titles,'Unique sitemap title '+url); titles[page.title]=url
    check(page.meta['description'] not in descriptions,'Unique sitemap description '+url); descriptions[page.meta['description']]=url
    article_schema=article_schema or any(schema.get('@type')=='Article' for script in page.scripts for schema in json.loads(script))
    if url.endswith('.html') and url!=BASE+'/selectcity.html':
        slug=url.rsplit('/',1)[-1][:-5]; check(len(slug)<=12,'Sitemap short detail '+url)
if (OUT/'fixtures.json').exists():
    fixtures=json.loads((OUT/'fixtures.json').read_text('utf-8'))
    for id in fixtures.get('listings',[]):
        response=request('/info/'+str(id)+'/',False)
        if response[0]==301: check(response[3]['Location'] in urls,'Published real listing in sitemap')
    if fixtures.get('articles'): check(article_schema,'Real published article has Article JSON-LD')
check(request('/fen-lei-xin-xi/',method='HEAD')[0]==200,'HEAD collection')
OUT.mkdir(exist_ok=True)
(OUT/'seo-results.json').write_text(json.dumps({'passed':len(checks),'sitemap_urls':len(urls),'checks':checks},ensure_ascii=False,indent=2),encoding='utf-8')
print('SUCCESS',len(checks),'SEO checks;',len(urls),'sitemap URLs crawled.')
