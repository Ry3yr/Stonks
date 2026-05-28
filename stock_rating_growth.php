<?php
// stock_rating.php?symbol=AMD
// stock_rating.php?symbol=COST&compact
// stock_rating.php?screen

error_reporting(0);
ini_set('display_errors', 0);

$symbol = strtoupper($_GET['symbol'] ?? '');
$isCompact = isset($_GET['compact']);
$screenMode = isset($_GET['screen']);

if ($screenMode) {
    showScreener();
    exit;
}

if (empty($symbol)) {
    die('❌ Provide a symbol: stock_rating.php?symbol=AMD');
}

// Fetch from Yahoo Finance
$url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}?interval=1d&range=6mo";
$response = @file_get_contents($url);

if ($response === false) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
}

if (!$response) {
    die("❌ Could not fetch data for {$symbol}");
}

$data = json_decode($response, true);
$result = $data['chart']['result'][0] ?? null;

if (!$result) {
    die("❌ Invalid response for {$symbol}");
}

// Parse data
$meta = $result['meta'];
$currentPrice = $meta['regularMarketPrice'] ?? 0;
$currency = $meta['currency'] ?? 'USD';

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

// ============================================================
// COMPACT MODE
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
    <title><?php echo $symbol; ?> – Accelerator Test</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: system-ui; background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%); min-height: 100vh; padding: 40px 20px; color: #e0e0e0; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: rgba(15,20,40,0.9); border-radius: 24px; padding: 30px; border: 1px solid rgba(255,255,255,0.1); }
        h1 { font-size: 48px; margin-bottom: 4px; }
        .price { font-size: 32px; font-weight: bold; color: #10b981; }
        .badge { display: inline-block; padding: 8px 20px; border-radius: 40px; font-weight: bold; background: <?php echo $isAccelerator ? '#10b981' : '#f59e0b'; ?>; color: #000; margin: 20px 0; }
        .info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin: 20px 0; }
        .info-card { background: rgba(0,0,0,0.3); padding: 16px; border-radius: 16px; text-align: center; }
        .info-label { font-size: 11px; color: #9ca3af; text-transform: uppercase; }
        .info-value { font-size: 24px; font-weight: bold; margin-top: 8px; }
        .positive { color: #10b981; }
        .negative { color: #ef4444; }
        canvas { max-height: 350px; margin: 20px 0; }
        .reason-list { list-style: none; padding: 0; margin: 20px 0; }
        .reason-list li { padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .principle { margin-top: 24px; padding: 20px; background: rgba(0,0,0,0.3); border-radius: 16px; border-left: 4px solid #f59e0b; }
        details { margin-top: 20px; }
        summary { cursor: pointer; color: #6b7280; font-size: 12px; }
        pre { background: rgba(0,0,0,0.5); padding: 15px; border-radius: 12px; overflow-x: auto; font-size: 10px; max-height: 300px; overflow-y: auto; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1><?php echo htmlspecialchars($symbol); ?></h1>
        <div class="price"><?php echo $currency; ?> <?php echo number_format($currentPrice, 2); ?></div>
        <div class="badge">
            <?php echo $isAccelerator ? '🍎 HIGH-GROWTH ACCELERATOR' : '📊 MONITOR / WAIT'; ?>
        </div>
        
        <div class="info-grid">
            <div class="info-card">
                <div class="info-label">6-Month Return</div>
                <div class="info-value <?php echo $totalReturn >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $totalReturn >= 0 ? '+' : ''; ?><?php echo $totalReturnFormatted; ?>%
                </div>
            </div>
            <div class="info-card">
                <div class="info-label">Recent 30d</div>
                <div class="info-value <?php echo $recentReturn >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $recentReturn >= 0 ? '+' : ''; ?><?php echo $recentReturnFormatted; ?>%
                </div>
            </div>
            <div class="info-card">
                <div class="info-label">Previous 30d</div>
                <div class="info-value <?php echo $previousReturn >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $previousReturn >= 0 ? '+' : ''; ?><?php echo $previousReturnFormatted; ?>%
                </div>
            </div>
            <div class="info-card">
                <div class="info-label">Trend</div>
                <div class="info-value <?php echo $isAccelerating ? 'positive' : 'negative'; ?>">
                    <?php echo $isAccelerating ? '▲ ACCEL' : '▼ DECEL'; ?>
                </div>
            </div>
        </div>
        
        <canvas id="priceChart"></canvas>
        
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
                <li>🔴 Only +<?php echo $totalReturnFormatted; ?>% return, trend is decelerating</li>
                <li>📉 Not meeting the high-growth accelerator criteria</li>
            <?php endif; ?>
        </ul>
        
        <div class="principle">
            <strong>📊 From the video (Charles, hellostocks.ai):</strong><br>
            • High-growth accelerators (50%+ revenue growth + accelerating YoY) → <strong>72.6% median 5-year return</strong><br>
            • Vs 53% for all S&P 500 stocks<br>
            • Outperformed in <strong>9 out of 11 sectors</strong><br>
            💡 <em>"Past performance doesn't guarantee future results, but it IS a good indicator."</em>
        </div>
        
        <details>
            <summary>🔧 Debug: Raw Yahoo Finance Response</summary>
            <pre><?php echo htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)); ?></pre>
        </details>
    </div>
</div>

<script>
const prices = <?php echo json_encode($prices); ?>;
const dates = <?php echo json_encode($dates); ?>;
const isAcc = <?php echo $isAccelerator ? 'true' : 'false'; ?>;

if (prices.length > 0) {
    const ctx = document.getElementById('priceChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: '<?php echo $symbol; ?> Price',
                data: prices,
                borderColor: isAcc ? '#10b981' : '#f59e0b',
                backgroundColor: isAcc ? 'rgba(16, 185, 129, 0.1)' : 'rgba(245, 158, 11, 0.1)',
                borderWidth: 2,
                fill: true,
                pointRadius: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { labels: { color: '#e0e0e0' } } },
            scales: {
                y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#e0e0e0' } },
                x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#e0e0e0', rotation: 45, maxRotation: 45 } }
            }
        }
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
        <title>High-Growth Accelerator Screener</title>
        <style>
            body { font-family: system-ui; background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%); min-height: 100vh; padding: 40px 20px; color: #e0e0e0; }
            .container { max-width: 800px; margin: 0 auto; }
            .card { background: rgba(15,20,40,0.9); border-radius: 24px; padding: 30px; border: 1px solid rgba(255,255,255,0.1); }
            h1 { font-size: 36px; margin-bottom: 8px; }
            h1 span { color: #10b981; }
            .subtitle { color: #9ca3af; margin-bottom: 24px; }
            .search { margin-bottom: 24px; display: flex; gap: 10px; }
            .search input { flex: 1; padding: 14px 20px; border-radius: 40px; border: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.4); color: #fff; font-size: 16px; }
            .search button { padding: 14px 28px; border-radius: 40px; border: none; background: #f59e0b; color: #000; font-weight: bold; cursor: pointer; }
            .stock-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; margin: 20px 0; }
            .stock-item { background: rgba(0,0,0,0.3); padding: 12px; border-radius: 12px; text-align: center; }
            .stock-item a { color: #e0e0e0; text-decoration: none; font-size: 16px; font-weight: bold; display: block; }
            .stock-item a:hover { color: #f59e0b; }
            .note { margin-top: 24px; padding: 16px; background: rgba(0,0,0,0.3); border-radius: 12px; font-size: 13px; border-left: 3px solid #10b981; }
        </style>
    </head>
    <body>
    <div class="container">
        <div class="card">
            <h1>🍎 <span>High-Growth Accelerator</span> Screener</h1>
            <div class="subtitle">Stocks that showed 50%+ growth + accelerating trend before big runs (AMD, COST, GOOGL)</div>
            
            <div class="search">
                <form action="" method="get">
                    <input type="text" name="symbol" placeholder="Enter any symbol (e.g., AMD, COST)" value="">
                    <button type="submit">Analyze →</button>
                </form>
            </div>
            
            <div class="stock-grid">
                <?php foreach ($stocks as $s): ?>
                <div class="stock-item">
                    <a href="?symbol=<?php echo $s; ?>"><?php echo $s; ?></a>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="note">
                <strong>📊 The Methodology (from the video):</strong><br>
                • Look for <strong>50%+ revenue growth over 5 years</strong><br>
                • AND the growth rate is <strong>ACCELERATING</strong> (each year faster than the last)<br>
                • Historical result: <strong>72.6% median 5-year return</strong> vs 53% for S&P 500
            </div>
        </div>
    </div>
    </body>
    </html>
    <?php
}
?>
