<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['csrf_token' => csrf_token()], JSON_UNESCAPED_UNICODE);
