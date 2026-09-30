<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'login page' => $root . '/frontend/login.php',
    'registration page' => $root . '/frontend/register.php',
    'dashboard' => $root . '/frontend/dashboard.php',
    'bootstrap' => $root . '/backend/src/bootstrap.php',
    'register API' => $root . '/backend/public/api/register.php',
    'login API' => $root . '/backend/public/api/login.php',
    'schema' => $root . '/database/schema.sql',
];

$failed = [];
foreach ($checks as $label => $file) {
    if (!is_file($file)) {
        $failed[] = $label;
    }
}

$bootstrap = file_get_contents($checks['bootstrap']);
$register = file_get_contents($checks['registration page']);
$login = file_get_contents($checks['login page']);

$requirements = [
    'PDO prepared statements' => str_contains($bootstrap, 'ATTR_EMULATE_PREPARES') && str_contains($register, 'db()->prepare'),
    'password_hash' => str_contains($register, 'password_hash('),
    'password_verify' => str_contains($login, 'password_verify('),
    'server validation' => str_contains($bootstrap, 'FILTER_VALIDATE_EMAIL'),
    'CSRF' => str_contains($bootstrap, 'verify_csrf'),
];
foreach ($requirements as $label => $ok) {
    if (!$ok) $failed[] = $label;
}

if ($failed) {
    fwrite(STDERR, "FAILED:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo "PASS: authentication static checks\n";
