"""Read-only acceptance of generated data and local admin pagination."""
import json, os, re, time
from pathlib import Path
import requests
from bs4 import BeautifulSoup

ROOT=Path(__file__).resolve().parents[1]
BASE='http://www.xiu.cn'
data=json.loads((ROOT/'tests/artifacts/nationwide-test-data-results.json').read_text(encoding='utf-8'))
checks, timings=[],[]
client=requests.Session()
def check(condition, label):
    if not condition: raise AssertionError(label)
    checks.append(label)
    print('PASS '+label,flush=True)
def get(path):
    started=time.monotonic(); response=client.get(BASE+path,timeout=30)
    timings.append({'path':path,'status':response.status_code,'seconds':round(time.monotonic()-started,3)})
    check(response.status_code==200,'Page loads: '+path)
    return response,BeautifulSoup(response.text,'html.parser')

response,soup=get('/selectcity.html')
for name in ['北京','上海','广州','成都','深圳','香港','澳门']:
    check(any(link.get_text(strip=True)==name for link in soup.find_all('a')),'City directory contains '+name)
for sample in data['samples']:
    response,soup=get(sample['url'])
    cards=soup.select('.listing-contact-card')
    check(0<len(cards)<=12,'Public list stays paginated: '+sample['city']+'/'+sample['category'])
    check(all(sample['city'] in card.get_text() for card in cards),'Listings match the selected city: '+sample['city'])
    check(any(card.select_one('.demo-label') and card.select_one('.demo-label').get_text()=='测试信息' for card in cards),'Generated cards have a visible test label: '+sample['city'])
    item=sample['items'][0]; response,soup=get(item['url'])
    check(soup.h1.get_text()==item['title'] and item['phone'] in soup.get_text() and item['contact'] in soup.get_text(),'Detail retains the generated title/contact/number')
    check(soup.select_one('.repair-demo-note') and '本地测试信息' in soup.select_one('.repair-demo-note').get_text(),'Detail explicitly identifies local test information')
    check(not soup.select('a[href^="tel:"]'),'Random test numbers are displayed without real dial links')
    check('noindex' in soup.select_one('meta[name="robots"]')['content'],'Generated detail pages remain excluded from indexing')
    check(not soup.select('.listing-verification'),'No fabricated identity badge appears on generated records')
    check(soup.select_one('link[rel="canonical"]')['href']==BASE+item['url'],'Canonical URL uses the persisted short identifier')

for path in ['/','/fen-lei-xin-xi/','/sitemap.xml']:
    get(path)
get('/shang-hai/jia-dian-chu-dian-wei-xiu/di-2-ye/')

password=os.environ.get('XIU_ADMIN_PASSWORD')
if password:
    response,soup=get('/dgadmin/login/')
    csrf=soup.select_one('input[name="csrf"]')['value']
    login=client.post(BASE+'/dgadmin/login/',data={'username':'daiit','password':password,'csrf':csrf},timeout=30)
    check(login.status_code==200 and '/dgadmin/login' not in login.url,'Authorized local administrator login succeeds')
    for path,expected in [('/dgadmin/listings/',20),('/dgadmin/listings/?page=2&per_page=50',50),('/dgadmin/listings/?page=999999&per_page=20',None)]:
        response,soup=get(path)
        reviews=soup.select('.review-row')
        check(len(reviews)==expected if expected else 0<len(reviews)<=20,'Actual large-volume admin page size and bounds are correct')
        check(str(data['admin_total']) in soup.select_one('.admin-listing-controls').get_text(),'Backend reports the correct total at full data volume')

(ROOT/'tests/artifacts/nationwide-test-http-results.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'timings':timings},ensure_ascii=False,indent=2),encoding='utf-8')
print('SUCCESS '+str(len(checks))+' HTTP checks.',flush=True)
