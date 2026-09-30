<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if ($user === null) {
    http_response_code(401);
    echo json_encode(['authenticated' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['authenticated' => true, 'user' => $user], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
