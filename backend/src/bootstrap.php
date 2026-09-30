<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function db(): PDO
{
    global $config;
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['database'],
        $config['charset']
    );

    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }

    $loaded = true;
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) && !ctype_digit((string)$id)) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, full_name, email, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int)$id]);
    $user = $stmt->fetch() ?: null;

    if ($user === null) {
        unset($_SESSION['user_id']);
    }

    return $user;
}

function require_guest(): void
{
    if (current_user() !== null) {
        header('Location: dashboard.php');
        exit;
    }
}

function require_auth(): array
{
    $user = current_user();
    if ($user === null) {
        set_flash('error', 'Сначала войдите в систему.');
        header('Location: login.php');
        exit;
    }
    return $user;
}

function validate_registration(array $input): array
{
    $errors = [];
    $fullName = trim((string)($input['full_name'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $passwordConfirm = (string)($input['password_confirm'] ?? '');

    if ($fullName === '' || mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) {
        $errors[] = 'Имя должно содержать от 2 до 120 символов.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
        $errors[] = 'Введите корректный адрес электронной почты.';
    }

    if (strlen($password) < 8 || strlen($password) > 72) {
        $errors[] = 'Пароль должен содержать от 8 до 72 символов.';
    }

    if (!preg_match('/[A-Za-zА-Яа-я]/u', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Пароль должен содержать хотя бы одну букву и одну цифру.';
    }

    if ($password !== $passwordConfirm) {
        $errors[] = 'Пароли не совпадают.';
    }

    return [$errors, $fullName, mb_strtolower($email), $password];
}

function validate_login(array $input): array
{
    $errors = [];
    $email = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный адрес электронной почты.';
    }
    if ($password === '') {
        $errors[] = 'Введите пароль.';
    }

    return [$errors, mb_strtolower($email), $password];
}
