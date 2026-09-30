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

[$errors, $email, $password] = validate_login($input);
if ($errors) {
    http_response_code(422);
    echo json_encode(['errors' => $errors], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = db()->prepare('SELECT id, full_name, email, password_hash FROM users WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Неверный email или пароль.'], JSON_UNESCAPED_UNICODE);
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

echo json_encode(['message' => 'Авторизация выполнена', 'user' => [
    'id' => (int)$user['id'],
    'full_name' => $user['full_name'],
    'email' => $user['email'],
]], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
