"""Read-only source preflight. Stop at access verification; never import placeholder records."""
import argparse, json, time, xml.etree.ElementTree as ET
from datetime import datetime, timezone, timedelta
from pathlib import Path
import requests

parser=argparse.ArgumentParser()
parser.add_argument('--report',default='reports/imports/baixiu-source-check.json')
args=parser.parse_args()
session=requests.Session()
report={'source':'https://www.baixiu.org/selectcity.html','checked_at':datetime.now(timezone(timedelta(hours=8))).isoformat(),'requested_per_city_category':{'minimum':5,'maximum':10},'checks':[],'candidate_detail_urls':[],'imported':0}

def fetch(url):
    try:
        response=session.get(url,timeout=15)
        verification=any(marker in response.text for marker in ['aliyun_waf_aa','name="aliyunwaf_','acw_sc__v2'])
        report['checks'].append({'url':url,'http_status':response.status_code,'access_verification':verification,'content_type':response.headers.get('Content-Type','')})
        return response if response.status_code==200 and not verification else None
    except requests.RequestException as error:
        report['checks'].append({'url':url,'error':type(error).__name__})
        return None

city=fetch(report['source'])
for sitemap in ['https://www.baixiu.org/sitemap.xml','https://www.baixiu.org/sitemap2.xml']:
    time.sleep(1)
    response=fetch(sitemap)
    if response is None: continue
    try:
        root=ET.fromstring(response.content)
        urls=[element.text.strip() for element in root.iter() if element.tag.rsplit('}',1)[-1]=='loc' and element.text]
        report.setdefault('sitemaps',[]).append({'url':sitemap,'url_count':len(urls)})
        report['candidate_detail_urls'].extend(url for url in urls if url.startswith('https://www.baixiu.org/repair/') and url.endswith('.html'))
    except ET.ParseError: report['checks'][-1]['invalid_xml']=True
report['candidate_detail_urls']=list(dict.fromkeys(report['candidate_detail_urls']))
detail=None
if report['candidate_detail_urls']:
    time.sleep(1)
    detail=fetch(report['candidate_detail_urls'][0])
report['status']='blocked' if city is None or detail is None else 'readable_requires_field_mapping'
report['reason']='城市目录或详情页无法读取真实信息；访问验证页不会被当作正文或导入数据库。' if report['status']=='blocked' else '已读取公开入口，需要以实际字段匹配城市、分类及联系方式后导入。'
destination=Path(args.report);destination.parent.mkdir(parents=True,exist_ok=True)
destination.write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({'status':report['status'],'candidate_details':len(report['candidate_detail_urls']),'imported':0,'report':str(destination),'reason':report['reason']},ensure_ascii=False))
