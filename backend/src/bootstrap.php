<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SERVER['FINANCE_REQUEST_ID'] ??= bin2hex(random_bytes(8));

function app_log_error(Throwable|string $error): void
{
    static $writing = false;
    if ($writing) {
        return;
    }
    $writing = true;
    $message = $error instanceof Throwable
        ? sprintf('%s: %s at %s:%d', $error::class, $error->getMessage(), basename($error->getFile()), $error->getLine())
        : $error;
    $entry = [
        'time' => date(DATE_ATOM),
        'level' => 'error',
        'request_id' => (string)$_SERVER['FINANCE_REQUEST_ID'],
        'path' => basename((string)($_SERVER['SCRIPT_NAME'] ?? 'cli')),
        'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'CLI'),
        'user_id' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
        'message' => substr(preg_replace('/[\r\n\t]+/', ' ', $message) ?? 'Unknown error', 0, 1800),
    ];

    try {
        $directory = dirname(__DIR__, 2) . '/var/logs';
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            return;
        }
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($line !== false) {
            file_put_contents($directory . '/application.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    } catch (Throwable) {
        // Failure to write a diagnostic must not break the application.
    } finally {
        $writing = false;
    }
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    app_log_error(sprintf('PHP error %d: %s at %s:%d', $severity, $message, basename($file), $line));
    return true;
});

set_exception_handler(static function (Throwable $error): void {
    app_log_error($error);
    $requestId = (string)$_SERVER['FINANCE_REQUEST_ID'];
    $isJsonRequest = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/backend/public/api/');
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        http_response_code(500);
        header($isJsonRequest ? 'Content-Type: application/json; charset=UTF-8' : 'Content-Type: text/html; charset=UTF-8');
    }
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Application error. Details were written to the private log.\n");
        return;
    }
    if ($isJsonRequest) {
        echo json_encode(['error' => 'internal_server_error', 'message' => 'Не удалось выполнить запрос. Попробуйте ещё раз позже.', 'request_id' => $requestId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }
    echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>Ошибка</title><p>Не удалось выполнить запрос. Попробуйте ещё раз позже.</p></html>';
});

register_shutdown_function(static function (): void {
    $lastError = error_get_last();
    if ($lastError && in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        app_log_error(sprintf('Fatal PHP error: %s at %s:%d', $lastError['message'], basename($lastError['file']), $lastError['line']));
        if (PHP_SAPI !== 'cli') {
            $requestId = (string)$_SERVER['FINANCE_REQUEST_ID'];
            $isJsonRequest = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
                || str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/backend/public/api/');
            if (!headers_sent()) {
                http_response_code(500);
                header($isJsonRequest ? 'Content-Type: application/json; charset=UTF-8' : 'Content-Type: text/html; charset=UTF-8');
            }
            if ($isJsonRequest) {
                echo json_encode(['error' => 'internal_server_error', 'message' => 'Не удалось выполнить запрос. Попробуйте ещё раз позже.', 'request_id' => $requestId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>Ошибка</title><p>Не удалось выполнить запрос. Попробуйте ещё раз позже.</p></html>';
            }
        }
    }
});

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

    $stmt = db()->prepare('SELECT id, full_name, email, role, is_active, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int)$id]);
    $user = $stmt->fetch() ?: null;

    if ($user === null || !(bool)$user['is_active']) {
        unset($_SESSION['user_id']);
        $user = null;
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

function require_admin(): array
{
    $user = require_auth();
    if (($user['role'] ?? 'user') !== 'admin') {
        http_response_code(403);
        exit('Доступ запрещён. Эта страница доступна только администратору.');
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
