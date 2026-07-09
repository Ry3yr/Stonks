<?php
function getPremarketActivity() {
    $url = "https://scanner.tradingview.com/america/scan";
    
    $payload = json_encode([
        "columns" => [
            "name", 
            "close", 
            "volume", 
            "premarket_volume",
            "premarket_change",
            "premarket_change_abs",
            "market_cap_basic"
        ],
        "filter" => [
            ["left" => "exchange", "operation" => "in_range", "right" => ["AMEX", "NASDAQ", "NYSE"]]
        ],
        "options" => ["lang" => "en"],
        "range" => [0, 50],
        "sort" => ["sortBy" => "premarket_volume", "sortOrder" => "desc"]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ["error" => "CURL Error: " . $error];
    }

    $data = json_decode($response, true);
    return $data;
}

// Fetch the data
$result = getPremarketActivity();

// Check if we have valid data
if (isset($result['error'])) {
    $error = $result['error'];
    $stocks = [];
} elseif (isset($result['data']) && is_array($result['data'])) {
    $stocks = [];
    foreach ($result['data'] as $item) {
        $d = $item['d'];
        $stocks[] = [
            'ticker' => $d[0] ?? 'N/A',
            'price' => $d[1] ?? 0,
            'volume' => $d[2] ?? 0,
            'premarket_volume' => $d[3] ?? 0,
            'premarket_change' => $d[4] ?? 0,
            'premarket_change_abs' => $d[5] ?? 0,
            'market_cap' => $d[6] ?? 0
        ];
    }
} else {
    $stocks = [];
    $error = "No data received from API";
}

// Handle sorting
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'premarket_volume';
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'asc' : 'desc';

// Map sort parameter to array key
$sortMap = [
    'ticker' => 'ticker',
    'price' => 'price',
    'premarket_volume' => 'premarket_volume',
    'premarket_change' => 'premarket_change',
    'premarket_change_abs' => 'premarket_change_abs'
];

$sortKey = isset($sortMap[$sortColumn]) ? $sortMap[$sortColumn] : 'premarket_volume';

// Sort the array
usort($stocks, function($a, $b) use ($sortKey, $sortOrder) {
    if ($a[$sortKey] == $b[$sortKey]) {
        return 0;
    }
    $result = $a[$sortKey] < $b[$sortKey] ? -1 : 1;
    return $sortOrder === 'asc' ? $result : -$result;
});

// Get next sort order for each column
function getNextOrder($column) {
    $currentSort = isset($_GET['sort']) ? $_GET['sort'] : '';
    $currentOrder = isset($_GET['order']) ? $_GET['order'] : 'desc';
    
    if ($currentSort === $column) {
        return $currentOrder === 'desc' ? 'asc' : 'desc';
    }
    return 'desc';
}

function getSortIcon($column) {
    $currentSort = isset($_GET['sort']) ? $_GET['sort'] : '';
    $currentOrder = isset($_GET['order']) ? $_GET['order'] : 'desc';
    
    if ($currentSort === $column) {
        return $currentOrder === 'desc' ? ' ▼' : ' ▲';
    }
    return '';
}

// Helper to format change
function formatChange($value) {
    $symbol = $value >= 0 ? '+' : '';
    return $symbol . number_format($value, 2) . '%';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premarket Activity Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0a0a;
            color: #e0e0e0;
            padding: 30px;
            min-height: 100vh;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: #1a1a1a;
            border-radius: 15px;
            padding: 30px;
            border: 1px solid #ffd700;
            box-shadow: 0 0 40px rgba(255, 215, 0, 0.1);
        }
        h1 {
            text-align: center;
            font-size: 42px;
            font-weight: 700;
            color: #ffd700;
            text-shadow: 0 0 30px rgba(255, 215, 0, 0.3);
            margin-bottom: 5px;
            letter-spacing: 2px;
        }
        .subtitle {
            text-align: center;
            color: #b8960f;
            font-size: 14px;
            margin-bottom: 30px;
            letter-spacing: 1px;
        }
        .stats-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #2a2a2a;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid #ffd70033;
            flex-wrap: wrap;
            gap: 10px;
        }
        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .stat-label {
            color: #999;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .stat-value {
            color: #ffd700;
            font-weight: 600;
            font-size: 16px;
        }
        .stat-value.green { color: #00ff88; }
        .stat-value.red { color: #ff4444; }
        .table-wrapper {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid #ffd70033;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        thead {
            background: linear-gradient(135deg, #2a1f00, #1a1000);
        }
        th {
            padding: 16px 20px;
            text-align: left;
            color: #ffd700;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 12px;
            border-bottom: 2px solid #ffd700;
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
            transition: all 0.3s ease;
            position: relative;
        }
        th:hover {
            background: #3a2a0a;
            color: #fff;
        }
        th a {
            color: #ffd700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        th a:hover {
            color: #fff;
        }
        td {
            padding: 14px 20px;
            border-bottom: 1px solid #333;
            transition: all 0.3s ease;
        }
        tr:hover td {
            background: #2a2a2a;
        }
        tr:last-child td {
            border-bottom: none;
        }
        .ticker-cell a {
            color: #ffd700;
            text-decoration: none;
            font-weight: 700;
            font-size: 15px;
            transition: all 0.3s;
            display: inline-block;
        }
        .ticker-cell a:hover {
            color: #fff;
            text-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
            transform: scale(1.05);
        }
        .volume-cell {
            font-weight: 600;
            color: #ffd700;
            font-family: 'Courier New', monospace;
        }
        .change-positive {
            color: #00ff88;
            font-weight: 600;
        }
        .change-negative {
            color: #ff4444;
            font-weight: 600;
        }
        .rank-badge {
            display: inline-block;
            background: #ffd700;
            color: #0a0a0a;
            font-weight: 700;
            font-size: 11px;
            padding: 2px 10px;
            border-radius: 20px;
            min-width: 30px;
            text-align: center;
        }
        .error-box {
            background: #2a0a0a;
            border: 1px solid #ff4444;
            color: #ff6666;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }
        .sort-indicator {
            display: inline-block;
            margin-left: 5px;
            font-size: 11px;
        }
        @media (max-width: 768px) {
            body { padding: 15px; }
            .container { padding: 15px; }
            h1 { font-size: 28px; }
            th, td { padding: 10px 12px; font-size: 12px; }
            .stats-bar { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚡ PREMARKET ACTIVITY</h1>
        <div class="subtitle">MOST ACTIVE STOCKS • CLICK HEADERS TO SORT</div>

        <?php if (isset($error) && !empty($stocks)): ?>
            <div class="error-box">⚠️ Warning: <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (empty($stocks) && isset($error)): ?>
            <div class="error-box">❌ Error: <?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <div class="stats-bar">
                <div class="stat-item">
                    <span class="stat-label">📊 Total Stocks</span>
                    <span class="stat-value"><?php echo count($stocks); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">🟢 Gainers</span>
                    <span class="stat-value green"><?php echo count(array_filter($stocks, function($s) { return $s['premarket_change'] > 0; })); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">🔴 Losers</span>
                    <span class="stat-value red"><?php echo count(array_filter($stocks, function($s) { return $s['premarket_change'] < 0; })); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">📈 Top Gain</span>
                    <span class="stat-value green"><?php 
                        $max = max(array_column($stocks, 'premarket_change'));
                        echo number_format($max, 2) . '%';
                    ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">📉 Top Loss</span>
                    <span class="stat-value red"><?php 
                        $min = min(array_column($stocks, 'premarket_change'));
                        echo number_format($min, 2) . '%';
                    ?></span>
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="cursor:default;">#</th>
                            <th>
                                <a href="?sort=ticker&order=<?php echo getNextOrder('ticker'); ?>">
                                    Ticker<span class="sort-indicator"><?php echo getSortIcon('ticker'); ?></span>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=price&order=<?php echo getNextOrder('price'); ?>">
                                    Price<span class="sort-indicator"><?php echo getSortIcon('price'); ?></span>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=premarket_volume&order=<?php echo getNextOrder('premarket_volume'); ?>">
                                    Premarket Volume<span class="sort-indicator"><?php echo getSortIcon('premarket_volume'); ?></span>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=premarket_change&order=<?php echo getNextOrder('premarket_change'); ?>">
                                    Change %<span class="sort-indicator"><?php echo getSortIcon('premarket_change'); ?></span>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=premarket_change_abs&order=<?php echo getNextOrder('premarket_change_abs'); ?>">
                                    Change (Abs)<span class="sort-indicator"><?php echo getSortIcon('premarket_change_abs'); ?></span>
                                </a>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        $base_url = "https://alceawis.de/other/extra/fetchdata/2026-05-13-Finance/2026-05-13-Stocks/process.php?symbol=";
                        foreach ($stocks as $stock): 
                            $change = $stock['premarket_change'];
                            $changeClass = $change >= 0 ? 'change-positive' : 'change-negative';
                        ?>
                        <tr>
                            <td><span class="rank-badge"><?php echo $rank++; ?></span></td>
                            <td class="ticker-cell">
                                <a href="<?php echo $base_url . urlencode($stock['ticker']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($stock['ticker']); ?>
                                </a>
                            </td>
                            <td>$<?php echo number_format($stock['price'], 2); ?></td>
                            <td class="volume-cell"><?php echo number_format($stock['premarket_volume']); ?></td>
                            <td class="<?php echo $changeClass; ?>">
                                <?php echo formatChange($change); ?>
                            </td>
                            <td><?php echo number_format($stock['premarket_change_abs'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>