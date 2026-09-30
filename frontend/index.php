<?php
declare(strict_types=1);
require __DIR__ . '/../backend/src/bootstrap.php';
header('Location: ' . (current_user() ? 'dashboard.php' : 'login.php'));
exit;
