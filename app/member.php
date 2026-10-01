<?php
function memberUrl(string $section='dashboard',array $params=[]): string {
    $paths=['dashboard'=>'','listings'=>'wo-de-fa-bu/','verification'=>'shen-fen-ren-zheng/'];
    return publicPath('user').$paths[$section].($params?qs($params):'');
}
function verificationStatus(?array $record): string { return $record?(['pending'=>'待审核','approved'=>'已认证','rejected'=>'认证未通过'][$record['status']]??'未认证'):'未认证'; }
function verificationType(string $type): string { return $type==='company'?'企业认证':'个人认证'; }
function verificationRoles(string $type): array { return $type==='personal'?['id_front'=>'身份证正面','id_back'=>'身份证反面']:['business_license'=>'营业执照']; }
function verificationFiles(int $id): array { return array_column(rows('SELECT * FROM xiu_verification_files WHERE verification_id=? ORDER BY id',[$id]),null,'role'); }
function refreshDay(): string { return (new DateTimeImmutable('now',new DateTimeZone('Asia/Shanghai')))->format('Y-m-d'); }
function refreshAvailable(int $userId): bool { return !scalar('SELECT id FROM xiu_listing_refreshes WHERE user_id=? AND refresh_date=?',[$userId,refreshDay()]); }
function refreshListing(array $user,int $id): void {
    db()->beginTransaction();
    try {
        if(!row('SELECT id FROM xiu_users WHERE id=? AND enabled=1 FOR UPDATE',[$user['id']])) throw new DomainException('账号已停用，无法刷新信息。');
        $listing=row('SELECT * FROM xiu_listings WHERE id=? AND user_id=? FOR UPDATE',[$id,$user['id']]);
        if(!$listing) throw new DomainException('信息不存在或无权刷新。');
        if(!visibleListing($listing)) throw new DomainException('只能刷新当前公开展示的已发布信息；请检查审核状态及分类、城市是否启用。');
        $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Shanghai')); $day=$now->format('Y-m-d');
        if(scalar('SELECT id FROM xiu_listing_refreshes WHERE user_id=? AND refresh_date=?',[$user['id'],$day])) throw new DomainException('今日免费刷新次数已用完，每天可刷新1条信息，请明天再来。');
        $time=$now->format('Y-m-d H:i:s.u');
        query('INSERT INTO xiu_listing_refreshes (user_id,listing_id,refresh_date,refreshed_at) VALUES (?,?,?,?)',[$user['id'],$id,$day,$time]);
        $order=db()->lastInsertId();
        query('UPDATE xiu_listings SET refreshed_at=?,refresh_order=? WHERE id=? AND user_id=?',[$time,$order,$id,$user['id']]);
        db()->commit();
    } catch(Throwable $exception) {
        if(db()->inTransaction()) db()->rollBack();
        if($exception instanceof PDOException && ($exception->errorInfo[1]??0)===1062) throw new DomainException('今日免费刷新次数已用完，每天可刷新1条信息，请明天再来。');
        throw $exception;
    }
}
function memberRefreshController(): void {
    $user=requireUser(); header('X-Robots-Tag: noindex, nofollow');
    if($_SERVER['REQUEST_METHOD']!=='POST') abortPage(405,'请使用信息旁的刷新按钮。');
    try { refreshListing($user,(int)input('id')); flash('刷新成功，信息已在所属分类中优先展示，最新刷新信息排在最前。'); }
    catch(DomainException $exception) { flash($exception->getMessage(),'error'); }
    $section=input('return_section')==='dashboard'?'dashboard':'listings'; $params=[];
    if($section==='listings') {
        if(in_array(input('status'),['pending','published','rejected','offline'],true)) $params['status']=input('status');
        if((int)input('page')>1) $params['page']=(int)input('page');
    }
    redirect(memberUrl($section,$params));
}
function memberController(string $section): void {
    $user=requireUser(); $error='';
    if($_SERVER['REQUEST_METHOD']==='POST') {
        if($section!=='verification') abortPage(405,'请使用对应的操作表单。');
        try { submitVerification($user); flash('认证材料已提交，请等待管理员审核。'); redirect(memberUrl('verification')); }
        catch(DomainException $exception) { $error=$exception->getMessage(); }
    }
    $verification=row('SELECT * FROM xiu_user_verifications WHERE user_id=?',[$user['id']]);
    $documents=$verification?verificationFiles((int)$verification['id']):[];
    $stats=row("SELECT COUNT(*) total,COALESCE(SUM(status='published'),0) published,COALESCE(SUM(status='pending'),0) pending,COALESCE(SUM(status='rejected'),0) rejected,COALESCE(SUM(status='offline'),0) offline,COALESCE(SUM(type='service'),0) services,COALESCE(SUM(type='request'),0) requests FROM xiu_listings WHERE user_id=?",[$user['id']]);
    $status=$section==='listings'?getValue('status'):'';
    if($status!=='' && !in_array($status,['pending','published','rejected','offline'],true)) abortPage(404,'信息状态不存在。');
    $page=max(1,(int)getValue('page',1)); $where=' WHERE l.user_id=?'; $params=[$user['id']];
    if($status!=='') { $where.=' AND l.status=?'; $params[]=$status; }
    $total=(int)scalar('SELECT COUNT(*) FROM xiu_listings l'.$where,$params); $pages=max(1,(int)ceil($total/12)); $page=min($page,$pages);
    $items=rows('SELECT l.*,k.name category_name,c.name city_name FROM xiu_listings l JOIN xiu_categories k ON k.id=l.category_id JOIN xiu_cities c ON c.id=l.city_id'.$where.' ORDER BY l.id DESC LIMIT 12 OFFSET '.(($page-1)*12),$params);
    $query=[]; if($status!=='') $query['status']=$status; if($page>1) $query['page']=$page;
    $formType=getValue('type',$verification['type']??'personal');
    if(!in_array($formType,['personal','company'],true)) abortPage(404,'认证类型不存在。');
    if($section==='verification') $query=getValue('type')!==''?['type'=>$formType]:[];
    enforceCanonical(memberUrl($section,$query));
    $joined=(string)scalar('SELECT created_at FROM xiu_users WHERE id=?',[$user['id']]);
    render('account',['title'=>['dashboard'=>'会员管理中心','listings'=>'我的发布','verification'=>'身份认证'][$section].' · '.siteName(),'memberPage'=>true,'memberSection'=>$section,'memberUser'=>$user,'joined'=>$joined,'verification'=>$verification,'documents'=>$documents,'formType'=>$formType,'error'=>$error,'stats'=>$stats,'status'=>$status,'refreshAvailable'=>refreshAvailable((int)$user['id']),'result'=>compact('items','page','pages','total')]);
}
function submitVerification(array $user): void {
    $type=input('type'); if(!in_array($type,['personal','company'],true)) throw new DomainException('请选择个人认证或企业认证。');
    $name=input('subject_name'); validateText($name,$type==='personal'?'真实姓名':'企业名称',2,120);
    $roles=verificationRoles($type); $uploaded=[];
    try {
        foreach(['id_front','id_back','business_license'] as $role) {
            $errors=$_FILES[$role]['error']??[];
            if(!is_array($errors)) throw new DomainException('证件上传格式不正确。');
            if(!isset($roles[$role]) && array_filter($errors,fn($error)=>$error!==UPLOAD_ERR_NO_FILE)) throw new DomainException('请上传所选认证类型对应的证件。');
        }
        foreach($roles as $role=>$label) { $images=uploadedImages($role,1,'verification'); if($images) $uploaded[$role]=$images[0]; }
        db()->beginTransaction();
        if(!row('SELECT id FROM xiu_users WHERE id=? AND enabled=1 FOR UPDATE',[$user['id']])) throw new DomainException('账号已停用，请重新登录。');
        $old=row('SELECT * FROM xiu_user_verifications WHERE user_id=? FOR UPDATE',[$user['id']]);
        $prior=$old?verificationFiles((int)$old['id']):[];
        foreach($roles as $role=>$label) if(!isset($uploaded[$role]) && (!$old || $old['type']!==$type || !isset($prior[$role]))) throw new DomainException('请上传'.$label.'。');
        if($old) {
            $id=(int)$old['id'];
            query("UPDATE xiu_user_verifications SET type=?,subject_name=?,status='pending',revision=revision+1,rejection_reason='',reviewed_by=NULL,reviewed_at=NULL,submitted_at=NOW(),updated_at=NOW() WHERE id=?",[$type,$name,$id]);
        } else {
            query('INSERT INTO xiu_user_verifications (user_id,type,subject_name) VALUES (?,?,?)',[$user['id'],$type,$name]); $id=(int)db()->lastInsertId();
        }
        $deleted=[];
        foreach($prior as $role=>$file) if($old['type']!==$type || isset($uploaded[$role])) { $deleted[]=$file; query('DELETE FROM xiu_verification_files WHERE id=? AND verification_id=?',[$file['id'],$id]); }
        foreach($uploaded as $role=>$file) query('INSERT INTO xiu_verification_files (verification_id,role,filename,mime,width,height) VALUES (?,?,?,?,?,?)',[$id,$role,$file['filename'],$file['mime'],$file['width'],$file['height']]);
        db()->commit(); removeImages($deleted,'verification');
    } catch(Throwable $exception) { if(db()->inTransaction()) db()->rollBack(); removeImages(array_values($uploaded),'verification'); throw $exception; }
}
function verificationDocumentController(int $id): void {
    $file=row('SELECT f.*,v.user_id FROM xiu_verification_files f JOIN xiu_user_verifications v ON v.id=f.verification_id WHERE f.id=?',[$id]);
    if(!$file || (!currentAdmin() && (int)(currentUser()['id']??0)!==(int)$file['user_id'])) abortPage(404,'材料不存在或无权查看。');
    $path=ROOT.'/storage/verification/'.$file['filename']; if(!is_file($path)) abortPage(404,'材料不存在。');
    header('Cache-Control: private, no-store, max-age=0'); header('X-Robots-Tag: noindex, nofollow'); header('Content-Type: '.$file['mime']);
    header('Content-Length: '.filesize($path)); header('Content-Disposition: inline'); readfile($path);
}
function reviewVerification(): void {
    $id=(int)input('id'); $revision=(int)input('revision'); $action=input('action'); $reason='';
    if($action==='delete') { deleteVerification($id,$revision,input('status')); return; }
    if(!in_array($action,['approve','reject'],true) || !$id || $revision<1) throw new DomainException('请选择待审核认证。');
    if($action==='reject') { $reason=input('reason'); validateText($reason,'驳回原因',2,500); }
    $updated=query("UPDATE xiu_user_verifications SET status=?,rejection_reason=?,reviewed_by=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=? AND revision=? AND status='pending'",[$action==='approve'?'approved':'rejected',$reason,currentAdmin()['id'],$id,$revision])->rowCount();
    if(!$updated) throw new DomainException('认证状态或材料已改变，请刷新后再审核。');
}
function deleteVerification(int $id,int $revision,string $status): void {
    $admin=currentAdmin();
    if(!$admin) throw new DomainException('请以管理员身份登录后操作。');
    if($id<1 || $revision<1 || !in_array($status,['pending','approved','rejected'],true)) throw new DomainException('请选择要删除的认证。');
    $owner=row('SELECT user_id FROM xiu_user_verifications WHERE id=?',[$id]);
    if(!$owner) throw new DomainException('认证已删除或不存在，请刷新页面。');
    $moved=[];
    db()->beginTransaction();
    try {
        // Match member submission's lock order so a replacement cannot race deletion.
        if(!row('SELECT id FROM xiu_users WHERE id=? FOR UPDATE',[$owner['user_id']])) throw new DomainException('会员账号不存在。');
        $record=row('SELECT * FROM xiu_user_verifications WHERE id=? FOR UPDATE',[$id]);
        if(!$record || (int)$record['revision']!==$revision || $record['status']!==$status) throw new DomainException('认证状态或材料已改变，请刷新后再删除。');
        $files=rows('SELECT * FROM xiu_verification_files WHERE verification_id=? ORDER BY id FOR UPDATE',[$id]);
        $backup=ROOT.'/backup/'.date('Y-m-d').'-verification-delete/'.$id.'-'.date('His').'-'.bin2hex(random_bytes(6));
        if(!mkdir($backup.'/files',0700,true)) throw new DomainException('无法创建认证恢复备份，删除未执行。');
        $plan=[];
        foreach($files as $file) {
            if(!preg_match('/^[a-f0-9]{40}\.(?:jpg|png|webp)$/',$file['filename'])) throw new DomainException('材料文件名异常，删除未执行。');
            $source=ROOT.'/storage/verification/'.$file['filename']; $target=$backup.'/files/'.$file['filename'];
            if(is_file($source)) {
                $resolved=realpath($source); $directory=realpath(ROOT.'/storage/verification');
                if(!$resolved || !$directory || strcasecmp(dirname($resolved),$directory)!==0) throw new DomainException('材料路径异常，删除未执行。');
            }
            $plan[]=['source'=>$source,'backup'=>$target,'exists'=>is_file($source)];
        }
        $manifest=['created_at'=>date(DATE_ATOM),'admin_id'=>(int)$admin['id'],'verification'=>$record,'files'=>$files,'file_operations'=>$plan];
        $json=json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
        if(file_put_contents($backup.'/manifest.json',$json,LOCK_EX)!==strlen($json)) throw new DomainException('认证记录备份失败，删除未执行。');
        // The manifest lists exact source and destination paths before moving any material.
        foreach($plan as $operation) if($operation['exists']) {
            if(!rename($operation['source'],$operation['backup'])) throw new DomainException('证件材料备份失败，删除未执行。');
            $moved[]=$operation;
        }
        if(query('DELETE FROM xiu_user_verifications WHERE id=? AND revision=? AND status=?',[$id,$revision,$status])->rowCount()!==1) throw new DomainException('认证状态或材料已改变，请刷新后再删除。');
        db()->commit();
    } catch(Throwable $error) {
        if(db()->inTransaction()) db()->rollBack();
        foreach(array_reverse($moved) as $operation) if(!rename($operation['backup'],$operation['source'])) error_log('Verification delete rollback could not restore material: '.$operation['source'].'; recovery copy: '.$operation['backup']);
        throw $error;
    }
}
