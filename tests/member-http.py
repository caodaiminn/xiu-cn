"""Synthetic documents only; journal fixtures for the guarded cleanup.php script."""
import json,os,re,time,struct,zlib,http.cookiejar,urllib.request,urllib.parse,urllib.error
from pathlib import Path
BASE='http://www.xiu.cn';TOKEN='xiuqa_'+str(int(time.time()));OUT=Path('tests/artifacts')
fixtures={'token':TOKEN,'users':[],'listings':[],'articles':[],'categories':[],'cities':[],'districts':[]};checks=[]
def check(value,label):
 if not value:raise AssertionError(label)
 checks.append(label);print('PASS',label,flush=True)
def remember(kind,value):
 fixtures[kind].append(value);(OUT/'fixtures.json').write_text(json.dumps(fixtures),encoding='utf-8')
def png(color=b'\xff\x88\x33'):
 def chunk(kind,payload):return struct.pack('!I',len(payload))+kind+payload+struct.pack('!I',zlib.crc32(kind+payload)&0xffffffff)
 return b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('!2I5B',1,1,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(b'\x00'+color))+chunk(b'IEND',b'')
class Client:
 def __init__(self):self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def request(self,path,data=None,files=None):
  headers={};payload=None
  if files is not None:
   boundary='MemberQA'+TOKEN;parts=[]
   for key,value in data.items():parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n').encode())
   for role,name,mime,content in files:parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{role}"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n').encode()+content+b'\r\n')
   payload=b''.join(parts)+(f'--{boundary}--\r\n').encode();headers['Content-Type']='multipart/form-data; boundary='+boundary
  elif data is not None:payload=urllib.parse.urlencode(data).encode()
  try:r=self.opener.open(urllib.request.Request(BASE+path,payload,headers),timeout=20)
  except urllib.error.HTTPError as e:r=e
  raw=r.read();return r.status,raw.decode('utf-8','replace'),r.geturl(),r.headers,raw
 def post(self,path,data,files=None):
  page=self.request(path)[1];csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',page).group(1)
  return self.request(path,{'csrf':csrf,**data},files)
def review_row(source,username):
 return next(s for s in re.findall(r'<article class="review-row verification-review".*?</article>',source,re.S) if username in s)
def review_fields(source,username):
 s=review_row(source,username);return {'id':re.search(r'data-verification-id="(\d+)"',s).group(1),'revision':re.search(r'name="revision" value="(\d+)"',s).group(1)}
def docs(source):return list(dict.fromkeys(re.findall(r'/yong-hu-zhong-xin/zheng-jian/\d+/',source)))
def stat(source,name):return int(re.search(r'data-stat="'+name+r'"><strong>(\d+)</strong>',source).group(1))
guest,owner,other,admin=Client(),Client(),Client(),Client();verify='/yong-hu-zhong-xin/shen-fen-ren-zheng/';manage='/dgadmin/verifications/'
try:
 for route in ['/yong-hu-zhong-xin/','/yong-hu-zhong-xin/wo-de-fa-bu/',verify]:check('/deng-lu/' in guest.request(route)[2],'Member route requires login '+route)
 check('/dgadmin/login/' in guest.request(manage)[2],'Verification review requires administrator')
 for client,suffix in [(owner,'member'),(other,'company')]:
  username=TOKEN+'_'+suffix;client.post('/zhu-ce/',{'username':username,'password':'QAMember2026','confirm_password':'QAMember2026'});remember('users',username)
 check(stat(owner.request('/yong-hu-zhong-xin/')[1],'total')==0,'New account has zero real publication counts')
 check('noindex' in owner.request(verify)[3].get('X-Robots-Tag',''),'Member verification is not indexed')
 person={'type':'personal','subject_name':'QA synthetic person'}
 front=('id_front[]','test-front.png','image/png',png());back=('id_back[]','test-back.png','image/png',png(b'\x55\xaa\xee'))
 baseline=len(list(Path('storage/verification').iterdir()))
 failed=owner.post(verify,person,[front]);check('请上传身份证反面' in failed[1],'Personal verification requires both sides')
 check(len(list(Path('storage/verification').iterdir()))==baseline,'Failed incomplete submission removes staged document')
 failed=owner.post(verify,person,[('id_front[]','bad.php','image/jpeg',b'<?php echo 1; ?>'),back]);check('有效的 JPEG' in failed[1],'Fake image content rejected')
 check(owner.request(verify,{'csrf':'wrong',**person})[0]==403,'Certification writes require CSRF')
 check('图片提交格式' in owner.post(verify,person,[('id_front','scalar.png','image/png',png()),back])[1] or '上传格式' in owner.post(verify,person,[('id_front','scalar.png','image/png',png()),back])[1],'Malformed upload shape rejected')
 result=owner.post(verify,person,[front,back]);document_urls=docs(result[1]);check(len(document_urls)==2 and '待审核' in result[1],'Personal documents saved for review')
 for route in document_urls:
  check(owner.request(route)[0]==200,'Owner can view '+route)
  check(guest.request(route)[0]==404 and other.request(route)[0]==404,'Guest and other account blocked '+route)
  check('no-store' in owner.request(route)[3].get('Cache-Control','') and 'noindex' in owner.request(route)[3].get('X-Robots-Tag',''),'Private document headers '+route)
 check(guest.request('/storage/verification/')[0]==404,'Storage directory not public')
 admin.post('/dgadmin/login/',{'username':'daiit','password':os.environ['XIU_ADMIN_PASSWORD']})
 check(all(admin.request(route)[0]==200 for route in document_urls),'Administrator can view review documents')
 fields=review_fields(admin.request(manage)[1],fixtures['users'][0]);old_revision=fields.copy()
 check(admin.request(manage,{'csrf':'bad',**fields,'action':'approve'})[0]==403,'Admin review requires CSRF')
 admin.post(manage,{**fields,'action':'reject','reason':'QA please replace the synthetic front image'})
 check('QA please replace' in owner.request(verify)[1],'Rejected identity includes reason')
 result=owner.post(verify,person,[('id_front[]','replacement.png','image/png',png(b'\x00\xff\xaa'))]);replaced=docs(result[1])
 check(len(replaced)==2 and document_urls[0] not in replaced,'Resubmission replaces selected side and retains the other')
 check(owner.request(document_urls[0])[0]==404,'Replaced document URL revoked')
 check('已改变' in admin.post(manage,{**old_revision,'action':'approve'})[1],'Outdated administrator review cannot approve newer materials')
 fields=review_fields(admin.request(manage)[1],fixtures['users'][0]);admin.post(manage,{**fields,'action':'approve'})
 check('已认证' in owner.request('/yong-hu-zhong-xin/')[1],'Approved certification appears in member dashboard')
 check('已改变' in admin.post(manage,{**fields,'action':'reject','reason':'QA stale action'})[1],'Reviewed certification cannot be reviewed twice')
 before=len(list(Path('storage/verification').iterdir()));failed=owner.post(verify,person,[('id_front[]','large.png','image/png',png()+b'x'*(5*1024*1024))]);check(('每张图片不可超过' in failed[1] or '图片上传失败' in failed[1]) and '已认证' in failed[1],'Oversized replacement preserves approved certification')
 check(len(list(Path('storage/verification').iterdir()))==before,'Failed replacement leaves no staged files')
 result=other.post(verify,{'type':'company','subject_name':'QA company <script>alert(1)</script>','user_id':fields['id']},[('business_license[]','test-license.png','image/png',png())]);company_docs=docs(result[1])
 check(len(company_docs)==1 and '&lt;script&gt;' in result[1],'Company license saved and submitted name escaped')
 check(owner.request(company_docs[0])[0]==404 and 'QA synthetic person' in owner.request(verify)[1],'Forged ownership cannot alter or reveal another identity')
 company_fields=review_fields(admin.request(manage)[1],fixtures['users'][1]);admin.post(manage,{**company_fields,'action':'approve'});check('企业认证' in other.request('/yong-hu-zhong-xin/')[1] and '已认证' in other.request('/yong-hu-zhong-xin/')[1],'Enterprise certification approved')
 failed=owner.post(verify,{'type':'company','subject_name':'QA synthetic new company'},[]);check('请上传营业执照' in failed[1] and '个人认证' in failed[1],'Changing type requires corresponding materials and preserves old record on failure')
 data={'type':'service','title':TOKEN+' member listing','category_id':2,'city_id':2,'address':'QA test address','contact':'QA test','phone':'00000000000','body':'QA synthetic listing for member dashboard checks.'}
 result=owner.post('/fa-bu-xin-xi/',data);listing=int(re.search(r'/fa-bu-xin-xi/\?id=(\d+)',result[1]).group(1));remember('listings',listing)
 check(stat(result[1],'total')==1 and stat(result[1],'pending')==1,'Dashboard counts pending publication')
 admin.post('/dgadmin/listings/',{'id':listing,'action':'approve'});check(stat(owner.request('/yong-hu-zhong-xin/')[1],'published')==1,'Dashboard counts approved publication')
 owner.post('/fa-bu-xin-xi/',{**data,'id':listing});admin.post('/dgadmin/listings/',{'id':listing,'action':'reject','reason':'QA clarify publication'})
 check(stat(owner.request('/yong-hu-zhong-xin/')[1],'rejected')==1,'Dashboard counts rejected publication')
 check('QA clarify publication' in owner.request('/yong-hu-zhong-xin/wo-de-fa-bu/?status=rejected')[1],'Publication filters include rejection details')
 check(TOKEN+' member listing' not in other.request('/yong-hu-zhong-xin/wo-de-fa-bu/')[1],'Member publication list scoped to owner')
 owner.post('/fa-bu-xin-xi/',{**data,'id':listing});owner.post('/yong-hu-zhong-xin/xia-jia/',{'id':listing});check(stat(owner.request('/yong-hu-zhong-xin/')[1],'offline')==1,'Dashboard counts offline publication')
 fresh=Client();fresh.post('/deng-lu/',{'username':fixtures['users'][0],'password':'QAMember2026'});check('已认证' in fresh.request('/yong-hu-zhong-xin/')[1] and stat(fresh.request('/yong-hu-zhong-xin/')[1],'total')==1,'Certification and counts persist after login')
 (OUT/'member-http-results.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'owner':fixtures['users'][0],'company':fixtures['users'][1]},ensure_ascii=False,indent=2),encoding='utf-8');print('SUCCESS',len(checks),'member/verification checks.')
except Exception:
 (OUT/'member-http-results.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'failed':True},ensure_ascii=False,indent=2),encoding='utf-8');raise
