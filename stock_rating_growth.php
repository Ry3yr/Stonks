<?php
// stock_rating.php?symbol=AMD
// stock_rating.php?symbol=COST&compact
// stock_rating.php?screen

error_reporting(E_ALL);
ini_set('display_errors', 1);

$symbol = strtoupper($_GET['symbol'] ?? '');
$isCompact = isset($_GET['compact']);
$screenMode = isset($_GET['screen']);
$useClipboard = isset($_POST['clipboard_data']) && !empty($_POST['clipboard_data']);
$forceRefresh = isset($_GET['refresh']);

// ============================================================
// ALPHA VANTAGE API CONFIGURATION
// ============================================================
$ALPHA_VANTAGE_API_KEY = 'apikey';
$CACHE_FILE = __DIR__ . '/stockpredictdata.json';

if ($screenMode) {
    showScreener();
    exit;
}

if (empty($symbol)) {
    die('❌ Provide a symbol: stock_rating.php?symbol=AMD');
}

// Build Alpha Vantage URL for links
$alphaVantageUrl = "https://www.alphavantage.co/query?function=OVERVIEW&symbol={$symbol}&apikey={$ALPHA_VANTAGE_API_KEY}";

// ============================================================
// CACHE FUNCTIONS
// ============================================================
function loadCache() {
    global $CACHE_FILE;
    if (file_exists($CACHE_FILE)) {
        $content = file_get_contents($CACHE_FILE);
        $data = json_decode($content, true);
        if (is_array($data)) {
            return $data;
        }
    }
    return [];
}

function saveCache($cache) {
    global $CACHE_FILE;
    $json = json_encode($cache, JSON_PRETTY_PRINT);
    $result = file_put_contents($CACHE_FILE, $json, LOCK_EX);
    if ($result === false) {
        error_log("Failed to write cache to: " . $CACHE_FILE);
        return false;
    }
    chmod($CACHE_FILE, 0666);
    return true;
}

function getCachedData($symbol) {
    $cache = loadCache();
    if (isset($cache[$symbol])) {
        if (time() - $cache[$symbol]['timestamp'] < 86400) {
            return $cache[$symbol]['data'];
        }
    }
    return null;
}

function saveToCache($symbol, $data) {
    $cache = loadCache();
    $cache[$symbol] = [
        'timestamp' => time(),
        'data' => $data
    ];
    return saveCache($cache);
}

function getCacheStatus() {
    global $CACHE_FILE;
    $status = [
        'file_path' => $CACHE_FILE,
        'exists' => file_exists($CACHE_FILE),
        'readable' => is_readable($CACHE_FILE),
        'directory_writable' => is_writable(dirname($CACHE_FILE)),
        'file_writable' => file_exists($CACHE_FILE) ? is_writable($CACHE_FILE) : null
    ];
    return $status;
}

// ============================================================
// PARSE CLIPBOARD DATA IF PROVIDED
// ============================================================
function parseClipboardData($jsonData, &$rawResponse = null) {
    $result = [
        'market_cap' => null,
        'trailing_pe' => null,
        'forward_pe' => null,
        'peg_ratio' => null,
        'price_to_sales' => null,
        'price_to_book' => null,
        'ev_to_revenue' => null,
        'ev_to_ebitda' => null,
        'profit_margin' => null,
        'quarterly_revenue_growth' => null,
        'available' => false
    ];
    
    $data = json_decode($jsonData, true);
    $rawResponse = $jsonData;
    
    if (!$data || !isset($data['Symbol'])) {
        return $result;
    }
    
    $result['market_cap'] = isset($data['MarketCapitalization']) ? floatval($data['MarketCapitalization']) : null;
    $result['trailing_pe'] = isset($data['TrailingPE']) ? floatval($data['TrailingPE']) : null;
    $result['forward_pe'] = isset($data['ForwardPE']) ? floatval($data['ForwardPE']) : null;
    $result['peg_ratio'] = isset($data['PEGRatio']) ? floatval($data['PEGRatio']) : null;
    $result['price_to_sales'] = isset($data['PriceToSalesRatioTTM']) ? floatval($data['PriceToSalesRatioTTM']) : null;
    $result['price_to_book'] = isset($data['PriceToBookRatio']) ? floatval($data['PriceToBookRatio']) : null;
    $result['ev_to_revenue'] = isset($data['EVToRevenue']) ? floatval($data['EVToRevenue']) : null;
    $result['ev_to_ebitda'] = isset($data['EVToEBITDA']) ? floatval($data['EVToEBITDA']) : null;
    $result['profit_margin'] = isset($data['ProfitMargin']) ? floatval($data['ProfitMargin']) * 100 : null;
    $result['quarterly_revenue_growth'] = isset($data['QuarterlyRevenueGrowthYOY']) ? floatval($data['QuarterlyRevenueGrowthYOY']) * 100 : null;
    $result['available'] = true;
    
    return $result;
}

// ============================================================
// FETCH FUNDAMENTAL DATA FROM ALPHA VANTAGE (WITH CACHE)
// ============================================================
function getAlphaVantageFundamentals($symbol, $apiKey, $forceRefresh = false) {
    $result = [
        'market_cap' => null,
        'trailing_pe' => null,
        'forward_pe' => null,
        'peg_ratio' => null,
        'price_to_sales' => null,
        'price_to_book' => null,
        'ev_to_revenue' => null,
        'ev_to_ebitda' => null,
        'profit_margin' => null,
        'quarterly_revenue_growth' => null,
        'available' => false,
        'rate_limited' => false,
        'raw_response' => null
    ];
    
    // Check cache first (unless force refresh)
    if (!$forceRefresh) {
        $cached = getCachedData($symbol);
        if ($cached) {
            $cached['from_cache'] = true;
            return $cached;
        }
    }
    
    $url = "https://www.alphavantage.co/query?function=OVERVIEW&symbol={$symbol}&apikey={$apiKey}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Accept-Language: en-US,en;q=0.9',
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result['raw_response'] = $response;
    
    if (!$response || $httpCode !== 200) {
        return $result;
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['Note']) && strpos($data['Note'], 'API rate limit') !== false) {
        $result['rate_limited'] = true;
        $result['raw_response'] = $response;
        return $result;
    }
    
    if (empty($data) || isset($data['Error Message']) || !isset($data['Symbol'])) {
        return $result;
    }
    
    $result['market_cap'] = isset($data['MarketCapitalization']) ? floatval($data['MarketCapitalization']) : null;
    $result['trailing_pe'] = isset($data['TrailingPE']) ? floatval($data['TrailingPE']) : null;
    $result['forward_pe'] = isset($data['ForwardPE']) ? floatval($data['ForwardPE']) : null;
    $result['peg_ratio'] = isset($data['PEGRatio']) ? floatval($data['PEGRatio']) : null;
    $result['price_to_sales'] = isset($data['PriceToSalesRatioTTM']) ? floatval($data['PriceToSalesRatioTTM']) : null;
    $result['price_to_book'] = isset($data['PriceToBookRatio']) ? floatval($data['PriceToBookRatio']) : null;
    $result['ev_to_revenue'] = isset($data['EVToRevenue']) ? floatval($data['EVToRevenue']) : null;
    $result['ev_to_ebitda'] = isset($data['EVToEBITDA']) ? floatval($data['EVToEBITDA']) : null;
    $result['profit_margin'] = isset($data['ProfitMargin']) ? floatval($data['ProfitMargin']) * 100 : null;
    $result['quarterly_revenue_growth'] = isset($data['QuarterlyRevenueGrowthYOY']) ? floatval($data['QuarterlyRevenueGrowthYOY']) * 100 : null;
    $result['available'] = true;
    $result['from_cache'] = false;
    
    // Save to cache
    saveToCache($symbol, $result);
    
    return $result;
}

// ============================================================
// FETCH CHART DATA FROM YAHOO FINANCE
// ============================================================
$url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}?interval=1d&range=6mo";
$response = @file_get_contents($url);

if ($response === false) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
}

if (!$response) {
    die("❌ Could not fetch chart data for {$symbol}");
}

$data = json_decode($response, true);
$result = $data['chart']['result'][0] ?? null;

if (!$result) {
    die("❌ Invalid response for {$symbol}");
}

// Parse chart data
$meta = $result['meta'];
$currentPrice = $meta['regularMarketPrice'] ?? 0;
$currency = $meta['currency'] ?? 'USD';
$fiftyTwoWeekHigh = $meta['fiftyTwoWeekHigh'] ?? 0;
$fiftyTwoWeekLow = $meta['fiftyTwoWeekLow'] ?? 0;
$longName = $meta['longName'] ?? $symbol;

$timestamp = $result['timestamp'] ?? [];
$quote = $result['indicators']['quote'][0] ?? [];
$closes = $quote['close'] ?? [];

// Filter valid prices
$prices = [];
$dates = [];
for ($i = 0; $i < count($timestamp); $i++) {
    if (isset($closes[$i]) && $closes[$i] !== null && $closes[$i] > 0) {
        $prices[] = $closes[$i];
        $dates[] = date('M d', $timestamp[$i]);
    }
}

if (count($prices) < 10) {
    die("❌ Not enough price data for {$symbol}");
}

// Calculate returns
$oldPrice = $prices[0];
$newPrice = end($prices);
$totalReturn = (($newPrice - $oldPrice) / $oldPrice) * 100;

// Acceleration test: last 30 vs previous 30 days
$recent = array_slice($prices, -30);
$previous = array_slice($prices, -60, 30);

$recentReturn = 0;
$previousReturn = 0;

if (count($recent) >= 2) {
    $recentReturn = (($recent[count($recent)-1] - $recent[0]) / $recent[0]) * 100;
}
if (count($previous) >= 2) {
    $previousReturn = (($previous[count($previous)-1] - $previous[0]) / $previous[0]) * 100;
}

$isAccelerating = $recentReturn > $previousReturn;
$isAccelerator = ($totalReturn >= 50 && $isAccelerating);
$totalReturnFormatted = round($totalReturn, 1);
$recentReturnFormatted = round($recentReturn, 1);
$previousReturnFormatted = round($previousReturn, 1);

$fromHighPercent = $fiftyTwoWeekHigh > 0 ? round((($fiftyTwoWeekHigh - $currentPrice) / $fiftyTwoWeekHigh) * 100, 1) : 0;

// ============================================================
// FETCH VALUATION METRICS - either from API, clipboard, or cache
// ============================================================
$alphaRawResponse = null;
if ($useClipboard) {
    $valuationMetrics = parseClipboardData($_POST['clipboard_data'], $alphaRawResponse);
    $dataSource = 'pasted';
    
    // Save pasted data to cache!
    if ($valuationMetrics['available']) {
        $saveResult = saveToCache($symbol, $valuationMetrics);
        $valuationMetrics['cache_saved'] = $saveResult;
        $valuationMetrics['from_cache'] = false;
    }
} else {
    $valuationMetrics = getAlphaVantageFundamentals($symbol, $ALPHA_VANTAGE_API_KEY, $forceRefresh);
    $alphaRawResponse = $valuationMetrics['raw_response'] ?? null;
    if (isset($valuationMetrics['from_cache']) && $valuationMetrics['from_cache']) {
        $dataSource = 'cache';
    } else {
        $dataSource = 'api';
    }
}

// Get cache status for display
$cacheStatus = getCacheStatus();

// ============================================================
// EVALUATE THE 3 FORCES
// ============================================================
$growthPass = ($totalReturn >= 50 && $isAccelerating);

$financialPass = false;
if ($valuationMetrics['available'] && $valuationMetrics['profit_margin'] !== null) {
    $financialPass = ($valuationMetrics['profit_margin'] > 0);
}

$valuationPass = false;
$valuationSweetSpot = false;
if ($valuationMetrics['available'] && $valuationMetrics['peg_ratio'] !== null && $valuationMetrics['peg_ratio'] > 0) {
    $valuationSweetSpot = ($valuationMetrics['peg_ratio'] < 1);
    $valuationPass = ($valuationMetrics['peg_ratio'] < 1.5);
}

$allThreeAlign = ($growthPass && $financialPass && $valuationPass);
$isRateLimited = isset($valuationMetrics['rate_limited']) && $valuationMetrics['rate_limited'];
$fromCache = isset($valuationMetrics['from_cache']) && $valuationMetrics['from_cache'];

// ============================================================
// COMPACT MODE (UNCHANGED)
// ============================================================
if ($isCompact) {
    $badge = $isAccelerator ? '🚀 Buy' : '× NOBUY';
    $color = $isAccelerator ? '#10b981' : '#f59e0b';
    $arrow = $totalReturn >= 0 ? '▲' : '▼';
    $returnText = ($totalReturn >= 0 ? '+' : '') . $totalReturnFormatted . '%';
    
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { background: transparent; display: inline-block; }
            .widget {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: #0a0e27;
                border: 1px solid rgba(255,255,255,0.2);
                border-radius: 8px;
                padding: 4px 10px;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                font-size: 11px;
                font-weight: 500;
                white-space: nowrap;
                cursor: pointer;
                text-decoration: none;
            }
            .widget:hover { background: #1a1f3a; }
            .symbol { color: #e0e0e0; font-weight: 600; }
            .badge {
                padding: 2px 6px;
                border-radius: 4px;
                font-weight: bold;
                font-size: 10px;
                background: <?php echo $color; ?>;
                color: #000;
            }
            .return {
                font-family: monospace;
                font-size: 10px;
                color: <?php echo $totalReturn >= 0 ? '#10b981' : '#ef4444'; ?>;
            }
            a { text-decoration: none; }
        </style>
    </head>
    <body>
        <a href="?symbol=<?php echo urlencode($symbol); ?>" target="_blank" class="widget" title="6-month return: <?php echo $returnText; ?> | Trend: <?php echo $isAccelerating ? 'Accelerating' : 'Decelerating'; ?>">
            <span class="symbol"><?php echo htmlspecialchars($symbol); ?></span>
            <span class="badge"><?php echo $badge; ?></span>
            <span class="return"><?php echo $arrow; ?> <?php echo $returnText; ?></span>
        </a>
    </body>
    </html>
    <?php
    exit;
}

// ============================================================
// FULL MODE
// ============================================================
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $symbol; ?> – Sweet Spot Analyzer</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%); min-height: 100vh; padding: 40px 20px; color: #e0e0e0; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: rgba(15,20,40,0.9); backdrop-filter: blur(10px); border-radius: 32px; padding: 30px; border: 1px solid rgba(255,255,255,0.1); }
        h1 { font-size: 48px; margin-bottom: 4px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .price { font-size: 32px; font-weight: bold; color: #10b981; margin-top: 8px; }
        .badge { display: inline-block; padding: 8px 24px; border-radius: 40px; font-weight: bold; background: <?php echo $isAccelerator ? '#10b981' : '#f59e0b'; ?>; color: #000; margin: 20px 0; font-size: 14px; }
        .info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin: 20px 0; }
        .info-card { background: rgba(0,0,0,0.3); padding: 16px; border-radius: 20px; text-align: center; transition: transform 0.2s; }
        .info-card:hover { transform: translateY(-2px); background: rgba(0,0,0,0.4); }
        .info-label { font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px; }
        .info-value { font-size: 24px; font-weight: bold; margin-top: 8px; }
        .positive { color: #10b981; }
        .negative { color: #ef4444; }
        canvas { max-height: 350px; margin: 20px 0; }
        
        .forces-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin: 30px 0 20px;
        }
        .force-card {
            background: rgba(0,0,0,0.4);
            border-radius: 24px;
            padding: 24px 20px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.08);
            transition: all 0.2s;
        }
        .force-card:hover { transform: translateY(-3px); border-color: rgba(255,255,255,0.2); }
        .force-icon { font-size: 48px; margin-bottom: 12px; }
        .force-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #9ca3af; margin-bottom: 12px; }
        .force-status {
            font-size: 14px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            display: inline-block;
            margin: 10px 0;
        }
        .force-value { font-size: 13px; color: #9ca3af; margin-top: 12px; line-height: 1.5; }
        .force-pass { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .force-fail { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .force-warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
        
        .sweet-spot-badge {
            background: linear-gradient(135deg, #f59e0b, #10b981);
            padding: 18px;
            border-radius: 20px;
            text-align: center;
            margin: 20px 0;
            font-weight: bold;
            font-size: 20px;
            color: #000;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
        }
        
        .valuation-table {
            background: rgba(0,0,0,0.3);
            border-radius: 20px;
            padding: 20px;
            margin: 20px 0;
        }
        .valuation-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        .valuation-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .valuation-label { color: #9ca3af; font-size: 13px; }
        .valuation-number { font-weight: 600; font-family: monospace; font-size: 14px; }
        
        .reason-list { list-style: none; padding: 0; margin: 20px 0; }
        .reason-list li { padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 10px; }
        .principle { margin-top: 24px; padding: 20px; background: rgba(0,0,0,0.3); border-radius: 20px; border-left: 4px solid #f59e0b; }
        details { margin-top: 20px; }
        summary { cursor: pointer; color: #6b7280; font-size: 12px; }
        pre { background: rgba(0,0,0,0.5); padding: 15px; border-radius: 16px; overflow-x: auto; font-size: 10px; max-height: 300px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word; }
        
        @media (max-width: 700px) {
            .forces-grid { grid-template-columns: 1fr; gap: 12px; }
            .info-grid { grid-template-columns: repeat(2, 1fr); }
            h1 { font-size: 32px; }
            .valuation-grid { grid-template-columns: 1fr; }
        }
        
        .refresh-btn, .retry-btn {
            background: #f59e0b;
            color: #000;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            margin-left: 10px;
        }
        .retry-btn { background: #10b981; }
        .refresh-btn:hover, .retry-btn:hover { opacity: 0.9; }
        
        .clipboard-form {
            margin: 15px 0;
            padding: 15px;
            background: rgba(0,0,0,0.3);
            border-radius: 16px;
            border: 1px dashed rgba(255,255,255,0.2);
        }
        .clipboard-form textarea {
            width: 100%;
            padding: 12px;
            background: rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 12px;
            color: #e0e0e0;
            font-family: monospace;
            font-size: 11px;
            resize: vertical;
        }
        .clipboard-form button {
            margin-top: 10px;
            background: #10b981;
            color: #000;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .cache-badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 10px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>
            <?php echo htmlspecialchars($symbol); ?>
            <span style="font-size: 14px; color: #9ca3af;"><?php echo htmlspecialchars($longName); ?></span>
            <button onclick="location.href='?symbol=<?php echo urlencode($symbol); ?>&refresh=1'" class="refresh-btn">⟳ Refresh (Skip Cache)</button>
        </h1>
        <div class="price"><?php echo $currency; ?> <?php echo number_format($currentPrice, 2); ?></div>
        
        <div class="badge">
            <?php echo $isAccelerator ? '🍎 HIGH-GROWTH ACCELERATOR DETECTED' : '📊 MONITOR / WAIT FOR SIGNAL'; ?>
            <?php if ($fromCache): ?>
                <span class="cache-badge">📦 from cache (24h)</span>
            <?php endif; ?>
        </div>
        
        <div class="info-grid">
            <div class="info-card"><div class="info-label">6-Month Return</div><div class="info-value <?php echo $totalReturn >= 0 ? 'positive' : 'negative'; ?>"><?php echo $totalReturn >= 0 ? '+' : ''; ?><?php echo $totalReturnFormatted; ?>%</div></div>
            <div class="info-card"><div class="info-label">Recent 30d</div><div class="info-value <?php echo $recentReturn >= 0 ? 'positive' : 'negative'; ?>"><?php echo $recentReturn >= 0 ? '+' : ''; ?><?php echo $recentReturnFormatted; ?>%</div></div>
            <div class="info-card"><div class="info-label">Previous 30d</div><div class="info-value <?php echo $previousReturn >= 0 ? 'positive' : 'negative'; ?>"><?php echo $previousReturn >= 0 ? '+' : ''; ?><?php echo $previousReturnFormatted; ?>%</div></div>
            <div class="info-card"><div class="info-label">vs 52-Week High</div><div class="info-value <?php echo $fromHighPercent < 15 ? 'positive' : 'negative'; ?>">-<?php echo $fromHighPercent; ?>%</div></div>
        </div>
        
        <canvas id="priceChart"></canvas>
        
        <h3 style="margin: 30px 0 10px 0; text-align: center;">🍎 The 3 Key Forces (The Sweet Spot)</h3>
        <p style="text-align: center; font-size: 14px; color: #9ca3af; margin-bottom: 20px;">"When all three align, that's the sweet spot for buying before the price rises."</p>
        
        <div class="forces-grid">
            <!-- FORCE 1: GROWTH -->
            <div class="force-card">
                <div class="force-icon">📈</div>
                <div class="force-title">1. GROWING BUSINESS</div>
                <div class="force-status <?php echo $growthPass ? 'force-pass' : 'force-warning'; ?>">
                    <?php echo $growthPass ? '✅ STRONG GROWTH' : '⚠️ MODERATE/WEAK'; ?>
                </div>
                <div class="force-value">
                    6-Month Return: <strong><?php echo $totalReturn >= 0 ? '+' : ''; ?><?php echo $totalReturnFormatted; ?>%</strong><br>
                    Trend: <strong><?php echo $isAccelerating ? 'ACCELERATING 📈' : 'DECELERATING 📉'; ?></strong><br>
                    <small>📊 Video: 50%+ return + accelerating YoY</small>
                </div>
            </div>
            
            <!-- FORCE 2: FINANCIAL STRENGTH -->
            <div class="force-card">
                <div class="force-icon">🛡️</div>
                <div class="force-title">2. FINANCIAL STRENGTH</div>
                <div class="force-status <?php echo (!$valuationMetrics['available']) ? 'force-warning' : ($financialPass ? 'force-pass' : 'force-fail'); ?>">
                    <?php if (!$valuationMetrics['available'] && !$isRateLimited): ?>
                        <a href="<?php echo $alphaVantageUrl; ?>" target="_blank" style="color: #f59e0b; text-decoration: none;">📊 VIEW API DATA →</a>
                    <?php elseif ($isRateLimited): ?>
                        ⏳ RATE LIMITED
                    <?php elseif ($financialPass): ?>
                        ✅ PROFITABLE
                    <?php else: ?>
                        🔴 NOT PROFITABLE
                    <?php endif; ?>
                </div>
                <div class="force-value">
                    <?php if ($valuationMetrics['available']): ?>
                        Profit Margin: <strong><?php echo $valuationMetrics['profit_margin'] !== null ? number_format($valuationMetrics['profit_margin'], 1) . '%' : 'N/A'; ?></strong><br>
                        <small>🎯 Video: Positive profit margin + low debt</small>
                    <?php elseif ($isRateLimited): ?>
                        <span style="color: #f59e0b;">Alpha Vantage: 25 requests/day limit</span><br>
                        <button onclick="location.href='?symbol=<?php echo urlencode($symbol); ?>'" class="retry-btn" style="margin-top: 8px;">⏰ Retry Now</button>
                        <small style="display: block; margin-top: 8px;">💡 Or paste data below</small>
                    <?php else: ?>
                        <a href="<?php echo $alphaVantageUrl; ?>" target="_blank" style="color: #f59e0b;">Click to view Alpha Vantage data</a><br>
                        <small>💡 Free tier: 25 requests per day</small>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- FORCE 3: VALUATION -->
            <div class="force-card">
                <div class="force-icon">💰</div>
                <div class="force-title">3. FAIR VALUATION</div>
                <div class="force-status <?php echo (!$valuationMetrics['available']) ? 'force-warning' : ($valuationSweetSpot ? 'force-pass' : ($valuationPass ? 'force-warning' : 'force-fail')); ?>">
                    <?php if (!$valuationMetrics['available'] && !$isRateLimited): ?>
                        <a href="<?php echo $alphaVantageUrl; ?>" target="_blank" style="color: #f59e0b; text-decoration: none;">📊 VIEW API DATA →</a>
                    <?php elseif ($isRateLimited): ?>
                        ⏳ RATE LIMITED
                    <?php elseif ($valuationSweetSpot): ?>
                        ✅ SWEET SPOT
                    <?php elseif ($valuationPass): ?>
                        ⚠️ FAIRLY VALUED
                    <?php else: ?>
                        🔴 EXPENSIVE
                    <?php endif; ?>
                </div>
                <div class="force-value">
                    <?php if ($valuationMetrics['available']): ?>
                        PEG Ratio: <strong><?php echo $valuationMetrics['peg_ratio'] !== null ? number_format($valuationMetrics['peg_ratio'], 2) : 'N/A'; ?></strong><br>
                        P/E (TTM): <strong><?php echo $valuationMetrics['trailing_pe'] !== null ? number_format($valuationMetrics['trailing_pe'], 1) : 'N/A'; ?></strong><br>
                        <small>🎯 Video: PEG < 1 = Sweet Spot, < 1.5 = Fair</small>
                    <?php elseif ($isRateLimited): ?>
                        <span style="color: #f59e0b;">Alpha Vantage: 25 requests/day limit</span><br>
                        <button onclick="location.href='?symbol=<?php echo urlencode($symbol); ?>'" class="retry-btn" style="margin-top: 8px;">⏰ Retry Now</button>
                        <small style="display: block; margin-top: 8px;">💡 Or paste data below</small>
                    <?php else: ?>
                        <a href="<?php echo $alphaVantageUrl; ?>" target="_blank" style="color: #f59e0b;">Click to view Alpha Vantage data</a><br>
                        <small>💡 Free tier: 25 requests per day</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- PASTE FROM CLIPBOARD SECTION -->
        <?php if (!$valuationMetrics['available'] && !$fromCache): ?>
        <div class="clipboard-form">
            <form method="post" action="">
                <input type="hidden" name="symbol" value="<?php echo htmlspecialchars($symbol); ?>">
                <label style="display: block; margin-bottom: 8px; font-size: 13px;">
                    📋 <strong>Or paste API response from clipboard:</strong>
                </label>
                <textarea name="clipboard_data" rows="4" placeholder='Paste the JSON response from Alpha Vantage here, e.g.:
{
    "Symbol": "<?php echo $symbol; ?>",
    "PEGRatio": "0.77",
    "ProfitMargin": "0.15",
    ...}'></textarea>
                <button type="submit">📋 Load from Pasted Data</button>
            </form>
            <p style="font-size: 11px; color: #9ca3af; margin-top: 10px;">
                💡 Tip: Copy the entire JSON from <a href="<?php echo $alphaVantageUrl; ?>" target="_blank" style="color: #f59e0b;">this link</a> and paste above
            </p>
        </div>
        <?php endif; ?>
        
        <!-- VALUATION METRICS TABLE -->
        <?php if ($valuationMetrics['available']): ?>
        <div class="valuation-table">
            <h4 style="margin: 0 0 15px 0; text-align: center;">📊 Valuation Measures (from <?php echo $useClipboard ? 'pasted data' : ($fromCache ? 'cache (24h)' : 'Alpha Vantage API'); ?>)</h4>
            <div class="valuation-grid">
                <div class="valuation-item"><span class="valuation-label">Market Cap</span><span class="valuation-number"><?php echo $valuationMetrics['market_cap'] !== null ? '$' . number_format($valuationMetrics['market_cap']/1000000000, 2) . 'B' : 'N/A'; ?></span></div>
                <div class="valuation-item"><span class="valuation-label">Trailing P/E</span><span class="valuation-number"><?php echo $valuationMetrics['trailing_pe'] !== null ? number_format($valuationMetrics['trailing_pe'], 2) : 'N/A'; ?></span></div>
                <div class="valuation-item"><span class="valuation-label">Forward P/E</span><span class="valuation-number"><?php echo $valuationMetrics['forward_pe'] !== null ? number_format($valuationMetrics['forward_pe'], 2) : 'N/A'; ?></span></div>
                <div class="valuation-item"><span class="valuation-label">PEG Ratio</span><span class="valuation-number"><?php echo $valuationMetrics['peg_ratio'] !== null ? number_format($valuationMetrics['peg_ratio'], 2) : 'N/A'; ?></span></div>
                <div class="valuation-item"><span class="valuation-label">Price/Sales (TTM)</span><span class="valuation-number"><?php echo $valuationMetrics['price_to_sales'] !== null ? number_format($valuationMetrics['price_to_sales'], 2) : 'N/A'; ?></span></div>
                <div class="valuation-item"><span class="valuation-label">Price/Book</span><span class="valuation-number"><?php echo $valuationMetrics['price_to_book'] !== null ? number_format($valuationMetrics['price_to_book'], 2) : 'N/A'; ?></span></div>
                <div class="valuation-item"><span class="valuation-label">EV/Revenue</span><span class="valuation-number"><?php echo $valuationMetrics['ev_to_revenue'] !== null ? number_format($valuationMetrics['ev_to_revenue'], 2) : 'N/A'; ?></span></div>
                <div class="valuation-item"><span class="valuation-label">EV/EBITDA</span><span class="valuation-number"><?php echo $valuationMetrics['ev_to_ebitda'] !== null ? number_format($valuationMetrics['ev_to_ebitda'], 2) : 'N/A'; ?></span></div>
                <div class="valuation-item"><span class="valuation-label">Profit Margin</span><span class="valuation-number"><?php echo $valuationMetrics['profit_margin'] !== null ? number_format($valuationMetrics['profit_margin'], 1) . '%' : 'N/A'; ?></span></div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- SWEET SPOT BADGE -->
        <?php if ($allThreeAlign): ?>
            <div class="sweet-spot-badge">🎯 SWEET SPOT DETECTED! 🎯<br><small>All 3 forces aligned → High growth + Profitable + Fair valuation</small></div>
        <?php elseif ($growthPass && $valuationMetrics['available']): ?>
            <div style="background: rgba(245, 158, 11, 0.15); padding: 16px; border-radius: 20px; text-align: center; margin: 20px 0;">⚡ Growth force is aligned! Check valuation above.<br><small>Meta, Nvidia, and Uber all had all 3 forces aligned before their big runs.</small></div>
        <?php elseif ($isAccelerator): ?>
            <div style="background: linear-gradient(135deg, #f59e0b, #f59e0b); padding: 18px; border-radius: 20px; text-align: center; font-weight: bold; font-size: 18px; color: #000; margin: 20px 0;">📈 PARTIAL SWEET SPOT<br><small>Force 1 (Growth) is strong. Check Forces 2 & 3 in the valuation table above.</small></div>
        <?php else: ?>
            <div style="background: rgba(0,0,0,0.3); padding: 16px; border-radius: 20px; text-align: center; margin: 20px 0;">📉 Not yet in sweet spot zone. Keep watching.<br><small>Meta was here. Nvidia was here. Uber was here. When all 3 align → that's the sweet spot.</small></div>
        <?php endif; ?>
        
        <!-- REASON LIST -->
        <ul class="reason-list">
            <?php if ($isAccelerator): ?>
                <li>✅ <strong>+<?php echo $totalReturnFormatted; ?>% in 6 months</strong> – meets the 50% growth threshold</li>
                <li>📈 Growth trend is <strong>ACCELERATING</strong> – like AMD, Costco, Alphabet before their big runs</li>
                <li>🎯 Recent 30d: +<?php echo $recentReturnFormatted; ?>% vs Previous 30d: +<?php echo $previousReturnFormatted; ?>%</li>
                <li>🍎 <strong>Don't cut the apple tree</strong> – historically, accelerators outperform by ~20%</li>
            <?php elseif ($totalReturn >= 50 && !$isAccelerating): ?>
                <li>📊 +<?php echo $totalReturnFormatted; ?>% – meets threshold but <strong>DECELERATING</strong></li>
                <li>⚠️ Recent 30d: +<?php echo $recentReturnFormatted; ?>% vs Previous 30d: +<?php echo $previousReturnFormatted; ?>%</li>
                <li>🟡 Growth is slowing – investigate why</li>
            <?php elseif ($totalReturn < 50 && $isAccelerating): ?>
                <li>📈 Accelerating trend but only +<?php echo $totalReturnFormatted; ?>% total return</li>
                <li>💡 Video's threshold is 50% – needs more time to qualify</li>
            <?php else: ?>
                <li>🔴 Only +<?php echo $totalReturnFormatted; ?>% return, trend is <?php echo $isAccelerating ? 'accelerating' : 'decelerating'; ?></li>
                <li>📉 Not meeting the high-growth accelerator criteria yet</li>
            <?php endif; ?>
            <?php if ($valuationMetrics['available'] && $valuationMetrics['peg_ratio'] !== null): ?>
                <li>📊 PEG Ratio: <strong><?php echo number_format($valuationMetrics['peg_ratio'], 2); ?></strong> – <?php echo $valuationMetrics['peg_ratio'] < 1 ? 'Sweet Spot!' : ($valuationMetrics['peg_ratio'] < 1.5 ? 'Fairly valued' : 'Expensive'); ?></li>
            <?php endif; ?>
        </ul>
        
        <div class="principle">
            <strong>📊 From the <a target="_blank" href="https://www.youtube.com/watch?v=3THSa4AtxvY" style="color: #f59e0b;">video</a> (Charles, hellostocks.ai):</strong><br>
            • <strong>Force 1 - Growth:</strong> 50%+ revenue growth over 5 years (~9%/year) + accelerating<br>
            • <strong>Force 2 - Financial Strength:</strong> Positive profit margin + low debt<br>
            • <strong>Force 3 - Valuation:</strong> PEG ratio &lt; 1 (sweet spot) or &lt; 1.5 (fair)<br>
            • <strong>Result:</strong> 72.6% median 5-year return vs 53% for S&P 500<br>
            💡 <em>"Past performance doesn't guarantee future results, but it IS a good indicator."</em>
        </div>
        
        <details>
            <summary>🔧 Debug: Alpha Vantage API Response</summary>
            <pre><?php 
                if ($alphaRawResponse) {
                    $decoded = json_decode($alphaRawResponse, true);
                    if ($decoded) {
                        echo htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT));
                    } else {
                        echo htmlspecialchars($alphaRawResponse);
                    }
                } else {
                    echo "No API response captured";
                }
            ?></pre>
        </details>
        
        <details>
            <summary>📦 Cache Status & File Info</summary>
            <pre><?php 
                echo "Cache file: " . $CACHE_FILE . "\n";
                echo "File exists: " . ($cacheStatus['exists'] ? 'Yes' : 'No') . "\n";
                echo "Directory writable: " . ($cacheStatus['directory_writable'] ? 'Yes' : 'No') . "\n";
                echo "\n--- Current Cache Contents ---\n";
                $currentCache = loadCache();
                if (!empty($currentCache)) {
                    foreach ($currentCache as $cachedSymbol => $cachedData) {
                        $age = round((time() - $cachedData['timestamp']) / 3600, 1);
                        echo "\n{$cachedSymbol}: cached {$age} hours ago";
                        if (isset($cachedData['data']['peg_ratio'])) {
                            echo " (PEG: {$cachedData['data']['peg_ratio']})";
                        }
                    }
                } else {
                    echo "Cache is empty";
                }
                
                echo "\n\n--- Files in directory ---\n";
                $files = scandir(__DIR__);
                foreach ($files as $file) {
                    if ($file != '.' && $file != '..' && $file != 'stock_rating.php') {
                        echo $file . "\n";
                    }
                }
            ?></pre>
        </details>
    </div>
</div>

<script>
const prices = <?php echo json_encode($prices); ?>;
const dates = <?php echo json_encode($dates); ?>;
const isAcc = <?php echo $isAccelerator ? 'true' : 'false'; ?>;

if (prices.length > 0) {
    new Chart(document.getElementById('priceChart').getContext('2d'), {
        type: 'line',
        data: { labels: dates, datasets: [{ label: '<?php echo addslashes($symbol); ?> Price', data: prices, borderColor: isAcc ? '#10b981' : '#f59e0b', backgroundColor: isAcc ? 'rgba(16, 185, 129, 0.08)' : 'rgba(245, 158, 11, 0.08)', borderWidth: 2.5, fill: true, pointRadius: 0, tension: 0.1 }] },
        options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { labels: { color: '#e0e0e0' } } }, scales: { y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#e0e0e0' } }, x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#e0e0e0', rotation: 45 } } } }
    });
}
</script>
</body>
</html>
<?php

function showScreener() {
    $stocks = ['AMD', 'COST', 'GOOGL', 'NVDA', 'MSFT', 'ORCL', 'PLTR', 'BA', 'AAPL', 'META', 'TSLA', 'AMZN'];
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Sweet Spot Stock Screener</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%); min-height: 100vh; padding: 40px 20px; color: #e0e0e0; }
            .container { max-width: 800px; margin: 0 auto; }
            .card { background: rgba(15,20,40,0.9); border-radius: 32px; padding: 30px; border: 1px solid rgba(255,255,255,0.1); }
            h1 { font-size: 36px; margin-bottom: 8px; }
            h1 span { color: #10b981; }
            .subtitle { color: #9ca3af; margin-bottom: 24px; }
            .search { margin-bottom: 24px; display: flex; gap: 10px; }
            .search input { flex: 1; padding: 14px 20px; border-radius: 40px; border: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.4); color: #fff; font-size: 16px; }
            .search button { padding: 14px 28px; border-radius: 40px; border: none; background: #f59e0b; color: #000; font-weight: bold; cursor: pointer; }
            .stock-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; margin: 20px 0; }
            .stock-item { background: rgba(0,0,0,0.3); padding: 12px; border-radius: 16px; text-align: center; }
            .stock-item a { color: #e0e0e0; text-decoration: none; font-size: 16px; font-weight: bold; display: block; }
            .stock-item a:hover { color: #f59e0b; }
            .note { margin-top: 24px; padding: 20px; background: rgba(0,0,0,0.3); border-radius: 20px; font-size: 13px; border-left: 3px solid #10b981; }
        </style>
    </head>
    <body>
    <div class="container">
        <div class="card">
            <h1>🍎 <span>The Sweet Spot</span> Screener</h1>
            <div class="subtitle">Find stocks before their big runs. Meta, Nvidia, Uber were here.</div>
            <div class="search">
                <form action="" method="get">
                    <input type="text" name="symbol" placeholder="Enter symbol (e.g., AMD, COST, AAPL)" value="">
                    <button type="submit">Analyze →</button>
                </form>
            </div>
            <div class="stock-grid">
                <?php foreach ($stocks as $s): ?>
                <div class="stock-item"><a href="?symbol=<?php echo $s; ?>"><?php echo $s; ?></a></div>
                <?php endforeach; ?>
            </div>
            <div class="note">
                <strong>🎯 The 3 Key Forces (The Sweet Spot):</strong><br>
                • <strong>Force 1 - Growth:</strong> 50%+ return over 6 months + ACCELERATING trend<br>
                • <strong>Force 2 - Financial Strength:</strong> Positive profit margin + low debt<br>
                • <strong>Force 3 - Valuation:</strong> PEG ratio &lt; 1 (sweet spot) or &lt; 1.5 (fair)<br>
                • <strong>When all 3 align → That's the sweet spot for buying before the price rises.</strong>
            </div>
        </div>
    </div>
    </body>
    </html>
    <?php
}
?>
