"""Check administrator access and mail forms without sending extra emails."""
import json, os
from pathlib import Path
import requests
from bs4 import BeautifulSoup

ROOT=Path(__file__).resolve().parents[1]
BASE='http://www.xiu.cn'
checks=[]
def check(ok,label):
    if not ok: raise AssertionError(label)
    checks.append(label)

anonymous=requests.Session()
response=anonymous.get(BASE+'/dgadmin/notifications/',timeout=15,allow_redirects=False)
check(response.status_code==303 and '/dgadmin/login/' in response.headers['Location'],'Anonymous requests are redirected to administrator login')
for path in ['/storage/mail/settings.json','/storage/mail/secret.key','/app/mail.php','/scripts/process-mail-queue.php','/vendor/PHPMailer/src/SMTP.php']:
    response=anonymous.get(BASE+path,timeout=15)
    check(response.status_code==404,'Private file is not served: '+path)
client=requests.Session()
soup=BeautifulSoup(client.get(BASE+'/dgadmin/login/',timeout=15).text,'html.parser')
password=os.environ.get('XIU_ADMIN_PASSWORD')
if not password: raise RuntimeError('Set XIU_ADMIN_PASSWORD for the authorized administrator check.')
response=client.post(BASE+'/dgadmin/login/',data={'username':'daiit','password':password,'csrf':soup.select_one('[name="csrf"]')['value']},timeout=15)
check(response.status_code==200 and '/dgadmin/login' not in response.url,'Administrator login succeeds')
response=client.get(BASE+'/dgadmin/notifications/',timeout=15)
soup=BeautifulSoup(response.text,'html.parser')
check(response.status_code==200 and soup.select_one('a.active[href="/dgadmin/notifications/"]'),'Mail notification page is linked in the administrator navigation')
check(soup.select_one('[name="recipient"]')['value']=='5841079@qq.com' and soup.select_one('[name="host"]')['value']=='smtp.exmail.qq.com' and soup.select_one('[name="port"]')['value']=='465','Saved SMTP server and recipient are displayed')
check(soup.select_one('[name="smtp_password"]')['value']=='' and '已设置' in soup.select_one('[name="smtp_password"]')['placeholder'],'Stored SMTP password is not returned to the browser')
check('服务器已接收' in soup.get_text() and '[五修网] 邮件通知测试' in soup.get_text(),'Successful real test is visible in the send history')
forms=soup.find_all('form')
check(all(form.select_one('[name="csrf"]') for form in forms),'All mail operations include CSRF tokens')
for data in [{'action':'test'}, {'action':'save','enabled':'0'}, {'action':'retry','id':'1'}]:
    response=client.post(BASE+'/dgadmin/notifications/',data=data,timeout=15)
    check(response.status_code==403,'Missing CSRF rejects '+data['action']+' before execution')
for path in ['/', '/fen-lei-xin-xi/']:
    response=client.get(BASE+path,timeout=15)
    check(response.status_code==200 and 'X-Xiu-Data-Cache' in response.headers,'Public page and data cache still work: '+path)
(ROOT/'tests/artifacts/mail-notifications-http-results.json').write_text(json.dumps({'passed':len(checks),'checks':checks},ensure_ascii=False,indent=2),encoding='utf-8')
print('SUCCESS '+str(len(checks))+' HTTP checks; no extra emails sent.')
