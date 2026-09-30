<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

if (!verify_csrf($input['csrf_token'] ?? null)) {
    http_response_code(419);
    echo json_encode(['error' => 'Неверный CSRF-токен'], JSON_UNESCAPED_UNICODE);
    exit;
}

$_SESSION = [];
session_destroy();
echo json_encode(['message' => 'Выход выполнен'], JSON_UNESCAPED_UNICODE);
