"""Category navigation and district acceptance; creates recorded fixtures for cleanup.php."""
import html,http.cookiejar,json,os,re,time,urllib.request,urllib.parse,urllib.error
from pathlib import Path
BASE=os.environ.get('XIU_BASE_URL','http://www.xiu.cn');OUT=Path(__file__).parent/'artifacts';TOKEN='xiuqa_'+str(int(time.time()))
fixtures={'token':TOKEN,'users':[],'listings':[],'categories':[],'cities':[],'articles':[],'districts':[]};checks=[]
def check(condition,label):
 if not condition: raise AssertionError(label)
 checks.append(label);print('PASS',label,flush=True)
def remember(kind,value):
 fixtures[kind].append(value);(OUT/'fixtures.json').write_text(json.dumps(fixtures),encoding='utf-8')
class Client:
 def __init__(self): self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def get(self,path,data=None):
  try:r=self.opener.open(BASE+path,None if data is None else urllib.parse.urlencode(data).encode(),timeout=20)
  except urllib.error.HTTPError as e:r=e
  return r.status,r.read().decode('utf-8'),r.geturl(),r.headers
 def post(self,path,data):
  source=self.get(path)[1];csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',source).group(1)
  return self.get(path,{'csrf':csrf,**data})
def fixture_id(source,name):
 record=re.search(r'<tr><td>[^<]*</td><td>'+re.escape(name)+r'</td>(.*?)</tr>',source,re.S)
 if not record: record=re.search(r'<tr><td>'+re.escape(name)+r'</td>(.*?)</tr>',source,re.S)
 return int(re.search(r'\?edit=(\d+)',record.group(1)).group(1))
guest,user,admin=Client(),Client(),Client()
try:
 check('/dgadmin/login/' in guest.get('/dgadmin/districts/')[2],'District backend requires login')
 check('/deng-lu/' in guest.get('/fa-bu-xin-xi/')[2],'District publishing requires login')
 admin.post('/dgadmin/login/',{'username':'daiit','password':os.environ['XIU_ADMIN_PASSWORD']})
 city=3
 area={'city_id':city,'name':TOKEN+' district','sort_order':999,'enabled':1}
 response=admin.post('/dgadmin/districts/',area);district=fixture_id(response[1],area['name']);remember('districts',district)
 check('操作已保存' in response[1],'District create persists')
 row_source=re.search(r'<tr><td>广州</td><td>'+re.escape(area['name'])+r'</td>(.*?)</tr>',response[1],re.S).group(1)
 district_url=html.unescape(re.search(r'href="([^"]*qu-yu/[^"]+)"',row_source).group(1))
 check(guest.get(district_url)[0]==200 and 'noindex' in guest.get(district_url)[3].get('X-Robots-Tag',''),'Empty district page is crawlable but noindex')
 check('已有同名区域' in admin.post('/dgadmin/districts/',area)[1],'Duplicate district name rejected')
 check(admin.get('/dgadmin/districts/',{'csrf':'wrong',**area})[0]==403,'District writes require CSRF')
 check('不能更改' in admin.post('/dgadmin/districts/',{**area,'id':district,'city_id':6})[1],'District cannot be moved to another city')
 category_data={'name':TOKEN+' category','slug':TOKEN.replace('_','-')+'-nav','parent_id':18,'kind':'service','sort_order':998,'enabled':1,'show_in_filter':1}
 result=admin.post('/dgadmin/categories/',category_data);category=fixture_id(result[1],category_data['name']);remember('categories',category)
 panel='/guang-zhou/kong-tiao-qing-xi/'
 check(category_data['name'] in guest.get(panel)[1],'New category appears as anchor in sibling group')
 admin.post('/dgadmin/categories/',{**category_data,'id':category,'show_in_filter':0})
 check(category_data['name'] not in guest.get(panel)[1],'Navigation hiding persists')
 admin.post('/dgadmin/categories/',{**category_data,'id':category})
 check(category_data['name'] in guest.get(panel)[1],'Navigation restored')
 name=TOKEN+'_nav';user.post('/zhu-ce/',{'username':name,'password':'QANavigation2026','confirm_password':'QANavigation2026','phone':'13800000000'});remember('users',name)
 form=user.get('/fa-bu-xin-xi/')[1];check('id="listing-city"' in form and 'id="listing-district"' in form,'Publisher can select city and district')
 data={'title':TOKEN+' category district listing','type':'service','category_id':category,'city_id':city,'district_id':district,'address':'QA district address','contact':'QA contact','phone':'00000000000','body':'QA test body for category district navigation, not a real service.'}
 wrong=user.post('/fa-bu-xin-xi/',{**data,'city_id':6});check('所选城市' in wrong[1],'Forged city/district combination rejected')
 result=user.post('/fa-bu-xin-xi/',data);listing=int(re.search(r'/fa-bu-xin-xi/\?id=(\d+)',result[1]).group(1));remember('listings',listing)
 detail=re.search(r'href="(/wei-xiu-xin-xi/[a-z]{1,8}[0-9]{4}\.html)"',result[1]).group(1)
 check(guest.get(detail)[0]==404,'Pending district information private')
 admin.post('/dgadmin/listings/',{'action':'approve','id':listing})
 check(guest.get(detail)[0]==200 and area['name'] in guest.get(detail)[1],'Approved detail displays district')
 check(detail in guest.get(district_url)[1],'Approved listing appears on district page')
 check('noindex' not in guest.get(district_url)[3].get('X-Robots-Tag',''),'Populated district page becomes indexable')
 chunks=guest.get('/sitemap.xml')[1];check('sitemap-xin-xi-1.xml' in chunks,'Real listing sitemap generated')
 nav_map=guest.get('/sitemap-dao-hang-1.xml')[1];check(BASE+district_url in nav_map,'Populated district in sitemap')
 category_row=re.search(r'<tr><td>'+re.escape(category_data['name'])+r'</td>(.*?)</tr>',admin.get('/dgadmin/categories/?category='+str(category))[1],re.S).group(1)
 category_url=html.unescape(re.search(r'href="(/fen-lei-xin-xi/[^"]+)"',category_row).group(1))
 combined=guest.get(category_url+'?'+urllib.parse.urlencode({'city':city,'district':district}));check(combined[0]==200 and '/qu-yu/' in combined[2] and detail in combined[1],'Category/city/district canonical combination')
 rename=admin.post('/dgadmin/districts/',{**area,'id':district,'name':area['name']+' renamed'})
 check(district_url in rename[1] and guest.get(district_url)[0]==200,'Renaming district preserves URL')
 admin.post('/dgadmin/districts/',{**area,'id':district,'enabled':0})
 check(guest.get(district_url)[0]==404 and guest.get(detail)[0]==404,'Disabled district hides route and assigned information')
 check(district_url not in guest.get('/sitemap-dao-hang-1.xml')[1],'Disabled district removed from sitemap')
 admin.post('/dgadmin/districts/',{**area,'id':district})
 check(guest.get(detail)[0]==200,'Re-enabling district restores content')
 check('仍被引用' in admin.post('/dgadmin/districts/',{'id':district,'action':'delete'})[1],'Referenced district cannot be deleted')
 check(guest.get('/hang-zhou/qu-yu/tian-he/')[0]==404,'Unknown district under wrong city is 404')
 check(guest.get('/guang-zhou/?district[]=1')[0]==404,'Non-scalar district filter rejected')
 check(guest.get('/guang-zhou/qu-yu/tian-he/di-999-ye/')[0]==404,'District pagination beyond results is 404')
 check(guest.get('/guang-zhou/')[1].count('class="category-filter"')==1,'One category navigation panel per page')
 (OUT/'category-http-results.json').write_text(json.dumps({'passed':len(checks),'checks':checks},ensure_ascii=False,indent=2),encoding='utf-8')
 print('SUCCESS',len(checks),'category/district HTTP checks.')
except Exception:
 (OUT/'category-http-results.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'failed':True},ensure_ascii=False,indent=2),encoding='utf-8');raise
