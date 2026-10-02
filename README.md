# 修网 · PHP＋MySQL 维修分类网站

源码仓库为 `caodaimin/xiu-cn`（私有），默认分支为 `main`。仓库包含源码、数据库表结构、离线IP查询资源、第三方许可及测试脚本。`app/config.php`、`storage/` 内配置密钥与运行数据、`backup/`、`reports/` 和 `tests/artifacts/` 留在本地，不随Git上传；数据库内容、会员上传图片及认证材料也不包含在仓库中。部署时按下文创建私有数据库配置，运行初始化及对应迁移，并让PHP可写 `storage/`。

已在本地 phpStudy 配置并运行。访问地址：

- 首页：<http://www.xiu.cn/>
- 分类信息：<http://www.xiu.cn/fen-lei-xin-xi/>
- 城市服务：<http://www.xiu.cn/selectcity.html>
- 知识库：<http://www.xiu.cn/wei-xiu-zhi-shi-ku/>
- 百科：<http://www.xiu.cn/wei-xiu-bai-ke/>
- 网站地图：<http://www.xiu.cn/wang-zhan-di-tu/>
- 会员发布管理中心：<http://www.xiu.cn/yong-hu-zhong-xin/>
- 管理后台：<http://www.xiu.cn/dgadmin/index.php>

管理员账号为 `daiit`，初始密码使用本次指定的密码；数据库账号、密码在本地私有文件 `app/config.php` 中配置，密码不写入这份说明。

## 运行环境与目录

使用 PHP 8.0.2、MySQL 5.7.26、Nginx；需要 PDO MySQL、mbstring、fileinfo、GD、session 扩展，智谱AI功能另需 cURL、OpenSSL 及有效的CA证书配置。本项目无需 Composer、Node 构建或联网加载前端资源。

- `public/`：唯一公开的网站根目录，包含 PHP 入口、静态样式和脚本。
- `app/`：数据库连接、业务控制器、模型及辅助函数。
- `views/`：前台和后台的服务端模板。
- `database/schema.sql`、`scripts/init.php`：表结构和 CLI 初始化程序。
- `storage/`：图片、服务器会话、登录限流记录、错误日志，禁止公开访问。
- `tests/`：HTTP 验收脚本、页面检查脚本及验收结果截图。
- `backup/2026-10-01-xiu-site/`：修改前的项目文件恢复备份。

phpStudy 中保持 Nginx、MySQL、PHP 服务启动即可。`www.xiu.cn` 的 hosts 映射保持 `127.0.0.1`，网站根目录已调整为 `D:/phpstudy_pro/WWW/www.xiu.cn/public`。没有变更公网 DNS，也没有发布到外部托管平台。

后台通过真实文件 `public/dgadmin/index.php` 访问，使用 `section` 参数选择管理功能，不依赖伪静态。例如登录 `/dgadmin/index.php?section=login`、认证审核 `/dgadmin/index.php?section=verifications`、百科编辑 `/dgadmin/index.php?section=articles&edit=8&kind=encyclopedia`。后台导航、编辑、筛选、分页、表单、认证材料、文章封面和通知邮件均使用此入口。旧后台路径在启用前台路由时自动跳转并保留查询参数；前台继续使用原有伪静态地址。

Nginx 当前站点已添加精确匹配的 `location = /dgadmin/index.php`，配置内容与 `/index.php` 的 PHP 处理块一致；迁移服务器时也需配置此实际 PHP 文件的处理。Apache 的 `public/.htaccess` 为真实目录保留 `DirectoryIndex index.php`，伪静态指令包在 `IfModule` 中，关闭重写模块时后台仍可直接访问。源码备份为 `backup/2026-10-02-admin-php-entry/`；本机 Nginx 配置备份为 `C:/Users/Administrator/.codex/backup/global-config/2026-10-02-admin-php-entry/`。

后台 PHP 入口通过46项浏览器/HTTP检查：在不加载路由脚本的 PHP 内置服务及当前 Nginx 分别检查11个管理页面、编辑链接、富文本、城市区域筛选、状态筛选、每页数量和翻页、导航新增编辑删除、图片与认证材料查看、登录与退出、权限及CSRF，并检查旧地址跳转和前台入口。结果为 `tests/artifacts/admin-php-entry-results.json`；临时账号、导航、图片、认证和会话已清理，原导航逐条比对保持一致。Apache 配置已调整，但未在 Apache 服务实测。复验时先启动 `php -S 127.0.0.1:8817 -t public`（无需 router.php），运行 `tests/admin-php-entry-fixture.php`、`node tests/admin-php-entry.cjs`，结束后始终执行 `tests/admin-php-entry-fixture.php cleanup` 并停止临时服务器；仅在本地或测试环境运行。

## 数据库初始化

数据库名 `xiu`，业务表以 `xiu_` 开头。`schema.sql` 只创建结构；`init.php` 创建管理员、基础分类城市及明确标注的原创演示内容。

在新的服务器上先创建数据库和账号，参照 `app/config.example.php` 创建私有的 `app/config.php`，确认 PHP 对 `storage/` 有写权限。首次初始化需要提供管理员密码：

```powershell
Set-Location 'D:\phpstudy_pro\WWW\www.xiu.cn'
$env:XIU_ADMIN_PASSWORD = '<初始管理员密码>'
try {
    & 'D:\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe' scripts\init.php
} finally {
    Remove-Item Env:XIU_ADMIN_PASSWORD
}
```

首次安装不需要演示信息时，运行 `scripts\init.php --no-demo`。已初始化后可直接再次运行 `init.php`，不需要重复提供密码；已有管理员密码和数据不会被覆盖。基础种子数据和演示内容分别记录初始化标记，后台修改或删除内容后，后续初始化不会重新填回旧内容。

当前基础数据为 89 个分类、24 个城市、11 个广州区域、28 条演示分类信息、8 篇原创演示知识/百科文章。演示账号 `demo` 使用随机密码，无法用公开的固定密码登录。正式运营前，可在后台下架演示信息、删除演示文章或停用 `demo` 用户。

## 使用流程

首页首次访问按 IP 默认选择城市，首页维修分类信息、分类入口和搜索跟随当前城市。手动切换后优先使用手动选择，保存365天；IP自动选择保存24小时。城市选择页的“全国信息”可明确恢复全国。浏览其它城市的列表会记住该城市，浏览全国列表不会取消已有选择。定位失败、境外IP、城市未开通时保留全国，可正常手动切换。IP归属地可能与实际所在地不同，尤其在代理、VPN和移动网络下。

定位使用 `app/data/ip2region/` 中的离线 IPv4/IPv6 城市库及 `app/vendor/ip2region/` 查询库（上游提交 `c1a1fc7d5941760db3f8431dc05c48cf7f0e30a1`，许可随库保留）。正常公网部署直接查询 `REMOTE_ADDR`，不信任未配置的转发头；反向代理部署可在私有配置中设置 `location.trusted_proxies` 精确IP列表，仅信任这些代理传递的 `X-Forwarded-For`。本地/私网访问首页时，浏览器通过 `https://api.ipify.org` 获取公网 IPv4，失败时尝试 `https://api64.ipify.org`，再交给本机离线库查询，最长等待约6秒；只增加这两个域名的CSP连接权限，不使用浏览器GPS。定位接口只在本地/私网连接时接受浏览器提供的IP。修改前源码备份在 `backup/2026-10-02-ip-city-location/`。

定位已通过25项服务端及10项浏览器/HTTP检查：离线IPv4郴州解析、IPv6深圳解析、私网/无效地址过滤、可信代理、首访自动选择、首页信息与分类/搜索联动、手动城市跨会话记忆、明确全国、过期设置、迟到定位请求保留手动选择、外网请求失败回退及CSRF。郴州流程测试模拟公网IP `113.243.0.1`，查询真实离线库；实网IP查询及本机定位接口均已连通，当前测试网络公网出口归属新加坡，实际回退全国，不能视为已真实定位到郴州。运行 `tests/city-location.php` 和 `node tests/city-location.cjs` 复验；结果为 `tests/artifacts/city-location-results.json`，页面截图以 `ip-city-home-` 开头。

后台 `/dgadmin/index.php?section=navigation` 管理顶部主导航，首次迁移加入现有的首页、分类信息、选择城市、维修知识库、维修百科。可新增、编辑名称与链接、设置排序、上移/下移、启停、删除及设置新窗口打开；提供站内常用功能的快捷选择。导航设置保存后立即应用到前台，停用导航只隐藏入口，相关功能和内容仍可使用。链接支持以 `/` 开头的站内路径或完整 HTTP/HTTPS 地址。全部停用或删除后不显示导航栏，不会自动补回已删除项。

已有数据库执行 `scripts/migrate-navigation.php` 新建 `xiu_navigation`；初始化脚本也包含此迁移。首次导入通过设置标记防止重复覆盖，后续迁移不改变已编辑的导航。修改前源码及相关设置快照保存在 `backup/2026-10-02-navigation-management/`。

导航已通过19项服务端检查、15项浏览器/HTTP检查，并验证重复迁移保留编辑结果及原富文本编辑器正常加载。覆盖新增、编辑、外部链接、新窗口、相同排序值移动、启停后的缓存更新、删除、选中项匹配、空导航、登录与CSRF限制，以及桌面/390px/320px手机布局。结果在 `tests/artifacts/navigation-results.json`，最终截图以 `navigation-final-` 开头。浏览器复验仅在本地或测试环境运行：先运行 `tests/navigation-fixture.php`，再运行 `node tests/navigation.cjs`，结束后始终运行 `tests/navigation-fixture.php cleanup`；独立测试项和临时会话会被清理。

用户注册账号并登录后，从“免费发布信息”提交维修服务或求修需求；在“我的信息”查看状态、编辑和下架。审核状态依次为待审核、已发布、已驳回或已下架。管理员在后台查看完整信息，通过或填写原因驳回；已发布内容修改后立即暂停公开展示，重新审核。修改失败时原数据与发布状态保留。

管理员可维护最多三级分类、城市、省份分组、热门城市、排序、启停、用户状态及站点名称。停用分类及其父分类、城市或发布者账号时，对应内容暂停展示；恢复启用后，原已发布内容恢复展示。被内容或子分类引用的分类/城市不能删除，应使用停用。

空调维修新增25个三级子分类：24个品牌（小米、格力、美的、海尔、海信、奥克斯、TCL、长虹、科龙、华凌、卡萨帝、统帅、松下、大金、三菱电机、三菱重工、日立、东芝、富士通、三星、LG、志高、约克、开利）及“其他品牌空调”，保留原有家用空调和中央空调。品牌名称和稳定全拼地址位于 `app/data/air-conditioner-brands.json`；已有库或新部署初始化后运行 `scripts/add-air-conditioner-brands.php` 添加。脚本先备份分类，再在事务中插入，重复执行保留已有分类的排序、状态和导航设置，恢复快照和仅针对新增ID的回退SQL位于 `backup/{日期}-air-conditioner-brand-categories/`。前台列表、首页分类、后台编辑及会员发布三级选择直接使用这些分类。本地已核对25个地址正常、159条原分类未改变、重复运行新增0项，以及1440px/390px/320px发布选择布局；结果位于 `tests/artifacts/air-conditioner-brands-results.json`。

空调品牌示例信息：`scripts/seed-air-conditioner-brand-listings.php` 默认只预览，添加 `--apply` 后在每个已启用城市、每个品牌目录分类各补8条（维修服务与求修需求各4条）。本地新增78,600条，覆盖393个城市及25个品牌分类，正文242–270字，说明设备故障、服务项目、维修需求与城市范围；沿用 `is_demo=1`，不提供真实联系电话或品牌授权声明。脚本写入前完整备份数据库，按城市提交事务，以 `xiu_local_test_seeds` 的城市/分类/槽位防止重复，重复执行新增0条；恢复材料保存在 `backup/{日期}-air-conditioner-brand-listings/`。已核对全部组合与原288,889条信息内容一致，并通过17项页面检查，报告位于 `reports/seeds/ac-brand-listings-verification.json` 和 `ac-brand-pages-verification.json`。

空调品牌旧详情链接兼容：最初一批示例信息的短地址前缀含数字，与原路由格式不匹配；改为纯字母前缀后仍需保留旧链接。现通过 `xiu_listing_slug_aliases` 将78,600个旧地址映射到原信息，详情控制器按现有规则301跳转到规范地址，既有信息和新地址不变。新安装包含 `scripts/migrate-listing-slug-aliases.php`；已有库运行该迁移建表，旧地址映射可用 `scripts/import-brand-listing-url-aliases.php <rollback-url-repair.sql> --apply` 从本地恢复记录导入，默认只预览，事务提交并备份原映射及新增项回退SQL，重复运行新增0条。用户反馈地址 `ab0b406a2025.html` 已恢复访问。本地核验78,600组新旧路由、全部城市/品牌各8条、367,489条既有信息内容哈希不变，并通过65组地址的197项HTTP/浏览器检查；复验 `tests/brand-listing-aliases.php` 与 `tests/brand-listing-aliases-http.cjs`，报告位于 `tests/artifacts/brand-listing-aliases-results.json` 及 `brand-listing-aliases-http-results.json`。恢复材料保留在 `backup/2026-10-02-brand-listing-old-links/`，映射数据与备份不上传GitHub。

手机号注册账号：注册页将原“账号”和“联系电话”合并为一个“手机号码”输入框，只接受11位大陆手机号码（`1[3-9][0-9]{9}`），手机号码同时保存为 `xiu_users.username` 和 `phone`，用于手机号加密码登录，并在新发布维修服务或求修需求时默认带出。手机号按现有账号唯一索引去重；前端和服务器都拒绝固定电话、字母账号及不符合格式的号码，服务器不使用额外提交的独立联系电话字段。发布联系电话仍可修改；编辑信息保留原信息电话，提交失败保留手动输入，发布改号不修改账号手机号。原有字母账号仍可登录，已有数据和账号ID不变。此前 `scripts/migrate-user-phone.php` 的列迁移继续适用，此次无需新增数据库列。已通过25项注册、手机号登录、重复号码校验、回填及兼容检查，并核对1440px/390px/320px布局；运行 `node tests/register-phone.cjs` 本地复验，结果为 `tests/artifacts/register-phone-results.json`。其他注册验收脚本已同步使用手机号，并在临时数据清理时核对记录的验收密码和创建时间，保留非本次验收账号。源码恢复备份位于 `backup/2026-10-02-mobile-number-account/`。

会员手机号码管理：会员后台侧栏新增“手机号码”，管理首页个人信息也提供添加/修改快捷入口，地址为 `/yong-hu-zhong-xin/shou-ji-hao-ma/`。支持原账号补填与已有号码修改，只接受11位大陆手机号，保存时要求当前登录密码及CSRF；按登录账号和已有联系电话检查其他账号占用，并由账号唯一索引处理并发重复。保存同时更新当前会员的 `username` 和 `phone`，会员ID及密码保留，之后使用新手机号码加原密码登录，新发布信息自动带出新号码；已有发布信息联系电话可单独编辑。每次实际修改前，在 `backup/{日期}-member-mobile-number/records/` 保存原账号/电话及条件回退SQL，不记录密码。无新增数据库列。已通过28项HTTP/浏览器检查，覆盖侧栏和首页入口、老账号补填、号码格式、当前密码、重复号码、CSRF、越权防护、新旧登录、发布回填及原信息保留，并核对1440px/390px/320px布局；临时账号、信息与会话已清理，原账号数据哈希保持一致。只在本地验收环境运行 `node tests/member-phone.cjs`，结果为 `tests/artifacts/member-phone-results.json`。

## 发布描述的AI生成

后台 [AI生成设置](http://www.xiu.cn/dgadmin/index.php?section=ai) 可开启或关闭功能、填写智谱 API Key、选择常用模型或直接填写自定义模型 ID，以及设置每位会员每天的生成次数（默认20次，范围1–200次；每分钟最多3次，请求计次）。初始关闭且未配置密钥；填入真实密钥、选定账户支持的模型并开启后，“发布服务或需求”的“详细描述”右侧可点击“AI生成”。按当前标题及信息类型生成约200–400字的维修服务或求修需求说明，生成后可继续修改，再提交原有审核流程。已有描述替换前确认；失败保留原文，生成期间手动修改标题、类型或正文时保留当前内容。禁用JavaScript时仍可手动发布。

密钥仅在服务器端使用，以 AES-256-GCM 加密保存在非公开的 `storage/ai/settings.json`，加密密钥位于 `storage/ai/secret.key`，页面不回显，密码框留空保留原密钥。迁移时同时保留这两个文件；修改设置前自动备份原配置至 `backup/{日期}-zhipu-ai-description/private-config/`。接口 `POST /api/ai-description/` 需要已启用的会员账号、CSRF及标题/类型校验；只发送标题及类型，不发送联系人、电话、服务地址、附图或已有描述。外部请求最长60秒，保留TLS校验；日志不记录API Key或供应商原始响应。额度及模型权限以您的智谱账户为准。模型参数按[智谱对话补全文档](https://docs.bigmodel.cn/api-reference/%E6%A8%A1%E5%9E%8B-api/%E5%AF%B9%E8%AF%9D%E8%A1%A5%E5%85%A8.md)与[模型概览](https://docs.bigmodel.cn/cn/guide/start/model-overview.md)处理，GLM-5.3使用开启思考及low档，旧模型不传不支持的thinking参数。

25项服务端检查及19项浏览器/HTTP检查通过，覆盖密钥加密和保留、模型设置、类型和标题提示词、响应异常、额度重置、权限与CSRF、生成回填、已有内容替换确认、生成失败和并发编辑保护、桌面及390px/320px布局。浏览器生成响应使用模拟数据，智谱HTTPS接口已真实连通并用无效测试Key确认401响应；未提供真实API Key，因此尚未验证账户模型的真实生成。结果为 `tests/artifacts/ai-description-server-results.json` 和 `ai-description-browser-results.json`，截图以 `ai-description-` 开头。复验只在本地或测试环境：依次运行 `tests/ai-description-fixture.php`、`tests/ai-description.php`、`node tests/ai-description.cjs`，结束后始终运行 `tests/ai-description-fixture.php cleanup`，恢复原AI配置并清理临时用户和会话。源码恢复备份位于 `backup/2026-10-02-zhipu-ai-description/`。

百科和知识库使用独立用途的分类，只允许管理员发布。正文提供本地 Quill 2.0.3 富文本编辑器，支持标题、加粗、斜体、下划线、删除线、列表、引用、缩进、对齐和链接；支持草稿和发布状态。服务端只保留允许的排版标签及安全链接，过滤脚本、事件属性和嵌入内容。原有纯文本文章保持原显示方式，编辑并保存后才改为富文本。分类停用后，其文章暂停展示。

已有数据库升级运行 `scripts/migrate-article-rich-text.php`（使用上文 PHP 可执行程序），新增 `xiu_articles.body_format`，默认 `text`；初始化脚本自动包含迁移。编辑器与样式保存在 `public/assets/vendor/quill/`，运行时无需 CDN。浏览器禁用 JavaScript 时使用正文文本框。修改前源码与文章快照保存在 `backup/2026-10-02-article-rich-editor/`。

富文本已通过16项服务端及旧文章检查、15项浏览器和HTTP检查，覆盖桌面、390px/320px手机、工具栏操作、保存后重新编辑、前台排版、危险内容过滤、正文长度验证、CSRF及禁用JavaScript回退。截图和结果位于 `tests/artifacts/rich-editor-*.png`、`rich-article-*.png`、`article-rich-editor-results.json`。`tests/article-rich-text.php` 检查过滤和原有文章；浏览器复验先运行 `tests/article-rich-text-fixture.php`，再运行 `node tests/article-rich-editor.cjs`，最后始终运行 `tests/article-rich-text-fixture.php cleanup` 清理独立文章与临时会话。检查会创建已发布的独立测试文章，请只在本地或测试环境运行。

分类信息最多保留 5 张附图，每张最大 5MB；文章最多一张封面。仅允许真实 JPEG、PNG、WebP，限制 1600 万像素，重新编码后保存。上传失败时保留文字，浏览器出于安全原因不能自动恢复所选文件，需要重新选择。

## 验收与检查

本次完成 71 项 HTTP 检查，覆盖发布审核闭环、跨用户权限、CSRF、脚本注入、SQL 参数处理、图片类型/大小/数量、分类层级/循环/停用、城市管理、用户停用、知识库/百科发布和新会话数据持久性。40 组页面检查覆盖桌面 1440px、手机 390px、小屏 320px及后台；已检查关键截图，中文正常，无页面横向溢出和浏览器 JavaScript 错误。后台较宽表格允许在表格区域内横向滚动。

结果文件：`tests/artifacts/smoke-results.json`、`tests/artifacts/ui-results.json`；截图位于同目录。测试账号和测试业务数据已清理。

再次运行 HTTP 测试需要 Python 3，以及通过环境变量提供管理员密码。`tests/smoke.py` 会创建独立测试数据，完成后执行 `tests/cleanup.php`；该程序只清理 `fixtures.json` 中记录且符合测试前缀的自建数据，不使用通配符删除业务数据。请只在本地或测试环境运行验收脚本，验收中会暂时测试站点名称等设置。

```powershell
$env:XIU_ADMIN_PASSWORD = '<管理密码>'
try {
    python tests\smoke.py
} finally {
    Remove-Item Env:XIU_ADMIN_PASSWORD
    & 'D:\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe' tests\cleanup.php
}
```

`tests/ui.cjs` 用于浏览器自动化验收，需要 Playwright 和本地 Chrome；脚本默认使用本机已安装的运行库路径。迁移到其他机器时调整该依赖路径；可通过 `XIU_CHROME_PATH` 指定 Chrome，通过 `XIU_BASE_URL` 指定测试站点。此依赖只用于测试，不是网站运行依赖。

## 伪静态 URL 与 SEO

Nginx 的现有 `try_files` 和 Apache 的 `public/.htaccess` 将公开页面交给统一 PHP 入口；URL 中无需出现 PHP 文件名。目录、分类、城市使用全拼，词间使用短横线。详情文件名按本次修改要求缩短，使用拼音或首拼加随机数字。

| 页面 | URL 规则 |
| --- | --- |
| 维修分类 | `/fen-lei-xin-xi/kong-tiao-wei-xiu/` |
| 城市 | `/hang-zhou/` |
| 城市＋分类 | `/hang-zhou/kong-tiao-wei-xiu/` |
| 服务 / 求修信息筛选 | 分类或城市路径后追加 `/lei-xing/wei-xiu-fu-wu/` 或 `/lei-xing/qiu-xiu-xu-qiu/` |
| 分类分页 | 对应路径后追加 `/di-2-ye/`，第1页不追加 |
| 信息详情 | `/wei-xiu-xin-xi/{short_slug}.html` |
| 知识文章 | `/wei-xiu-zhi-shi-ku/{short_slug}.html` |
| 百科详情 | `/wei-xiu-bai-ke/{short_slug}.html` |

详情的 `short_slug` 最多12个 ASCII 字符，不包含目录和 `.html`：最多8个拼音字母或汉字拼音首字母，加4位随机数字。短名称能够完整放入8个字母时采用全拼；长标题采用首拼。数据库唯一索引防止重号，重号自动重新生成。分类、城市、详情标识生成后保持固定，改名称或标题不会改变地址。

旧 `/services/`、`/city/hangzhou/`、`/info/28/`、`/baixiuquan/1.html`、`/baike/5.html` 及已生成过的“长标题拼音＋ID”地址，均保留301兼容跳转。页面内链接、面包屑、canonical、分享地址和站点地图使用新地址。大小写、缺少结尾斜线、跟踪参数、第1页参数等统一跳转到规范地址；越界分页、无效分类和不存在的内容返回真实404。

每个公开页面提供标题、描述、一个主标题、canonical、robots、Open Graph；可收录页面提供 JSON-LD，包含页面、网站或面包屑信息，正式知识/百科文章另有 Article 数据。分页具有自己的规范地址、页码标题和描述。搜索结果、空列表、演示详情、待审核/下架预览、登录注册、个人中心和后台不进入索引；正式审核通过的信息和正式发布的文章可以进入索引。停止公开的内容不再出现在 XML 地图中。

`/robots.txt` 提供 XML 地图入口；`/sitemap.xml` 为地图索引，按导航、正式信息、知识和百科分片，每片最多1000个URL。地图仅包含当前有效、可索引的规范地址，文章和详情使用实际更新时间。HTML 地图从页脚进入。后台“站点设置”中的网站主地址决定 canonical 和地图域名；公网部署后应填写实际 HTTPS 主地址。

升级已有数据库时执行以下幂等迁移；初始化脚本也会自动执行，不覆盖已有内容或已分配的地址：

```powershell
& 'D:\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe' scripts\migrate-seo.php
```

新增 `tests/seo.py` 验证旧地址301、短地址长度、唯一标题与描述、robots/canonical、JSON-LD与CSP、404和站点地图中的实际页面。可在 HTTP 测试后、清理测试数据前运行，以覆盖正式发布文章的结构化数据。`tests/short-slugs.php` 在事务内验证400个同标题记录的短标识唯一性、改标题稳定性及唯一索引，测试结束回滚。

本次复验71项业务检查、40组浏览器页面与尺寸检查，以及262个地图地址对应的2991项SEO检查；测试数据随后清理。SEO结果保存于 `tests/artifacts/seo-results.json`。源码恢复备份在 `backup/2026-10-01-pinyin-seo/`，修改前的业务数据快照在其中的 `database-before.json`。

全拼词典与生成脚本随源码提供，PHP 运行时无需 Python。`scripts/build-pinyin.py` 仅用于重建离线词典，需要 `pypinyin==0.55.0`；第三方许可保留于 `app/data/PINYIN-LICENSE.txt`。

当前网站仅在本机 hosts 中可访问，搜索引擎无法抓取；本次完成的是本地SEO技术准备，不代表已实际收录。公网服务器、HTTPS与域名上线后，再向搜索引擎站长平台提交 `/sitemap.xml`。

## 配置恢复与部署边界

本地 Nginx 和 Apache 的该站点虚拟主机配置备份位于：

`C:\Users\Administrator\.codex\backup\global-config\2026-10-01-xiu-site\`

文件分别为 `nginx-www.xiu.cn_80.conf` 和 `apache-www.xiu.cn_80.conf`。如需恢复旧站点根目录，将对应备份复制回 phpStudy 的该站点配置路径，再恢复项目备份中的 `nginx.htaccess`，检查 Nginx 配置后重新加载。恢复配置不会删除数据库或新网站源码。

Nginx 已配置 `client_max_body_size 32m`、PHP 上传 5MB/总提交 32MB、前端路由及点文件拦截。Apache 配置已同步网站根目录，`public/.htaccess` 和 `.user.ini` 提供路由与上传设置；本次实际运行和 HTTP 验收使用 Nginx，未切换到 Apache 实测。

本次只完成本地 HTTP 网站。正式公网服务器、HTTPS、域名绑定及数据库定期备份尚未配置；不包含支付、短信验证、聊天、VIP 推广或独立商铺。

## 截图式分类与区域导航

分类和城市列表页使用白底矩形区域：顶部面包屑，类别文字链接按排序横向换行，橙色选中项，右侧展开/收起按钮，下方区域文字链接。链接为服务器渲染的真实 `a href`，点击加载独立列表页；不依赖 JavaScript 才能访问。桌面按1200px主体展示，手机自动换行；类别默认完整展开，用户可以收起。

示例：

- 广州空调清洗：<http://www.xiu.cn/guang-zhou/kong-tiao-qing-xi/>
- 广州天河空调清洗：<http://www.xiu.cn/guang-zhou/kong-tiao-qing-xi/qu-yu/tian-he/>
- 分类后台：<http://www.xiu.cn/dgadmin/index.php?section=categories>
- 区域后台：<http://www.xiu.cn/dgadmin/index.php?section=districts>

进入服务大类后，“类别”列出该大类的子分类与三级分类；“不限”返回该大类。面包屑中的城市/分类入口可返回上级。区域点击保持当前类别与信息类型，类型及关键词检索在导航下方独立展示。

后台分类管理新增“分类导航展示”设置、公开URL预览；父分类决定展示大类，排序决定链接次序。隐藏导航只移除链接，不下架已有信息；停用分类才会暂停对应内容。后台城市管理提供“管理区域”入口；区域管理支持新增、名称、排序、启停、城市筛选、URL预览及删除。区域生成全拼地址后保持固定，所属城市不能更改；被信息引用的区域不能删除，可停用。

用户发布表单新增服务区域，随城市联动；切换城市时清除不匹配区域。服务端验证区域启用状态及所属城市，审核通过后在对应城市/区域/类别页面展示。原信息未指定区域时保留在全市页面，不猜测或自动分配区域。停用区域会暂停区域页面及其中信息的公开展示，恢复启用后原已审核内容恢复。

新增 `xiu_districts` 表、分类 `show_in_filter` 字段、信息 `district_id` 字段及查询索引。现有数据库升级执行：

```powershell
& 'D:\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe' scripts\migrate-category-navigation.php
```

初始化脚本自动包含此迁移。首次升级补齐参考图中的生活服务目录、调整对应导航顺序，并按广州政府区划初始化11个区域；完成后写入初始化标记，后续运行不会覆盖后台修改或重新补回已删除项目。其它城市的区域由后台维护。原分类ID、信息和详情短URL保留。升级前源码、业务数据快照保存在 `backup/2026-10-01-category-navigation/`。

区域列表沿用标题、描述、canonical、面包屑、分页及索引设置。有已发布内容的区域组合进入站点地图；空区域页 `noindex`，停用或不存在区域返回404。正式区域信息还按原规则审核，不绕过用户权限和CSRF。

本次通过新增28项分类/区域HTTP检查、16组分类及后台尺寸检查、城市/区域选择联动与发布表单桌面手机检查，并复验71项原业务检查、40组原页面检查及2991项SEO检查。检查结果保存在 `tests/artifacts/category-http-results.json`、`category-ui-results.json`、`category-publish-ui-results.json`，最终分类与后台截图也在该目录；测试记录已清理。

`tests/category-http.py` 创建记录在 `fixtures.json` 的测试数据，执行后运行 `tests/cleanup.php`；不要在未清理上一批数据前覆盖该文件。`tests/category-ui.cjs` 检查分类点击、区域点击、选中状态、收起展开和页面尺寸；`tests/category-publish-ui.cjs` 在原HTTP及浏览器测试创建的账号仍存在时验证城市/区域联动。

## 维修信息详情展示

维修服务和求修需求详情采用白底信息页，包含标题、信息编号、发布时间、更新时间、上方基本资料、正文、附图、底部联系方式及右侧联系卡。基本资料显示联系人、联系电话、维修分类、服务区域、地址；联系电话在正文之前，手机页面同样可直接看到。分类和区域可点击进入对应列表，详情短URL保持不变。

电话提供 `tel:` 拨号链接及复制按钮；复制支持 HTTPS Clipboard API，普通HTTP采用复制回退方式，失败时提示手动选中或长按。图片按原上传规则和权限展示，可打开原图。服务介绍、图片展示、联系方式栏目链接可跳转到正文对应位置。所有联系方式仍来自用户发布表单，经原后台审核流程展示；未公开信息仅发布者与管理员可预览，没有新增数据库字段或修改已有商家资料。

参考链接为 `https://www.baixiu.org/repair/27350530.html`；读取时遇到网站访问验证及浏览器正文读取超时，因此本次按已明确的分类信息和联系方式结构实施，尚未逐项核对参考页版式。不复制参考商家的内容或电话。演示记录继续明确标注，号码与地址不是实际维修服务联系方式。

本次复验71项HTTP业务检查及9组详情检查，覆盖1440px桌面、900px平板、390px手机、320px小屏、服务/求修两种详情、正式审核信息联系方式、附图、复制成功/失败反馈、电话链接、页内跳转和SEO规范地址。测试复制边界使用模拟接口，不拨打电话或更改用户电脑剪贴板。结果在 `tests/artifacts/detail-ui-results.json`，关键截图以 `repair-` 开头；测试记录已清理。执行 `tests/detail-ui.cjs` 可复验，若在 `smoke.py` 后、清理前运行，还会检查正式发布的附图信息。

修改前文件恢复备份：`backup/2026-10-01-repair-detail/`。

## 后台分类分级管理

`/dgadmin/index.php?section=categories` 按维修分类、知识库、百科分开管理。上方以按钮网格展示一级大类，点击后只展示该大类的二级分类；选中二级后有三级分类时继续展开。橙色表示选中项，停用项显示状态标记，手机端改为两列。下方管理表只列出当前大类及其下属分类，并明确标注层级和上级名称。

各层级右侧提供新增入口，自动选择所属分类。点击“编辑当前分类”或表格中的编辑按钮打开表单，可修改名称、所属分类、排序、导航展示和启停；已有三级分类保留，表格可新增子分类。编辑时不能选择自身、子分类或三级分类作为上级，服务端继续限制循环和最多三级。被内容或子分类引用的分类不能删除。新增和保存后返回当前分类，不重置到其它大类。

兼容旧地址的英文标识移入高级设置，新增时留空自动生成并处理重复；已有标识留空保持原值。公开全拼 URL、分类 ID、已有分类和所属信息保持原有规则，无需数据库迁移。

验证通过：27项后台分类浏览器与操作检查、71项业务回归、28项分类和区域检查。覆盖桌面、390px及320px手机的中文名称和页面宽度，新增二/三级、编辑排序、改名后URL稳定、用途隔离、自动兼容标识、引用删除限制、四级提交拦截、CSRF及编辑父级选项。测试记录已清理。结果与截图在 `tests/artifacts/admin-categories-ui-results.json` 和 `admin-categories-*.png`，检查脚本为 `tests/admin-categories-ui.cjs`。

源码恢复备份：`backup/2026-10-01-admin-category-levels/`。


## 全国城市选择页与城市根路径

城市入口改为 `/selectcity.html`，主导航、页脚及“全部城市”均指向这个全国城市选择单页。按34个省级地区展示393个城市及地区，包含热门城市、省份快速定位和中文、全拼、首拼查找。城市链接由PHP直接输出；关闭JavaScript后仍可浏览和点击全部城市。全国分类导航仅列出热门城市及“全部城市”入口，完整城市目录集中在选择页。

城市列表改用根路径，例如上海 `/shang-hai/`，广州空调清洗 `/guang-zhou/kong-tiao-qing-xi/`，广州天河空调清洗 `/guang-zhou/kong-tiao-qing-xi/qu-yu/tian-he/`。分类、区域、信息类型、关键词和分页继续按原规则组合。旧 `/cheng-shi-fu-wu/`、`/cities/`、`/selectcity.html` 301到选择页；旧 `/cheng-shi-fu-wu/{城市}/...` 和 `/city/{旧标识}/` 301到对应新地址。公开链接、面包屑、canonical、OG地址及站点地图均使用新地址；管理、静态资源和原有内容路径保留各自入口。

城市目录基础数据来自 [Administrative-divisions-of-China](https://github.com/modood/Administrative-divisions-of-China)，固定版本 `c49d495b40ac73eb1a66f6eeae5f8fd10696f035`，大陆基础数据日期为2023-06-30。涵盖地级服务地区、直辖市、省直辖县级地区，并补充台湾城市/县及香港、澳门服务区域；不是全部县、区、乡镇清单，也不宣称为2026年最新行政区划。数据来源与范围记录于 `app/data/nationwide-cities.json`。台湾名称参照[地方制度资料](https://www.moi.gov.tw/cp.aspx?n=674)。后台可继续维护城市名称、排序、热门和启停。

运行 `scripts/migrate-city-directory.php` 导入目录；初始化脚本也包含该步骤。首次导入新增369条，保留原24条城市的ID、数据、热门、启停和标识；完成标记确保重复运行不覆盖后台修改或重新补回删除项目。未改动已有信息或区域归属。拼音生成脚本纳入城市完整名称及多音字修正，已有持久化的URL标识保持固定。

通过809项城市URL/旧地址/规范地址检查、39项城市选择页浏览器检查、71项业务检查、2994项SEO检查、28项分类区域检查及16组原分类页面检查。检查包括全部393个城市链接、301兼容、错误地址404、站点地图、桌面和390px/320px手机、搜索清除、省份定位及无JavaScript访问；QA数据已清理。主要结果为 `tests/artifacts/city-routing-results.json`、`city-selector-ui-results.json`，截图以 `city-selector-` 开头。复验可运行 `tests/city-routing.py` 和 `tests/city-selector-ui.cjs`。

参考 `https://www.baixiu.org/selectcity.html` 时遇到访问验证，未读取到该页实际版式；本次按全国城市选择单页、热门城市和按省份展示的要求实现。

恢复备份：`backup/2026-10-01-city-selector/`，其中 `cities-before.json` 保存导入前城市及站点设置数据；源码和拼音数据也已备份。


## 顶部城市切换

站名旁显示定位图标、当前城市和 `[切换]` 链接，点击直接访问 `/selectcity.html`。该地址现在为全国城市选择页的正式地址，旧 `/xuan-ze-cheng-shi.html` 301跳转至此，导航、页脚、canonical、OG和站点地图已同步。

首次访问显示“全国”。浏览城市信息列表后，在同一会话中记住所选城市，回首页或打开选择页仍显示该城市；进入全国分类列表恢复“全国”。信息详情显示该条信息所在城市，停用城市不作为当前城市展示。城市名称仅用于页头显示，不改变内容查询或SEO规范地址，不进行IP定位。

通过39项原城市选择页浏览器检查，以及桌面、390px和320px的城市显示、切换直达、会话保留、全国恢复、长城市名和旧地址跳转检查；同时验证选择页canonical与站点地图。截图为 `tests/artifacts/header-city-switch-1440.png`、`header-city-switch-390.png`、`header-city-switch-320.png`。恢复备份位于 `backup/2026-10-01-header-city-switch/`。

## 会员发布管理中心与身份认证

会员登录后进入 `/yong-hu-zhong-xin/`。管理首页展示当前账号、注册时间、认证状态和真实发布统计：总发布数、已发布、待审核、已驳回、已下架，另按维修服务与求修需求汇总。统计卡片可以进入对应状态的“我的发布”；最近发布和管理列表保留编辑、预览、下架及驳回原因。管理列表地址为 `/yong-hu-zhong-xin/wo-de-fa-bu/`，每页12条，只能操作本人的信息。

身份认证入口为 `/yong-hu-zhong-xin/shen-fen-ren-zheng/`。个人认证填写真实姓名并上传身份证正面、反面；企业认证填写企业名称并上传营业执照。上传仅支持真实 JPEG、PNG、WebP，每张最多5MB、1600万像素，重新编码后保存。一名会员当前保留一种认证类型，切换类型必须提交对应的完整材料；同一类型更新时，可不重新上传未变更的证件。姓名、企业名称或证件变更后进入待审核，先前通过状态不继续保留；提交失败则保留原记录和状态。

后台 `/dgadmin/index.php?section=verifications` 新增“身份认证审核”，支持按待审核、已通过、已驳回筛选，查看材料、通过、填写驳回原因以及删除认证。所有状态均可删除，按钮先确认；删除仅移除该认证及材料，保留会员账号和发布信息，已通过的认证标识随之取消，会员可重新提交。执行前将记录和材料保存至非公开的 `backup/{日期}-verification-delete/{认证ID}-{时间}-{随机标识}/`；材料移入恢复备份，不再通过原材料URL访问。删除检查提交版本及状态，旧页面不能删除会员新提交或审核状态已改变的材料；数据库操作失败时恢复材料。删除后保持当前筛选和页码。会员看到驳回原因后可修改重提。审核记录包含提交版本、时间、审核管理员和审核时间；旧页面中的审核操作不能通过会员随后替换的材料。这里是平台人工审核，没有接入公安或工商的自动核验接口。

证件独立保存在网站根目录之外的 `storage/verification/`，不混入公开信息附图。通过带登录与所有权校验的接口查看，仅本人和管理员可访问，停用会员不能访问；响应不缓存、不索引。更新证件后旧链接立即失效，原图片文件随之清理。会员中心本身也不进入搜索索引或站点地图，公开信息页面不展示姓名认证材料或证件图片。

认证删除已通过12项浏览器/HTTP及26项数据库/文件检查，并实测第二张材料被Windows独占锁定时，前一张已移动的材料会恢复，认证记录和材料记录保留。覆盖全部状态、确认取消、版本/状态变更拦截、管理员和CSRF校验、筛选保留、证件URL失效、认证标识消失、账号及发布信息保留、本地恢复备份及桌面/手机按钮布局。结果为 `tests/artifacts/verification-delete-results.json`，截图以 `verification-delete-` 开头；本次独立测试数据已清理。源码恢复备份位于 `backup/2026-10-02-verification-delete-feature/`。复验依次运行 `tests/verification-delete-fixture.php`、`node tests/verification-delete.cjs`、`tests/verification-delete-assert.php`，结束后始终运行 `tests/verification-delete-fixture.php cleanup`；仅在本地或测试环境执行。

新增结构位于 `database/member-schema.sql`，包含 `xiu_user_verifications` 和 `xiu_verification_files`。本地已完成迁移，初始化脚本也会执行；升级其他已有站点运行以下幂等命令，不覆盖原会员和发布信息：

```powershell
& 'D:\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe' scripts\migrate-member-center.php
```

本次通过40项会员HTTP检查、61项会员浏览器检查及71项原业务回归，覆盖个人和企业认证、驳回重提、材料替换、审核版本冲突、跨用户权限、CSRF、脚本转义、非法/超大上传、统计筛选及刷新后持久性。已查看1440px桌面及390px/320px手机截图，文字完整、无页面横向溢出。测试使用纯色模拟图片，没有使用真实身份证或营业执照，测试账号、业务数据和模拟证件均已清理。

结果为 `tests/artifacts/member-http-results.json`、`member-ui-results.json`，截图以 `member-` 开头。复验时先设置 `XIU_ADMIN_PASSWORD`，依次运行 `python tests/member-http.py`、`node tests/member-ui.cjs`，最后执行 `tests/cleanup.php` 并移除密码环境变量。浏览器测试需要前一步创建的会员测试账号，请勿在两步之间清理，或在未清理时运行其他会覆盖 `fixtures.json` 的测试。恢复备份位于 `backup/2026-10-01-member-center/`。

## 认证优先展示与详情标识

未刷新的公开维修信息按“已通过企业认证 → 已通过个人认证 → 未通过认证”排序，同一等级内按信息ID倒序保留原来的新发布优先规则。刷新信息优先于认证排序，具体规则见下方“会员每日免费刷新”。排序先于分页执行，适用于首页信息、首页分类栏目、分类/城市/区域列表、关键词与信息类型筛选、详情同类信息。后台和会员自己的管理列表继续按原顺序管理。列表中说明实际排序规则，首页信息栏目名称调整为“维修分类信息”。

认证以发布账号当前审核状态为准：未认证、待审核、驳回均不获得优先级；更新认证材料进入待审核时，优先级和标识立即取消，重新通过后恢复，无需重新编辑信息。认证不会绕过信息审核、城市/分类/区域启停和发布者停用限制。

详情页上方联系人和右侧联系卡显示带盾牌图标的“企业已认证”或“个人已认证”，未通过认证时不显示。手机端上方联系人同样显示标识。公开页面只显示认证类型，不输出认证姓名、企业名称或证件材料；标识表示发布账号的认证状态。现有详情短URL不变，无需新增字段或迁移。

本次通过10项模型检查、90项浏览器检查、71项原业务回归及8组原详情页面检查；已查看桌面与320px手机认证标识截图。检查覆盖跨页排序、同级新信息优先、首页/筛选/同类信息一致、认证状态实时变化、停用账号不展示及认证材料不泄露。排序测试使用独立模拟账号和明确标注的演示信息，测试数据已清理。结果为 `tests/artifacts/verification-priority-model-results.json`、`verification-priority-ui-results.json`，截图以 `verification-` 开头。

复验时依次执行 `tests/verification-priority.php` 和 `node tests/verification-priority-ui.cjs`，最后执行 `tests/cleanup.php`，两步之间不要覆盖测试数据记录。`tests/detail-ui.cjs` 支持通过 `XIU_SERVICE_DETAIL`、`XIU_REQUEST_DETAIL` 指定当前公开的演示信息；本次求修详情采用 `/wei-xiu-xin-xi/bjxyxyjw8812.html`，旧固定记录28已下架，保持该业务状态。恢复备份位于 `backup/2026-10-01-verification-priority/`。

## 会员每日免费刷新

会员中心的“最近发布”和“我的发布”在已发布信息旁提供“刷新”按钮。普通会员每个北京时间自然日可以免费刷新一条信息，全部分类共用该账号的1次额度；个人/企业身份认证不增加额度。页面显示剩余次数及上次刷新时间，成功后当天所有刷新按钮停用，重新登录仍保持已使用状态。每天北京时间凌晨0点恢复资格，重新打开或刷新管理页面即可看到，无需定时任务。

只有当前公开展示且属于本人的已发布信息可以刷新；待审核、驳回、下架、发布者停用、分类/城市/区域停用的信息不能刷新。无权访问、失效信息和失败请求不扣次数。操作仅接受带登录和CSRF校验的POST，通过用户行锁、事务及账号/日期唯一约束限制并发请求；重复点击、另一个会话或不同分类的信息均不能突破每日额度。删除已刷新信息也不返还当天额度。

刷新信息按最近一次成功刷新的顺序在匹配的公开列表中优先展示，排在认证信息之前；同一栏目的其他会员随后刷新会排得更前。其后是未刷新信息的企业认证、个人认证及未认证排序，同级新发布在前。刷新不改变所属分类、城市、正文、审核状态、原发布时间、内容更新时间或短URL。凌晨恢复的是免费刷新额度，既有刷新顺序保留；重新编辑或下架仍按原审核与公开展示规则处理。

新增 `xiu_listing_refreshes` 表记录每日额度及历史刷新，信息表增加 `refreshed_at`、`refresh_order` 和排序索引。本地已迁移，初始化脚本自动包含此迁移。其他已安装环境升级执行以下幂等命令：

```powershell
& 'D:\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe' scripts\migrate-member-refresh.php
```

通过40项刷新HTTP检查、45项浏览器检查及71项原业务回归，覆盖免费次数、不同分类共用额度、跨用户限制、CSRF、并发提交、前一日记录不占今日额度、刷新覆盖认证排序、分类/城市/区域/关键词筛选、首页与同类信息一致，以及刷新后数据持久性。已查看1440px桌面和320px手机截图，文字完整、按钮正常、无页面横向溢出。测试账号、业务数据及刷新记录已清理。结果为 `tests/artifacts/member-refresh-http-results.json`、`member-refresh-ui-results.json`，截图以 `member-refresh-` 开头。

复验依次运行 `tests/member-refresh-fixtures.php`、`python tests/member-refresh-http.py`、`node tests/member-refresh-ui.cjs`，最后执行 `tests/cleanup.php`。测试仅操作记录在 `fixtures.json` 的模拟账号和信息，三步之间不要清理或覆盖该文件。恢复备份在 `backup/2026-10-01-member-refresh/`，包含修改前源码、信息表结构及原信息时间/状态元数据；本次未修改phpStudy全局配置。

## 发布表单省市区域三级选择

发布维修服务和求修需求的“服务区域”改为“选择省份 → 选择城市 → 选择区域”三个下拉框。桌面在同一行展示，手机纵向排列。省份按全国目录顺序组织，城市仅显示所选省份的启用城市，区域仅显示所选城市的启用区域。切换省份清空城市及区域，切换城市清空原区域；未选省份或城市时，相应下级选择框停用，避免误选。

区域可选择“全市”；没有已开通区域的城市提示“暂无区域，将按全市发布”，不伪造区县数据。省份、城市、区域继续来自现有后台城市与区域管理，未导入新的行政区划数据。直辖市、港澳及台湾沿用现有省份分组。编辑已有信息时，由城市推导省份并自动选回城市、区域；验证失败保留文字及有效的地区选择。

服务端校验显式提交的省份是否与启用城市对应，并保留原来的区域/城市归属、启停、账号和CSRF校验。旧表单只提交城市ID时继续按该城市实际省份处理，不破坏已有发布接口。省份不额外存入信息表，城市ID和区域ID、原信息数据及短URL保持原规则，无需数据库迁移。关闭JavaScript时仍可从带归属名称的完整下拉列表提交，服务器会检查对应关系。

通过58项三级选择与发布检查、71项原业务回归，覆盖全部34个省份分组的城市过滤、下级重置、广州天河选择、无区域城市全市发布、维修服务与求修需求提交、编辑回填、错误省市/区域组合拒绝、失败回填及无JavaScript操作。已查看1440px桌面、390px及320px手机截图，三个选择框和中文完整、无横向溢出。测试账号与信息已清理；原业务验收脚本也调整为恢复测试开始时的实际站名。

新增检查脚本 `tests/publish-region-ui.cjs`，执行后运行 `tests/cleanup.php`，不要在未清理时覆盖 `fixtures.json`。结果在 `tests/artifacts/publish-region-ui-results.json`，截图以 `publish-region-` 开头。旧 `tests/category-publish-ui.cjs` 同步加入省份选择。恢复备份在 `backup/2026-10-01-publish-region-selector/`。

## 发布表单分级分类选择

发布维修服务或求修需求时，先进入“选择行业”，通过按钮选择一级大类和对应的二级分类，选中项显示橙色。有三级分类时可继续细分；没有下级的一级分类可以直接使用。桌面按五列、平板三列、手机两列展示，长分类名称自动换行。分类来自后台当前启用的维修分类，新增、排序和停用后自动同步，无需另行维护发布页。

点击“下一步”进入信息填写，顶部显示完整分类路径，并可“重新选择分类”。在同一页面返回选择分类时，已填文字、服务区域及所选图片保留。编辑已有信息和提交验证失败时自动恢复有效分类及填写步骤；已有信息使用宽泛一级分类时也可保留，不强制改动原归属。关闭JavaScript后保留完整分类下拉框和信息表单，仍可提交。原有分类启停、地区归属、图片、登录、所有权和CSRF服务端校验继续生效，无数据库迁移。

通过59项分类选择浏览器检查、58项省市区域检查及71项原业务回归。覆盖全部14个一级分类的下级联动、橙色选中、三级路径、键盘操作、下一步、返回后文字/区域/图片保留、实际发布与编辑、无效分类拒绝、验证失败回填及无JavaScript发布。已检查1440px桌面、900px平板和390px/320px手机截图，中文完整、无页面横向溢出。测试账号和信息已清理，实际站名保持“五修网”。

新增检查脚本 `tests/publish-category-ui.cjs`，执行后运行 `tests/cleanup.php`，清理之前不要运行其他会覆盖 `fixtures.json` 的测试。结果为 `tests/artifacts/publish-category-ui-results.json`，截图为 `publish-category-1440.png`、`publish-category-900.png`、`publish-category-390.png`、`publish-category-320.png`。现有地区选择检查脚本已同步新的分类步骤。恢复备份在 `backup/2026-10-01-publish-category-picker/`，本次未改动phpStudy全局配置。

## 维修信息列表图片卡片与电话咨询

全国分类、城市、区域及筛选结果列表改为图片卡片：左侧使用该信息第一张附图，没有附图时显示统一的本地维修封面；中间显示标题、信息类型、地区、分类、联系人及已有个人/企业认证标识；右侧显示相对时间和“立即咨询”。标题及图片仍链接到原详情短URL，演示内容保留标记，不生成虚假的会员等级或商家名称。手机按两列组织图片和内容，时间及咨询按钮放到下方。

“立即咨询”在当前信息内展开对应联系电话，再次点击收起；号码可直接点击拨号。使用浏览器原生展开控件，支持键盘和无JavaScript操作，无需额外请求或登录。只有原来已公开的信息会进入结果列表，证件资料不会出现在卡片中。

全部时间根据真实记录计算：有刷新记录时使用最近刷新时间，否则使用信息创建时间，显示“刚刚”“1小时前”“5小时前”“1天前”等；前两条不固定时间。悬停可查看精确时间，`time` 的 `datetime` 保留实际带时区日期。原有刷新、认证排序、筛选、分页和SEO规范地址保持原规则，无数据库迁移。

验证覆盖上传图/默认图、电话展开收起及拨号、键盘和无JavaScript操作、真实相对时间与刷新优先、身份标识、排序、搜索分页、内容转义，以及1440px、900px、390px和320px布局。检查脚本 `tests/listing-contact-cards.cjs` 仅读取已有业务数据；结果在 `tests/artifacts/listing-contact-results.json`，截图以 `listing-contact-` 开头。另通过71项发布审核业务回归，模拟账号和信息已清理。恢复备份为 `backup/2026-10-01-listing-contact-cards/`。

## 发布标题自动填写

发布服务或需求时，选择城市和分类后自动填写“城市＋服务地址＋一级分类＋二级分类＋三级分类”，只包含实际选中的层级。生成结果去除空格、标点和其他特殊符号，保留中文等文字及数字，例如“郴州北湖电脑数码通讯维修电脑维修”。自动标题随城市、服务地址和分类变化而更新；清空地址时同步移除标题里的地址，切换省份清空城市时也会清除旧城市。标题继续限制最多120字符，较长地址按剩余标题容量截短，优先留出城市和分类所需空间。地址输入框及保存的原地址保持原始文字和符号。

用户手动修改后不再自动覆盖，下面显示当前建议并提供“使用建议标题”，点击后重新启用自动更新。清空标题后，下一次选择变化或地址输入会重新自动填写。编辑已保存信息或提交失败返回时，保留已有标题，只有主动点击建议按钮才替换；关闭JavaScript时仍可手动填写。清理符号只作用于自动标题及建议，不批量修改已有信息。标题随原表单提交和保存，服务端长度校验、审核流程、权限及短URL规则保持原有实现，无需数据库迁移。

当前通过35项自动标题浏览器检查，覆盖二/三级完整路径、城市和省份切换、服务地址实时更新、清空地址、符号清理、长地址限制、原地址保存、手动标题保护、恢复自动填写、服务/需求、实际提交持久性、编辑和失败回填、桌面及390px/320px手机布局。已查看截图，测试账号和信息已清理。检查脚本为 `tests/publish-title-ui.cjs`，执行后运行 `tests/cleanup.php`，清理之前不要覆盖 `fixtures.json`。结果在 `tests/artifacts/publish-title-ui-results.json`，截图以 `publish-title-` 开头。首次自动填标题恢复备份为 `backup/2026-10-01-publish-title-autofill/`，地址及符号清理升级备份为 `backup/2026-10-01-title-address-clean/`。

## 后台信息分页与大数据量统计缓存

`/dgadmin/index.php?section=listings` 默认每页20条，可选择30或50条。顶部和底部均显示总数、当前页、页数、翻页及页码跳转。状态切换和翻页保留每页数量；审核通过、驳回和下架后返回当前筛选、页码及每页数量。负数和越界页码会夹到有效范围，无效每页数量回到20条。原有30条分页在信息不足30条时没有翻页入口，现在29条真实已有记录分为两页。

达到1000条信息时自动启用全部及各状态数量的30秒文件缓存，缓存仅放在非公开的 `storage/cache/admin-listing-counts.json`，导航待审核数量复用同一统计。数量不足1000条时直接查询。审核、会员发布/修改和下架会在写入成功后立即清除统计缓存；文件损坏、过期或无法写入时回到数据库统计。列表内容和审核状态仍逐页查询，每页只读取180字符正文预览，避免一次载入完整长正文。批量导入脚本若直接写数据库，应在事务提交后调用 `clearAdminListingCountCache()`；外部手工写库最多30秒后更新统计。

通过31项分页和缓存检查、71项原业务回归。缓存检查使用1001条临时、非公开待审核信息，覆盖启用阈值、重复读取、过期/损坏恢复、筛选翻页、页码边界、状态变更及会员修改/下架失效、导航统计、权限、CSRF和1440px/390px/320px布局。测试账号和信息均已清理，实际站名仍为“五修网”。无数据库迁移和全局配置改动。

复验：设置 `XIU_ADMIN_PASSWORD` 后依次运行 `tests/admin-pagination-fixtures.php`、`node tests/admin-pagination-ui.cjs`，最后执行 `tests/cleanup.php`，再通过PHP CLI调用 `clearAdminListingCountCache()`，并移除密码环境变量。两步之间不要覆盖 `fixtures.json`。结果为 `tests/artifacts/admin-pagination-results.json`，截图以 `admin-pagination-` 开头。恢复备份为 `backup/2026-10-01-admin-pagination-import/`。

## 百修网采集可读性检查（历史只读检查）

2026-10-01检查 `https://www.baixiu.org/selectcity.html` 及维修详情入口：城市目录和多个详情URL返回阿里云访问验证页面，未得到真实标题、分类、城市和联系方式。公开站点地图能够读取，本次发现9700个候选详情链接；候选链接不等于已经取得的有效分类信息，因此没有导入任何记录，也没有新增演示内容。

`python scripts/baixiu-source-check.py` 可重复执行只读检查，结果保存在 `reports/imports/baixiu-source-check.json`，包括HTTP状态、是否访问验证、来源站点地图及候选地址。该历史检查报告的导入数0，仅表示只读脚本不执行导入。后来通过普通浏览器取得可见业务字段，已导入5条广州空调维修信息；来源和采集时间记录在 `xiu_listing_sources`，导入报告位于 `reports/imports/baixiu-browser/`。脚本不会把验证页当作正文写入数据库，全国每个城市/分类5～10条真实信息采集仍未完成。用户随后改为生成本地测试信息，见下节。

## 全国城市与维修分类本地测试信息

按2026-10-01的新要求，首次对城市目录中的393个启用城市和140个启用维修分类进行全组合生成：每个城市、分类直接新增5条，共275,100条。补充下节的7个家政分类后，目前为147个分类、288,855条生成信息。包括一、二、三级维修分类；知识库和百科文章不属于本次城市维修分类填充范围。父级列表会汇总子分类，页面数量可以超过5条，直接归属该分类的新增数量仍为5条。已有业务信息、用户、认证资料及5条来源信息均保留。

内容根据类别选择不同主题，例如家电包括空调、冰箱、洗衣机、热水器和厨电检修；餐饮设备、工业设备、充电设施、智能家居、适老化改造及居家养老分别使用对应服务主题。标题包括城市与分类路径，并去除标点和空格。联系人从百家姓随机选择单姓并加“师傅”；号码随机生成138开头的11位手机格式，仅用于本地展示。地址使用实际城市和已有所属区域，不虚构具体真实街道地址。所有新记录均 `is_demo=1`，前台、详情和后台标注测试信息，不生成认证标识，不把随机号码变成可拨打的链接，也不冒充采集数据。

使用独立的 `local_test_seed` 数据归属账号，随机密码不提供对外登录。`xiu_local_test_seeds` 通过城市、分类和槽位唯一索引记录批次，重复执行本次生成命令新增0条；按城市提交事务，意外中断可重新执行补齐缺额。实际创建时间用于相对时间显示，不虚构刷新时间；短URL用最多8个拼音字母或首字母加4位随机数字，总长度不超过12字符。测试详情保持 `noindex`，测试内容不进入详情和导航XML站点地图。

数据库写入前的完整恢复备份和改动文件备份位于 `backup/2026-10-01-nationwide-test-info/`。生成报告为 `reports/seeds/nationwide-test-2026-10-01-213927-ded4e4.json`。表 `xiu_local_test_seeds` 可精确定位本批生成的信息，避免与真实用户或采集内容混淆。

运行与升级：

```powershell
& 'D:/phpstudy_pro/Extensions/php/php8.0.2nts/php.exe' scripts/seed-nationwide-test.php
& 'D:/phpstudy_pro/Extensions/php/php8.0.2nts/php.exe' scripts/seed-nationwide-test.php --apply
& 'D:/phpstudy_pro/Extensions/php/php8.0.2nts/php.exe' scripts/migrate-listing-category-index.php
```

第一条只查看计划数量。为27万条数据增加分类查询覆盖索引，公开列表先筛选排序信息ID，再加载当前页正文、图片和名称；认证优先级、刷新优先、城市、分类、区域、类型及关键词筛选保持业务规则。后台继续使用已有分页与30秒统计缓存。本机实测城市分类页约0.1秒，全国列表约2.2秒，首页约2.9秒，后台前两页约0.02～0.03秒，后台末页约3.3秒；这些是本次本机测量，不是性能保证。

已通过19项数据检查（逐条校验275,100条标题与正文归属，并比较原始记录与备份）、78项HTTP检查（城市目录、代表性分类、详情、短URL、测试标记、收录设置、咨询号码、前后台分页和管理员登录）及10项认证排序与权限模型回归。重复生成新增0条。QA账号、临时分类、临时区域和临时信息均已清理。结果在 `tests/artifacts/nationwide-test-data-results.json`、`tests/artifacts/nationwide-test-http-results.json` 和 `tests/artifacts/verification-priority-model-results.json`。复验数据与HTTP可执行 `tests/nationwide-test-data.php` 和 `tests/nationwide-test-http.py`，HTTP后台检查需临时设置 `XIU_ADMIN_PASSWORD`；任何认证回归产生的QA数据应按 `tests/cleanup.php` 清理。

## 家政陪同服务分类与分类页SEO标题

“家政、生活服务”下新增7个启用的二级分类：陪护公司、陪练车、陪诊师、陪驾公司、陪护、陪餐、陪爬山。沿用既有前台分类导航、会员发布分类选择和后台分级分类管理；公开地址分别使用 `pei-hu-gong-si`、`pei-lian-che`、`pei-zhen-shi`、`pei-jia-gong-si`、`pei-hu`、`pei-can`、`pei-pa-shan`。每个新分类在393个城市各补5条对应主题的本地测试信息，新增13,755条；报告 `reports/seeds/nationwide-test-2026-10-01-222158-0b5a0b.json` 记录具体数量及本次写入前的完整数据库备份。生成内容保留测试标识，未虚构认证或采集来源。

全部维修服务分类页的HTML标题改为 `城市分类名|城市分类名电话 - 品牌名`，例如 `郴州空调维修|郴州空调维修电话 - 五修网`。品牌名读取后台的站点名称；未指定城市使用“全国”，已选区域并入地域名称。“公司”仅随分类原名保留，例如 `郴州陪护公司|郴州陪护公司电话 - 五修网`。服务/需求筛选和第2页起的分页继续添加对应标识，避免不同筛选页标题完全相同。页面可见的主标题、已有信息标题和详情短URL保持原有行为；关键词搜索仍使用搜索标题与 `noindex`。

```powershell
& 'D:/phpstudy_pro/Extensions/php/php8.0.2nts/php.exe' scripts/add-companion-service-categories.php
& 'D:/phpstudy_pro/Extensions/php/php8.0.2nts/php.exe' scripts/add-companion-service-categories.php --apply
& 'D:/phpstudy_pro/Extensions/php/php8.0.2nts/php.exe' scripts/seed-nationwide-test.php --apply
& 'D:/phpstudy_pro/Extensions/php/php8.0.2nts/php.exe' tests/life-companion-seo.php
python tests/life-companion-seo-http.py
```

分类添加脚本默认仅预览；使用 `--apply` 时先保存分类快照，再按所属一级大类和名称查重。重复添加分类创建0个，重复生成信息新增0条。新增验证已通过189项数据检查和795项HTTP检查，覆盖全部147个分类的实际标题、规范地址、结构化数据，新增分类的父级/层级、每城5条信息、发布选择项、后台入口和统计缓存；再次通过19项全国数据检查，逐条校验当前288,855条生成信息并核对原业务与认证资料。结果位于 `tests/artifacts/life-companion-seo-data.json`、`life-companion-seo-http.json` 和 `nationwide-test-data-results.json`；HTTP后台检查需临时设置 `XIU_ADMIN_PASSWORD`。源码及分类快照备份位于 `backup/2026-10-01-life-companion-seo/`，未改动phpStudy全局配置。

## 51.LA网站统计

统计ID及CK均为 `LKvmGacoqwoNTRyG`，启用 `autoTrack:true` 和 `hashMode:true`。`app/analytics.php` 统一提供初始化代码和安全策略；公共模板在页面结束前引入SDK并初始化，前台、会员中心、后台、普通错误页与500异常提示页均覆盖，XML站点地图、图片及robots文本不添加HTML脚本。

安全策略仅新增允许 `sdk.51.la` 脚本与 `collect-v6.51.la` 连接/图片上报，兼容当前HTTP和以后HTTPS访问；初始化脚本使用SHA-256哈希授权，同时保留已有JSON-LD哈希，不启用 `unsafe-inline`、`unsafe-eval` 或通配域名。

已检查27个公开、会员、后台和404页面：SDK与初始化代码各出现一次，统计初始化与JSON-LD均被对应CSP哈希授权。SDK与自动追踪模块返回200；普通浏览器重新加载后出现 `LA_COLLECT` 与 `LA_CODELESS` 脚本，没有发现浏览器错误或警告。验收记录位于 `reports/analytics-acceptance.json`，源码恢复备份为 `backup/2026-10-01-site-analytics/`。未登录51.LA后台核验统计报表。

## 首页与分类信息加载优化

公开信息查询、分类树、城市目录、筛选所需的启用/认证状态与SEO城市分类资料使用60秒文件缓存，位于非公开目录 `storage/cache/public-data/`。缓存键区分城市、分类、区域、信息类型、页码、每页数量及首页预览；只缓存前10页的普通筛选结果，关键词搜索直接查询。页面HTML、登录状态、会员资料、后台审核资料、表单CSRF及当前城市展示按请求生成，不共用整页缓存；相对时间仍在每次页面渲染时计算。

所有经 `query()` 或连接 `exec()` 写入的相关业务表会使公开数据缓存失效；事务中的修改在提交成功后更新缓存版本，回滚保留上次已提交的版本，事务中的查询不写共享缓存。覆盖发布/编辑、审核/下架、会员刷新、用户停用、认证变更、分类/城市/区域变更、图片、文章和站点设置；全国测试信息批量生成已有的 `clearAdminListingCountCache()` 也同步失效公开缓存。缓存重建使用文件锁，多个请求不会同时重建同一结果。绕过应用直接执行数据库SQL时，缓存最多保留60秒；需要立即更新可执行：

```powershell
& 'D:/phpstudy_pro/Extensions/php/php8.0.2nts/php.exe' -r "require 'app/bootstrap.php'; invalidatePublicDataCache();"
```

首页仅读取需要显示的5～6条信息，不再执行7次分页总数查询。第1页按“已刷新、企业认证、个人认证、未认证”分组读取，保留各组既有排序，避免将全部已发布记录按计算表达式排序；后续分页沿用相同总体排序。普通筛选总数使用已有 `listings_category_filter` 覆盖索引，当前页详情按主键读取。`scripts/init.php` 已加入该索引的幂等迁移；现有数据库无需重新初始化。统计SDK使用 `async`，待加载成功后执行原来的 `LA.init` 配置，保留自动追踪、Hash统计和CSP精确哈希授权。

本机针对当前288,889条信息的HTTP响应测量：优化前首页3次约1.34～1.44秒、分类总页约0.66～0.81秒；优化后清空缓存首次约0.27秒/0.43秒，缓存命中后两页约0.02～0.05秒（个别采样约0.052秒）。记录位于 `reports/public-performance-before.json`、`public-performance-after.json`。这是本机请求结果，不代表外网速度或数据库完全冷启动速度。页面响应头 `X-Xiu-Data-Cache` 提供命中、未命中和绕过次数，便于排查。

已通过22项缓存验证和10项认证排序回归，检查独立HTTP请求复用、过期重建、提交/回滚、修改/审核/下架、会员真实刷新、认证与启停立即更新、关键词绕过和缓存不含敏感字段；测试数据按记录的QA范围清理。再次通过795项分类SEO/后台HTTP检查和78项全国信息/详情/分页检查。3个同时到达的空缓存请求只重建一次，并返回相同结果，异步统计与JSON-LD哈希均被安全策略允许；浏览器首页已看到自动追踪模块加载，没有错误或警告。

缓存验收可依次运行 `tests/verification-priority.php`、`tests/public-cache.php`、`tests/cleanup.php`；清理前不要覆盖 `tests/artifacts/fixtures.json` 或执行全国信息填充。结果为 `tests/artifacts/public-cache-results.json` 和 `reports/public-cache-concurrent.json`。源码及改动前相关产物备份位于 `backup/2026-10-01-public-page-cache/`，没有更改phpStudy全局配置。

## 分类信息审核邮件通知

后台入口 `/dgadmin/index.php?section=notifications`（侧栏“邮件通知”）支持启停通知、修改SMTP与收件邮箱、发送测试邮件、查看分页发送记录和重试失败邮件。当前已启用腾讯企业邮箱 `smtp.exmail.qq.com:465`、SSL/TLS、发件账号 `notice@dgid.cn`，收件邮箱为 `5841079@qq.com`。SMTP密码仅在非公开目录 `storage/mail/settings.json` 中以AES-256-GCM加密保存，密钥为同目录 `secret.key`；后台密码框留空会保留已有密码，页面和发送错误不回显密码。迁移或恢复邮件配置时需要同时保留配置和密钥。

新发布、修改已发布信息或驳回后重提进入待审核时，在提交事务中写入 `xiu_mail_notifications` 队列，提交成功后启动隐藏的PHP CLI进程发送。通知包含信息编号、标题、类型、城市、分类、发布账号、提交时间与后台审核入口，不包含正文、联系电话或身份证资料；不自动改变信息审核状态。相同提交事件去重，事务回滚也回滚通知；入队或发送失败不会阻止用户提交信息。发送前再次检查信息仍待审核且收件配置未改变。队列任务以原子状态更新领取，后台进程使用文件锁防止并发重复发送；发送失败可手动重试，最多3次，发送中断且结果不明的任务需先核实收件情况再重试。测试邮件独立同步发送，避免和后台队列抢占。

发送库使用官方 [PHPMailer v7.1.1](https://github.com/PHPMailer/PHPMailer/releases/tag/v7.1.1)，源码、许可、版本及下载来源记录在 `vendor/PHPMailer/`；保留服务器证书和域名验证。数据库初始化已包含幂等邮件表迁移，升级或手动处理待发队列可执行：

```powershell
& 'D:/phpstudy_pro/Extensions/php/php8.0.2nts/php.exe' scripts/migrate-mail-notifications.php
& 'D:/phpstudy_pro/Extensions/php/php8.0.2nts/php.exe' scripts/process-mail-queue.php
```

2026-10-01已实际向上述收件邮箱发送1封“[五修网] 邮件通知测试”，SMTP服务器确认接收；记录保存在后台和 `reports/mail-smtp-test.json`。此结果仅确认SMTP接收，未登录收件邮箱核实送达。通过19项队列检查及17项HTTP检查，覆盖去重、回滚、修改重提、关闭通知、失败隔离、密码隐藏、重试限制、后台异步进程、认证与CSRF、私有目录不可访问及公开页缓存。其余检查使用本地替代发送器或保留测试域名，没有再次发送真实邮件；QA用户、信息与发送记录已清理。验收记录为 `tests/artifacts/mail-notifications-results.json` 和 `mail-notifications-http-results.json`，可运行 `tests/mail-notifications.php` 与 `tests/mail-notifications-http.py`；HTTP检查需临时设置 `XIU_ADMIN_PASSWORD`。源码恢复备份位于 `backup/2026-10-01-mail-notifications/`，配置后续修改前会保存加密备份，没有修改phpStudy全局配置。
