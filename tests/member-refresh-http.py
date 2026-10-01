"""Exercise daily quota, ownership, ranking and concurrent requests using isolated fixtures."""
import concurrent.futures, http.cookiejar, json, re, subprocess, threading, urllib.error, urllib.parse, urllib.request
from pathlib import Path
BASE='http://www.xiu.cn';OUT=Path('tests/artifacts');PHP='D:/phpstudy_pro/Extensions/php/php8.0.2nts/php.exe'
data=json.loads((OUT/'member-refresh-fixtures.json').read_text(encoding='utf-8'));checks=[]
route='/yong-hu-zhong-xin/shua-xin/';manage='/yong-hu-zhong-xin/wo-de-fa-bu/'
def check(value,label):
 assert value,label
 checks.append(label);print('PASS',label,flush=True)
def php(source):return json.loads(subprocess.check_output([PHP,'-r',"require 'app/bootstrap.php';"+source],encoding='utf-8'))
def state(id):return php(f"echo json_encode(row('SELECT * FROM xiu_listings WHERE id=?',[{id}]));")
def quota(kind):return php(f"echo json_encode((int)scalar('SELECT COUNT(*) FROM xiu_listing_refreshes WHERE user_id=? AND refresh_date=?',[{data['users'][kind]['id']},refreshDay()]));")
class Client:
 def __init__(self):self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def request(self,path,fields=None):
  request=urllib.request.Request(BASE+path,urllib.parse.urlencode(fields).encode() if fields is not None else None)
  try:r=self.opener.open(request,timeout=25)
  except urllib.error.HTTPError as e:r=e
  return r.status,r.read().decode('utf-8','replace'),r.geturl()
 def csrf(self,path):return re.search(r'name="csrf" value="([a-f0-9]+)"',self.request(path)[1]).group(1)
 def post(self,path,fields):return self.request(path,{'csrf':self.csrf(manage),**fields})
 def login(self,kind):return self.request('/deng-lu/',{'csrf':self.csrf('/deng-lu/'),'username':data['users'][kind]['username'],'password':'QARefresh2026'})
def urls(source):return re.findall(r'<h2><a href="(/wei-xiu-xin-xi/[^"]+)">',source)
def owner_ids(kind):return [item['id'] for item in data['listings'][kind]]
guest=Client();clients={kind:Client() for kind in data['users']}
try:
 for kind,client in clients.items():check('/yong-hu-zhong-xin/' in client.login(kind)[2],kind+' fixture login')
 owner=clients['owner'];first,second,pending,rejected,offline,unrelated=owner_ids('owner');before=state(first)
 check('/deng-lu/' in guest.request(route)[2],'Anonymous refresh requires login')
 check(owner.request(route+'?id='+str(first))[0]==405,'GET cannot refresh or consume quota')
 check(owner.request(route,{'id':first,'csrf':'bad'})[0]==403,'Refresh requires valid CSRF')
 check('无权刷新' in owner.post(route,{'id':owner_ids('other')[0]})[1] and quota('owner')==0,'Cross-user refresh rejected without consuming quota')
 check('不存在' in owner.post(route,{'id':999999999})[1] and quota('owner')==0,'Nonexistent listing rejected without consuming quota')
 for status,id in [('pending',pending),('rejected',rejected),('offline',offline)]:check('只能刷新' in owner.post(route,{'id':id})[1] and quota('owner')==0,status+' listing cannot refresh or consume quota')
 category=data['categories']['main'];php(f"query('UPDATE xiu_categories SET enabled=0 WHERE id=?',[{category}]);echo json_encode(true);")
 check('只能刷新' in owner.post(route,{'id':first})[1] and quota('owner')==0,'Disabled category cannot refresh or consume quota')
 php(f"query('UPDATE xiu_categories SET enabled=1 WHERE id=?',[{category}]);echo json_encode(true);")
 check(urls(guest.request(data['category_url'])[1])[0]==data['listings']['company'][1]['url'],'Existing enterprise priority applies before refresh')
 result=owner.post(route,{'id':first,'return_section':'listings','status':'published','page':1});after=state(first)
 check('刷新成功' in result[1] and 'status=published' in result[2],'Refresh succeeds and preserves member filter')
 check(quota('owner')==1 and after['refresh_order']>0 and after['refreshed_at'],'Successful refresh reserves one daily quota and persists timestamp')
 check(all(before[key]==after[key] for key in before if key not in ['refreshed_at','refresh_order']),'Refresh preserves title, body, URL, status and content timestamps')
 for name,path in [('category',data['category_url']),('city',data['city_category_url']),('district',data['district_url']),('search','/fen-lei-xin-xi/?q='+data['token'])]:check(urls(guest.request(path)[1])[0]==data['listings']['owner'][0]['url'],name+' refreshed unverified member outranks certified members')
 check('今日免费刷新：已用完' in result[1] and 'member-refresh-button" disabled' in result[1],'Member view displays exhausted quota and disables refresh buttons')
 check('上次刷新' in result[1],'Member view displays last refresh time')
 check('已用完' in owner.post(route,{'id':second})[1] and state(second)['refresh_order']==0,'Second information on same day cannot refresh')
 check('已用完' in owner.post(route,{'id':first})[1] and state(first)['refresh_order']==after['refresh_order'],'Duplicate submission cannot move listing again')
 check('已用完' in owner.post(route,{'id':unrelated})[1] and state(unrelated)['refresh_order']==0,'Daily quota is shared across categories')
 check(urls(guest.request(data['other_category_url'])[1])==[data['listings']['owner'][5]['url']],'Refreshing does not move information into another category')
 newer=clients['other'].post(route,{'id':owner_ids('other')[0]});check('刷新成功' in newer[1] and urls(guest.request(data['category_url'])[1])[0]==data['listings']['other'][0]['url'],'Another member has own quota and later refresh becomes first')
 company=clients['company'];check('刷新成功' in company.post(route,{'id':owner_ids('company')[0]})[1],'Certified ordinary member can refresh')
 check('已用完' in company.post(route,{'id':owner_ids('company')[1]})[1],'Identity certification does not increase daily quota')
 yesterday=data['users']['yesterday']['id'];yesterday_listing=owner_ids('yesterday')[0]
 php(f"query('INSERT INTO xiu_listing_refreshes (user_id,listing_id,refresh_date,refreshed_at) VALUES (?,?,?,?)',[{yesterday},{yesterday_listing},date('Y-m-d',strtotime('-1 day')),date('Y-m-d H:i:s',strtotime('-1 day'))]);echo json_encode(true);")
 check('今日免费刷新：剩余1次' in clients['yesterday'].request(manage)[1],'Previous day refresh does not consume today quota')
 check('刷新成功' in clients['yesterday'].post(route,{'id':owner_ids('yesterday')[1]})[1] and quota('yesterday')==1,'New calendar day restores free refresh')
 check(php("echo json_encode(refreshDay());")==php("echo json_encode((new DateTimeImmutable('now',new DateTimeZone('Asia/Shanghai')))->format('Y-m-d'));") ,'Quota day uses Asia Shanghai timezone')
 race_a=clients['race'];race_b=Client();race_b.login('race');barrier=threading.Barrier(2)
 race_csrf=[race_a.csrf(manage),race_b.csrf(manage)]
 def concurrent_refresh(pair):
  index,client=pair;barrier.wait();return client.request(route,{'csrf':race_csrf[index],'id':owner_ids('race')[index]})
 with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:responses=list(pool.map(concurrent_refresh,enumerate([race_a,race_b])))
 check(sum('刷新成功' in response[1] for response in responses)==1 and sum('今日免费刷新次数已用完' in response[1] for response in responses)==1,'Concurrent sessions accept exactly one refresh')
 check(quota('race')==1 and sum(state(id)['refresh_order']>0 for id in owner_ids('race'))==1,'Concurrent refresh has one quota record and one modified listing')
 check(php(f"db()->beginTransaction();query('DELETE FROM xiu_listings WHERE id=?',[{first}]);$available=refreshAvailable({data['users']['owner']['id']});$count=(int)scalar('SELECT COUNT(*) FROM xiu_listing_refreshes WHERE user_id=?',[{data['users']['owner']['id']}]);db()->rollBack();echo json_encode(!$available && $count===1);") ,'Deleting refreshed information cannot restore today quota')
 fresh=Client();fresh.login('owner');check('今日免费刷新：已用完' in fresh.request(manage)[1] and state(first)['refresh_order']==after['refresh_order'],'Refresh and daily quota persist after new login')
 check(php(f"echo json_encode(array_column(listingSearch(['category'=>{category}],1)['items'],'id')); ")[0] in owner_ids('race'),'Latest committed refresh is first before pagination')
 (OUT/'member-refresh-http-results.json').write_text(json.dumps({'passed':len(checks),'checks':checks},ensure_ascii=False,indent=2),encoding='utf-8');print('SUCCESS',len(checks),'refresh HTTP checks.')
except Exception:
 (OUT/'member-refresh-http-results.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'failed':True},ensure_ascii=False,indent=2),encoding='utf-8');raise
