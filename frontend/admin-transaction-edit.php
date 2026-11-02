<?php
declare(strict_types=1);
require __DIR__ . '/../backend/src/bootstrap.php';
$admin = require_admin();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(404); exit('Запись не найдена.'); }
$stmt = db()->prepare('SELECT id,user_id,category_id,type,status,amount,transaction_date,description FROM transactions WHERE id=:id');
$stmt->execute(['id'=>$id]); $row = $stmt->fetch();
if (!$row) { http_response_code(404); exit('Запись не найдена.'); }
$categoriesStmt = db()->prepare('SELECT id,name,type FROM categories WHERE user_id=:user_id OR user_id IS NULL ORDER BY name');
$categoriesStmt->execute(['user_id'=>(int)$row['user_id']]); $categories = $categoriesStmt->fetchAll();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) $errors[] = 'Форма устарела. Обновите страницу.';
    $type = (string)($_POST['type'] ?? ''); $status = (string)($_POST['status'] ?? '');
    $amount = trim((string)($_POST['amount'] ?? '')); $date = (string)($_POST['transaction_date'] ?? '');
    $description = trim((string)($_POST['description'] ?? '')); $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
    if (!in_array($type, ['income','expense'], true)) $errors[] = 'Выберите тип операции.';
    if (!in_array($status, ['planned','completed','cancelled'], true)) $errors[] = 'Выберите статус.';
    if (!is_numeric($amount) || (float)$amount <= 0 || (float)$amount > 999999999999) $errors[] = 'Сумма должна быть больше нуля.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))) $errors[] = 'Укажите корректную дату.';
    if (mb_strlen($description) > 255) $errors[] = 'Описание не должно превышать 255 символов.';
    $category = null; foreach ($categories as $candidate) if ((int)$candidate['id'] === (int)$categoryId) $category = $candidate;
    if (!$category || $category['type'] !== $type) $errors[] = 'Выберите категорию, подходящую выбранному типу.';
    if (!$errors) {
        $update = db()->prepare('UPDATE transactions SET category_id=:category_id,type=:type,status=:status,amount=:amount,transaction_date=:transaction_date,description=:description WHERE id=:id');
        $update->execute(['category_id'=>$categoryId,'type'=>$type,'status'=>$status,'amount'=>number_format((float)$amount,2,'.',''),'transaction_date'=>$date,'description'=>$description === '' ? null : $description,'id'=>$id]);
        set_flash('success', 'Запись обновлена.'); header('Location: admin-transactions.php'); exit;
    }
    $row = array_merge($row, ['category_id'=>$categoryId ?: $row['category_id'],'type'=>$type,'status'=>$status,'amount'=>$amount,'transaction_date'=>$date,'description'=>$description]);
}
$statuses = ['planned'=>'Запланирована','completed'=>'Выполнена','cancelled'=>'Отменена'];
?>
<!doctype html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Редактирование записи</title><link rel="stylesheet" href="auth.css"></head><body><div class="dashboard"><aside class="sidebar"><div class="logo"><div class="logo-mark">₽</div>FinanceAssistant</div><nav><a href="dashboard.php">Личный кабинет</a><a class="active" href="admin-transactions.php">Записи</a><a href="admin-users.php">Пользователи</a><a href="logout.php">Выйти</a></nav></aside><main class="main"><div class="topbar"><div><h1>Редактирование записи #<?= (int)$id ?></h1><p>Администратор: <?= e($admin['full_name']) ?></p></div><a class="action-link" href="admin-transactions.php">← К списку</a></div><section class="panel">
<?php if ($errors): ?><div class="alert error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="filters"><div class="field"><label for="type">Тип</label><select id="type" name="type"><option value="income" <?= $row['type']==='income'?'selected':'' ?>>Доход</option><option value="expense" <?= $row['type']==='expense'?'selected':'' ?>>Расход</option></select></div><div class="field"><label for="category_id">Категория</label><select id="category_id" name="category_id"><?php foreach($categories as $cat): ?><option value="<?= (int)$cat['id'] ?>" <?= (int)$row['category_id']===(int)$cat['id']?'selected':'' ?>><?= e($cat['name']) ?> · <?= $cat['type']==='income'?'доход':'расход' ?></option><?php endforeach; ?></select></div><div class="field"><label for="status">Статус</label><select id="status" name="status"><?php foreach($statuses as $key=>$label): ?><option value="<?= e($key) ?>" <?= $row['status']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="field"><label for="amount">Сумма, ₽</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" value="<?= e((string)$row['amount']) ?>" required></div><div class="field"><label for="transaction_date">Дата</label><input id="transaction_date" name="transaction_date" type="date" value="<?= e($row['transaction_date']) ?>" required></div><div class="field"><label for="description">Описание</label><input id="description" name="description" maxlength="255" value="<?= e((string)$row['description']) ?>"></div><button class="btn" type="submit">Сохранить изменения</button></div></form></section></main></div></body></html>
