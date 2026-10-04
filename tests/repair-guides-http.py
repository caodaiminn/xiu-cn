import concurrent.futures, json, pathlib, re, sys, urllib.request, urllib.error, xml.etree.ElementTree as ET
origin=sys.argv[1].rstrip('/') if len(sys.argv)>1 else 'http://www.xiu.cn'
folder=pathlib.Path('reports/guides/2026-10-04');fixture=json.loads((folder/'http-fixture.json').read_text(encoding='utf-8'))
def get(path):
    req=urllib.request.Request(origin+path,headers={'User-Agent':'5pz-maintenance-verification/1.0'})
    try: res=urllib.request.urlopen(req,timeout=40)
    except urllib.error.HTTPError as e: res=e
    return res.status,res.read().decode('utf-8'),res.geturl()
def collection(case):
    status,body,url=get(case['path']);assert status==200,(case,status)
    links=re.findall(r'class="repair-guide-card" href="([^"]+)"',body)
    assert links==case['guides'],(case['path'],links)
    assert len(links)==5
    return case['path']
def detail(pair):
    path,title=pair;status,body,url=get(path);assert status==200,(path,status)
    assert '<h1>'+title+'</h1>' in body,(path,'title')
    assert '<link rel="canonical" href="'+origin+path+'">' in body,(path,'canonical')
    assert '<meta name="robots" content="index, follow, max-image-preview:large">' in body
    schema=json.loads(re.search(r'<script type="application/ld\+json">(.*?)</script>',body,re.S).group(1))
    assert len([row for row in schema if row['@type']=='Article'])==1
    assert len(re.findall('id="guide-section-',body))>=4
    return path
with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
    collections=list(pool.map(collection,fixture['collections']))
    details=list(pool.map(detail,fixture['details'].items()))
status,body,_=get('/wei-xiu-zhi-nan/');assert status==200 and '维修与服务指南' in body
status,xml,_=get('/sitemap-zhi-nan-1.xml');assert status==200
root=ET.fromstring(xml);urls=[node.text for node in root.findall('.//{*}loc')]
assert len(urls)==fixture['unique_guides'] and len(set(urls))==len(urls)
assert all(url.startswith(origin+'/wei-xiu-zhi-nan/') for url in urls)
status,body,_=get('/sitemap.xml');assert status==200 and 'sitemap-zhi-nan-1.xml' in body
assert get('/wei-xiu-zhi-nan/not-a-guide.html')[0]==404
assert get('/sitemap-zhi-nan-2.xml')[0]==404
path=next(iter(fixture['details']));assert get(path+'?city=23')[2]==origin+path
assert 'repair-guide-card' not in get(fixture['collections'][0]['path']+'?q=zzzz-nothing')[1]
report={'origin':origin,'collections':len(collections),'details':len(details),'sitemap_urls':len(urls),'passed':True}
(folder/('http-online.json' if origin.startswith('https:') else 'http-local.json')).write_text(json.dumps(report,indent=2),encoding='utf-8')
print(json.dumps(report),flush=True)
