"""Map browser-observed facts; never fetch remote pages or copy their article bodies."""
import argparse, json, re
from collections import Counter
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DIRECTORY = ROOT / 'reports/imports/baixiu-browser'

def value(line):
    text = line.split(': ', 1)[-1].strip()
    if text.startswith('"'):
        try: return json.loads(text)
        except ValueError: pass
    return text

def snapshot_facts(text, url, collected_at):
    lines = text.splitlines()
    heading = next((line for line in lines if re.search(r'heading .*\[level=1\]', line)), '')
    if not heading: raise ValueError('missing visible detail heading')
    title = re.search(r'heading "(.*?)" \[level=1\]', heading).group(1)
    header = text.split(heading, 1)[0]
    breadcrumbs = re.findall(r'link "([^"\n]*)":\s*\n\s*- /url: (/[^\s]+/wx-[^\s]+)', header)
    if not breadcrumbs: raise ValueError('missing category breadcrumb')
    city = re.search(r'link "([^"\n>]+)百修网', header)
    if not city: raise ValueError('missing city breadcrumb')
    city = city.group(1)
    category = breadcrumbs[-1][0]
    if category.startswith(city): category = category[len(city):]
    def following(label):
        for i, line in enumerate(lines):
            if value(line) == label and i+1 < len(lines): return value(lines[i+1])
        return ''
    region = following('服务区域') or following('服务范围')
    published = following('发布时间') or following('发布日期')
    company = following('入驻商家')
    if not company:
        company_match = re.search(r'企业信息[\s\S]*?link "([^"\n]+)":\s*\n\s*- /url: /usershop/index-', text)
        if company_match: company = company_match.group(1)
    contact = following('联系人') or company
    basic = text.split(heading, 1)[1].split('温馨提示', 1)[0]
    phone = re.search(r'(?:联系电话[：:]\s*)?((?:400|800)[- ]?\d{3,4}[- ]?\d{3,4}|1[3-9]\d{9}|0\d{2,3}[- ]\d{7,8})', basic)
    if not phone: raise ValueError('missing visible business telephone')
    if not region or not company or not contact: raise ValueError('missing service area or business contact')
    datetime.strptime(published, '%Y-%m-%d %H:%M:%S')
    if not region.startswith(city): raise ValueError('service area differs from breadcrumb city')
    return dict(source_url=url, source_title=title, city=city, source_category=category,
                category_breadcrumbs=[name[len(city):] if name.startswith(city) else name for name, _ in breadcrumbs],
                source_category_url='https://www.baixiu.org'+breadcrumbs[-1][1],
                address=region, company=company, contact=contact, phone=phone.group(1),
                published_at=published, collected_at=collected_at)

def build(facts):
    catalog = json.loads((DIRECTORY / 'local-catalog.json').read_text(encoding='utf-8'))
    cities = {item['name']: item for item in catalog['cities']}
    categories = catalog['categories']
    aliases = {'台式电脑维修': '电脑维修', '投影机维修': '投影仪维修', '电动自行车维修':'电动车维修', '空调加氟清洗':'空调加氟清洗'}
    root_aliases = {'生活服务':18, '家政、生活服务类':18, '建筑、防水维修':9, '家电、厨电维修':1, '工控产品维修':35, '办公设备维修':27, '电脑数码、通讯维修':14, '交通工具维修':31}
    accepted, rejected, seen = [], [], set()
    for fact in facts:
        try:
            city = cities.get(fact['city'])
            if not city: raise ValueError('city is absent from local directory')
            name = aliases.get(fact['source_category'], fact['source_category'])
            matches = [item for item in categories if item['name'] == name and item['depth'] > 1]
            if len(matches) > 1:
                root = root_aliases.get(fact['category_breadcrumbs'][0])
                matches = [item for item in matches if item['parent_id'] == root]
            if len(matches) != 1: raise ValueError('category has no unambiguous local match')
            key = fact['source_url']
            if key in seen: continue
            seen.add(key)
            title = re.sub(r'[^\w\u3400-\u9fff]', '', fact['source_title'])[:120]
            accepted.append(dict(fact, city_id=city['id'], category_id=matches[0]['id'], category_name=matches[0]['name'], title=title))
        except (ValueError, KeyError) as error:
            rejected.append({'source_url': fact.get('source_url'), 'reason': str(error), 'city': fact.get('city'), 'category':fact.get('source_category')})
    return accepted, rejected

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--output', default='reports/imports/baixiu-browser/batch.json')
    args = parser.parse_args()
    facts, errors = [], []
    for path in sorted(DIRECTORY.glob('detail-*.txt')):
        try:
            facts.append(snapshot_facts(path.read_text(encoding='utf-8'), 'https://www.baixiu.org/repair/'+path.stem.removeprefix('detail-')+'.html', datetime.fromtimestamp(path.stat().st_mtime).astimezone().isoformat()))
        except (ValueError, AttributeError) as error: errors.append({'file':path.name, 'reason':str(error)})
    observed = DIRECTORY / 'observed-facts.jsonl'
    if observed.exists():
        facts.extend(json.loads(line) for line in observed.read_text(encoding='utf-8').splitlines() if line.strip())
    accepted, rejected = build(facts)
    result = {'source':'https://www.baixiu.org/selectcity.html', 'records':accepted, 'rejected':rejected, 'parse_errors':errors}
    (ROOT / args.output).write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps({'observed':len(facts),'accepted':len(accepted),'rejected':len(rejected),'parse_errors':errors,'groups':dict(Counter(item['city']+' / '+item['category_name'] for item in accepted))},ensure_ascii=False))
