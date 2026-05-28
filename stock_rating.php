<?php
// stock_rating.php?symbol=HSBC
// stock_rating.php?symbol=HSBC&compact

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors in browser
ini_set('log_errors', 1);

$symbol = strtoupper($_GET['symbol'] ?? '');

if (empty($symbol)) {
    die('❌ Please provide a symbol: stock_rating.php?symbol=HSBC');
}

// Check if compact mode is enabled
$isCompact = isset($_GET['compact']);

// ============================================================
// STEP 1: Load stock purchase data from stocks.json
// ============================================================
$jsonPath = 'stocks.json';
if (!file_exists($jsonPath)) {
    die('❌ stocks.json not found');
}

$stocks = json_decode(file_get_contents($jsonPath), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die('❌ Invalid JSON in stocks.json');
}

$purchasedStock = null;
foreach ($stocks as $item) {
    if ($item['stock'] === $symbol) {
        $purchasedStock = $item;
        break;
    }
}

if (!$purchasedStock) {
    die("❌ Stock '{$symbol}' not found in stocks.json");
}

$purchasePrice = $purchasedStock['price'];
$purchaseCurrency = $purchasedStock['currency'];
$exchange = $purchasedStock['exchange_market'];
$purchaseDate = $purchasedStock['date'];
$savedAt = $purchasedStock['saved_at'];

// Calculate holding period
$holdingDays = floor((time() - $savedAt) / 86400);
$holdingYears = round($holdingDays / 365, 1);

// Format days held nicely for compact mode
if ($holdingDays < 1) {
    $holdingDisplay = 'today';
} elseif ($holdingDays == 1) {
    $holdingDisplay = '1d';
} elseif ($holdingDays < 30) {
    $holdingDisplay = $holdingDays . 'd';
} elseif ($holdingDays < 365) {
    $months = floor($holdingDays / 30);
    $days = $holdingDays % 30;
    $holdingDisplay = $months . 'm ' . $days . 'd';
} else {
    $years = floor($holdingDays / 365);
    $days = $holdingDays % 365;
    $holdingDisplay = $years . 'y ' . floor($days / 30) . 'm';
}

// ============================================================
// STEP 2: Fetch data with Curl
// ============================================================

function fetchWithCurl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        return null;
    }
    return $response;
}

// Try Yahoo Finance v8 chart endpoint
$yahooUrl = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}?interval=1d&range=6mo";
$response = fetchWithCurl($yahooUrl);
$data = json_decode($response, true);

$currentPrice = 0;
$historicalPrices = [];
$historicalDates = [];
$purchasePointIndex = null;
$dayChangePercent = 0;
$currency = $purchaseCurrency;

if ($data && isset($data['chart']['result'][0])) {
    $result = $data['chart']['result'][0];
    $meta = $result['meta'];
    $currentPrice = $meta['regularMarketPrice'] ?? $meta['previousClose'] ?? 0;
    $currency = $meta['currency'] ?? $purchaseCurrency;
    $previousClose = $meta['previousClose'] ?? $currentPrice;
    $dayChange = $currentPrice - $previousClose;
    $dayChangePercent = ($previousClose > 0) ? ($dayChange / $previousClose) * 100 : 0;
    
    // Get historical data
    $timestamps = $result['timestamp'] ?? [];
    $quotes = $result['indicators']['quote'][0] ?? [];
    $closes = $quotes['close'] ?? [];
    
    // Find purchase date in historical data
    $closestIndex = -1;
    $closestDiff = PHP_INT_MAX;
    
    foreach ($timestamps as $i => $ts) {
        if (isset($closes[$i]) && $closes[$i] !== null && $closes[$i] > 0) {
            $dateStr = date('M d', $ts);
            $historicalDates[] = $dateStr;
            $historicalPrices[] = $closes[$i];
            
            // Find closest timestamp to purchase date
            $diff = abs($ts - $savedAt);
            if ($diff < $closestDiff) {
                $closestDiff = $diff;
                $closestIndex = count($historicalPrices) - 1;
            }
        }
    }
    
    // Mark the purchase point
    if ($closestIndex >= 0 && $closestIndex < count($historicalPrices)) {
        $purchasePointIndex = $closestIndex;
    }
}

// If price not found via API, try scraping as fallback
if ($currentPrice == 0) {
    $altUrl = "https://finance.yahoo.com/quote/{$symbol}";
    $html = fetchWithCurl($altUrl);
    if ($html && preg_match('/"regularMarketPrice":\{\"raw\":([0-9.]+)/', $html, $matches)) {
        $currentPrice = floatval($matches[1]);
    } elseif ($html && preg_match('/<fin-streamer[^>]*data-value="([0-9.]+)"[^>]*>/', $html, $matches)) {
        $currentPrice = floatval($matches[1]);
    }
}

// If still no price, use last known from historical or default
if ($currentPrice == 0 && !empty($historicalPrices)) {
    $currentPrice = end($historicalPrices);
}

// Calculate gain/loss
$gainLoss = $currentPrice - $purchasePrice;
$gainLossPercent = ($purchasePrice > 0) ? ($gainLoss / $purchasePrice) * 100 : 0;

// ============================================================
// STEP 3: Apply SELL/HOLD logic from video
// ============================================================
$rating = 'HOLD';
$reasons = [];
$score = 5;

// Rule 1: Massive loss (>30%) + no recovery signs
if ($gainLossPercent < -30 && $holdingDays > 90) {
    $rating = 'SELL';
    $reasons[] = "🔴 Down " . number_format($gainLossPercent, 1) . "% over {$holdingDays} days – significant capital erosion";
    $score -= 3;
}
// Rule 2: Product deterioration signal (from video - Cyberpunk example)
elseif ($gainLossPercent < -15 && $holdingDays > 180) {
    $reasons[] = "⚠️ Down " . number_format($gainLossPercent, 1) . "% over {$holdingDays} days – investigate product quality (like Cyberpunk pre-crash)";
    $score -= 1.5;
    if ($score <= 3.5) $rating = 'SELL';
}
// Rule 3: Taking profits too early (DON'T cut the apple tree!)
elseif ($gainLossPercent > 20 && $holdingDays < 365) {
    $reasons[] = "🍎 Up " . number_format($gainLossPercent, 1) . "% in only {$holdingDays} days – DON'T cut the tree! Let it mature (The Witcher 3)";
    $score += 1;
    if ($score >= 6) $rating = 'BUY';
}
// Rule 4: Long-term underperformance (opportunity cost)
elseif ($holdingDays > 730 && $gainLossPercent < 10) {
    $reasons[] = "⚠️ Held {$holdingYears} years with only " . number_format($gainLossPercent, 1) . "% return – consider swapping (Adobe→Uber example)";
    $score -= 1;
    if ($score <= 4) $rating = 'SELL';
}
// Rule 5: Healthy gain with long hold - let it ride
elseif ($gainLossPercent > 30 && $holdingDays > 365) {
    $reasons[] = "✅ Up " . number_format($gainLossPercent, 1) . "% over {$holdingDays} days – tree is bearing excellent fruit!";
    $score += 0.5;
}
// Rule 6: Small loss, short hold - be patient
elseif ($gainLossPercent > -10 && $holdingDays < 180) {
    $reasons[] = "🟡 Down only " . number_format(abs($gainLossPercent), 1) . "% – too early to judge, give the tree time";
    $score += 0.5;
}
// Rule 7: Default - monitor
else {
    $reasons[] = "📊 Monitoring mode – check product quality and sales growth quarterly";
}

// Add video wisdom based on rating
if ($rating === 'SELL') {
    $reasons[] = "💡 The apple tree is showing signs of decline – sell on business deterioration, not price targets";
} elseif ($rating === 'BUY') {
    $reasons[] = "💡 This tree is still bearing excellent fruit – don't cap your harvest at 100 apples";
} else {
    $reasons[] = "🟡 Watch for: product quality, slowing sales, or better opportunities elsewhere";
}

// ============================================================
// STEP 4: Output based on mode
// ============================================================

// COMPACT MODE - Small widget
if ($isCompact) {
    $pnlText = ($gainLossPercent >= 0 ? '+' : '') . number_format($gainLossPercent, 1) . '%';
    $pnlColor = $gainLossPercent >= 0 ? '#10b981' : '#ef4444';
    $ratingText = $rating === 'SELL' ? 'SELL' : ($rating === 'BUY' ? 'HOLD' : 'HOLD');
    $ratingColor = $rating === 'SELL' ? '#ef4444' : ($rating === 'BUY' ? '#10b981' : '#f59e0b');
    
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
                transition: all 0.2s ease;
                text-decoration: none;
                box-shadow: 0 1px 3px rgba(0,0,0,0.3);
            }
            .widget:hover {
                background: #1a1f3a;
                transform: scale(1.02);
            }
            .symbol {
                color: #e0e0e0;
                font-weight: 600;
            }
            .rating {
                padding: 2px 6px;
                border-radius: 4px;
                font-weight: bold;
                font-size: 10px;
                background: <?php echo $ratingColor; ?>;
                color: <?php echo $rating === 'HOLD' ? '#000' : '#fff'; ?>;
            }
            .pnl {
                font-family: monospace;
                font-size: 10px;
                color: <?php echo $pnlColor; ?>;
            }
            .days {
                background: rgba(255,255,255,0.1);
                padding: 2px 5px;
                border-radius: 4px;
                font-size: 9px;
                color: #9ca3af;
                font-family: monospace;
            }
            a { text-decoration: none; }
        </style>
    </head>
    <body>
        <a href="?symbol=<?php echo urlencode($symbol); ?>" target="_blank" class="widget" title="Held for <?php echo $holdingDisplay; ?> | P&L: <?php echo $pnlText; ?> | Click for full analysis">
            <span class="symbol"><?php echo htmlspecialchars($symbol); ?></span>
            <span class="rating"><?php echo $ratingText; ?></span>
            <span class="pnl"><?php echo $pnlText; ?></span>
            <span class="days">📅 <?php echo $holdingDisplay; ?></span>
            <span style="font-size: 9px; color: #6b7280;">↗</span>
        </a>
    </body>
    </html>
    <?php
    exit;
}

// ============================================================
// FULL MODE - Complete analysis page (your original)
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($symbol); ?> – Should You Sell?</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
            min-height: 100vh;
            padding: 40px 20px;
            color: #e0e0e0;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .card {
            background: rgba(15, 20, 40, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 24px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .rating-badge {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 60px;
            font-size: 26px;
            font-weight: bold;
        }
        .rating-HOLD { background: #f59e0b; color: #000; }
        .rating-SELL { background: #ef4444; color: #fff; }
        .rating-BUY { background: #10b981; color: #fff; }
        .price { font-size: 52px; font-weight: bold; color: #fff; }
        .change { font-size: 22px; margin-left: 12px; }
        .positive { color: #10b981; }
        .negative { color: #ef4444; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 30px 0;
        }
        .info-card {
            background: rgba(0,0,0,0.4);
            padding: 16px;
            border-radius: 16px;
            border-left: 3px solid #f59e0b;
        }
        .info-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; }
        .info-value { font-size: 22px; font-weight: bold; margin-top: 6px; }
        .reason-list { list-style: none; margin-top: 16px; }
        .reason-list li { padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .chart-container { margin: 30px 0; }
        canvas { max-height: 400px; }
        .footer-note { text-align: center; font-size: 12px; color: #6b7280; margin-top: 24px; }
        h1 { font-size: 36px; margin-bottom: 8px; }
        .subtitle { color: #9ca3af; margin-bottom: 24px; }
        .holding-badge {
            display: inline-block;
            background: rgba(255,255,255,0.1);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            margin-left: 12px;
        }
        @media (max-width: 768px) {
            .price { font-size: 32px; }
            .rating-badge { font-size: 18px; padding: 8px 16px; }
            .info-value { font-size: 18px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <h1><?php echo htmlspecialchars($symbol); ?> 
                    <span class="holding-badge">📅 Held <?php echo $holdingDays; ?> days</span>
                </h1>
                <div class="subtitle"><?php echo htmlspecialchars($exchange); ?> | Bought: <?php echo htmlspecialchars($purchaseDate); ?></div>
            </div>
            <div class="rating-badge rating-<?php echo $rating; ?>">
                <?php echo $rating === 'BUY' ? '🍎 HOLD / ADD' : ($rating === 'SELL' ? '🔴 SELL' : '🟡 HOLD'); ?>
            </div>
        </div>

        <!-- Price Section -->
        <div style="margin: 24px 0; padding: 20px; background: rgba(0,0,0,0.3); border-radius: 20px;">
            <div>
                <span class="price"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($currentPrice, 4); ?></span>
                <span class="change <?php echo $dayChangePercent >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $dayChangePercent >= 0 ? '▲' : '▼'; ?>
                    <?php echo number_format(abs($dayChangePercent), 1); ?>% today
                </span>
            </div>
            <div style="font-size: 16px; color: #9ca3af; margin-top: 12px;">
                💰 Bought at: <strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($purchasePrice, 4); ?></strong> | 
                Total P&L: <strong class="<?php echo $gainLossPercent >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo htmlspecialchars($currency); ?> <?php echo number_format($gainLoss, 4); ?> (<?php echo $gainLossPercent >= 0 ? '+' : ''; ?><?php echo number_format($gainLossPercent, 1); ?>%)
                </strong>
            </div>
        </div>

        <!-- Key Info -->
        <div class="info-grid">
            <div class="info-card"><div class="info-label">Holding Period</div><div class="info-value"><?php echo $holdingDays; ?> days</div></div>
            <div class="info-card"><div class="info-label">Annualized Return</div><div class="info-value"><?php echo $holdingDays > 0 ? number_format(($gainLossPercent / $holdingDays) * 365, 1) : '0'; ?>%</div></div>
            <div class="info-card"><div class="info-label">Current Price</div><div class="info-value"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($currentPrice, 4); ?></div></div>
            <div class="info-card"><div class="info-label">Purchase Price</div><div class="info-value"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($purchasePrice, 4); ?></div></div>
        </div>

        <!-- Chart -->
        <div class="chart-container">
            <canvas id="priceChart"></canvas>
            <div style="text-align: center; margin-top: 12px; font-size: 12px; color: #9ca3af;">
                📍 <span style="color: #ef4444;">Red line = Your purchase price</span> | 
                🔴 <span style="color: #ef4444;">Red dot = Purchase date</span>
            </div>
        </div>

        <!-- Analysis -->
        <div style="margin-top: 24px;">
            <h3>📊 The Verdict – Apple Tree Test</h3>
            <ul class="reason-list">
                <?php foreach ($reasons as $reason): ?>
                    <li><?php echo htmlspecialchars($reason); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Video Wisdom -->
        <div style="margin-top: 24px; padding: 20px; background: rgba(0,0,0,0.4); border-radius: 16px; border-left: 4px solid #f59e0b;">
            <h4>🍎 The Apple Tree Principle</h4>
            <p style="margin-top: 10px; line-height: 1.6;">
                <?php if ($rating === 'SELL'): ?>
                    <strong>⚠️ This tree is showing signs of decline</strong> – like CD Projekt's Cyberpunk (80% crash).<br>
                    <strong>Sell when the BUSINESS deteriorates, not when you hit a price target.</strong>
                <?php elseif ($rating === 'BUY'): ?>
                    <strong>✅ This tree is still bearing excellent fruit</strong> – like The Witcher 3 after release.<br>
                    <strong>Don't cut it down just because you hit "100 apples."</strong> Let it compound.
                <?php else: ?>
                    <strong>🟡 Monitor this tree carefully</strong> – watch for product quality or a better opportunity (like swapping Adobe for Uber).<br>
                    <em>"Never cut down a good tree just because you hit your target."</em>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="footer-note">
        📈 Data: Yahoo Finance | 💡 Philosophy: <a target="_blank" href="https://m.youtube.com/watch?v=E2O2Qs6B6JA">Sell on business deterioration, not candles or moving averages</a>
    </div>
</div>

<script>
const historicalPrices = <?php echo json_encode($historicalPrices); ?>;
const historicalDates = <?php echo json_encode($historicalDates); ?>;
const purchasePrice = <?php echo $purchasePrice; ?>;
const purchasePointIndex = <?php echo json_encode($purchasePointIndex); ?>;

if (historicalPrices.length > 0 && historicalDates.length > 0) {
    const ctx = document.getElementById('priceChart').getContext('2d');
    
    const datasets = [
        {
            label: '<?php echo htmlspecialchars($symbol); ?> Price',
            data: historicalPrices,
            borderColor: '#f59e0b',
            backgroundColor: 'rgba(245, 158, 11, 0.1)',
            borderWidth: 2.5,
            fill: true,
            tension: 0.2,
            pointRadius: 0
        },
        {
            label: '💰 Purchase Price',
            data: Array(historicalPrices.length).fill(purchasePrice),
            borderColor: '#ef4444',
            borderWidth: 2,
            borderDash: [10, 6],
            fill: false,
            pointRadius: 0
        }
    ];
    
    // Add purchase point marker
    if (purchasePointIndex !== null && purchasePointIndex >= 0 && purchasePointIndex < historicalPrices.length) {
        const purchasePoints = Array(historicalPrices.length).fill(null);
        purchasePoints[purchasePointIndex] = historicalPrices[purchasePointIndex];
        
        datasets.push({
            label: '📍 You bought here',
            data: purchasePoints,
            backgroundColor: '#ef4444',
            borderColor: '#ffffff',
            pointRadius: 8,
            pointBorderWidth: 2,
            pointBackgroundColor: '#ef4444',
            type: 'scatter',
            showLine: false
        });
    }
    
    new Chart(ctx, {
        type: 'line',
        data: { labels: historicalDates, datasets: datasets },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { labels: { color: '#e0e0e0', usePointStyle: true } },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#e0e0e0' } },
                x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#e0e0e0', rotation: 45, maxRotation: 45 } }
            }
        }
    });
} else {
    document.getElementById('priceChart').innerHTML = '<p style="text-align:center; padding:40px;">📈 Chart loading... Try again in a moment.</p>';
}
</script>
</body>
</html>
