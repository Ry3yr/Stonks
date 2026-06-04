<?php
/**
 * Stock High/Low - WITH CHART AND DETAILED LIST
 * Compact: Click to open full chart in new tab
 * Full: Shows interactive chart + detailed peaks/valleys list
 * Usage: stock_filter.php?symbol=MSFT
 *        stock_filter.php?symbol=MSFT&compact
 */

if (isset($_GET['symbol']) && !empty($_GET['symbol'])) {
    $symbol = strtoupper(trim(htmlspecialchars($_GET['symbol'])));
    $isCompact = isset($_GET['compact']);
    
    $stockData = getYahooStockDataAllTime($symbol);
    
    if ($stockData && !isset($stockData['error']) && count($stockData['data'] ?? $stockData) > 0) {
        if ($isCompact) {
            displayCompactWidget($symbol, $stockData);
        } else {
            displayFullChartWithList($symbol, $stockData);
        }
    } else {
        echo "Error: {$symbol}";
    }
} else {
    echo "?symbol=REQUIRED";
}

/**
 * COMPACT WIDGET - Clickable, opens full view in new tab
 */
function displayCompactWidget($symbol, $stockData) {
    $data = $stockData['data'];
    
    if (empty($data)) {
        echo "NO DATA";
        return;
    }
    
    $prices = array_filter(array_column($data, 'close'), function($p) {
        return $p > 1;
    });
    
    $highest = max($prices);
    $lowest = min($prices);
    $current = end($data)['close'];
    $percentFromHigh = (($highest - $current) / $highest) * 100;
    
    $fullUrl = $_SERVER['SCRIPT_NAME'] . '?symbol=' . urlencode($symbol);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { background: transparent; display: inline-block; margin: 0; padding: 0; }
            .widget {
                width: 180px;
                height: 21px;
                background: #1a1a2e;
                border-radius: 6px;
                padding: 0 8px;
                font-family: 'Segoe UI', Arial, sans-serif;
                font-size: 10px;
                font-weight: bold;
                display: flex;
                align-items: center;
                justify-content: space-between;
                border: 1px solid #333;
                cursor: pointer;
                transition: all 0.2s ease;
                text-decoration: none;
                color: inherit;
            }
            .widget:hover {
                background: #2a2a3e;
                border-color: #ffd700;
                transform: scale(1.02);
            }
            .symbol { color: #ffd700; }
            .current { color: white; }
            .high { color: #51cf66; }
            .low { color: #ff6b6b; }
            .separator { color: #444; }
            .percent { color: #ff6b6b; font-size: 9px; }
            .click-hint { font-size: 7px; color: #555; text-align: center; margin-top: 2px; display: block; }
        </style>
    </head>
    <body>
        <a href="<?php echo $fullUrl; ?>" target="_blank" style="text-decoration: none;">
            <div class="widget" title="Click to open full chart in new tab">
                <span class="symbol"><?php echo $symbol; ?></span>
                <span class="current">$<?php echo number_format($current, 0); ?></span>
                <span class="high">H:$<?php echo number_format($highest, 0); ?></span>
                <span class="low">L:$<?php echo number_format($lowest, 0); ?></span>
                <span class="percent">▼<?php echo number_format($percentFromHigh, 0); ?>%</span>
            </div>
            <div class="click-hint">   </div>
        </a>
    </body>
    </html>
    <?php
}

/**
 * FULL CHART VIEW with Chart.js AND detailed list
 */
function displayFullChartWithList($symbol, $stockData) {
    $data = $stockData['data'];
    $currentPrice = end($data)['close'];
    
    // Get last 2 years of data for chart
    $twoYearsAgo = strtotime('-2 years');
    $chartData = array_filter($data, function($point) use ($twoYearsAgo) {
        return strtotime($point['date']) >= $twoYearsAgo;
    });
    $chartData = array_values($chartData);
    
    // Prepare data for Chart.js
    $dates = array_column($chartData, 'date');
    $prices = array_column($chartData, 'close');
    
    // Find peaks and valleys for chart
    $peaks = [];
    $valleys = [];
    $window = 10;
    
    for ($i = $window; $i < count($chartData) - $window; $i++) {
        $isPeak = true;
        $isValley = true;
        
        for ($j = 1; $j <= $window; $j++) {
            if ($chartData[$i]['close'] <= $chartData[$i - $j]['close']) $isPeak = false;
            if ($chartData[$i]['close'] >= $chartData[$i - $j]['close']) $isValley = false;
            if ($chartData[$i]['close'] <= $chartData[$i + $j]['close']) $isPeak = false;
            if ($chartData[$i]['close'] >= $chartData[$i + $j]['close']) $isValley = false;
        }
        
        if ($isPeak) {
            $peaks[] = ['x' => $i, 'y' => $chartData[$i]['close']];
        }
        if ($isValley) {
            $valleys[] = ['x' => $i, 'y' => $chartData[$i]['close']];
        }
    }
    
    // Create overlay areas
    $peakZones = [];
    foreach ($peaks as $peak) {
        $peakZones[] = ['from' => $peak['y'] * 0.95, 'to' => $peak['y'] * 1.05, 'peak' => $peak['y']];
    }
    
    $valleyZones = [];
    foreach ($valleys as $valley) {
        $valleyZones[] = ['from' => $valley['y'] * 0.95, 'to' => $valley['y'] * 1.05, 'valley' => $valley['y']];
    }
    
    // Find ALL peaks and valleys for the list (last 5 years)
    $fiveYearsAgo = strtotime('-5 years');
    $listData = array_filter($data, function($point) use ($fiveYearsAgo) {
        return strtotime($point['date']) >= $fiveYearsAgo;
    });
    $listData = array_values($listData);
    
    $listPeaks = [];
    $listValleys = [];
    
    for ($i = $window; $i < count($listData) - $window; $i++) {
        $isPeak = true;
        $isValley = true;
        
        for ($j = 1; $j <= $window; $j++) {
            if ($listData[$i]['close'] <= $listData[$i - $j]['close']) $isPeak = false;
            if ($listData[$i]['close'] >= $listData[$i - $j]['close']) $isValley = false;
            if ($listData[$i]['close'] <= $listData[$i + $j]['close']) $isPeak = false;
            if ($listData[$i]['close'] >= $listData[$i + $j]['close']) $isValley = false;
        }
        
        if ($isPeak) {
            $listPeaks[] = [
                'price' => round($listData[$i]['close'], 0),
                'year' => date('Y', strtotime($listData[$i]['date']))
            ];
        }
        if ($isValley) {
            $listValleys[] = [
                'price' => round($listData[$i]['close'], 0),
                'year' => date('Y', strtotime($listData[$i]['date']))
            ];
        }
    }
    
    // Deduplicate list
    $uniquePeaks = [];
    foreach ($listPeaks as $p) {
        if (!isset($uniquePeaks[$p['price']])) {
            $uniquePeaks[$p['price']] = $p;
        }
    }
    $uniqueValleys = [];
    foreach ($listValleys as $v) {
        if (!isset($uniqueValleys[$v['price']])) {
            $uniqueValleys[$v['price']] = $v;
        }
    }
    
    $listPeaks = array_values($uniquePeaks);
    $listValleys = array_values($uniqueValleys);
    rsort($listPeaks);
    sort($listValleys);
    $listPeaks = array_slice($listPeaks, 0, 8);
    $listValleys = array_slice($listValleys, 0, 8);
    
    // Get all-time stats
    $allPrices = array_filter(array_column($data, 'close'), function($p) { return $p > 1; });
    $allTimeHigh = max($allPrices);
    $allTimeLow = min($allPrices);
    
    $athDate = '';
    foreach ($data as $point) {
        if ($point['close'] == $allTimeHigh) {
            $athDate = $point['date'];
            break;
        }
    }
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title><?php echo $symbol; ?> Stock Analysis</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 100%);
                font-family: 'Segoe UI', Arial, sans-serif;
                padding: 20px;
                min-height: 100vh;
            }
            .container {
                max-width: 1400px;
                margin: 0 auto;
                background: #0a0a15;
                border-radius: 16px;
                padding: 20px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.4);
                border: 1px solid rgba(255,255,255,0.1);
            }
            .header {
                display: flex;
                justify-content: space-between;
                align-items: baseline;
                margin-bottom: 20px;
                flex-wrap: wrap;
                gap: 10px;
            }
            .title {
                font-size: 28px;
                font-weight: bold;
                color: #ffd700;
            }
            .current-price {
                font-size: 36px;
                font-weight: bold;
                color: white;
            }
            .stats {
                display: flex;
                gap: 20px;
                margin-bottom: 20px;
                flex-wrap: wrap;
            }
            .stat-card {
                background: rgba(255,255,255,0.05);
                padding: 12px 20px;
                border-radius: 8px;
                border-left: 3px solid;
            }
            .stat-card.high { border-left-color: #51cf66; }
            .stat-card.low { border-left-color: #ff6b6b; }
            .stat-label { font-size: 11px; color: #888; margin-bottom: 4px; }
            .stat-value { font-size: 22px; font-weight: bold; }
            .stat-card.high .stat-value { color: #51cf66; }
            .stat-card.low .stat-value { color: #ff6b6b; }
            .stat-date { font-size: 10px; color: #666; margin-top: 4px; }
            
            /* Two column layout */
            .two-columns {
                display: flex;
                gap: 20px;
                margin: 20px 0;
                flex-wrap: wrap;
            }
            .chart-column {
                flex: 2;
                min-width: 300px;
            }
            .list-column {
                flex: 1;
                min-width: 250px;
                background: rgba(255,255,255,0.03);
                border-radius: 12px;
                padding: 15px;
            }
            .chart-container {
                position: relative;
                height: 450px;
            }
            .list-section {
                margin-bottom: 20px;
            }
            .list-title {
                font-size: 14px;
                font-weight: bold;
                text-align: center;
                margin-bottom: 10px;
                padding: 6px;
                border-radius: 6px;
            }
            .list-title.peaks {
                background: rgba(81, 207, 102, 0.15);
                color: #51cf66;
            }
            .list-title.valleys {
                background: rgba(255, 107, 107, 0.15);
                color: #ff6b6b;
            }
            .list-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 11px;
                margin: 6px 0;
                padding: 6px 10px;
                background: rgba(255,255,255,0.03);
                border-radius: 6px;
            }
            .list-item.peaks { border-left: 3px solid #51cf66; }
            .list-item.valleys { border-left: 3px solid #ff6b6b; }
            .item-price {
                font-weight: bold;
                font-size: 13px;
            }
            .list-item.peaks .item-price { color: #51cf66; }
            .list-item.valleys .item-price { color: #ff6b6b; }
            .item-year {
                color: #ffd700;
                font-size: 10px;
                background: rgba(255,215,0,0.1);
                padding: 2px 6px;
                border-radius: 10px;
            }
            .item-range {
                color: #666;
                font-size: 9px;
            }
            .range-down { color: #51cf66; }
            .range-up { color: #ff6b6b; }
            
            .legend {
                display: flex;
                gap: 20px;
                justify-content: center;
                margin-top: 15px;
                font-size: 11px;
                flex-wrap: wrap;
            }
            .legend-item {
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .legend-color {
                width: 12px;
                height: 12px;
                border-radius: 2px;
            }
            .legend-color.peak { background: #51cf66; }
            .legend-color.valley { background: #ff6b6b; }
            .legend-color.zone-peak { background: rgba(81, 207, 102, 0.3); width: 20px; }
            .legend-color.zone-valley { background: rgba(255, 107, 107, 0.3); width: 20px; }
            .note {
                text-align: center;
                font-size: 10px;
                color: #555;
                margin-top: 15px;
                padding-top: 10px;
                border-top: 1px solid rgba(255,255,255,0.1);
            }
            .compact-link {
                text-align: right;
                margin-bottom: 10px;
            }
            .compact-link a {
                color: #ffd700;
                font-size: 11px;
                text-decoration: none;
                background: rgba(255,215,0,0.1);
                padding: 4px 10px;
                border-radius: 4px;
            }
            .compact-link a:hover {
                background: rgba(255,215,0,0.2);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="compact-link">
                <a href="?symbol=<?php echo $symbol; ?>&compact" target="_blank">📱 Open Compact Widget</a>
            </div>
            
            <div class="header">
                <div class="title">📊 <?php echo $symbol; ?></div>
                <div class="current-price">$<?php echo number_format($currentPrice, 2); ?></div>
            </div>
            
            <div class="stats">
                <div class="stat-card high">
                    <div class="stat-label">ALL-TIME HIGH</div>
                    <div class="stat-value">$<?php echo number_format($allTimeHigh, 2); ?></div>
                    <div class="stat-date"><?php echo $athDate; ?></div>
                </div>
                <div class="stat-card low">
                    <div class="stat-label">ALL-TIME LOW</div>
                    <div class="stat-value">$<?php echo number_format($allTimeLow, 2); ?></div>
                    <div class="stat-date">Since inception</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">DRAWDOWN FROM ATH</div>
                    <div class="stat-value" style="color: #ff6b6b;"><?php echo number_format((($allTimeHigh - $currentPrice) / $allTimeHigh) * 100, 1); ?>%</div>
                </div>
            </div>
            
            <div class="two-columns">
                <!-- Chart Column -->
                <div class="chart-column">
                    <div class="chart-container">
                        <canvas id="stockChart"></canvas>
                    </div>
                </div>
                
                <!-- List Column -->
                <div class="list-column">
                    <div class="list-section">
                        <div class="list-title peaks">🟢 PEAKS (Resistance) ±10%</div>
                        <?php foreach ($listPeaks as $peak): ?>
                        <div class="list-item peaks">
                            <span class="item-price">$<?php echo number_format($peak['price'], 0); ?></span>
                            <span class="item-year"><?php echo $peak['year']; ?></span>
                            <span class="item-range">
                                <span class="range-down">↓$<?php echo number_format($peak['price'] * 0.9, 0); ?></span>
                                <span class="range-up"> ↑$<?php echo number_format($peak['price'] * 1.1, 0); ?></span>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="list-section">
                        <div class="list-title valleys">🔴 VALLEYS (Support) ±10%</div>
                        <?php foreach ($listValleys as $valley): ?>
                        <div class="list-item valleys">
                            <span class="item-price">$<?php echo number_format($valley['price'], 0); ?></span>
                            <span class="item-year"><?php echo $valley['year']; ?></span>
                            <span class="item-range">
                                <span class="range-down">↓$<?php echo number_format($valley['price'] * 0.9, 0); ?></span>
                                <span class="range-up"> ↑$<?php echo number_format($valley['price'] * 1.1, 0); ?></span>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="legend">
                <div class="legend-item"><div class="legend-color" style="background: #4caf50;"></div><span>Price Line</span></div>
                <div class="legend-item"><div class="legend-color peak"></div><span>🟢 Peaks (Resistance)</span></div>
                <div class="legend-item"><div class="legend-color valley"></div><span>🔴 Valleys (Support)</span></div>
                <div class="legend-item"><div class="legend-color zone-peak"></div><span>🟢 Peak Zone (±5%)</span></div>
                <div class="legend-item"><div class="legend-color zone-valley"></div><span>🔴 Valley Zone (±5%)</span></div>
            </div>
            
            <div class="note">
                📅 Chart: Last 2 years | List: Last 5 years | 🟢 Green = Peaks (Resistance) with translucent zone | 🔴 Red = Valleys (Support) with translucent zone | Hover chart for details
            </div>
        </div>
        
        <script>
            const ctx = document.getElementById('stockChart').getContext('2d');
            
            const dates = <?php echo json_encode($dates); ?>;
            const prices = <?php echo json_encode($prices); ?>;
            const peaks = <?php echo json_encode($peaks); ?>;
            const valleys = <?php echo json_encode($valleys); ?>;
            const peakZones = <?php echo json_encode($peakZones); ?>;
            const valleyZones = <?php echo json_encode($valleyZones); ?>;
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [
                        {
                            label: '<?php echo $symbol; ?> Price',
                            data: prices,
                            borderColor: '#4caf50',
                            backgroundColor: 'rgba(76, 175, 80, 0.05)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.1,
                            pointRadius: 0,
                            pointHoverRadius: 5
                        },
                        {
                            label: 'Peaks (Resistance) 🟢',
                            data: peaks.map(p => ({ x: dates[p.x], y: p.y })),
                            type: 'scatter',
                            backgroundColor: '#51cf66',
                            borderColor: '#51cf66',
                            pointRadius: 6,
                            pointHoverRadius: 10,
                            pointBorderWidth: 2,
                            pointBorderColor: 'white',
                            showLine: false,
                            order: 1
                        },
                        {
                            label: 'Valleys (Support) 🔴',
                            data: valleys.map(v => ({ x: dates[v.x], y: v.y })),
                            type: 'scatter',
                            backgroundColor: '#ff6b6b',
                            borderColor: '#ff6b6b',
                            pointRadius: 6,
                            pointHoverRadius: 10,
                            pointBorderWidth: 2,
                            pointBorderColor: 'white',
                            showLine: false,
                            order: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.label === 'Peaks (Resistance) 🟢') {
                                        return `🟢 Peak (Resistance): $${context.parsed.y}`;
                                    }
                                    if (context.dataset.label === 'Valleys (Support) 🔴') {
                                        return `🔴 Valley (Support): $${context.parsed.y}`;
                                    }
                                    return `${context.dataset.label}: $${context.parsed.y}`;
                                }
                            }
                        },
                        legend: {
                            position: 'top',
                            labels: { color: '#ccc', font: { size: 11 } }
                        },
                        annotation: {
                            annotations: {
                                ...Object.fromEntries(peakZones.map((zone, i) => [
                                    `peakZone${i}`,
                                    {
                                        type: 'box',
                                        yMin: zone.from,
                                        yMax: zone.to,
                                        backgroundColor: 'rgba(81, 207, 102, 0.15)',
                                        borderWidth: 0
                                    }
                                ])),
                                ...Object.fromEntries(valleyZones.map((zone, i) => [
                                    `valleyZone${i}`,
                                    {
                                        type: 'box',
                                        yMin: zone.from,
                                        yMax: zone.to,
                                        backgroundColor: 'rgba(255, 107, 107, 0.15)',
                                        borderWidth: 0
                                    }
                                ]))
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#888', font: { size: 9 }, maxRotation: 45, minRotation: 45 },
                            grid: { color: 'rgba(255,255,255,0.05)' }
                        },
                        y: {
                            ticks: { color: '#ccc', font: { size: 10 }, callback: function(v) { return '$' + v; } },
                            grid: { color: 'rgba(255,255,255,0.05)' },
                            title: { display: true, text: 'Price (USD)', color: '#888', font: { size: 10 } }
                        }
                    },
                    interaction: { intersect: false, mode: 'index' }
                }
            });
        </script>
    </body>
    </html>
    <?php
}

function getYahooStockDataAllTime($symbol) {
    $startDate = 0;
    $endDate = time();
    
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}";
    $url .= "?period1={$startDate}&period2={$endDate}&interval=1d";
    $url .= "&events=capitalGains|div|split&includeAdjustedClose=false";
    
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
        return ['error' => "HTTP Error"];
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['chart']['result'][0])) {
        return ['error' => "Invalid response"];
    }
    
    $result = $data['chart']['result'][0];
    $timestamp = $result['timestamp'] ?? [];
    $quote = $result['indicators']['quote'][0] ?? [];
    
    $stockData = [];
    for ($i = 0; $i < count($timestamp); $i++) {
        if (isset($quote['close'][$i]) && $quote['close'][$i] !== null && $quote['close'][$i] > 1) {
            $stockData[] = [
                'date' => date('Y-m-d', $timestamp[$i]),
                'close' => round($quote['close'][$i], 2)
            ];
        }
    }
    
    return ['data' => $stockData];
}
?>