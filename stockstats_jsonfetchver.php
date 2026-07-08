<?php
/**
 * stock_performance.php
 *
 * Reads stocks.json, fetches current prices, converts to USD via Yahoo FX,
 * calculates gains/losses in USD, outputs compact JSON and appends to stockstats.json.
 * FORMAT: {"timestamp":"...","stocks":{"SYMBOL":usd_gain,...},"summary":{...}}
 */

// Configuration
$stocksFile = __DIR__ . '/stocks.json';
$statsFile  = __DIR__ . '/stockstats.json';
$yahooTimeout = 15;

// --- FX CONVERSION (same logic as the portfolio HTML script) ---
// Fetches e.g. "HKDUSD=X" or "EURUSD=X" from Yahoo and caches per-currency
$GLOBALS['fxCache'] = [];

function getFxRate($currency) {
    $currency = strtoupper($currency);
    if ($currency === 'USD') {
        return 1.0;
    }
    if (isset($GLOBALS['fxCache'][$currency])) {
        return $GLOBALS['fxCache'][$currency];
    }

    $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($currency) . "USD=X";
    
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
    curl_close($ch);
    
    if (!$response) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['chart']['result'][0]['meta']['regularMarketPrice'])) {
        $GLOBALS['fxCache'][$currency] = (float)$data['chart']['result'][0]['meta']['regularMarketPrice'];
        return $GLOBALS['fxCache'][$currency];
    }
    
    return null;
}

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
 * Fetch current price AND currency from Yahoo Finance
 */
function getCurrentPriceWithCurrency($symbol) {
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
    if (!isset($data['chart']['result'][0])) return null;

    $meta = $data['chart']['result'][0]['meta'];
    $price = null;

    if (isset($meta['regularMarketPrice'])) {
        $price = (float)$meta['regularMarketPrice'];
    } elseif (isset($data['chart']['result'][0]['indicators']['quote'][0]['close'][0])) {
        $price = (float)$data['chart']['result'][0]['indicators']['quote'][0]['close'][0];
    }

    if ($price === null) return null;

    return [
        'price' => $price,
        'currency' => $meta['currency'] ?? 'USD'
    ];
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

// Process stocks — ALL values converted to USD
$stocksGains = [];
$totalGain = 0;
$totalLoss = 0;

foreach ($qualifiedStocks as $stock) {
    $symbol = $stock['stock'];
    $buyPrice = (float)$stock['price'];
    $shares = (int)$stock['nrbght'];

    if ($shares <= 0 || $buyPrice <= 0) continue;

    $quote = getCurrentPriceWithCurrency($symbol);
    if ($quote === null) continue;

    $rawPrice = $quote['price'];
    $listingCurrency = strtoupper($quote['currency'] ?? 'USD');

    // --- Convert fetched price to USD if not already USD ---
    $currentPrice = $rawPrice;
    
    if ($listingCurrency !== 'USD') {
        $fxRate = getFxRate($listingCurrency);
        if ($fxRate === null) continue; // Skip if FX fails
        $currentPrice = round($rawPrice * $fxRate, 4);
    }

    // Calculate gain/loss in USD
    $gainPerShare = $currentPrice - $buyPrice;
    $totalGainLoss = $gainPerShare * $shares;
    $roundedValue = round($totalGainLoss, 2);

    // FORMAT PRESERVED: flat number in USD
    $stocksGains[$symbol] = $roundedValue;

    if ($roundedValue >= 0) $totalGain += $roundedValue;
    else $totalLoss += $roundedValue;
}

$netResult = $totalGain + $totalLoss;

// Build entry in EXACT same format as before
$entry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'stocks' => $stocksGains,
    'summary' => [
        'total_gain' => round($totalGain, 2),
        'total_loss' => round($totalLoss, 2),
        'net_result' => round($netResult, 2)
    ]
];

// Output compact JSON to browser (EXACT same format)
header('Content-Type: application/json; charset=utf-8');
echo json_encode($stocksGains + ['summary' => $entry['summary']], JSON_UNESCAPED_UNICODE);

// ALWAYS append to stockstats.json (SAME format)
$allData = [];
if (file_exists($statsFile)) {
    $decoded = json_decode(file_get_contents($statsFile), true);
    if (is_array($decoded)) $allData = $decoded;
}

$allData[] = $entry;

file_put_contents($statsFile, json_encode($allData, JSON_UNESCAPED_UNICODE));
?>