import collections,hashlib,json,pathlib,re,sys
if len(sys.argv)!=2:raise SystemExit('Usage: prepare-workbook-listings.py REPORT_DIRECTORY containing source-records.json and catalog.json')
folder=pathlib.Path(sys.argv[1]).resolve()
data=json.loads((folder/'source-records.json').read_text(encoding='utf-8'));catalog=json.loads((folder/'catalog.json').read_text(encoding='utf-8'))
categories=catalog['categories'];byid={int(c['id']):c for c in categories}
roots={'机械维修':23,'家政生活服务':18,'工控产品维修':35,'家电厨电维修':1,'电脑数码通讯':14,'建筑防水维修':9,'办公设备维修':27,'交通工具维修':31,'工具仪器维修':None,'物品回收':None}
rootnames={'工具仪器维修':'工具仪器维修','物品回收':'物品回收'}
normalize=lambda s:re.sub(r'[\s、，,/\-]','',s).replace('其它','其他').replace('类','').lower()
aliases={'工程机械维修':'工程机械','工业机械维修':'工业机械','中央空调维修':'中央空调','屋面防水补漏':'屋面防水','投影机维修':'投影仪维修','电动自行车维修':'电动车维修','台式电脑维修':'电脑维修','网络设备维护':'网络维护','电脑数码、通讯维修':'电脑数码、通讯','建筑、防水维修':'房屋、防水维修','机械维修':'机械设备维修','家政、生活服务类':'家政、生活服务','家电、厨电维修':'家电、厨电维修','制冰机维修':'制冰机维修','电烤箱维修':'烤箱维修','抽油烟机维修':'油烟机维修','扫地机器人':'扫地机器人维修','洗碗机维修':'洗碗机维修','CNC维修':'数控机床维修','仪器仪表维修':'仪器仪表维修'}
def descendants(c,root):
    while c:
        if int(c['id'])==root:return True
        c=byid.get(c['parent_id'])
    return False
plans={}; accepted=[]; skipped=[]; seen={}
# Explicitly unrelated rows are retained in the audit, never relabelled as the claimed repair.
misplaced={7,8,9,10,12,75,139,140,142,143,151,152,153,222,223,224,227,242,243,244,245,296,297,298,300,321,357,358,359,360,361,377,379,387,388,390,409,420,434,435,528,529,530,547,548,549,550,572}
service=re.compile(r'维修|检修|修理|修复|修补|维护|保养|安装|疏通|清洗|清理|清淤|防水|防腐|补漏|恢复|翻新|改造|更换|回收|开锁|换锁|搬家|保洁|打孔|切割|打捞|打蜡|贴膜|装修|改装|移机|拆装|拆除|调试|打井|修井|洗井|施工|救援|修缝纫机|服务热线|报修|修沙发|换皮')
reclass={}
def move(ids,root,category):
    for i in ids:reclass[i]=(root,category)
move([5,7,8,10],'建筑防水维修','潜水打捞')
move([46,91,95],'物品回收','工业设备回收')
move([47],'机械维修','化工设备及机械维修')
move([71,72,73],'建筑防水维修','地面维修')
move([75],'家政生活服务','麻将机维修')
move([139],'机械维修','工业机械维修')
move([143],'工控产品维修','其他工控维修')
move([222,223,224],'建筑防水维修','暖通维修')
move([227],'家政生活服务','化粪池、污水池清理')
move([243],'家电厨电维修','空调维修')
move([244],'机械维修','医疗设备维修')
move([245],'家电厨电维修','其它家电维修')
move([296,298],'家政生活服务','开锁换锁')
move([297],'家政生活服务','沙发翻新护理')
move([300],'机械维修','医疗设备维修')
move([358,359,360],'家政生活服务','瓷砖空鼓维修')
move([321],'家政生活服务','门窗维修')
move([377],'工控产品维修','工控产品维修')
move([379],'工控产品维修','触摸屏维修')
move([387],'工控产品维修','变频器维修')
move([388],'工控产品维修','直流调速器维修')
move([409],'工控产品维修','机器人维修')
move([420],'工控产品维修','控制器维修')
move([434,435],'机械维修','工业机械维修')
move([528,529,530],'家电厨电维修','壁挂炉维修')
move([547,548,549,550,583,584,585],'家电厨电维修','燃气灶维修')
move([572],'家电厨电维修','电饭煲维修')
move([593,597,617,618,622,623,624,767],'家电厨电维修','其它家电维修')
move([619,620],'家政生活服务','咖啡机维修')
move([602,603,604,605],'家电厨电维修','净水机维修')
move([632,634],'家电厨电维修','热水器维修')
move([633,635],'家电厨电维修','太阳能热水器维修')
move([638],'家政生活服务','洗地机维修')
move([643,645],'家政生活服务','保险柜维修')
move([722,777,778,779,780],'电脑数码通讯','笔记本维修')
move([733,734,735],'电脑数码通讯','安防监控')
move([747,748],'电脑数码通讯','数据恢复')
move([754],'电脑数码通讯','数码摄像机维修')
move([756,757],'物品回收','数码电子回收')
move([773],'电脑数码通讯','服务器维修')
move([774],'电脑数码通讯','复读机游戏机PDA维修')
move([775],'家电厨电维修','音响维修')
move([802,805],'建筑防水维修','卫生间防水')
move([803,804,812],'建筑防水维修','房屋防水')
move([1061,1062,1064,1065],'物品回收','其他回收')
move([1063],'物品回收','数码电子回收')
move([242],'家电厨电维修','其它家电维修')
move([837,838,839,840],'建筑防水维修','其它建筑防水')
move([852,853,854,855],'建筑防水维修','小区道路维修')
move([876,877,878,880],'建筑防水维修','房屋防水')
move([902,903,904,905],'家电厨电维修','抽油烟机维修')
move([962,967,972,973,974,975,986,987,988,989,990,1001,1002,1003,1004,1005,1012,1013],'交通工具维修','道路救援')
move([1011],'家电厨电维修','其它家电维修')
move([1014,1015],'机械维修','其他设备维修')
move([1016,1017,1018,1019,1020],'机械维修','医疗设备维修')
move([1022],'物品回收','工业设备回收')
misplaced-=set(reclass)
misplaced.update({2,3,4,33,595})
for r in data['records']:
    seq=int(r['sequence']);reason=None;phone=re.sub(r'[^0-9+]','',r['phone'])
    if seq in reclass:r=dict(r,source_category=r['category'],source_root=r['root'],root=reclass[seq][0],category=reclass[seq][1])
    if not re.fullmatch(r'(?:1[3-9]\d{9}|0\d{9,11}|(?:400|800)\d{7})',phone):reason='联系电话缺失或格式不完整'
    elif seq in misplaced:reason='服务内容与原分类不符'
    elif re.search(r'繁殖基地|刷卡机办理|刷卡机领取|办卡|信用卡|代理商防坑|招商加盟',r['title']):reason='非维修服务广告'
    elif not service.search(r['title']):reason='仅产品销售或服务内容不足'
    if reason:skipped.append(dict(r,reason=reason));continue
    parent=roots[r['root']];targetname=aliases.get(r['category'],r['category'])
    matches=[c for c in categories if normalize(c['name'])==normalize(targetname) and (parent is None or descendants(c,parent))]
    if not matches:matches=[c for c in categories if normalize(c['name'])==normalize(targetname)]
    target=matches[0] if matches else None
    if target:
        key='existing:'+str(target['id']); plan={'key':key,'id':target['id'],'name':target['name'],'parent_id':target['parent_id']}
    else:
        key='new:'+hashlib.sha256((r['root']+'|'+r['category']).encode()).hexdigest()[:12]
        isroot=normalize(r['category'])==normalize(r['root'])
        plan={'key':key,'id':None,'name':targetname,'parent_id':parent,'root_name':rootnames.get(r['root']), 'is_root':isroot}
    title=r['title'].replace('\u200b','').strip()
    title=re.sub(r'\b1[3-9]\d{9}\b','',title)
    title=re.sub(r'\[[^\]]+\]|【[^】]+】','',title)
    title=re.sub(r'20\d{2}年?\s*\d{0,2}月?','',title)
    title=re.sub(r'(?:全国统一|全国)?\s*(?:24小时)?\s*(?:人工)?(?:维修)?(?:服务)?热线.*$','维修预约服务',title)
    title=re.sub(r'(?<=空调)\s*年.*$','维修预约服务',title)
    title=re.sub(r'全国统一|全国|全市|本地|附近|同城|正规|专业|优质|靠谱|惠民|推荐|口碑好|老师傅坐镇|别慌|极速响应|优先派单|就近派单|师傅直派|师傅带工具|快速响应|全时段|全天候|随叫随到|当天可约|价格透明|收费合理|消费透明|按标收费|明码标价|收费公道|省心省力|品质保障|真实测评|实地测评|实用干货|无忧|原厂预约入口|原厂五金|原厂配件|品牌认证师傅|公安备案|持证安装|持证维保|认证杨师傅|不通不收费|不收尾款|保修免费|不收费|免费检测|技术过硬|技术可靠|技术专业可靠|经验丰富|技术专业|有保障|服务保障|无痕上门|质保无忧|维修有保障','',title)
    title=re.sub(r'(?:24\s*(?:小时|h)|15分钟|\d+分钟|\d+米处)|top行业榜推荐|正式启用.*|全时段.*|全天候.*','',title,flags=re.I)
    title=re.sub(r'(?<=销售)[a-z0-9]{6,}$','',title)
    for c in sorted(catalog['cities'],key=lambda c:len(c['name']),reverse=True):
        n=c['name'];title=re.sub(r'^'+re.escape(n)+r'(?:市)?','',title)
    title=title.replace('跳匝','跳闸').replace('电饼档','电饼铛').replace('上门哪家好','预约服务').replace('哪家强','预约服务').replace('找谁','预约').replace('怎么选','预约').replace('怎么收费','费用咨询').replace('多少钱','费用咨询').replace('维修电话','维修预约').replace('报修热线电话','报修预约').replace('服务网点查询','服务咨询').replace('400统一','').replace('官方','').replace('授权','')
    parts=[p.strip(' ，,、-：:；;。.!！？?（）()') for p in re.split(r'[|｜·_—]+',title)]
    parts=list(dict.fromkeys(p for p in parts if p))
    title='，'.join(parts).strip(' ，,、-：:；;。.!！？?（）()')
    if not title:skipped.append(dict(r,reason='清理后没有有效标题'));continue
    if not service.search(title):title+='预约咨询'
    title=title.replace('服务电话推出人工服务热线','维修预约服务').replace('人工报修服务热线电话','维修预约服务').replace('服务热线:、上门，','维修预约服务')
    title=title.replace('欢迎咨询','预约咨询').replace('各种','多种').replace('上门维修','上门检修').replace('维修中心','检修服务').replace('故障处理维修','故障检修').replace('维修厂','检修服务').replace('收费合理','费用咨询')
    title=re.sub(r'，?老师傅，?|多年经验值得信赖|效率高|随时待命及时|支持寄修\)?|商家排行|师傅口碑榜|靠谱商家|品牌认证|口碑榜|商家排行|TOP\d*|最佳|最好|滑轨顺滑如新|电话秒接|极速|实在的|高价|全国联保|厂家官方|解决一切|彻底解决','',title,flags=re.I)
    title=re.sub(r'[，,、]+','，',title).strip('， ')
    if title==r['title']:title=title.removesuffix('服务')+'预约咨询'
    if len(title)>85:title=title[:85].rstrip('，、 ')
    if not title.endswith(('服务','咨询','检修','维修','保养','安装','清洗','修复')):title+='服务咨询'
    fingerprint=hashlib.sha256((key+'|'+phone+'|'+re.sub(r'[^\w\u4e00-\u9fff]','',title).lower()).encode()).hexdigest()
    if fingerprint in seen:skipped.append(dict(r,reason='同分类、电话及改写标题重复',duplicate_of=seen[fingerprint]));continue
    seen[fingerprint]=seq;plans[key]=plan
    company=re.sub(r'第\d+年$','',r['company']).strip(' []【】')
    contact=company if 0<len(company)<=64 and not re.search(r'频道|平台|为您|为你|提供|解决|汇集|拨打|我们|一站式|故障|专业的',company) else '业务咨询'
    accepted.append(dict(r,optimized_title=title,normalized_phone=phone,contact=contact,category_key=key,fingerprint=fingerprint))
batch={'source_hash':data['source_hash'],'sheet':data['sheet'],'nationwide_confirmed':True,'category_plan':list(plans.values()),'records':accepted}
(folder/'batch.json').write_text(json.dumps(batch,ensure_ascii=False,indent=2),encoding='utf-8')
(folder/'skipped.json').write_text(json.dumps(skipped,ensure_ascii=False,indent=2),encoding='utf-8')
counts=collections.Counter(r['category_key'] for r in accepted)
report={'source_rows':len(data['records']),'accepted_rows':len(accepted),'skipped_rows':len(skipped),'skipped_reasons':dict(collections.Counter(r['reason'] for r in skipped)),'target_categories':len(plans),'new_categories':sum(p['id'] is None for p in plans.values()),'category_counts':[dict(plans[k],count=n) for k,n in counts.items()]}
(folder/'preflight.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
with (folder/'title-preview.html').open('w',encoding='utf-8') as f:
    import html
    f.write('<!doctype html><meta charset="utf-8"><title>分类导入与标题改写预览</title><style>body{font:14px/1.7 Arial;margin:30px}table{border-collapse:collapse;width:100%}td,th{border-bottom:1px solid #ddd;padding:9px;text-align:left}th{background:#fff3e8}</style><h1>分类导入与标题改写预览</h1><p>全国服务范围已由网站负责人确认；原始行号及字段保存在私有导入记录中。</p><table><tr><th>原表行号</th><th>分类</th><th>原始标题</th><th>改写标题</th></tr>')
    for r in accepted:f.write('<tr>'+''.join('<td>'+html.escape(str(v))+'</td>' for v in [r['source_row'],plans[r['category_key']]['name'],r['title'],r['optimized_title']])+'</tr>')
    f.write('</table>')
print(json.dumps({k:v for k,v in report.items() if k!='category_counts'},ensure_ascii=False))
