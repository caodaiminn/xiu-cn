"""HTTP acceptance tests. Set XIU_ADMIN_PASSWORD; creates isolated QA fixtures."""
import html as html_module
import base64, hashlib, http.cookiejar, json, os, re, struct, time, urllib.error, urllib.parse, urllib.request, zlib
from pathlib import Path

BASE = os.environ.get('XIU_BASE_URL', 'http://www.xiu.cn')
ARTIFACTS = Path(__file__).parent / 'artifacts'
ARTIFACTS.mkdir(exist_ok=True)
TOKEN = 'xiuqa_' + str(int(time.time()))
fixtures = {'token': TOKEN, 'users': [], 'listings': [], 'articles': [], 'categories': [], 'cities': []}
checks = []

def remember(kind, value):
    fixtures[kind].append(value)
    (ARTIFACTS / 'fixtures.json').write_text(json.dumps(fixtures), encoding='utf-8')

def check(condition, name):
    if not condition: raise AssertionError(name)
    checks.append(name)
    print('PASS', name, flush=True)

class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(self, path, data=None, files=None):
        headers = {}
        if files is not None:
            boundary = 'XiuBoundary' + TOKEN
            chunks = []
            for key, value in data.items():
                chunks.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n').encode())
            for name, filename, mime, content in files:
                chunks.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n').encode() + content + b'\r\n')
            payload = b''.join(chunks) + (f'--{boundary}--\r\n').encode()
            headers['Content-Type'] = 'multipart/form-data; boundary=' + boundary
        else:
            payload = urllib.parse.urlencode(data).encode() if data is not None else None
        try: response = self.opener.open(urllib.request.Request(BASE + path, payload, headers), timeout=30)
        except urllib.error.HTTPError as exception: response = exception
        raw = response.read()
        return response.status, raw.decode('utf-8', 'replace'), response.geturl(), raw, response.headers
    def csrf(self, path):
        response = self.request(path)
        match = re.search(r'name="csrf" value="([a-f0-9]+)"', response[1])
        if not match: raise AssertionError('Missing CSRF at ' + path)
        return match.group(1)
    def post(self, path, data, files=None):
        return self.request(path, {'csrf': self.csrf(path), **data}, files)

def png():
    def chunk(kind, payload): return struct.pack('!I',len(payload))+kind+payload+struct.pack('!I', zlib.crc32(kind+payload)&0xffffffff)
    return b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('!2I5B',1,1,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(b'\x00\xff\x88\x33'))+chunk(b'IEND',b'')

guest, user, other, admin = Client(), Client(), Client(), Client()
original_site_name=html_module.unescape(re.search(r'<span class="brand-name">([^<]+)',guest.request('/')[1]).group(1))
settings_changed=False
try:
    for path in ['/', '/services/', '/cities/', '/baixiuquan/', '/baike/', '/city/hangzhou/', '/login/', '/register/']:
        status, html, *_ = guest.request(path)
        check(status == 200 and '服务暂时不可用' not in html, 'Public route ' + path)
    check('/dgadmin/login/' in guest.request('/dgadmin/')[2], 'Anonymous admin access redirected')
    check('/deng-lu/' in guest.request('/publish/')[2], 'Anonymous publishing redirected')
    for path in ['/app/config.php', '/database/schema.sql', '/backup/2026-10-01-xiu-site/nginx.htaccess', '/storage/sessions/', '/.user.ini', '/router.php']:
        check(guest.request(path)[0] in [403,404], 'Private path blocked ' + path)
    check(guest.request('/register/', {'username':'bad','password':'12345678'})[0] == 403, 'CSRF required')
    for client, suffix in [(user,'a'),(other,'b')]:
        name=TOKEN+'_'+suffix
        result=client.post('/register/', {'username':name,'password':'QATestPass2026','confirm_password':'QATestPass2026'})
        remember('users',name)
        check('/yong-hu-zhong-xin/' in result[2], 'Register and login ' + suffix)
    check('已被使用' in guest.post('/register/', {'username':TOKEN+'_a','password':'QATestPass2026','confirm_password':'QATestPass2026'})[1], 'Duplicate username rejected')
    check('账号或密码不正确' in guest.post('/login/', {'username':'daiit','password':'invalid-password'})[1], 'Invalid credentials rejected')
    services = user.request('/publish/')[1]
    category = re.search(r'<select name="category_id".*?<option value="(\d+)"', services, re.S).group(1)
    city = re.search(r'<select name="city_id".*?<option value="(\d+)"', services, re.S).group(1)
    data={'type':'service','title':TOKEN+' service <script>alert(1)</script>','category_id':category,'city_id':city,'address':'QA test address','contact':'QA contact','phone':'00000000000','body':TOKEN+' body <script>alert(1)</script>\nSecond line preserved.'}
    bad=user.post('/publish/',data,[('images[]','bad.php','image/jpeg',b'<?php echo "malicious";?>')])
    check('请上传有效' in bad[1] and TOKEN+' service' in bad[1], 'Invalid upload rejected and text preserved; errors='+str(re.findall(r'<div class="notice error"[^>]*>(.*?)</div>',bad[1])))
    large_png=png()+b'x'*(3*1024*1024)
    result=user.post('/publish/',data,[('images[]','photo.png','image/png',large_png)])
    check('/yong-hu-zhong-xin/' in result[2] and '待审核' in result[1], 'Upload over 2MB and submit for review')
    listing=int(re.search(r'/fa-bu-xin-xi/\?id=(\d+)',result[1]).group(1)); listing_url=re.search(r'href="(/wei-xiu-xin-xi/[a-z]{1,8}[0-9]{4}\.html)"',result[1]).group(1); remember('listings',listing)
    check(guest.request(f'/info/{listing}/')[0] == 404, 'Pending information private')
    preview=user.request(f'/info/{listing}/')[1]
    check('&lt;script&gt;' in preview and '<script>alert(1)</script>' not in preview, 'User HTML escaped')
    image=int(re.search(r'/media/(\d+)/',preview).group(1))
    check(guest.request(f'/media/{image}/')[0] == 404, 'Pending image private')
    image_result=user.request(f'/media/{image}/')
    check(image_result[0]==200 and len(image_result[3])<2000 and image_result[4]['Content-Type']=='image/png', 'Image re-encoded and served safely')
    check(other.request(f'/publish/?id={listing}')[0] == 404, 'Cross-user editing blocked')
    check(other.post('/publish/',{**data,'id':listing})[0] == 404, 'Forged ownership POST blocked')
    check(user.request(f'/user/offline/?id={listing}')[0] == 405, 'GET cannot mutate status')
    result=admin.post('/dgadmin/login/', {'username':'daiit','password':os.environ['XIU_ADMIN_PASSWORD']})
    check(result[2].rstrip('/').endswith('/dgadmin'), 'Administrator login')
    for section in ['categories','cities','users','listings','articles','settings']:
        check(admin.request('/dgadmin/'+section+'/')[0] == 200, 'Admin page ' + section)
    check(admin.request('/dgadmin/listings/', {'action':'approve','id':listing,'csrf':'invalid'})[0]==403, 'Admin writes require CSRF')
    admin.post('/dgadmin/listings/', {'action':'reject','id':listing,'reason':'QA please clarify service area'})
    check('QA please clarify' in user.request('/user/')[1], 'Rejection reason visible to owner')
    data['id']=listing
    user.post('/publish/',data)
    check('待审核' in user.request('/user/')[1], 'Rejected information resubmitted')
    admin.post('/dgadmin/listings/', {'action':'approve','id':listing})
    check(guest.request(f'/info/{listing}/')[0] == 200, 'Approved information public')
    search=guest.request('/services/?'+urllib.parse.urlencode({'q':TOKEN,'category':category,'city':city,'type':'service'}))[1]
    check(listing_url in search and '1</strong>' in search, 'Combined keyword category city type filter')
    check('暂无匹配信息' in guest.request('/services/?q='+urllib.parse.quote("' OR 1=1 --"))[1], 'SQL injection treated as keyword')
    check('aria-current="page"' in guest.request('/services/?page=2')[1], 'Public pagination')
    data['title']=TOKEN+' edited service'
    user.post('/publish/',data)
    check(guest.request(f'/info/{listing}/')[0] == 404, 'Editing published information returns to review')
    admin.post('/dgadmin/listings/', {'action':'approve','id':listing})
    user.post('/user/offline/', {'id':listing})
    check(guest.request(f'/info/{listing}/')[0] == 404 and '已下架' in user.request('/user/')[1], 'Owner can take information offline')
    data['type']='request'; data['title']=TOKEN+' request information'
    user.post('/publish/',data); admin.post('/dgadmin/listings/', {'action':'approve','id':listing})
    check('求修需求' in guest.request(f'/info/{listing}/')[1], 'Request type supported')
    category_slug=TOKEN.replace('_','-')+'-category'
    category_data={'name':TOKEN+' category','slug':category_slug,'kind':'service','parent_id':0,'sort_order':900,'enabled':1}
    result=admin.post('/dgadmin/categories/', category_data)
    # Find the exact table row instead of matching another record's edit link.
    table_row=re.search(r'<tr><td>'+re.escape(TOKEN+' category')+r'</td>(.*?)</tr>',result[1],re.S).group(1)
    qa_category=int(re.search(r'\?edit=(\d+)',table_row).group(1)); remember('categories',qa_category)
    check('操作已保存' in result[1], 'Category creation')
    city_data={'name':TOKEN+' city','province':'QA province','slug':TOKEN.replace('_','-')+'-city','is_hot':1,'sort_order':900,'enabled':1}
    result=admin.post('/dgadmin/cities/',city_data)
    table_row=re.search(r'<tr><td>QA province</td><td>'+re.escape(TOKEN+' city')+r'</td>(.*?)</tr>',result[1],re.S).group(1)
    qa_city=int(re.search(r'\?edit=(\d+)',table_row).group(1)); remember('cities',qa_city)
    check('操作已保存' in result[1] and 'QA province' in guest.request('/cities/')[1], 'City creation and grouping')
    data.update(category_id=qa_category,city_id=qa_city)
    user.post('/publish/',data); admin.post('/dgadmin/listings/', {'action':'approve','id':listing})
    admin.post('/dgadmin/categories/',{**category_data,'id':qa_category,'enabled':0})
    check(guest.request(f'/info/{listing}/')[0]==404, 'Disabled category hides information')
    admin.post('/dgadmin/categories/',{**category_data,'id':qa_category})
    admin.post('/dgadmin/cities/',{**city_data,'id':qa_city,'enabled':0})
    check(guest.request(f'/info/{listing}/')[0]==404, 'Disabled city hides information')
    admin.post('/dgadmin/cities/',{**city_data,'id':qa_city})
    check(guest.request(f'/info/{listing}/')[0]==200, 'Re-enabling restores public information')
    in_use_delete=admin.post('/dgadmin/categories/',{'id':qa_category,'action':'delete'})
    check('仍被引用' in in_use_delete[1], 'Referenced category cannot be deleted')
    in_use_delete=admin.post('/dgadmin/cities/',{'id':qa_city,'action':'delete'})
    check('仍被引用' in in_use_delete[1], 'Referenced city cannot be deleted')
    cycle=admin.post('/dgadmin/categories/',{**category_data,'id':qa_category,'parent_id':qa_category})
    check('自身或其子分类' in cycle[1], 'Category cycle rejected')
    # Base category home-air is already at level three.
    leaf_row=re.search(r'<tr><td>家用空调</td>(.*?)</tr>', admin.request('/dgadmin/categories/')[1],re.S).group(1)
    leaf_id=int(re.search(r'\?edit=(\d+)',leaf_row).group(1))
    too_deep=admin.post('/dgadmin/categories/',{**category_data,'slug':category_slug+'-deep','parent_id':leaf_id})
    check('最多支持三级' in too_deep[1], 'Fourth category level rejected')
    parent=root=int(category)
    child_data={**category_data,'name':TOKEN+' child','slug':category_slug+'-child','parent_id':qa_category}
    result=admin.post('/dgadmin/categories/',child_data)
    table_row=re.search(r'<tr><td>'+re.escape(TOKEN+' child')+r'</td>(.*?)</tr>',result[1],re.S).group(1)
    child_id=int(re.search(r'\?edit=(\d+)',table_row).group(1))
    data['category_id']=child_id
    user.post('/publish/',data); admin.post('/dgadmin/listings/', {'action':'approve','id':listing})
    admin.post('/dgadmin/categories/',{**category_data,'id':qa_category,'enabled':0})
    check(guest.request(f'/info/{listing}/')[0]==404, 'Disabled parent category hides child content')
    admin.post('/dgadmin/categories/',{**category_data,'id':qa_category})
    data['category_id']=qa_category
    user.post('/publish/',data); admin.post('/dgadmin/listings/', {'action':'approve','id':listing})
    result=admin.post('/dgadmin/categories/',{'id':child_id,'action':'delete'})
    check(TOKEN+' child' not in result[1], 'Unused category deletion')
    oversized=user.post('/publish/',data,[('images[]','oversized.png','image/png',png()+b'x'*(5*1024*1024))])
    check('图片上传失败' in oversized[1], 'Upload above 5MB rejected')
    excessive=user.post('/publish/',data,[('images[]',f'photo{i}.png','image/png',png()) for i in range(6)])
    check('每次最多上传 5 张' in excessive[1], 'Six uploaded images rejected')
    check(guest.request(f'/info/{listing}/')[0]==200, 'Failed edit preserves published state')
    category_form=admin.request('/dgadmin/articles/')[1]
    knowledge_category=re.search(r'<select name="category_id".*?<option value="(\d+)"',category_form,re.S).group(1)
    article_data={'kind':'knowledge','title':TOKEN+' knowledge article','summary':'QA summary','body':TOKEN+' article body <script>alert(1)</script>','category_id':knowledge_category,'status':'draft'}
    result=admin.post('/dgadmin/articles/',article_data,[('images[]','cover.png','image/png',png())])
    table_row=re.search(r'<tr><td class="wide-cell"><a href="(/wei-xiu-zhi-shi-ku/[a-z]{1,8}[0-9]{4}\.html)"[^>]*>'+re.escape(article_data['title'])+r'</a>(.*?)</tr>',result[1],re.S)
    article=int(re.search(r'(?:&amp;|\?)edit=(\d+)',table_row.group(2)).group(1)); remember('articles',article)
    check(guest.request(f'/baixiuquan/{article}.html')[0]==404, 'Article draft private')
    admin.post('/dgadmin/articles/',{**article_data,'id':article,'status':'published'})
    check(guest.request(f'/baixiuquan/{article}.html')[0]==200 and '&lt;script&gt;' in guest.request(f'/baixiuquan/{article}.html')[1], 'Knowledge article published with escaped text')
    check('1</strong>' in guest.request('/baixiuquan/?q='+TOKEN)[1], 'Knowledge article search')
    article_html=guest.request(f'/baixiuquan/{article}.html')[1]
    cover_id=int(re.search(r'/media/(\d+)/',article_html).group(1))
    check(guest.request(f'/media/{cover_id}/')[0]==200, 'Published article cover visible')
    admin.post('/dgadmin/articles/',{**article_data,'id':article,'status':'published','remove_cover':1})
    check(guest.request(f'/media/{cover_id}/')[0]==404, 'Removed cover no longer served')
    # Confirm the encyclopedia uses separate categories and routes.
    encyclopedia_form=admin.request('/dgadmin/articles/?kind=encyclopedia')[1]
    encyclopedia_category=re.search(r'<select name="category_id".*?<option value="(\d+)"',encyclopedia_form,re.S).group(1)
    result=admin.post('/dgadmin/articles/?kind=encyclopedia',{**article_data,'kind':'encyclopedia','title':TOKEN+' encyclopedia','category_id':encyclopedia_category,'status':'published'})
    encyclopedia_row=re.search(r'<tr><td class="wide-cell"><a href="/wei-xiu-bai-ke/[a-z]{1,8}[0-9]{4}\.html"[^>]*>'+re.escape(TOKEN+' encyclopedia')+r'</a>(.*?)</tr>',result[1],re.S).group(1)
    encyclopedia=int(re.search(r'(?:&amp;|\?)edit=(\d+)',encyclopedia_row).group(1)); remember('articles',encyclopedia)
    check(guest.request(f'/baike/{encyclopedia}.html')[0]==200, 'Encyclopedia published')
    settings_changed=True
    admin.post('/dgadmin/settings/',{'site_name':'修网 QA'})
    check('修网 QA' in guest.request('/')[1], 'Site name setting persisted')
    admin.post('/dgadmin/settings/',{'site_name':original_site_name})
    settings_changed=False
    # Disable/re-enable only the newly created account.
    users_html=admin.request('/dgadmin/users/')[1]
    user_row=re.search(r'<tr><td>'+re.escape(TOKEN+'_a')+r'</td>(.*?)</tr>',users_html,re.S).group(1)
    user_id=int(re.search(r'name="id" value="(\d+)"',user_row).group(1))
    admin.post('/dgadmin/users/',{'id':user_id,'action':'toggle'})
    check('/deng-lu/' in user.request('/user/')[2] and guest.request(f'/info/{listing}/')[0]==404, 'Disabled user cannot manage or expose content')
    admin.post('/dgadmin/users/',{'id':user_id,'action':'toggle'})
    fresh=Client()
    fresh.post('/login/',{'username':TOKEN+'_a','password':'QATestPass2026'})
    check(TOKEN+' request information' in fresh.request('/user/')[1], 'Database persists after new login')
    cookie_header=Client().request('/deng-lu/')[4].get('Set-Cookie','')
    check('HttpOnly' in cookie_header and 'SameSite=Lax' in cookie_header, 'Session cookie is HttpOnly and SameSite')
    (ARTIFACTS/'smoke-results.json').write_text(json.dumps({'base_url':BASE,'checks':checks,'passed':len(checks)},ensure_ascii=False,indent=2),encoding='utf-8')
    print('SUCCESS',len(checks),'checks')
except Exception:
    (ARTIFACTS/'smoke-results.json').write_text(json.dumps({'base_url':BASE,'checks':checks,'passed':len(checks),'failed':True},ensure_ascii=False,indent=2),encoding='utf-8')
    raise

finally:
    if settings_changed: admin.post('/dgadmin/settings/',{'site_name':original_site_name})
