<?php
declare(strict_types=1);

function mailDirectory(): string {
    $directory=ROOT.'/storage/mail';
    if(!is_dir($directory) && !mkdir($directory,0700,true)) throw new DomainException('无法创建邮件配置目录。');
    return $directory;
}
function mailSecretKey(): string {
    $path=mailDirectory().'/secret.key';
    if(!is_file($path)) {
        $key=random_bytes(32); $stream=@fopen($path,'xb');
        if(!$stream || fwrite($stream,$key)!==32) throw new DomainException('无法保存邮件配置密钥。');
        fclose($stream); @chmod($path,0600);
    }
    $key=file_get_contents($path);
    if(strlen($key)!==32) throw new DomainException('邮件配置密钥无效。');
    return $key;
}
function mailSettings(): array {
    $defaults=['enabled'=>false,'host'=>'smtp.exmail.qq.com','port'=>465,'security'=>'ssl','username'=>'','recipient'=>'','password'=>''];
    $path=mailDirectory().'/settings.json';
    if(!is_file($path)) return $defaults;
    $stream=fopen($path,'rb'); flock($stream,LOCK_SH); $raw=stream_get_contents($stream); flock($stream,LOCK_UN); fclose($stream);
    $stored=json_decode($raw,true);
    if(!is_array($stored)) throw new DomainException('邮件配置文件无效。');
    $settings=array_merge($defaults,array_intersect_key($stored,$defaults));
    if(!empty($stored['secret'])) {
        $secret=$stored['secret'];
        $password=openssl_decrypt(base64_decode($secret['cipher'],true),'aes-256-gcm',mailSecretKey(),OPENSSL_RAW_DATA,base64_decode($secret['iv'],true),base64_decode($secret['tag'],true));
        if($password===false) throw new DomainException('无法解密邮件密码，请重新配置。');
        $settings['password']=$password;
    }
    return $settings;
}
function saveMailSettings(array $input): void {
    $directory=mailDirectory(); $lock=fopen($directory.'/settings.lock','c'); flock($lock,LOCK_EX);
    try {
        $old=mailSettings(); $password=(string)($input['password']??'');
        if($password==='') $password=$old['password'];
        $settings=['enabled'=>!empty($input['enabled']),'host'=>trim((string)($input['host']??'')),'port'=>(int)($input['port']??0),'security'=>(string)($input['security']??'ssl'),'username'=>trim((string)($input['username']??'')),'recipient'=>trim((string)($input['recipient']??''))];
        if(!preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/i',$settings['host'])) throw new DomainException('SMTP服务器请填写域名，不填写协议或路径。');
        if($settings['port']<1 || $settings['port']>65535 || !in_array($settings['security'],['ssl','tls'],true)) throw new DomainException('请选择有效端口及加密方式。');
        if(!filter_var($settings['username'],FILTER_VALIDATE_EMAIL) || !filter_var($settings['recipient'],FILTER_VALIDATE_EMAIL)) throw new DomainException('请填写有效的发件账号和收件邮箱。');
        if($password==='' || strlen($password)>512) throw new DomainException('请填写SMTP密码或授权码。');
        $iv=random_bytes(12); $tag=''; $cipher=openssl_encrypt($password,'aes-256-gcm',mailSecretKey(),OPENSSL_RAW_DATA,$iv,$tag);
        if($cipher===false) throw new DomainException('无法加密SMTP密码。');
        $settings['secret']=['iv'=>base64_encode($iv),'tag'=>base64_encode($tag),'cipher'=>base64_encode($cipher)];
        $path=$directory.'/settings.json';
        if(is_file($path)) {
            $backup=ROOT.'/backup/'.date('Y-m-d').'-mail-notifications/private-config';
            if(!is_dir($backup)) mkdir($backup,0700,true);
            if(!copy($path,$backup.'/settings-before-'.date('His').'-'.bin2hex(random_bytes(3)).'.json')) throw new DomainException('邮件配置备份失败。');
        }
        $json=json_encode($settings,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
        if(file_put_contents($path,$json,LOCK_EX)===false) throw new DomainException('邮件配置保存失败。');
        @chmod($path,0600);
    } finally { flock($lock,LOCK_UN); fclose($lock); }
}
function mailError(string $message,string $password=''): string {
    if($password!=='') $message=str_replace([$password,base64_encode($password)],'[已隐藏]',$message);
    return mb_substr(trim(preg_replace('/\s+/u',' ',strip_tags($message))),0,1000);
}
function sendSmtpMessage(array $settings,array $job): string {
    foreach(['Exception','SMTP','PHPMailer'] as $class) require_once ROOT.'/vendor/PHPMailer/src/'.$class.'.php';
    $mail=new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP(); $mail->Host=$settings['host']; $mail->Port=$settings['port'];
    $mail->SMTPAuth=true; $mail->Username=$settings['username']; $mail->Password=$settings['password'];
    $mail->SMTPSecure=$settings['security']; $mail->Timeout=8; $mail->Timelimit=20; $mail->SMTPDebug=0;
    $mail->SMTPOptions=['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]];
    $mail->CharSet='UTF-8'; $mail->Encoding='base64'; $mail->setFrom($settings['username'],siteName());
    $mail->addAddress($job['recipient']); $mail->Subject=$job['subject']; $mail->Body=$job['body']; $mail->isHTML(false);
    try { $mail->send(); return $mail->getLastMessageID(); }
    catch(Throwable $exception) {
        $error=$mail->ErrorInfo?:$exception->getMessage(); $smtp=$mail->getSMTPInstance()->getError();
        if(!empty($smtp['smtp_code'])) $error.=' SMTP '.$smtp['smtp_code'].': '.($smtp['detail']??'');
        throw new DomainException(mailError($error,$settings['password']));
    } finally { $mail->smtpClose(); }
}
function enqueueListingNotification(int $id,?array $settings=null): ?int {
    $settings=$settings??mailSettings(); if(!$settings['enabled']) return null;
    $listing=row("SELECT l.*,u.username,c.name city_name,k.name category_name FROM xiu_listings l JOIN xiu_users u ON u.id=l.user_id JOIN xiu_cities c ON c.id=l.city_id JOIN xiu_categories k ON k.id=l.category_id WHERE l.id=? AND l.status='pending'",[$id]);
    if(!$listing) return null;
    $event=hash('sha256',json_encode([$id,$listing['updated_at'],$listing['title'],$listing['body'],$listing['category_id'],$listing['city_id'],$listing['district_id'],$listing['address'],$listing['contact'],$listing['phone']],JSON_UNESCAPED_UNICODE));
    $subject='['.siteName().'] 分类信息待审核：'.$listing['title'];
    $body=siteName()."收到一条待审核分类信息。\n\n信息编号：{$id}\n标题：{$listing['title']}\n类型：".listingType($listing['type'])."\n城市：{$listing['city_name']}\n分类：{$listing['category_name']}\n发布账号：{$listing['username']}\n提交时间：{$listing['updated_at']}\n\n请登录后台核实内容：\n".absoluteUrl(adminUrl('listings',['status'=>'pending'])).'#listing-'.$id."\n\n此邮件由网站自动发送。";
    query("INSERT IGNORE INTO xiu_mail_notifications (listing_id,event_key,recipient,subject,body) VALUES (?,?,?,?,?)",[$id,$event,$settings['recipient'],$subject,$body]);
    return (int)scalar('SELECT id FROM xiu_mail_notifications WHERE event_key=?',[$event]);
}
function sendMailJob(int $id,?callable $transport=null): array {
    if($transport!==null && PHP_SAPI!=='cli') throw new LogicException('Test transports are CLI-only.');
    $job=row('SELECT * FROM xiu_mail_notifications WHERE id=?',[$id]);
    if(!$job || $job['status']!=='queued') return ['status'=>'skipped'];
    $settings=mailSettings();
    if($transport===null && $job['kind']==='listing') {
        if(!$settings['enabled']) return ['status'=>'paused'];
        if($job['recipient']!==$settings['recipient'] || !$job['listing_id'] || !scalar("SELECT id FROM xiu_listings WHERE id=? AND status='pending'",[$job['listing_id']])) {
            query("UPDATE xiu_mail_notifications SET status='cancelled',last_error='收件配置已变化或信息已不再待审核。' WHERE id=? AND status='queued'",[$id]);
            return ['status'=>'cancelled'];
        }
    }
    if(!query("UPDATE xiu_mail_notifications SET status='sending',attempts=attempts+1,last_attempt_at=NOW(),last_error='' WHERE id=? AND status='queued'",[$id])->rowCount()) return ['status'=>'skipped'];
    try {
        $messageId=$transport?$transport($settings,$job):sendSmtpMessage($settings,$job);
        query("UPDATE xiu_mail_notifications SET status='sent',message_id=?,sent_at=NOW(),last_error='' WHERE id=?",[mb_substr((string)$messageId,0,255),$id]);
        return ['status'=>'sent','message_id'=>$messageId];
    } catch(Throwable $exception) {
        $error=mailError($exception->getMessage(),$settings['password']);
        query("UPDATE xiu_mail_notifications SET status='failed',last_error=? WHERE id=?",[$error,$id]);
        return ['status'=>'failed','error'=>$error];
    }
}
function queueListingReviewNotification(int $id,?array $settings=null): ?int {
    try { return enqueueListingNotification($id,$settings); }
    catch(Throwable $exception) { error_log('Listing review mail could not be queued for listing '.$id.'.'); return null; }
}
function retryMailJob(int $id): void {
    $job=row('SELECT * FROM xiu_mail_notifications WHERE id=?',[$id]);
    if(!$job || $job['status']!=='failed') throw new DomainException('只能重试发送失败的邮件。');
    if($job['attempts']>=3) throw new DomainException('此邮件已尝试3次，请先检查SMTP配置。');
    query("UPDATE xiu_mail_notifications SET status='queued',last_error='' WHERE id=? AND status='failed'",[$id]);
}
function processMailQueue(int $limit=5): array {
    query("UPDATE xiu_mail_notifications SET status='failed',last_error='上次发送中断，结果未确认；请核实收件情况后再重试。' WHERE status='sending' AND last_attempt_at<DATE_SUB(NOW(),INTERVAL 5 MINUTE)");
    $results=[];
    foreach(rows("SELECT id FROM xiu_mail_notifications WHERE status='queued' AND kind='listing' ORDER BY id LIMIT ".max(1,min(20,$limit))) as $job) $results[$job['id']]=sendMailJob((int)$job['id']);
    return $results;
}
function startMailWorker(): bool {
    try {
    if(!function_exists('proc_open')) throw new RuntimeException('Process launching is unavailable.');
    $php=PHP_OS_FAMILY==='Windows'?dirname(PHP_BINARY).'/php.exe':PHP_BINARY;
    if(!is_file($php)) throw new RuntimeException('PHP CLI is unavailable.');
    $script=ROOT.'/scripts/process-mail-queue.php';
    if(PHP_OS_FAMILY==='Windows') {
        $quote=fn($value)=>"'".str_replace("'","''",$value)."'";
        $command=['powershell.exe','-NoProfile','-NonInteractive','-WindowStyle','Hidden','-Command','Start-Process -FilePath '.$quote($php).' -ArgumentList '.$quote('"'.$script.'"').' -WindowStyle Hidden'];
        $null='NUL';
    } else { $command=escapeshellarg($php).' '.escapeshellarg($script).' > /dev/null 2>&1 &'; $null='/dev/null'; }
    $process=@proc_open($command,[0=>['file',$null,'r'],1=>['file',$null,'a'],2=>['file',$null,'a']],$pipes,ROOT);
    if(!is_resource($process) || proc_close($process)!==0) throw new RuntimeException('Mail worker launch failed.');
    return true;
    } catch(Throwable $exception) { error_log('Mail worker could not be started. Pending notifications remain in the queue.'); return false; }
}
function queueMailTest(): int {
    $settings=mailSettings();
    $body=siteName()."邮件通知测试\n\n这是一封后台SMTP测试邮件。\n发送时间：".date('Y-m-d H:i:s')."\n网站：".siteUrl()."\n\n如收到此邮件，说明当前SMTP配置能够发送邮件。新提交或修改重提的分类信息进入待审核后，将向此收件邮箱发送通知。";
    query("INSERT INTO xiu_mail_notifications (event_key,kind,recipient,subject,body) VALUES (?,'test',?,?,?)",[bin2hex(random_bytes(32)),$settings['recipient'],'['.siteName().'] 邮件通知测试',$body]);
    return (int)db()->lastInsertId();
}
function mailNotificationStatus(string $status): string { return ['queued'=>'等待发送','sending'=>'正在发送','sent'=>'服务器已接收','failed'=>'发送失败','cancelled'=>'已取消'][$status]??$status; }
function adminMailController(): void {
    requireAdmin(); $error='';
    if($_SERVER['REQUEST_METHOD']==='POST') {
        try {
            $action=input('action');
            if($action==='save') {
                saveMailSettings(['enabled'=>input('enabled')==='1','host'=>input('host'),'port'=>input('port'),'security'=>input('security'),'username'=>input('username'),'recipient'=>input('recipient'),'password'=>(string)field('smtp_password')]);
                flash('邮件通知配置已保存。'); startMailWorker();
            } elseif($action==='test') {
                $id=queueMailTest(); $sent=sendMailJob($id);
                if($sent['status']!=='sent') throw new DomainException('测试邮件发送失败：'.($sent['error']??'请检查配置。'));
                flash('SMTP服务器已接收测试邮件，请检查收件箱或垃圾邮件。');
            } elseif($action==='retry') {
                $id=(int)input('id'); retryMailJob($id);
                if(scalar('SELECT kind FROM xiu_mail_notifications WHERE id=?',[$id])==='test') {
                    $sent=sendMailJob($id);
                    if($sent['status']!=='sent') throw new DomainException('测试邮件发送失败：'.($sent['error']??'请检查配置。'));
                    flash('SMTP服务器已接收测试邮件，请检查收件箱或垃圾邮件。');
                } else { $started=startMailWorker(); flash($started?'已重新加入发送队列。':'已加入队列，但发送进程未启动，请检查服务器日志。'); }
            }
            elseif($action==='process') { $started=startMailWorker(); flash($started?'邮件发送任务已启动。':'发送进程未启动，请检查服务器日志或运行队列脚本。'); }
            else throw new DomainException('无效的邮件操作。');
            redirect(adminUrl('notifications'));
        } catch(DomainException $exception) { $error=$exception->getMessage(); }
    }
    $settings=mailSettings(); $settings['has_password']=$settings['password']!==''; unset($settings['password']);
    $total=(int)scalar('SELECT COUNT(*) FROM xiu_mail_notifications'); $pages=max(1,(int)ceil($total/20)); $page=min(max(1,(int)getValue('page',1)),$pages);
    $logs=rows('SELECT id,listing_id,kind,recipient,subject,status,attempts,last_error,created_at,sent_at FROM xiu_mail_notifications ORDER BY id DESC LIMIT 20 OFFSET '.(($page-1)*20));
    render('admin',['adminPage'=>true,'section'=>'notifications','error'=>$error,'mailConfig'=>$settings,'mailLogs'=>$logs,'result'=>compact('total','page','pages')]);
}
