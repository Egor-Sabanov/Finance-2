<?php
declare(strict_types=1);

require __DIR__ . '/../backend/src/bootstrap.php';
$user = require_auth();

$currencies = [
    'USD' => 'Доллар США',
    'EUR' => 'Евро',
    'CNY' => 'Китайский юань',
    'BYN' => 'Белорусский рубль',
    'KZT' => 'Казахстанский тенге',
    'GBP' => 'Фунт стерлингов',
];
$rates = [];
$apiError = null;
$conversion = null;
$amountValue = '';
$selectedCurrency = 'USD';
$flash = pull_flash();

function fetch_cbr_rates(): void
{
    $url = 'https://www.cbr.ru/scripts/XML_daily.asp';
    if (function_exists('curl_init')) {
        $request = curl_init($url);
        curl_setopt_array($request, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'FinanceAssistant/1.0 (student finance project)',
        ]);
        $body = curl_exec($request);
        $status = (int)curl_getinfo($request, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($request);
        curl_close($request);
        if (!is_string($body) || $status < 200 || $status >= 300) {
            throw new RuntimeException('CBR request failed with HTTP ' . $status . ($curlError !== '' ? ': ' . $curlError : ''));
        }
    } else {
        $context = stream_context_create(['http' => [
            'timeout' => 12,
            'ignore_errors' => true,
            'header' => "User-Agent: FinanceAssistant/1.0\r\n",
        ]]);
        $body = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        if (!is_string($body) || !preg_match('/\s2\d\d\s/', $statusLine)) {
            throw new RuntimeException('CBR request failed: ' . ($statusLine !== '' ? $statusLine : 'network unavailable'));
        }
    }

    $previousErrors = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrors);
    if (!$xml || !isset($xml['Date'])) {
        throw new RuntimeException('CBR returned an invalid rates document.');
    }
    $rateDate = DateTimeImmutable::createFromFormat('d.m.Y', (string)$xml['Date']);
    if (!$rateDate) {
        throw new RuntimeException('CBR response contains an invalid date.');
    }

    $db = db();
    $upsert = $db->prepare(
        'INSERT INTO exchange_rates (currency_code, rate_rub, nominal, rate_date, fetched_at)
         VALUES (:code, :rate, :nominal, :rate_date, CURRENT_TIMESTAMP)
         ON DUPLICATE KEY UPDATE rate_rub = VALUES(rate_rub), nominal = VALUES(nominal),
         rate_date = VALUES(rate_date), fetched_at = CURRENT_TIMESTAMP'
    );
    $saved = 0;
    foreach ($xml->Valute as $item) {
        $code = strtoupper((string)$item->CharCode);
        $nominal = (float)str_replace(',', '.', (string)$item->Nominal);
        $value = (float)str_replace(',', '.', (string)$item->Value);
        if (preg_match('/^[A-Z]{3}$/', $code) !== 1 || $nominal <= 0 || $value <= 0) {
            continue;
        }
        $upsert->execute([
            'code' => $code,
            'rate' => number_format($value / $nominal, 8, '.', ''),
            'nominal' => number_format($nominal, 4, '.', ''),
            'rate_date' => $rateDate->format('Y-m-d'),
        ]);
        $saved++;
    }
    if ($saved === 0) {
        throw new RuntimeException('CBR response does not contain currency rates.');
    }
}

function read_cbr_rates(): array
{
    return db()->query('SELECT currency_code, rate_rub, nominal, rate_date, fetched_at FROM exchange_rates ORDER BY currency_code')->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $flash = ['type' => 'error', 'message' => 'Форма устарела. Обновите страницу и попробуйте снова.'];
    } elseif (($_POST['action'] ?? '') === 'refresh_rates') {
        try {
            fetch_cbr_rates();
            set_flash('success', 'Курсы Банка России обновлены.');
        } catch (Throwable $error) {
            app_log_error($error);
            set_flash('error', 'Не удалось получить новые курсы. Показываем сохранённые данные, если они есть.');
        }
        header('Location: currency.php');
        exit;
    } elseif (($_POST['action'] ?? '') === 'convert') {
        $amountValue = trim((string)($_POST['amount'] ?? ''));
        $selectedCurrency = strtoupper(trim((string)($_POST['currency'] ?? 'USD')));
        $amountInput = str_replace(',', '.', $amountValue);
        if (!isset($currencies[$selectedCurrency]) || preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $amountInput) !== 1 || (float)$amountInput <= 0) {
            $flash = ['type' => 'error', 'message' => 'Введите сумму больше нуля и выберите валюту из списка.'];
        } else {
            $conversion = ['amount' => (float)$amountInput, 'currency' => $selectedCurrency];
        }
    }
}

try {
    $cachedRows = read_cbr_rates();
    $lastFetched = $cachedRows ? strtotime((string)$cachedRows[0]['fetched_at']) : false;
    if (!$lastFetched || $lastFetched < time() - 12 * 60 * 60) {
        try {
            fetch_cbr_rates();
            $cachedRows = read_cbr_rates();
        } catch (Throwable $error) {
            app_log_error($error);
            $apiError = 'Сервис курсов временно недоступен. Отображаются последние сохранённые значения.';
        }
    }
    foreach ($cachedRows as $row) {
        $code = (string)$row['currency_code'];
        if (isset($currencies[$code])) {
            $rates[$code] = $row;
        }
    }
} catch (Throwable $error) {
    app_log_error($error);
    $apiError = 'Не удалось прочитать курсы из базы данных. Повторите попытку позже.';
}

if ($conversion !== null) {
    if (isset($rates[$conversion['currency']])) {
        $rate = (float)$rates[$conversion['currency']]['rate_rub'];
        $conversion['result'] = $conversion['amount'] / $rate;
        $conversion['rate'] = $rate;
        $conversion['rate_date'] = (string)$rates[$conversion['currency']]['rate_date'];
    } else {
        $conversion = null;
        $flash = ['type' => 'error', 'message' => 'Для выбранной валюты пока нет сохранённого курса. Проверьте подключение и обновите данные.'];
    }
}

$navAdmin = ($user['role'] ?? 'user') === 'admin';
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Курсы валют — FinanceAssistant</title>
  <link rel="stylesheet" href="vendor/bootstrap.min.css">
  <link rel="stylesheet" href="auth.css">
</head>
<body>
<div class="dashboard">
  <aside class="sidebar">
    <div class="logo"><div class="logo-mark">₽</div>FinanceAssistant</div>
    <nav>
      <a href="dashboard.php">Личный кабинет</a>
      <a href="transactions.php">Мои операции</a>
      <a href="budget.php">Бюджет</a>
      <a class="active" href="currency.php">Конвертер валют</a>
      <?php if ($navAdmin): ?><a href="admin-transactions.php">Все записи</a><a href="admin-users.php">Пользователи</a><?php endif; ?>
      <a href="logout.php">Выйти</a>
    </nav>
  </aside>
  <main class="main">
    <div class="topbar">
      <div><h1>Конвертер валют</h1><p>Справочные курсы Банка России · входящие суммы хранятся в рублях</p></div>
      <div class="profile"><div class="avatar"><?= e(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?></div><span><?= e($user['full_name']) ?></span></div>
    </div>

    <?php if ($flash): ?><div class="alert <?= e((string)$flash['type']) ?>" role="status"><?= e((string)$flash['message']) ?></div><?php endif; ?>
    <?php if ($apiError): ?><div class="alert error" role="status"><?= e($apiError) ?></div><?php endif; ?>

    <section class="panel currency-converter">
      <div>
        <span class="currency-eyebrow">РАСЧЁТ ПО ОФИЦИАЛЬНОМУ КУРСУ</span>
        <h2>Перевести рубли</h2>
        <p>Курсы обновляются автоматически не чаще одного раза в 12 часов. Конвертация справочная и не меняет записи операций. <a href="https://www.cbr.ru/scripts/XML_daily.asp" target="_blank" rel="noopener noreferrer">Источник курсов — Банк России</a>.</p>
      </div>
      <form class="currency-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="convert">
        <div class="field"><label for="amount">Сумма, ₽</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" max="999999999999.99" value="<?= e($amountValue) ?>" placeholder="Например, 10 000" required></div>
        <div class="field"><label for="currency">Валюта результата</label><select id="currency" name="currency"><?php foreach ($currencies as $code => $name): ?><option value="<?= e($code) ?>" <?= $selectedCurrency === $code ? 'selected' : '' ?>><?= e($code . ' — ' . $name) ?></option><?php endforeach; ?></select></div>
        <button class="btn" type="submit">Рассчитать</button>
      </form>
      <?php if ($conversion): ?>
        <div class="currency-result" role="status">
          <span><?= e(number_format($conversion['amount'], 2, ',', ' ')) ?> ₽</span>
          <strong><?= e(number_format($conversion['result'], 2, ',', ' ')) ?> <?= e($conversion['currency']) ?></strong>
          <small>По курсу <?= e(number_format($conversion['rate'], 4, ',', ' ')) ?> ₽ · дата курса <?= e(date('d.m.Y', strtotime($conversion['rate_date']))) ?></small>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="currency-heading"><div><h2>Сохранённые курсы</h2><p>Значения взяты из официального XML-сервиса Банка России.</p></div>
        <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="refresh_rates"><button class="quiet-button" type="submit">Обновить курсы</button></form>
      </div>
      <?php if ($rates): ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Валюта</th><th>Название</th><th>Курс за 1 единицу</th><th>Дата курса</th></tr></thead><tbody>
          <?php foreach ($currencies as $code => $name): if (!isset($rates[$code])) continue; ?>
            <tr><td><strong><?= e($code) ?></strong></td><td><?= e($name) ?></td><td><?= e(number_format((float)$rates[$code]['rate_rub'], 4, ',', ' ')) ?> ₽</td><td><?= e(date('d.m.Y', strtotime((string)$rates[$code]['rate_date']))) ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
        <p class="budget-hint">Последняя загрузка: <?= e(date('d.m.Y H:i', strtotime((string)$rates[array_key_first($rates)]['fetched_at']))) ?>. При недоступности сервиса используется сохранённый курс.</p>
      <?php else: ?>
        <div class="empty">Курсы пока не загружены. Нажмите «Обновить курсы» после запуска Apache и подключения к интернету.</div>
      <?php endif; ?>
      <p class="currency-disclaimer">Справочные значения не являются курсом обмена банка или платёжной системы.</p>
    </section>
  </main>
</div>
</body>
</html>
