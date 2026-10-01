<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
$lock=fopen(ROOT.'/storage/init.lock','c');
if(!flock($lock,LOCK_EX|LOCK_NB)) exit("Initialization already running.\n");
$hasAdmins=(bool)scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='xiu_admins'");
$needsAdmin=!$hasAdmins || !(bool)scalar("SELECT COUNT(*) FROM xiu_admins WHERE username='daiit'");
$adminPassword=getenv('XIU_ADMIN_PASSWORD');
if($needsAdmin && (!$adminPassword || strlen($adminPassword)<8 || strlen($adminPassword)>72)) exit("Set XIU_ADMIN_PASSWORD to the initial administrator password (8-72 bytes), then run again.\n");
foreach(explode(';',file_get_contents(ROOT.'/database/schema.sql')) as $statement) if(trim($statement)) db()->exec(trim($statement));
db()->beginTransaction();
try {
    if($needsAdmin) query('INSERT INTO xiu_admins (username,password_hash) VALUES (?,?)',['daiit',password_hash($adminPassword,PASSWORD_DEFAULT)]);
    query("INSERT IGNORE INTO xiu_settings (setting_key,setting_value) VALUES ('site_name','修网')");
    $category=function(string $name,string $slug,string $kind='service',?int $parent=null,int $sort=0): int {
        $existing=scalar('SELECT id FROM xiu_categories WHERE slug=?',[$slug]); if($existing) return (int)$existing;
        query('INSERT INTO xiu_categories (name,slug,kind,parent_id,sort_order) VALUES (?,?,?,?,?)',[$name,$slug,$kind,$parent,$sort]); return (int)db()->lastInsertId();
    };
    if(!scalar("SELECT setting_value FROM xiu_settings WHERE setting_key='base_seeded'")) {
    $groups=[
        ['家电、厨电维修','home-appliance',[['空调维修','air-conditioner',[['家用空调','home-air'],['中央空调','central-air']]],['冰箱维修','refrigerator',[]],['洗衣机维修','washing-machine',[]],['热水器维修','water-heater',[]],['厨电维修','kitchen-appliance',[]]]],
        ['房屋、防水维修','house',[['房屋防水','waterproof',[]],['水电维修','electric-plumbing',[]],['门窗维修','doors-windows',[]],['墙面修复','wall-repair',[]]]],
        ['电脑数码、通讯','digital',[['电脑维修','computer',[]],['手机维修','phone',[]],['网络维护','network',[]]]],
        ['家政、生活服务','life',[['管道疏通','drain',[]],['家电清洗','cleaning',[]],['家具维修','furniture',[]],['开锁换锁','lock',[]]]],
        ['机械设备维修','machinery',[['工程机械','construction-machine',[]],['工业机械','industrial-machine',[]],['空压机维修','compressor',[]]]],
        ['办公设备维修','office',[['打印机维修','printer',[]],['复印机维修','copier',[]],['投影仪维修','projector',[]]]],
        ['交通工具维修','vehicle',[['汽车维修','car',[]],['道路救援','road-rescue',[]],['电动车维修','electric-bike',[]]]],
        ['工控产品维修','industrial-control',[['变频器维修','inverter',[]],['电机维修','motor',[]],['控制器维修','controller',[]]]],
    ];
    foreach($groups as $sort=>[$name,$slug,$children]) {
        $parent=$category($name,$slug,'service',null,$sort);
        foreach($children as $childSort=>[$childName,$childSlug,$leaves]) { $child=$category($childName,$childSlug,'service',$parent,$childSort); foreach($leaves as $leafSort=>[$leafName,$leafSlug]) $category($leafName,$leafSlug,'service',$child,$leafSort); }
    }
    foreach(['knowledge','encyclopedia'] as $kind) foreach([['空调','air'],['洗衣机','washer'],['冰箱','fridge'],['房屋防水','waterproof'],['电脑与数码','digital'],['日常维护','maintenance']] as $sort=>[$name,$slug]) $category($name,$kind.'-'.$slug,$kind,null,$sort);
    $cities=[['北京市','北京','beijing'],['上海市','上海','shanghai'],['广东省','广州','guangzhou'],['广东省','深圳','shenzhen'],['四川省','成都','chengdu'],['浙江省','杭州','hangzhou'],['江苏省','南京','nanjing'],['天津市','天津','tianjin'],['湖北省','武汉','wuhan'],['重庆市','重庆','chongqing'],['山东省','济南','jinan'],['辽宁省','沈阳','shenyang'],['陕西省','西安','xian'],['江苏省','苏州','suzhou'],['江苏省','无锡','wuxi'],['广东省','东莞','dongguan'],['广东省','佛山','foshan'],['浙江省','宁波','ningbo'],['福建省','福州','fuzhou'],['福建省','厦门','xiamen'],['湖南省','长沙','changsha'],['河南省','郑州','zhengzhou'],['河北省','石家庄','shijiazhuang'],['安徽省','合肥','hefei']];
    foreach($cities as $sort=>[$province,$name,$slug]) if(!scalar('SELECT id FROM xiu_cities WHERE slug=?',[$slug])) query('INSERT INTO xiu_cities (province,name,slug,is_hot,sort_order) VALUES (?,?,?,?,?)',[$province,$name,$slug,$sort<14?1:0,$sort]);
    query("INSERT INTO xiu_settings (setting_key,setting_value) VALUES ('base_seeded','1')");
    }
    if(!in_array('--no-demo',$argv,true) && !scalar("SELECT setting_value FROM xiu_settings WHERE setting_key='demo_seeded'")) {
        $demoUser=scalar("SELECT id FROM xiu_users WHERE username='demo'");
        if(!$demoUser) { query('INSERT INTO xiu_users (username,password_hash) VALUES (?,?)',['demo',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]); $demoUser=(int)db()->lastInsertId(); }
        $examples=[
            ['杭州空调清洗与家用空调维修服务','air-conditioner','hangzhou','service'],['北京家用冰箱故障检查与维修','refrigerator','beijing','service'],['上海洗衣机维修与日常保养服务','washing-machine','shanghai','service'],['广州热水器检修与安装服务','water-heater','guangzhou','service'],
            ['深圳厨房电器维修服务信息','kitchen-appliance','shenzhen','service'],['成都卫生间防水与渗漏检查服务','waterproof','chengdu','service'],['南京水电检修与灯具安装服务','electric-plumbing','nanjing','service'],['武汉门窗维修与配件更换服务','doors-windows','wuhan','service'],
            ['苏州电脑维护与系统故障排查','computer','suzhou','service'],['西安手机维修与屏幕更换服务','phone','xian','service'],['济南家庭网络维护服务信息','network','jinan','service'],['无锡管道疏通与排水检查服务','drain','wuxi','service'],
            ['东莞家电清洗与日常维护服务','cleaning','dongguan','service'],['天津家具维修与养护服务信息','furniture','tianjin','service'],['重庆空压机检修与保养服务','compressor','chongqing','service'],['佛山工业机械检修服务信息','industrial-machine','foshan','service'],
            ['宁波打印机检修与耗材维护服务','printer','ningbo','service'],['福州复印机维修服务信息','copier','fuzhou','service'],['长沙汽车检修与日常维护服务','car','changsha','service'],['厦门电动车维修与保养服务','electric-bike','xiamen','service'],
            ['郑州变频器检测与维修服务','inverter','zhengzhou','service'],['合肥电机维护与故障检查服务','motor','hefei','service'],['杭州寻找空调故障检修师傅','home-air','hangzhou','request'],['北京需要洗衣机维修服务','washing-machine','beijing','request'],
            ['上海寻找房屋漏水检查师傅','waterproof','shanghai','request'],['广州需要电脑上门维护服务','computer','guangzhou','request'],['成都寻找打印机故障检修服务','printer','chengdu','request'],['深圳办公室投影仪维修需求','projector','shenzhen','request'],
        ];
        foreach($examples as $index=>[$title,$categorySlug,$citySlug,$type]) {
            if(scalar('SELECT id FROM xiu_listings WHERE user_id=? AND title=? AND is_demo=1',[$demoUser,$title])) continue;
            $description=$type==='service'?"【演示信息】用于展示维修服务发布效果，不提供真实服务。\n\n服务范围：设备检查、日常维护及相关维修咨询。\n联系前可说明设备类型、出现的问题与所在区域，具体安排以双方沟通为准。\n\n正式运营时，请通过后台下架演示内容，并由真实用户发布服务信息。":"【演示需求】用于展示客户求修需求，不是真实订单。\n\n希望寻找本地维修师傅，检查设备问题并了解维修安排。\n沟通时会提供设备类型、使用情况及所在区域。\n\n正式运营时，请用真实需求替换演示信息。";
            query("INSERT INTO xiu_listings (user_id,category_id,city_id,type,title,address,contact,phone,body,status,is_demo,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,'published',1,?,?)",[$demoUser,scalar('SELECT id FROM xiu_categories WHERE slug=?',[$categorySlug]),scalar('SELECT id FROM xiu_cities WHERE slug=?',[$citySlug]),$type,$title,'演示区域 · 地址待填写','演示联系人','000-00000000',$description,date('Y-m-d H:i:s',time()-(count($examples)-$index)*3600),date('Y-m-d H:i:s',time()-(count($examples)-$index)*3600)]);
        }
        $articles=[
            ['knowledge','air','联系空调维修师傅前，先整理这些信息','报修前记录设备型号、故障表现和使用情况，有助于沟通。',"这是一篇原创演示文章，用于展示知识库排版。\n\n一、记录设备信息\n准备品牌、型号和大致使用年限，方便师傅了解设备。\n\n二、描述问题表现\n说明问题出现的时间、是否持续发生，以及面板显示的文字或代码。\n\n三、确认上门安排\n沟通地址、服务时间及检查费用。涉及设备拆卸、电气或制冷系统的操作，请联系专业人员。"],
            ['knowledge','washer','洗衣机报修沟通清单：把问题说清楚','整理运行阶段、异常表现和设备信息，让报修沟通更顺畅。',"这是一篇原创演示文章，用于展示知识库功能。\n\n记录洗衣机的品牌、型号，以及问题发生在洗涤、漂洗还是脱水阶段。\n\n如有面板提示，可以拍照记录，再把信息提供给维修人员。\n\n联系服务方时，确认是否提供检测、维修和配件服务，并询问费用说明。请勿自行拆卸设备。"],
            ['knowledge','waterproof','房屋漏水报修时，需要提供哪些情况','记录漏水位置、发生时间和天气情况，便于安排现场检查。',"这是一篇原创演示文章。\n\n一、描述位置\n说明漏水出现在屋顶、墙面、窗边还是其他部位。\n\n二、记录时间\n记录出现时间，以及是否与降雨或用水有关。\n\n三、整理照片\n拍摄可见区域，记录变化情况。维修方案应由专业人员现场检查后确定。"],
            ['knowledge','digital','电脑送修前的数据与配件交接清单','送修前整理数据备份和设备配件，明确交接事项。',"这是一篇原创演示文章。\n\n送修前，在设备仍能正常访问的情况下备份重要数据。\n\n整理随设备送修的充电器、外接配件等物品，记录外观和故障表现。\n\n与维修服务方确认是否可能清除数据、维修报价和取回时间。不要随意向他人提供与维修无关的账号密码。"],
            ['encyclopedia','air','空调设备铭牌：常见信息说明','认识设备型号、额定参数和制造信息，方便维修沟通。',"这是一个原创演示百科词条。\n\n设备铭牌通常记录型号、额定参数和制造信息，不同设备的标注方式可能有所区别。\n\n型号用于区分产品规格，维修人员可据此查找相关资料。\n\n报修时可提供铭牌照片。具体参数与操作要求，请以设备说明书为准。"],
            ['encyclopedia','fridge','冰箱日常维护与维修：两个概念的区别','日常维护关注清洁和使用习惯，维修处理具体设备故障。',"这是一个原创演示百科词条。\n\n日常维护\n通常指按照说明书要求进行清洁、整理和使用检查。\n\n设备维修\n指对设备故障进行检测和处理，可能涉及专用工具与配件。\n\n当设备出现异常时，应按照说明书咨询厂家或专业维修人员。不要把普通保养当作故障维修。"],
            ['encyclopedia','maintenance','维修服务中的检测、报价与维修','了解维修服务沟通中的常见环节。',"这是一个原创演示百科词条。\n\n检测\n服务方了解设备状况并排查问题的过程。\n\n报价\n说明预计维修项目、配件和费用，实际安排需要双方确认。\n\n维修\n在确认项目后实施的处理工作。\n\n联系服务时，可以先确认检测是否收费、配件来源和后续保障。"],
            ['encyclopedia','digital','设备型号与序列号：报修时怎样区分','型号说明产品系列或规格，序列号用于识别具体设备。',"这是一个原创演示百科词条。\n\n型号\n通常表示设备的产品系列或规格，多台同类设备可以使用相同型号。\n\n序列号\n通常用于识别具体设备，查询方式以厂家说明为准。\n\n报修时，先提供型号和问题表现；如服务方需要序列号，应确认用途后再提供。"],
        ];
        foreach($articles as [$kind,$slug,$title,$summary,$body]) if(!scalar('SELECT id FROM xiu_articles WHERE kind=? AND title=? AND is_demo=1',[$kind,$title])) query("INSERT INTO xiu_articles (category_id,kind,title,summary,body,status,is_demo) VALUES (?,?,?,?,?,'published',1)",[scalar('SELECT id FROM xiu_categories WHERE slug=?',[$kind.'-'.$slug]),$kind,$title,$summary,$body]);
        query("INSERT INTO xiu_settings (setting_key,setting_value) VALUES ('demo_seeded','1')");
    }
    db()->commit();
    require ROOT.'/scripts/migrate-seo.php';
    require ROOT.'/scripts/migrate-category-navigation.php';
require ROOT.'/scripts/migrate-city-directory.php';
    require ROOT.'/scripts/migrate-member-center.php';
    require ROOT.'/scripts/migrate-member-refresh.php';
    require ROOT.'/scripts/migrate-listing-category-index.php';
    require ROOT.'/scripts/migrate-mail-notifications.php';
    require ROOT.'/scripts/migrate-article-rich-text.php';
    require ROOT.'/scripts/migrate-navigation.php';
    echo json_encode(['initialized'=>true,'categories'=>(int)scalar('SELECT COUNT(*) FROM xiu_categories'),'cities'=>(int)scalar('SELECT COUNT(*) FROM xiu_cities'),'listings'=>(int)scalar('SELECT COUNT(*) FROM xiu_listings'),'articles'=>(int)scalar('SELECT COUNT(*) FROM xiu_articles')],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
} catch(Throwable $exception) { if(db()->inTransaction()) db()->rollBack(); throw $exception; }
