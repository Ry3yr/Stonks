<?php
/**
 * Delisting Risk Checker
 * Uses query1.finance.yahoo.com/v8/finance/chart/ (same as your working stock chart)
 * 
 * Usage: delist.php?stock=TICKER[&exchange=NASDAQ|NYSE|...]
 *        delist.php?stock=TICKER&compact   (for 120x40 widget)
 */

$isCompact = isset($_GET['compact']);

if (!$isCompact) {
    header('Content-Type: application/json');
}

$stock = isset($_GET['stock']) ? trim(strtoupper($_GET['stock'])) : null;
$exchangeOverride = isset($_GET['exchange']) ? trim(strtoupper($_GET['exchange'])) : null;

if (!$stock) {
    if ($isCompact) {
        echo '<span style="color:red;font-size:10px;">?stock=</span>';
    } else {
        echo json_encode(['error' => 'Missing ?stock= parameter']);
    }
    exit;
}

$RULES = [
    'NASDAQ' => [
        'price_floor'    => 1.00,
        'price_currency' => 'USD',
        'consecutive_days' => 30,
        'grace_days'     => 180,
        'mv_floor'       => 35000000,
        'name'           => 'NASDAQ',
        'delist_trigger' => 'price',
    ],
    'NYSE' => [
        'price_floor'    => 1.00,
        'price_currency' => 'USD',
        'consecutive_days' => 30,
        'grace_days'     => 180,
        'mv_floor'       => 50000000,
        'name'           => 'NYSE',
        'delist_trigger' => 'price',
    ],
    'TSX' => [
        'price_floor'    => 0.10,
        'price_currency' => 'CAD',
        'consecutive_days' => 30,
        'grace_days'     => 120,
        'mv_floor'       => 2000000,
        'name'           => 'TSX Venture (CVE)',
        'delist_trigger' => 'price',
    ],
    'ASX' => [
        'price_floor'    => 0.01,
        'price_currency' => 'AUD',
        'consecutive_days' => 999,
        'grace_days'     => 0,
        'mv_floor'       => 15000000,
        'name'           => 'ASX',
        'delist_trigger' => 'discretion',
    ],
    'XETRA' => [
        'price_floor'    => 0.01,
        'price_currency' => 'EUR',
        'consecutive_days' => 999,
        'grace_days'     => 0,
        'mv_floor'       => 1250000,
        'name'           => 'Xetra (ETR/FRA)',
        'delist_trigger' => 'discretion',
    ],
    'EURONEXT' => [
        'price_floor'    => 0.01,
        'price_currency' => 'EUR',
        'consecutive_days' => 999,
        'grace_days'     => 0,
        'mv_floor'       => 1000000,
        'name'           => 'Euronext (EPA)',
        'delist_trigger' => 'discretion',
    ],
    'OTC' => [
        'price_floor'    => 0.0001,
        'price_currency' => 'USD',
        'consecutive_days' => 99999,
        'grace_days'     => 0,
        'mv_floor'       => 0,
        'name'           => 'OTC Markets',
        'delist_trigger' => 'none',
    ],
];

function detectExchange($meta) {
    $exchange = strtoupper($meta['exchangeName'] ?? '');
    $map = [
        'NMS' => 'NASDAQ', 'NASDAQ' => 'NASDAQ', 'NGM' => 'NASDAQ', 'NCM' => 'NASDAQ',
        'NYQ' => 'NYSE', 'NYSE' => 'NYSE', 'PCX' => 'NYSE',
        'TOR' => 'TSX', 'V'   => 'TSX', 'CVE' => 'TSX', 'TSX' => 'TSX',
        'ASX' => 'ASX', 'YHD' => 'ASX',
        'GER' => 'XETRA', 'FRA' => 'XETRA', 'ETR' => 'XETRA', 'XETRA' => 'XETRA',
        'PAR' => 'EURONEXT', 'EPA' => 'EURONEXT', 'EURONEXT' => 'EURONEXT',
        'OTC' => 'OTC', 'OTCMKTS' => 'OTC', 'PINK' => 'OTC',
    ];
    if (isset($map[$exchange])) return $map[$exchange];
    return 'OTC';
}

// --- FETCH 6-MONTH HISTORY ---
$period2 = time();
$period1 = strtotime('-6 months');

$url = "https://query1.finance.yahoo.com/v8/finance/chart/{$stock}";
$url .= "?period1={$period1}&period2={$period2}&interval=1d";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    if ($isCompact) {
        echo '<span style="color:red;font-size:9px;">HTTP ' . $httpCode . '</span>';
    } else {
        echo json_encode(['error' => 'HTTP ' . $httpCode, 'tried_url' => $url]);
    }
    exit;
}

$data = json_decode($response, true);

if (!$data || !isset($data['chart']['result'][0])) {
    if ($isCompact) {
        echo '<span style="color:red;font-size:9px;">No data</span>';
    } else {
        echo json_encode(['error' => 'Invalid response or no data', 'tried' => $stock]);
    }
    exit;
}

$result = $data['chart']['result'][0];
$meta = $result['meta'];
$closes = $result['indicators']['quote'][0]['close'] ?? [];

$yahooTicker = $meta['symbol'] ?? $stock;
$currentPrice = $meta['regularMarketPrice'] ?? ($meta['chartPreviousClose'] ?? 0);
$currency = $meta['currency'] ?? 'USD';

if (empty($closes) || count(array_filter($closes)) === 0) {
    if ($isCompact) {
        echo '<span style="color:red;font-size:9px;">Empty data</span>';
    } else {
        echo json_encode([
            'error' => 'Yahoo returned empty price data',
            'tried' => $stock,
            'hint' => 'ASX stocks need .AX suffix (e.g., ATX.AX)'
        ]);
    }
    exit;
}

$exchangeKey = $exchangeOverride ?: detectExchange($meta);

if ($exchangeOverride && !isset($RULES[$exchangeOverride])) {
    if ($isCompact) {
        echo '<span style="color:red;font-size:9px;">Bad exchange</span>';
    } else {
        echo json_encode(['error' => 'Invalid exchange', 'provided' => $exchangeOverride]);
    }
    exit;
}

$rules = $RULES[$exchangeKey];

// --- ANALYZE DANGER ZONE ---
$floor = $rules['price_floor'];
$totalDays = 0;
$dangerDays = 0;
$maxConsecutive = 0;
$currentStreak = 0;
$streaks = [];
$last30Days = [];
$dayCount = 0;
$allPrices = [];

foreach ($closes as $price) {
    if ($price === null) continue;
    $totalDays++;
    $dayCount++;
    $allPrices[] = $price;

    if ($dayCount > count(array_filter($closes)) - 30) {
        $last30Days[] = $price;
    }

    if ($price < $floor) {
        $dangerDays++;
        $currentStreak++;
    } else {
        if ($currentStreak > 0) {
            $streaks[] = $currentStreak;
        }
        $currentStreak = 0;
    }
    $maxConsecutive = max($maxConsecutive, $currentStreak);
}
if ($currentStreak > 0) $streaks[] = $currentStreak;

$dangerPercentile = $totalDays > 0 ? round(($dangerDays / $totalDays) * 100, 1) : 0;

// Detect suspicious recovery
$recentlyBelowFloor = false;
$daysSinceBelowFloor = 999;
if (!empty($last30Days)) {
    for ($i = count($last30Days) - 1; $i >= 0; $i--) {
        if ($last30Days[$i] < $floor) {
            $recentlyBelowFloor = true;
            $daysSinceBelowFloor = count($last30Days) - 1 - $i;
            break;
        }
    }
}

// Detect reverse split
$hasReverseSplit = false;
if ($totalDays >= 10) {
    $recentPrices = array_slice(array_filter($closes), -10);
    if (count($recentPrices) >= 5) {
        $avgFirst5 = array_sum(array_slice($recentPrices, 0, 5)) / 5;
        $avgLast5 = array_sum(array_slice($recentPrices, -5)) / 5;
        if ($avgLast5 > $avgFirst5 * 3 && $avgFirst5 > 0.5) {
            $hasReverseSplit = true;
        }
    }
}

// ASX-specific warnings
$asxLowPriceWarning = false;
if ($exchangeKey === 'ASX' && $currentPrice > 0 && $currentPrice < 0.20) {
    $asxLowPriceWarning = true;
}

$asxStagnantWarning = false;
if ($exchangeKey === 'ASX' && count($allPrices) >= 20) {
    $recent20 = array_slice($allPrices, -20);
    $range = max($recent20) - min($recent20);
    $avg = array_sum($recent20) / count($recent20);
    if ($avg > 0 && ($range / $avg) < 0.05) {
        $asxStagnantWarning = true;
    }
}

// --- CROSS-EXCHANGE WARNING ---
$crossExchangeWarnings = [];

$usStocks = array_filter($allPrices, function($p) { return $p > 0; });
if (!empty($usStocks)) {
    $daysBelowUSD1 = 0;
    foreach ($usStocks as $p) {
        if ($p < 1.00) $daysBelowUSD1++;
    }
    $pctBelowUSD1 = count($usStocks) > 0 ? round(($daysBelowUSD1 / count($usStocks)) * 100, 1) : 0;
    if ($pctBelowUSD1 > 50) {
        $crossExchangeWarnings[] = 'Spent ' . $pctBelowUSD1 . '% of last ' . count($usStocks) . ' days below $1.00 (NASDAQ/NYSE threshold).';
    }
}

$daysBelowCAD10 = 0;
foreach ($allPrices as $p) {
    if ($p < 0.10) $daysBelowCAD10++;
}
$pctBelowCAD10 = count($allPrices) > 0 ? round(($daysBelowCAD10 / count($allPrices)) * 100, 1) : 0;
if ($pctBelowCAD10 > 50) {
    $crossExchangeWarnings[] = 'Spent ' . $pctBelowCAD10 . '% below C$0.10 (TSX Venture threshold).';
}

$daysBelowAUD20 = 0;
foreach ($allPrices as $p) {
    if ($p < 0.20) $daysBelowAUD20++;
}
$pctBelowAUD20 = count($allPrices) > 0 ? round(($daysBelowAUD20 / count($allPrices)) * 100, 1) : 0;
if ($pctBelowAUD20 > 50) {
    $crossExchangeWarnings[] = 'Spent ' . $pctBelowAUD20 . '% below A$0.20 (ASX admission minimum).';
}

$daysBelow10c = 0;
foreach ($allPrices as $p) {
    if ($p < 0.10) $daysBelow10c++;
}
$pctBelow10c = count($allPrices) > 0 ? round(($daysBelow10c / count($allPrices)) * 100, 1) : 0;
if ($pctBelow10c > 30 && $pctBelow10c <= 50) {
    $crossExchangeWarnings[] = 'Spent ' . $pctBelow10c . '% below 10 cents. Penny stock territory.';
}

// --- DETERMINE RISK ---
$atRisk = false;
$caution = false;
$riskReasons = [];
$cautionReasons = [];

if ($rules['delist_trigger'] === 'price') {
    if ($currentPrice > 0 && $currentPrice < $floor) {
        $atRisk = true;
        $riskReasons[] = 'Price below floor: $' . round($currentPrice, 2) . ' < $' . $floor;
    }
    if ($maxConsecutive >= $rules['consecutive_days'] && $rules['consecutive_days'] < 900) {
        $atRisk = true;
        $riskReasons[] = 'Streak: ' . $maxConsecutive . 'd (limit ' . $rules['consecutive_days'] . 'd)';
    }
}

if ($recentlyBelowFloor && $currentPrice >= $floor && $rules['delist_trigger'] === 'price') {
    $caution = true;
    $cautionReasons[] = 'Below floor ' . $daysSinceBelowFloor . 'd ago. Possible reverse split.';
}
if ($hasReverseSplit) {
    $caution = true;
    $cautionReasons[] = 'Possible reverse split detected.';
}
if ($dangerPercentile > 15 && $dangerPercentile <= 30 && !$atRisk && $rules['delist_trigger'] === 'price') {
    $caution = true;
    $cautionReasons[] = 'Elevated danger: ' . $dangerPercentile . '% below floor.';
}

if ($exchangeKey === 'ASX') {
    if ($asxLowPriceWarning) {
        $caution = true;
        $cautionReasons[] = 'ASX: Below A$0.20 admission minimum.';
    }
    if ($asxStagnantWarning) {
        $caution = true;
        $cautionReasons[] = 'ASX: Very low volatility (<5% over 20d).';
    }
    if ($dangerPercentile > 20) {
        $caution = true;
        $cautionReasons[] = 'ASX: ' . $dangerPercentile . '% in low range.';
    }
}

if ($rules['delist_trigger'] === 'discretion' && $dangerPercentile > 30) {
    $caution = true;
    $cautionReasons[] = 'High danger: ' . $dangerPercentile . '%. Exchange discretion.';
}

if (!empty($crossExchangeWarnings)) {
    $caution = true;
    foreach ($crossExchangeWarnings as $w) {
        $cautionReasons[] = $w;
    }
    foreach ($crossExchangeWarnings as $w) {
        if (strpos($w, '70%') !== false || strpos($w, '80%') !== false || strpos($w, '90%') !== false) {
            $atRisk = true;
            $riskReasons[] = $w;
        }
    }
}

if ($atRisk) {
    $riskStatus = 'YES: Delist risk!!';
    $riskColor = 'red';
} elseif ($caution) {
    $riskStatus = 'CAUTION: Delisting Possible';
    $riskColor = 'yellow';
} else {
    $riskStatus = 'NO Delist Risk';
    $riskColor = 'green';
}

// ========== COMPACT WIDGET MODE ==========
if ($isCompact) {
    $fullUrl = $_SERVER['SCRIPT_NAME'] . '?stock=' . urlencode($stock);
    if ($exchangeOverride) {
        $fullUrl .= '&exchange=' . urlencode($exchangeOverride);
    }

    $bgColor = $riskColor === 'red' ? '#2a0a0a' : ($riskColor === 'yellow' ? '#2a2a0a' : '#0a2a0a');
    $borderColor = $riskColor === 'red' ? '#ff4444' : ($riskColor === 'yellow' ? '#ffaa00' : '#44ff44');
    $textColor = $riskColor === 'red' ? '#ff6b6b' : ($riskColor === 'yellow' ? '#ffd700' : '#51cf66');
    $badgeText = $riskStatus;

    $dangerPct = $dangerPercentile;
    $maxStreak = $maxConsecutive;

    ?>
<!DOCTYPE html>
<html>
<head>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { background: transparent; }
.widget {
    width: 120px;
    height: 40px;
    background: <?php echo $bgColor; ?>;
    border: 1px solid <?php echo $borderColor; ?>;
    border-radius: 4px;
    padding: 2px 4px;
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 9px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}
.widget:hover {
    opacity: 0.9;
    transform: scale(1.02);
    transition: all 0.15s ease;
}
.top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1px;
}
.symbol {
    color: #fff;
    font-weight: bold;
    font-size: 10px;
}
.badge {
    color: <?php echo $textColor; ?>;
    font-weight: bold;
    font-size: 9px;
    padding: 0 3px;
    border-radius: 2px;
    background: rgba(0,0,0,0.3);
}
.bottom-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.price {
    color: #ccc;
    font-size: 8px;
}
.stats {
    color: #888;
    font-size: 7px;
}
.indicator {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: <?php echo $borderColor; ?>;
}
</style>
</head>
<body>
<a href="<?php echo $fullUrl; ?>" target="_blank" style="text-decoration:none;">
<div class="widget" title="Click for full details&#10;Exchange: <?php echo $rules['name']; ?>&#10;Floor: $<?php echo $floor; ?>&#10;Danger: <?php echo $dangerPct; ?>%&#10;Max streak: <?php echo $maxStreak; ?>d">
    <div class="indicator"></div>
    <div class="top-row">
        <span class="symbol"><?php echo $yahooTicker; ?></span>
        <span class="badge"><?php echo $badgeText; ?></span>
    </div>
    <div class="bottom-row">
        <span class="price">$<?php echo number_format($currentPrice, 2); ?></span>
        <span class="stats"><?php echo $dangerPct; ?>% | <?php echo $maxStreak; ?>d</span>
    </div>
</div>
</a>
</body>
</html>
    <?php
    exit;
}

// ========== FULL JSON MODE ==========
$response = [
    'ticker'           => $yahooTicker,
    'exchange'         => $rules['name'],
    'exchange_raw'     => $meta['exchangeName'] ?? 'N/A',
    'current_price'    => round($currentPrice, 4),
    'currency'         => $currency,

    'delisting_risk'   => $riskStatus,
    'risk_color'       => $riskColor,
    'risk_reasons'     => $riskReasons,
    'caution_reasons'  => $cautionReasons,

    'danger_zone' => [
        'price_floor'        => $floor,
        'floor_currency'     => $rules['price_currency'],
        'days_below_floor'   => $dangerDays,
        'total_trading_days' => $totalDays,
        'danger_percentile'  => $dangerPercentile,
        'max_consecutive_below' => $maxConsecutive,
        'consecutive_streaks'   => $streaks,
        'recently_below_floor' => $recentlyBelowFloor,
        'days_since_below_floor' => $daysSinceBelowFloor < 900 ? $daysSinceBelowFloor : null,
        'possible_reverse_split' => $hasReverseSplit,
    ],

    'exchange_rules' => [
        'price_floor'      => $floor,
        'consecutive_days' => $rules['consecutive_days'] >= 900 ? 'N/A (discretion)' : $rules['consecutive_days'],
        'grace_period'     => $rules['grace_days'] > 0 ? $rules['grace_days'] . ' days' : 'N/A',
        'market_cap_floor' => $rules['mv_floor'] > 0 ? '$' . number_format($rules['mv_floor']) : 'N/A',
        'delist_trigger'   => $rules['delist_trigger'],
    ],

    'cross_exchange_warnings' => $crossExchangeWarnings,
    'disclaimer' => 'This tool analyzes price data only. Cross-exchange warnings flag stocks that would be at risk on OTHER exchanges.',
    'last_updated' => date('Y-m-d H:i:s'),
];

echo json_encode($response, JSON_PRETTY_PRINT);
