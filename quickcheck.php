<a href="javascript:var spoiler = document.getElementById('pastplaying'); spoiler.open = !spoiler.open;" style="opacity: 0">LastPlaying</a><br><br>



<a target="_blank" href="https://alceawis.de/other/extra/scripts/fakesocialmedia/commentload.html?number=8000&text=How%20to%20set%20%23stock%20sell%20estimate%3A%0D%0A0)%20Nominal%20(how%20many%20share">SET Stock Sell UPPER Limit</a> [[<a target="_blank" href="stockstatsrender.html">(I)nfo</a>]]]

<!---All Bought Stocks-->
<details id="pastplaying">
<summary style="display: none;"></summary>
<div class="custom-summary" onclick="toggleDetails()"></div>
   <script>
        function stringToArrayBuffer(str) {
            var encoder = new TextEncoder();
            return encoder.encode(str);
        }
        function arrayBufferToString(buffer) {
            var decoder = new TextDecoder();
            return decoder.decode(buffer);
        }
        async function decryptAES(ciphertext, key, iv) {
            var decrypted = await crypto.subtle.decrypt(
                {
                    name: "AES-CBC",
                    iv: iv
                },
                key,
                ciphertext
            );
            return new Uint8Array(decrypted);
        }
        async function handleDecrypt(event) {
            event.preventDefault();
            var password = document.getElementById("password").value;
            var encryptedHex = document.getElementById("encryptedOutput").value;
            var passwordBuffer = stringToArrayBuffer(password);
            var keyMaterial = await crypto.subtle.importKey(
                "raw",
                passwordBuffer,
                { name: "PBKDF2" },
                false,
                ["deriveKey"]
            );
            var aesKey = await crypto.subtle.deriveKey(
                {
                    name: "PBKDF2",
                    salt: new Uint8Array(16),
                    iterations: 100000,
                    hash: "SHA-256"
                },
                keyMaterial,
                { name: "AES-CBC", length: 256 },
                true,
                ["encrypt", "decrypt"]
            );
            var ivHex = encryptedHex.substr(0, 32);
            var ciphertextHex = encryptedHex.substr(32);
            var ivBytes = new Uint8Array(ivHex.match(/.{1,2}/g).map((byte) => parseInt(byte, 16)));
            var ciphertextBytes = new Uint8Array(ciphertextHex.match(/.{1,2}/g).map((byte) => parseInt(byte, 16)));
            var decryptedBytes = await decryptAES(ciphertextBytes, aesKey, ivBytes);
            var decryptedHTML = arrayBufferToString(decryptedBytes);
            document.getElementById("decryptedOutput").innerHTML = decryptedHTML;
        }
    </script>
    <form onsubmit="handleDecrypt(event)">
        <input type="password" id="password" required>
        <textarea id="encryptedOutput" rows="10" cols="50" style="display: none;">0300058a3fd3ec51410edd6bc648e1a52b7737acc16b67b12cd23a78f75526055c52e3fba45c3d4ed6084cd463fafe6eb2dbc9b4ab3660931b0eb03028ad3f48cd89079818a3d0f0ef47edc604425eb86125f6f7b9614c13688e2263dc0cd322a451bfed54267db05b5307c7d595557e0db71f3c762617ecb123087f2dfcc6428923407e532b4d746efc5e2d6745c6f4
</textarea>
        <input type="submit" value="All">
</form><div><div id="decryptedOutput"></div></div>
</details>

<?php

/**
 * Script to fetch stocks from local stocks.json file,
 * calculate total gain/loss PER LINE (shares × gain per share),
 * and display results in HTML table.
 */

// Configuration - use LOCAL file, not URL
$stocksJsonFile = 'stocks.json';

// --- Check if info parameter exists ---
$showRatingColumn = isset($_GET['info']);

// --- Function to read local JSON file ---
function readLocalJson($filename) {
    if (!file_exists($filename)) {
        return ['error' => "File not found: $filename"];
    }
    
    $content = file_get_contents($filename);
    if ($content === false) {
        return ['error' => "Could not read file: $filename"];
    }
    
    return ['content' => $content];
}

// --- Get current price by symbol using CHART endpoint ---
function getCurrentPrice($symbol) {
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!$response) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['chart']['result'][0]['meta']['regularMarketPrice'])) {
        return [
            'price' => (float)$data['chart']['result'][0]['meta']['regularMarketPrice'],
            'symbol' => $data['chart']['result'][0]['meta']['symbol'],
            'currency' => $data['chart']['result'][0]['meta']['currency'] ?? 'USD'
        ];
    }
    
    return null;
}

// --- Clean symbol (remove .F, .DE, .XE, .TO, .L, etc.) ---
function cleanSymbol($symbol) {
    $dotPos = strpos($symbol, '.');
    if ($dotPos !== false) {
        return substr($symbol, 0, $dotPos);
    }
    return $symbol;
}

// --- Get price with fallback: Try original FIRST, then cleaned ---
function getPriceWithFallback($originalSymbol) {
    // Step 1: Try original symbol as-is (with .TO, .L, .AX, etc.)
    $quote = getCurrentPrice($originalSymbol);
    
    if ($quote) {
        return $quote;
    }
    
    // Step 2: If original fails, try cleaned symbol (remove suffix)
    $cleanedSymbol = cleanSymbol($originalSymbol);
    if ($cleanedSymbol !== $originalSymbol) {
        $quote = getCurrentPrice($cleanedSymbol);
        if ($quote) {
            return $quote;
        }
    }
    
    return null;
}

// --- Read local stocks.json ---
echo "Reading stocks.json from local directory...\n";
$result = readLocalJson($stocksJsonFile);

if (isset($result['error'])) {
    die("Error: " . $result['error'] . "\n");
}

$stocks = json_decode($result['content'], true);
if (!$stocks) {
    die("Invalid JSON in stocks.json\n");
}

// Filter: ONLY stocks with ISIN AND nrbght
$stocksWithIsin = array_filter($stocks, function($s) {
    return isset($s['isin']) && !empty($s['isin']) && isset($s['nrbght']) && $s['nrbght'] > 0;
});

echo "Found " . count($stocksWithIsin) . " stocks with ISIN and share counts\n";

// Process each stock
$risen = [];
$fallen = [];
$same = [];
$errors = [];
$totalInvested = 0;
$totalCurrentValue = 0;
$totalWinsSum = 0;    // NEW: Sum of all winning gains
$totalLossesSum = 0;  // NEW: Sum of all losing losses (absolute value for display)

foreach ($stocksWithIsin as $stock) {
    $name = $stock['stock'];  // Keep original with suffix!
    $isin = $stock['isin'];
    $oldPrice = (float)$stock['price'];
    $shares = (int)$stock['nrbght'];
    $depot = $stock['depot'] ?? 'unknown';
    
    // Calculate invested amount
    $invested = $oldPrice * $shares;
    $totalInvested += $invested;
    
    // Get price with fallback: try original FIRST, then cleaned
    $quote = getPriceWithFallback($name);
    
    if (!$quote) {
        $errors[] = [
            'name' => $name,
            'original_symbol' => $name,
            'isin' => $isin,
            'shares' => $shares,
            'old' => $oldPrice,
            'invested' => $invested,
            'depot' => $depot,
            'error' => 'Could not fetch price'
        ];
        continue;
    }
    
    $currentPrice = $quote['price'];
    $usedSymbol = $quote['symbol'];  // This might be cleaned version
    
    // Calculate position values
    $currentTotal = $currentPrice * $shares;
    $totalChange = $currentTotal - $invested;
    $changePerShare = $currentPrice - $oldPrice;
    $percentChange = ($changePerShare / $oldPrice) * 100;
    
    $totalCurrentValue += $currentTotal;
    
    // NEW: Add to totals for wins/losses
    if ($totalChange > 0) {
        $totalWinsSum += $totalChange;
    } elseif ($totalChange < 0) {
        $totalLossesSum += abs($totalChange);  // Store as positive number for display
    }
    
    $result = [
        'name' => $name,
        'original_symbol' => $name,  // Store original for iframe!
        'lookup_symbol' => $usedSymbol,  // What Yahoo actually used
        'isin' => $isin,
        'shares' => $shares,
        'old' => $oldPrice,
        'current' => $currentPrice,
        'invested' => $invested,
        'currentTotal' => $currentTotal,
        'totalChange' => $totalChange,
        'changePerShare' => $changePerShare,
        'percent' => $percentChange,
        'depot' => $depot
    ];
    
    if ($currentPrice > $oldPrice) {
        $risen[] = $result;
    } elseif ($currentPrice < $oldPrice) {
        $fallen[] = $result;
    } else {
        $same[] = $result;
    }
}

$totalGainLoss = $totalCurrentValue - $totalInvested;
$totalReturnPercent = ($totalInvested > 0) ? ($totalGainLoss / $totalInvested) * 100 : 0;

// --- Output HTML ---
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio Performance - Individual Position Gains</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 1400px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { margin: 0 0 8px 0; color: #1a1a2e; }
        .subtitle { color: #666; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #eee; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 13px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { background: #f8f9fa; font-weight: 600; }
        .number { text-align: right; }
        .positive { color: #00a86b; font-weight: 600; }
        .negative { color: #e31b23; font-weight: 600; }
        .stats { display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap; }
        .stat-card { background: #f8f9fa; border-radius: 12px; padding: 16px 24px; text-align: center; flex: 1; min-width: 140px; }
        .stat-number { font-size: 28px; font-weight: 700; }
        .stat-label { color: #666; font-size: 13px; margin-top: 4px; }
        .stat-rose .stat-number { color: #00a86b; }
        .stat-fell .stat-number { color: #e31b23; }
        details { margin-top: 24px; padding: 16px; background: #fafafa; border-radius: 12px; }
        summary { cursor: pointer; font-weight: 600; color: #555; padding: 8px; border-radius: 8px; }
        summary:hover { background: #f0f0f0; }
        .symbol { font-family: monospace; font-weight: 600; }
        .isin-code { font-family: monospace; font-size: 11px; color: #888; }
        .footer { margin-top: 24px; padding-top: 16px; text-align: center; color: #999; font-size: 12px; border-top: 1px solid #eee; }
        .total-row { background: #f0f0f0; font-weight: 700; border-top: 2px solid #ccc; }
        .total-row td { padding: 12px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-ingdiba { background: #e3f2fd; color: #1565c0; }
        .badge-comdirect { background: #e8f5e9; color: #2e7d32; }
        h2, h3 { margin-top: 24px; margin-bottom: 12px; }
        .highlight { background-color: #fff8e1; }
        .rating-iframe {
            width: 280px;
            height: 80px;
            border: none;
            background: transparent;
        }
        .summary-badge {
            display: inline-block;
            margin-left: 15px;
            font-size: 0.9rem;
            font-weight: normal;
            background: #f0f0f0;
            padding: 4px 12px;
            border-radius: 20px;
        }
        .summary-badge.win {
            background: #e6f7ec;
            color: #00a86b;
        }
        .summary-badge.loss {
            background: #fee9e8;
            color: #e31b23;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>📊 Portfolio Performance</h1>
    <div class="subtitle">Stocks with ISIN codes | Local file: stocks.json | Lookup: Try original symbol first, then cleaned (remove suffix)</div>
    
    <!-- Portfolio Summary Cards -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-number">$<?= number_format($totalInvested, 2) ?></div>
            <div class="stat-label">Total Invested</div>
        </div>
        <div class="stat-card <?= $totalGainLoss >= 0 ? 'stat-rose' : 'stat-fell' ?>">
            <div class="stat-number"><?= $totalGainLoss >= 0 ? '+' : '' ?>$<?= number_format(abs($totalGainLoss), 2) ?></div>
            <div class="stat-label">Total <?= $totalGainLoss >= 0 ? 'Gain' : 'Loss' ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-number">$<?= number_format($totalCurrentValue, 2) ?></div>
            <div class="stat-label">Current Value</div>
        </div>
        <div class="stat-card <?= $totalReturnPercent >= 0 ? 'stat-rose' : 'stat-fell' ?>">
            <div class="stat-number"><?= $totalReturnPercent >= 0 ? '+' : '' ?><?= number_format($totalReturnPercent, 2) ?>%</div>
            <div class="stat-label">Total Return</div>
        </div>
    </div>
    
    <!-- RISEN STOCKS (always visible) with total wins sum -->
    <?php if (!empty($risen)): ?>
        <h2>
            📈 Winning Positions (Price Increased) 
            <span class="summary-badge win">🏆 Total Gains: +$<?= number_format($totalWinsSum, 2) ?></span>
        </h2>
        <table>
            <thead>
                <tr>
                    <th>Stock / Symbol</th>
                    <th>ISIN</th>
                    <th>Depot</th>
                    <th class="number">Shares ×</th>
                    <th class="number">Buy Price</th>
                    <th class="number">Current</th>
                    <th class="number">Gain/Share</th>
                    <th class="number">= Total Gain</th>
                    <th class="number">Return %</th>
                    <?php if ($showRatingColumn): ?><th>Rating</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($risen as $r): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['name']) ?></strong><br><span class="symbol"><?= htmlspecialchars($r['lookup_symbol']) ?></span></td>
                    <td class="isin-code"><?= htmlspecialchars($r['isin']) ?></td>
                    <td><span class="badge badge-<?= htmlspecialchars($r['depot']) ?>"><?= htmlspecialchars($r['depot']) ?></span></td>
                    <td class="number"><strong><?= number_format($r['shares']) ?></strong> ×</td>
                    <td class="number">$<?= number_format($r['old'], 2) ?></td>
                    <td class="number">$<?= number_format($r['current'], 2) ?></td>
                    <td class="number positive">+$<?= number_format($r['changePerShare'], 2) ?></td>
                    <td class="number positive"><strong>= +$<?= number_format($r['totalChange'], 2) ?></strong></td>
                    <td class="number positive">+<?= number_format($r['percent'], 2) ?>%</td>
                    <?php if ($showRatingColumn): ?>
                    <td><iframe class="rating-iframe" src="stock_rating.php?symbol=<?= urlencode($r['original_symbol']) ?>&compact" frameborder="0"></iframe></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <!-- FALLEN STOCKS (hidden in spoiler) with total losses sum -->
    <?php if (!empty($fallen)): ?>
        <details>
            <summary>
                📉 Show Losing Positions (<?= count($fallen) ?> items)
                <span class="summary-badge loss">💸 Total Losses: -$<?= number_format($totalLossesSum, 2) ?></span>
            </summary>
            <h3>📉 Losing Positions (Price Decreased)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Stock / Symbol</th>
                        <th>ISIN</th>
                        <th>Depot</th>
                        <th class="number">Shares ×</th>
                        <th class="number">Buy Price</th>
                        <th class="number">Current</th>
                        <th class="number">Loss/Share</th>
                        <th class="number">= Total Loss</th>
                        <th class="number">Return %</th>
                        <?php if ($showRatingColumn): ?><th>Rating</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($fallen as $f): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($f['name']) ?></strong><br><span class="symbol"><?= htmlspecialchars($f['lookup_symbol']) ?></span></td>
                        <td class="isin-code"><?= htmlspecialchars($f['isin']) ?></td>
                        <td><span class="badge badge-<?= htmlspecialchars($f['depot']) ?>"><?= htmlspecialchars($f['depot']) ?></span></td>
                        <td class="number"><strong><?= number_format($f['shares']) ?></strong> ×</td>
                        <td class="number">$<?= number_format($f['old'], 2) ?></td>
                        <td class="number">$<?= number_format($f['current'], 2) ?></td>
                        <td class="number negative">-$<?= number_format(abs($f['changePerShare']), 2) ?></td>
                        <td class="number negative"><strong>= -$<?= number_format(abs($f['totalChange']), 2) ?></strong></td>
                        <td class="number negative"><?= number_format($f['percent'], 2) ?>%</td>
                        <?php if ($showRatingColumn): ?>
                        <td><iframe class="rating-iframe" src="stock_rating.php?symbol=<?= urlencode($f['original_symbol']) ?>&compact" frameborder="0"></iframe></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </details>
    <?php endif; ?>
    
    <!-- ERRORS (hidden in spoiler) -->
    <?php if (!empty($errors)): ?>
        <details>
            <summary>⚠️ Show Lookup Errors (<?= count($errors) ?> items)</summary>
            <h3>⚠️ Lookup Errors</h3>
            <table>
                <thead><tr><th>Stock</th><th>ISIN</th><th>Depot</th><th class="number">Shares</th><th class="number">Invested</th><th>Error</th><?php if ($showRatingColumn): ?><th>Rating</th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($errors as $e): ?>
                    <tr class="highlight">
                        <td><strong><?= htmlspecialchars($e['name']) ?></strong></td>
                        <td class="isin-code"><?= htmlspecialchars($e['isin']) ?></td>
                        <td><span class="badge badge-<?= htmlspecialchars($e['depot']) ?>"><?= htmlspecialchars($e['depot']) ?></span></td>
                        <td class="number"><?= number_format($e['shares']) ?></td>
                        <td class="number">$<?= number_format($e['invested'], 2) ?></td>
                        <td class="negative"><?= htmlspecialchars($e['error']) ?></td>
                        <?php if ($showRatingColumn): ?><td>—</td><?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </details>
    <?php endif; ?>
    
    <!-- TOTAL PORTFOLIO ROW -->
    <table class="total-row">
        <tr>
            <td colspan="<?= $showRatingColumn ? '8' : '7' ?>"><strong>TOTAL PORTFOLIO</strong> (Sum of all: Shares × Gain/Loss per share)</td>
            <td class="number <?= $totalGainLoss >= 0 ? 'positive' : 'negative' ?>">
                <strong><?= $totalGainLoss >= 0 ? '+' : '' ?>$<?= number_format(abs($totalGainLoss), 2) ?></strong>
            </td>
            <td class="number <?= $totalReturnPercent >= 0 ? 'positive' : 'negative' ?>">
                <strong><?= $totalReturnPercent >= 0 ? '+' : '' ?><?= number_format($totalReturnPercent, 2) ?>%</strong>
            </td>
            <?php if ($showRatingColumn): ?><td></td><?php endif; ?>
        </tr>
        <tr style="background: #e8f5e9;">
            <td colspan="<?= $showRatingColumn ? '8' : '7' ?>"><strong>BREAKDOWN:</strong></td>
            <td class="number"><strong>$<?= number_format($totalInvested, 2) ?></strong> invested</td>
            <td class="number"><strong>$<?= number_format($totalCurrentValue, 2) ?></strong> current</td>
            <?php if ($showRatingColumn): ?><td></td><?php endif; ?>
        </tr>
    </table>
    
    <div class="footer">
        <strong>Lookup logic: 1) Try original symbol (with suffix like .TO) → 2) Try cleaned symbol (without suffix)</strong><br>
        <strong>Formula: [Shares] × ([Current Price] - [Buy Price]) = Total Gain/Loss per position</strong><br>
        <strong>🏆 Total Wins: +$<?= number_format($totalWinsSum, 2) ?> &nbsp;|&nbsp; 💸 Total Losses: -$<?= number_format($totalLossesSum, 2) ?></strong><br>
        Data source: local stocks.json | Live prices: Yahoo Finance Chart API<br>
        Generated: <?= date('Y-m-d H:i:s') ?>
        <?php if ($showRatingColumn): ?><br><strong>ℹ️ Rating column active</strong> — embedded stock_rating.php?symbol={original_symbol}&compact (keeps .TO, .L, etc.)<?php endif; ?>
    </div>
</div>
</body>
</html>
