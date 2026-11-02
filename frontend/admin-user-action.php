<?php
declare(strict_types=1);
require __DIR__ . '/../backend/src/bootstrap.php';
$admin=require_admin();
if($_SERVER['REQUEST_METHOD']!=='POST'||!verify_csrf($_POST['csrf_token']??null)){http_response_code(400);exit('Некорректный запрос.');}
$id=filter_var($_POST['user_id']??null,FILTER_VALIDATE_INT); $role=(string)($_POST['role']??''); $active=(string)($_POST['is_active']??'');
if(!$id||$id<1||!in_array($role,['user','admin'],true)||!in_array($active,['0','1'],true)){http_response_code(400);exit('Проверьте данные формы.');}
if($id===(int)$admin['id']){set_flash('error','Нельзя менять роль или блокировать текущий аккаунт администратора.');header('Location: admin-users.php');exit;}
$stmt=db()->prepare('SELECT id,role,is_active FROM users WHERE id=:id');$stmt->execute(['id'=>$id]);$target=$stmt->fetch();
if(!$target){set_flash('error','Пользователь не найден.');header('Location: admin-users.php');exit;}
if($target['role']==='admin'&&(bool)$target['is_active']&&($role!=='admin'||$active==='0')){
    $admins=(int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
    if($admins<=1){set_flash('error','Нельзя отключить последнего активного администратора.');header('Location: admin-users.php');exit;}
}
$update=db()->prepare('UPDATE users SET role=:role,is_active=:active WHERE id=:id');$update->execute(['role'=>$role,'active'=>(int)$active,'id'=>$id]);
set_flash('success','Роль и состояние пользователя обновлены.');header('Location: admin-users.php');exit;
