<?php
declare(strict_types=1);

$config = require __DIR__ . '/../backend/config.php';
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database'], $config['charset']);
$pdo = new PDO($dsn, $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$server = $pdo->query('SELECT VERSION() AS version, DATABASE() AS db_name')->fetch(PDO::FETCH_ASSOC);
echo 'База: ' . $server['db_name'] . ' · сервер: ' . $server['version'] . PHP_EOL;

$queries = [
    'Сводка расходов за период' => "SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = 2 AND status = 'completed' AND type = 'expense' AND transaction_date >= '2026-09-01' AND transaction_date < '2026-10-01'",
    'Расходы пользователя по категории' => "SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = 2 AND category_id = 9 AND status = 'completed' AND type = 'expense' AND transaction_date >= '2026-09-01' AND transaction_date < '2026-10-01'",
    'Постраничный список операций' => 'SELECT id FROM transactions WHERE user_id = 2 ORDER BY transaction_date DESC, id DESC LIMIT 20 OFFSET 0',
];

foreach ($queries as $label => $sql) {
    $plan = $pdo->query('EXPLAIN ' . $sql)->fetch(PDO::FETCH_ASSOC);
    printf("%s: access=%s, index=%s\n", $label, $plan['type'] ?? '?', $plan['key'] ?? 'полное сканирование');
}

echo PHP_EOL . 'Индексы финансовых выборок:' . PHP_EOL;
$indexes = $pdo->query("SHOW INDEX FROM transactions WHERE Key_name LIKE 'idx_transactions_user%'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($indexes as $index) {
    printf("%s: %s\n", $index['Key_name'], $index['Column_name']);
}
