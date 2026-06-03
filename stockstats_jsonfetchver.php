<?php
/**
 * stock_performance.php
 *
 * Reads stocks.json, fetches current prices, calculates gains/losses,
 * outputs compact JSON and always appends to stockstats.json.
 */

// Configuration
$stocksFile = __DIR__ . '/stocks.json';
$statsFile  = __DIR__ . '/stockstats.json';
$yahooTimeout = 15;

// Check if stocks.json exists
if (!file_exists($stocksFile)) {
    die(json_encode(['error' => "File not found: $stocksFile"]));
}

// Read and decode stocks.json
$stocksData = json_decode(file_get_contents($stocksFile), true);
if (!$stocksData || !is_array($stocksData)) {
    die(json_encode(['error' => 'Invalid JSON in stocks.json']));
}

/**
 * Fetch current price from Yahoo Finance
 */
function getCurrentPrice($symbol) {
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return null;

    $data = json_decode($response, true);

    if (isset($data['chart']['result'][0]['meta']['regularMarketPrice'])) {
        return (float)$data['chart']['result'][0]['meta']['regularMarketPrice'];
    }

    if (isset($data['chart']['result'][0]['indicators']['quote'][0]['close'][0])) {
        return (float)$data['chart']['result'][0]['indicators']['quote'][0]['close'][0];
    }

    return null;
}

// Filter ISIN-qualified stocks with >0 shares
$qualifiedStocks = array_filter($stocksData, function($stock) {
    return isset($stock['isin'], $stock['nrbght'], $stock['stock'])
        && !empty($stock['isin'])
        && $stock['nrbght'] > 0
        && !empty($stock['stock']);
});

if (empty($qualifiedStocks)) {
    echo json_encode(['error' => 'No ISIN-qualified stocks found']);
    exit;
}

// Process stocks
$stocksGains = [];
$totalGain = 0;
$totalLoss = 0;

foreach ($qualifiedStocks as $stock) {
    $symbol = $stock['stock'];
    $buyPrice = (float)$stock['price'];
    $shares = (int)$stock['nrbght'];

    if ($shares <= 0 || $buyPrice <= 0) continue;

    $currentPrice = getCurrentPrice($symbol);
    if ($currentPrice === null) continue;

    $gainPerShare = $currentPrice - $buyPrice;
    $totalGainLoss = $gainPerShare * $shares;

    $roundedValue = round($totalGainLoss, 2);
    $stocksGains[$symbol] = $roundedValue;

    if ($roundedValue >= 0) $totalGain += $roundedValue;
    else $totalLoss += $roundedValue;
}

$netResult = $totalGain + $totalLoss;

// Build entry in same format as stockstats.php (with timestamp)
$entry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'stocks' => $stocksGains,
    'summary' => [
        'total_gain' => round($totalGain, 2),
        'total_loss' => round($totalLoss, 2),
        'net_result' => round($netResult, 2)
    ]
];

// Output compact JSON to browser (keeping backward compatibility with previous output format)
header('Content-Type: application/json; charset=utf-8');
echo json_encode($stocksGains + ['summary' => $entry['summary']], JSON_UNESCAPED_UNICODE);

// ALWAYS append to stockstats.json (with timestamp, matching stockstats.php format)
$allData = [];
if (file_exists($statsFile)) {
    $decoded = json_decode(file_get_contents($statsFile), true);
    if (is_array($decoded)) $allData = $decoded;
}

$allData[] = $entry;

file_put_contents($statsFile, json_encode($allData, JSON_UNESCAPED_UNICODE));
?>