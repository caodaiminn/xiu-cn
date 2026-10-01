"""Verify the actual response for every repair category and the life-service backend."""
import json, os, time
from pathlib import Path
import requests
from bs4 import BeautifulSoup

ROOT=Path(__file__).resolve().parents[1]
BASE='http://www.xiu.cn'
data=json.loads((ROOT/'tests/artifacts/life-companion-seo-data.json').read_text(encoding='utf-8'))
client=requests.Session()
checks=[]

def check(condition,label):
    if not condition: raise AssertionError(label)
    checks.append(label)

def get(path):
    response=client.get(BASE+path,timeout=30)
    check(response.status_code==200,'HTTP 200: '+path)
    return BeautifulSoup(response.text,'html.parser')

for index,page in enumerate(data['pages'],1):
    soup=get(page['url'])
    check(soup.title.get_text()==page['title'],'Exact city/category/phone/brand title: '+page['name'])
    check(soup.select_one('link[rel="canonical"]')['href']==BASE+page['url'],'Canonical category address: '+page['name'])
    check(page['name'] in soup.h1.get_text() and '服务与求修信息' in soup.h1.get_text(),'Visible heading keeps its description: '+page['name'])
    schema=[]
    for script in soup.select('script[type="application/ld+json"]'):
        item=json.loads(script.string)
        schema.extend(item if isinstance(item,list) else [item])
    check(any(item.get('@type')=='CollectionPage' and item['name']==page['title'] for item in schema),'Collection schema uses the new SEO title: '+page['name'])
    if index%25==0: print('Checked '+str(index)+' categories.',flush=True)

base='/chen-zhou/kong-tiao-wei-xiu/'
expected='郴州空调维修|郴州空调维修电话'
brand=' - '+data['brand']
for suffix,tail in [('', ''),('di-2-ye/',' - 第2页'),('lei-xing/wei-xiu-fu-wu/',' - 维修服务'),('lei-xing/qiu-xiu-xu-qiu/',' - 求修需求')]:
    soup=get(base+suffix)
    check(soup.title.get_text()==expected+tail+brand,'Pagination/type titles retain their distinguishing labels: '+suffix)
soup=get(base+'?q=xyz-no-match')
check(soup.title.get_text().startswith('搜索') and 'noindex' in soup.select_one('meta[name="robots"]')['content'],'Keyword search keeps its search title and noindex')
soup=get('/fen-lei-xin-xi/pei-hu-gong-si/')
check(soup.title.get_text()=='全国陪护公司|全国陪护公司电话'+brand,'Nationwide company category preserves 公司 without duplication')
publish=BeautifulSoup((ROOT/'tests/artifacts/life-companion-publish-options.html').read_text(encoding='utf-8'),'html.parser')
for category in data['new_categories']:
    option=publish.select_one('option[value="'+str(category['id'])+'"]')
    check(option and option['data-parent']==str(data['parent_id']) and option['data-depth']=='2' and option['data-name']==category['name'],'Publish picker exposes the new second-level child: '+category['name'])
    soup=get(category['url'])
    check(len(soup.select('.listing-contact-card'))==5,'New city category displays exactly five test listings: '+category['name'])
    check(any(link.get('href')==category['url'] for link in soup.find_all('a')),'New category has a clickable public URL: '+category['name'])

password=os.environ.get('XIU_ADMIN_PASSWORD')
if not password: raise RuntimeError('Set XIU_ADMIN_PASSWORD for the authorized local admin check.')
soup=get('/dgadmin/login/')
login=client.post(BASE+'/dgadmin/login/',data={'username':'daiit','password':password,'csrf':soup.select_one('input[name="csrf"]')['value']},timeout=30)
check(login.status_code==200 and '/dgadmin/login' not in login.url,'Administrator login')
soup=get('/dgadmin/categories/?kind=service&category='+str(data['parent_id']))
for category in data['new_categories']:
    link=soup.select_one('[data-level="2"] [data-category-id="'+str(category['id'])+'"]')
    check(link and link.get_text(strip=True)==category['name'],'Backend second-level category is clickable: '+category['name'])
    row=next((row for row in soup.select('.category-branch tbody tr') if row.select_one('td') and row.select_one('td').get_text()==category['name']),None)
    check(row and '二级分类' in row.get_text() and '家政、生活服务' in row.get_text(),'Backend shows the correct level and parent: '+category['name'])
soup=get('/dgadmin/listings/')
check(str(data['listing_total']) in soup.select_one('.admin-listing-controls').get_text(),'Admin count cache reflects the added records')
check(len(soup.select('.review-row'))==20,'Admin pagination still limits the first page to 20')

(ROOT/'tests/artifacts/life-companion-seo-http.json').write_text(json.dumps({'passed':len(checks),'checks':checks},ensure_ascii=False,indent=2),encoding='utf-8')
print('SUCCESS '+str(len(checks))+' HTTP checks.',flush=True)
