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

[$errors, $fullName, $email, $password] = validate_registration($input);
if ($errors) {
    http_response_code(422);
    echo json_encode(['errors' => $errors], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Пользователь с таким email уже зарегистрирован.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = db()->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (:full_name, :email, :password_hash)');
$stmt->execute([
    'full_name' => $fullName,
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
]);

http_response_code(201);
echo json_encode(['message' => 'Пользователь создан', 'user_id' => (int)db()->lastInsertId()], JSON_UNESCAPED_UNICODE);
