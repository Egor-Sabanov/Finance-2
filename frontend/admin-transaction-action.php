<?php
declare(strict_types=1);
require __DIR__ . '/../backend/src/bootstrap.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) { http_response_code(400); exit('Некорректный запрос.'); }
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Некорректный ID.'); }
$return = (string)($_POST['return'] ?? '');
$allowedReturnKeys = ['q','type','status','from','to','sort','direction','page'];
parse_str($return, $returnParams); $returnParams = array_intersect_key($returnParams, array_flip($allowedReturnKeys));
$redirect = 'admin-transactions.php' . ($returnParams ? '?' . http_build_query($returnParams) : '');
if (($_POST['action'] ?? '') === 'delete') {
    $stmt = db()->prepare('DELETE FROM transactions WHERE id = :id'); $stmt->execute(['id' => $id]);
    set_flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Запись удалена.' : 'Запись не найдена.');
} else {
    $status = (string)($_POST['status'] ?? '');
    if (!in_array($status, ['planned','completed','cancelled'], true)) { http_response_code(400); exit('Недопустимый статус.'); }
    $stmt = db()->prepare('UPDATE transactions SET status = :status WHERE id = :id'); $stmt->execute(['status' => $status, 'id' => $id]);
    set_flash('success', 'Статус записи обновлён.');
}
header('Location: ' . $redirect); exit;
