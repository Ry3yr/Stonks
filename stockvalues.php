<?php
if (ob_get_level()) ob_end_clean();
header('Content-Type: text/html; charset=utf-8');
header('X-Accel-Buffering: no');
?>
<meta charset="utf-8">
<script src="/jquery.min.js"></script>
<script src="stockattributesdisplay.js"></script>
<script type="text/javascript">
$(document).ready(function(){
    var navbarUrl = "list.html";
    $("#stocklist").load(navbarUrl)
        .fail(function() {
            console.error("Failed to load navbar from: " + navbarUrl);
            $("#ffnavbar").html("<p>Navigation failed to load. Please check console for errors.</p>");
        });
});
</script>
<div class="formClass">
    <div id="stocklist">
        <p>Loading navigation...</p>
    </div>
</div>

<a target="_blank" href="process.php">(+)</a><br><hr>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Portfolio - Live Values</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { margin-bottom: 10px; color: #1a1a2e; }
        .sub { color: #666; margin-bottom: 20px; }
        .highlights { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .winners-box, .losers-box { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .winners-box h2 { color: #28a745; border-bottom: 2px solid #28a745; padding-bottom: 10px; margin-bottom: 15px; }
        .losers-box h2 { color: #dc3545; border-bottom: 2px solid #dc3545; padding-bottom: 10px; margin-bottom: 15px; }
        .winner-item, .loser-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #eee; }
        .winner-change { color: #28a745; font-weight: bold; }
        .loser-change { color: #dc3545; font-weight: bold; }
        .no-data { color: #666; text-align: center; padding: 20px; }
        .all-stocks { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: bold; }
        .price-up { color: #28a745; font-weight: bold; }
        .price-down { color: #dc3545; font-weight: bold; }
        .price-neutral { color: #666; }
        .google-link { background: #4285f4; color: white; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; display: inline-block; }
        .google-link:hover { background: #3367d6; }
        .gray-buy-link { background: #6c757d; color: white; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; display: inline-block; margin-left: 5px; }
        .gray-buy-link:hover { background: #5a6268; }
        .delete-btn { background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; }
        .delete-btn:hover { background: #c82333; }
        .commit-btn { background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 10px; margin-left: 5px; }
        .commit-btn:hover { background: #1e7e34; }
        .commit-btn:disabled { background: #6c757d; cursor: not-allowed; }
        .refresh-btn { background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; margin-bottom: 20px; }
        .refresh-btn:hover { background: #0056b3; }
        footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; }
        .timestamp { font-size: 11px; color: #999; }
        .action-cell { white-space: nowrap; }
        .saved-cell { display: flex; flex-direction: column; }
        .saved-original { font-size: 10px; color: #999; }
        .exchange-badge { font-size: 10px; background: #e9ecef; padding: 2px 6px; border-radius: 10px; }
        .fetching { font-size: 10px; color: #ffc107; margin-left: 5px; }
        .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; }
        .stock-name-bold { font-weight: bold; color: #2c3e50; }
        .stock-with-data { background-color: #fff8e7; }
        .quantity-badge { background: #ff9800; color: white; font-size: 11px; padding: 2px 8px; border-radius: 20px; margin-left: 8px; display: inline-block; }
        .pl-positive { color: #28a745; font-weight: bold; }
        .pl-negative { color: #dc3545; font-weight: bold; }
        .isin-badge { font-size: 10px; color: #6c757d; margin-left: 8px; font-family: monospace; }
        .purchase-price { font-size: 11px; color: #856404; background: #fff3cd; padding: 2px 6px; border-radius: 4px; margin-top: 4px; display: inline-block; }
        .search-btn { background: #17a2b8; color: white; padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 11px; display: inline-block; }
        .search-btn:hover { background: #138496; }
        .depot-icon { width: 20px; height: 20px; border-radius: 50%; object-fit: cover; margin-left: 6px; vertical-align: middle; display: inline-block; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .stock-symbol-wrapper { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; }
        .trend-up { color: #28a745; font-weight: bold; }
        .trend-down { color: #dc3545; font-weight: bold; }
        .spark-wrapper { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; cursor: pointer; }
        .trend-cell { white-space: nowrap; }
    </style>
</head>
<body>
<div class="container">
    <h1>Stock Portfolio</h1>
    <div class="sub">Live prices vs saved values</div>
    
    <button class="refresh-btn" onclick="location.reload()">Refresh Live Prices</button>
    
    <?php
    // ============================================
    // CENTRALIZED CONFIGURATION (ADD NEW CURRENCIES HERE)
    // ============================================
    $CURRENCY_CONFIG = [
        'USD' => ['symbol' => '$', 'code' => 'USD', 'decimals' => 2],
        'EUR' => ['symbol' => '€', 'code' => 'EUR', 'decimals' => 2],
        'JPY' => ['symbol' => '¥', 'code' => 'JPY', 'decimals' => 0],
        'GBP' => ['symbol' => '£', 'code' => 'GBP', 'decimals' => 2],
        'HKD' => ['symbol' => 'HK$', 'code' => 'HKD', 'decimals' => 2],
        'CNY' => ['symbol' => 'CN¥', 'code' => 'CNY', 'decimals' => 2],
        'SEK' => ['symbol' => 'kr', 'code' => 'SEK', 'decimals' => 2],
        'BRL' => ['symbol' => 'R$', 'code' => 'BRL', 'decimals' => 2],
        'MXN' => ['symbol' => 'MX$', 'code' => 'MXN', 'decimals' => 2]
    ];
    
    $DEFAULT_RATES = [
        'USD' => 1, 'EUR' => 0.92, 'JPY' => 148.5, 'GBP' => 0.79, 'HKD' => 7.82,
        'CNY' => 7.24, 'SEK' => 10.45, 'MXN' => 16.80, 'BRL' => 5.80
    ];
    
    function curl_get($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
    
    function getExchangeRates() {
        global $DEFAULT_RATES;
        $rateUrl = "https://api.exchangerate-api.com/v4/latest/USD";
        $response = curl_get($rateUrl);
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['rates'])) {
                $rates = [];
                foreach (array_keys($GLOBALS['DEFAULT_RATES']) as $code) {
                    $rates[$code] = $data['rates'][$code] ?? $GLOBALS['DEFAULT_RATES'][$code];
                }
                return $rates;
            }
        }
        return $GLOBALS['DEFAULT_RATES'];
    }
    
    function getCurrencyInfo($currencyCode) {
        global $CURRENCY_CONFIG;
        $symbolMap = [
            '$' => 'USD', '€' => 'EUR', '¥' => 'JPY', '£' => 'GBP',
            'HK$' => 'HKD', 'CN¥' => 'CNY', 'kr' => 'SEK', 'MX$' => 'MXN'
        ];
        
        if (isset($symbolMap[$currencyCode])) {
            $currencyCode = $symbolMap[$currencyCode];
        }
        
        if (isset($CURRENCY_CONFIG[$currencyCode])) {
            return $CURRENCY_CONFIG[$currencyCode];
        }
        
        if ($currencyCode === 'kr') {
            return $CURRENCY_CONFIG['SEK'];
        }
        
        return ['symbol' => '$', 'code' => 'USD', 'decimals' => 2];
    }
    
    function convertCurrency($amount, $fromCurrency, $toCurrency, $exchangeRates) {
        $fromInfo = getCurrencyInfo($fromCurrency);
        $toInfo = getCurrencyInfo($toCurrency);
        
        $amountUSD = $amount / $exchangeRates[$fromInfo['code']];
        $result = $amountUSD * $exchangeRates[$toInfo['code']];
        return $result;
    }
    
    function formatCurrency($amount, $currency, $exchangeRates) {
        $info = getCurrencyInfo($currency);
        $symbol = $info['symbol'];
        $decimals = $info['decimals'];
        return $symbol . number_format($amount, $decimals);
    }
    
    function getLivePriceWithCurrency($symbol, $exchangeRates) {
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol);
        $response = curl_get($url);
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['chart']['result'][0]['meta']['regularMarketPrice'])) {
                $price = $data['chart']['result'][0]['meta']['regularMarketPrice'];
                $currency = strtoupper($data['chart']['result'][0]['meta']['currency'] ?? 'USD');
                
                $priceUSD = $price;
                if ($currency !== 'USD' && isset($exchangeRates[$currency]) && $exchangeRates[$currency] > 0) {
                    $priceUSD = $price / $exchangeRates[$currency];
                } elseif ($currency !== 'USD' && !isset($exchangeRates[$currency])) {
                    error_log("Unknown currency: $currency for symbol $symbol");
                    $priceUSD = $price;
                    $currency = 'USD';
                }
                
                return [
                    'price_usd' => $priceUSD,
                    'original_currency' => $currency,
                    'original_price' => $price
                ];
            }
        }
        return null;
    }
    
    function get7DayTrend($symbol, $exchangeRates) {
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol) . "?range=7d&interval=1d";
        $response = curl_get($url);
        
        if (!$response) return null;
        
        $data = json_decode($response, true);
        if (!isset($data['chart']['result'][0])) return null;
        
        $result = $data['chart']['result'][0];
        $closes = $result['indicators']['quote'][0]['close'] ?? [];
        $validCloses = array_values(array_filter($closes, function($v) { return $v !== null; }));
        
        if (count($validCloses) < 2) return null;
        
        $oldPrice = $validCloses[0];
        $newPrice = $validCloses[count($validCloses) - 1];
        
        if ($oldPrice <= 0) return null;
        
        $change = $newPrice - $oldPrice;
        $changePct = ($change / $oldPrice) * 100;
        $trend = $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat');
        
        $min = min($validCloses);
        $max = max($validCloses);
        $range = $max - $min ?: 1;
        $height = 30;
        $width = 100;
        $step = $width / (count($validCloses) - 1);
        
        $points = [];
        foreach ($validCloses as $i => $value) {
            $x = $i * $step;
            $y = $height - (($value - $min) / $range * $height);
            $points[] = "$x,$y";
        }
        
        $color = $trend === 'up' ? '#28a745' : ($trend === 'down' ? '#dc3545' : '#6c757d');
        $sparkline = '<svg width="100" height="30" style="display:inline-block; vertical-align:middle;">
                        <polyline points="' . implode(' ', $points) . '" fill="none" stroke="' . $color . '" stroke-width="2"/>
                      </svg>';
        
        return [
            'change' => $change,
            'change_pct' => $changePct,
            'trend' => $trend,
            'sparkline' => $sparkline,
            'trend_text' => $trend === 'up' ? '📈 UP' : ($trend === 'down' ? '📉 DOWN' : '➡️ FLAT')
        ];
    }
    
    function fetchExchangeInfo($symbol) {
        $symbolUpper = strtoupper($symbol);
        
        if (substr($symbolUpper, -3) == '.HK') {
            return ['display' => 'Hong Kong', 'code' => 'HKG'];
        }
        if (substr($symbolUpper, -3) == '.SA') {
            return ['display' => 'B3 - São Paulo', 'code' => 'BVMF'];
        }
        if (substr($symbolUpper, -3) == '.L') {
            return ['display' => 'London', 'code' => 'LON'];
        }
        if (substr($symbolUpper, -3) == '.T' || substr($symbolUpper, -2) == '.T') {
            return ['display' => 'Tokyo', 'code' => 'TYO'];
        }
        if (substr($symbolUpper, -3) == '.DE') {
            return ['display' => 'XETRA', 'code' => 'FRA'];
        }
        if (substr($symbolUpper, -3) == '.MX') {
            return ['display' => 'Mexico', 'code' => 'MEX'];
        }
        if (strpos($symbolUpper, '.BMV') !== false || strpos($symbolUpper, ':BMV') !== false) {
            return ['display' => 'Mexico', 'code' => 'MEX'];
        }
        
        $url = "https://query1.finance.yahoo.com/v1/finance/search?q=" . urlencode($symbol) . "&quotesCount=5&newsCount=0";
        $response = curl_get($url);
        
        $exchangeDisplay = 'NASDAQ';
        $exchangeCode = 'NASDAQ';
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['quotes']) && count($data['quotes']) > 0) {
                foreach ($data['quotes'] as $quote) {
                    if (isset($quote['quoteType']) && $quote['quoteType'] === 'EQUITY') {
                        $rawExchange = $quote['exchange'] ?? 'NMS';
                        $exchangeDisplay = $quote['exchDisp'] ?? $rawExchange;
                        
                        $exchangeMap = [
                            'PNK' => 'OTCMKTS', 'NYQ' => 'NYSE', 'NYM' => 'NYSE',
                            'ASE' => 'AMEX', 'TYO' => 'TYO', 'LON' => 'LON',
                            'FRA' => 'FRA', 'HKG' => 'HKG', 'MEX' => 'MEX',
                            'BMV' => 'MEX', 'HKE' => 'HKG',
                        ];
                        
                        $exchangeCode = $exchangeMap[$rawExchange] ?? $rawExchange;
                        
                        if (strpos($exchangeDisplay, 'Hong Kong') !== false || $exchangeCode == 'HKG') {
                            $exchangeDisplay = 'Hong Kong';
                            $exchangeCode = 'HKG';
                        }
                        if (strpos($exchangeDisplay, 'Mexico') !== false || $exchangeCode == 'MEX' || $rawExchange == 'MEX') {
                            $exchangeDisplay = 'Mexico';
                            $exchangeCode = 'MEX';
                        }
                        break;
                    }
                }
            }
        }
        
        return ['display' => $exchangeDisplay, 'code' => $exchangeCode];
    }
    
    function getSearchButtons() {
        $txtFile = __DIR__ . '/searchengs.txt';
        $buttons = [];
        
        if (file_exists($txtFile)) {
            $lines = file($txtFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $parts = explode('#', trim($line), 2);
                if (count($parts) === 2) {
                    $buttons[] = [
                        'file' => trim($parts[0]),
                        'label' => trim($parts[1])
                    ];
                }
            }
        }
        
        return $buttons;
    }
    
    function getDepotIcon($depotName) {
        if (empty($depotName)) return '';
        
        $depotIcons = [
            'comdirect' => 'https://res.cloudinary.com/apideck/image/upload/v1594331712/icons/comdirect-de.jpg',
            'ingdiba' => 'https://e7.pngegg.com/pngimages/396/711/png-clipart-ing-group-ing-vysya-bank-ing-belgium-ing-bank-slaski-bank-mammal-cat-like-mammal-thumbnail.png'
        ];
        
        $depotKey = strtolower(trim($depotName));
        if (isset($depotIcons[$depotKey])) {
            return '<img src="' . htmlspecialchars($depotIcons[$depotKey]) . '" alt="' . htmlspecialchars($depotKey) . '" class="depot-icon" title="' . htmlspecialchars($depotKey) . '">';
        }
        
        return '';
    }
    
    $exchangeRates = getExchangeRates();
    
    $jsonFile = 'stocks.json';
    if (!file_exists($jsonFile)) {
        echo '<div class="all-stocks"><h2>No Stocks Yet</h2><p>No stocks have been saved.</p></div>';
        exit;
    }
    
    $stocks = json_decode(file_get_contents($jsonFile), true);
    if (empty($stocks)) {
        echo '<div class="all-stocks"><h2>No Stocks Found</h2><p>stocks.json is empty.</p></div>';
        exit;
    }
    
    usort($stocks, function($a, $b) {
        return $b['saved_at'] - $a['saved_at'];
    });
    
    // Build fetch order: isin+nrbght first, then rest
    $fetchOrderPriority = [];
    $fetchOrderNormal = [];
    $seenOrder = [];
    foreach ($stocks as $_s) {
        $sym = $_s['stock'];
        if (isset($seenOrder[$sym])) continue;
        $seenOrder[$sym] = true;
        if (!empty($_s['isin']) && !empty($_s['nrbght']) && $_s['nrbght'] > 0)
            $fetchOrderPriority[] = $_s;
        else
            $fetchOrderNormal[] = $_s;
    }
    $fetchQueue = array_merge($fetchOrderPriority, $fetchOrderNormal);
    
    $marketToCode = [
        'OTC Markets' => 'OTCMKTS', 'NASDAQ' => 'NASDAQ', 'NYSE' => 'NYSE',
        'Tokyo' => 'TYO', 'London' => 'LON', 'XETRA' => 'FRA',
        'Hong Kong' => 'HKG', 'HongKong' => 'HKG', 'Mexico' => 'MEX',
        'B3 - São Paulo' => 'BVMF'
    ];
    ?>
    
    <div class="highlights">
        <div class="winners-box">
            <h2>Biggest Winners</h2>
            <div id="winners-content"><div class="no-data">Loading...</div></div>
        </div>
        
        <div class="losers-box">
            <h2>Biggest Losers</h2>
            <div id="losers-content"><div class="no-data">Loading...</div></div>
        </div>
    </div>
    
    <div class="all-stocks">
        <h2>All Saved Stocks - [<a href="stockvalues.php?bought">BOUGHT</a>]</h2>
        <?php
        $debugButtons = getSearchButtons();
        if (count($debugButtons) > 0) {
            echo '<div style="background:#e8f4f8; padding:5px 10px; margin-bottom:10px; font-size:12px; border-radius:5px;">🔍 Custom search buttons loaded: ' . count($debugButtons) . ' (<strong>' . htmlspecialchars($debugButtons[0]['label']) . '</strong>)</div>';
        } else {
            echo '<div style="background:#fff3cd; padding:5px 10px; margin-bottom:10px; font-size:12px; border-radius:5px;">⚠️ No searchengs.txt found or empty. Create file with format: filename.php#buttonlabel</div>';
        }
        ?>
        <div id="stockTableContainer">
            <table id="stockTable">
                <thead>
                    <tr>
                        <th>Stock</th>
                        <th>Saved / Purchase Value</th>
                        <th>Live Price</th>
                        <th>Change (Value)</th>
                        <th>P&L (Total)</th>
                        <th>7-Day Trend</th>
                        <th>Date</th>
                        <th>Exchange</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($stocks as $stock): 
                    $symbol = $stock['stock'];
                    $savedPrice = $stock['price'];
                    $savedCurrency = $stock['currency'];
                    $hasExchange = isset($stock['exchange_market']) && !empty($stock['exchange_market']);
                    $exchangeMarket = $hasExchange ? $stock['exchange_market'] : '';
                    
                    $hasIsin = isset($stock['isin']) && !empty($stock['isin']);
                    $hasQuantity = isset($stock['nrbght']) && !empty($stock['nrbght']) && $stock['nrbght'] > 0;
                    $hasPurchaseData = $hasIsin && $hasQuantity;
                    $quantity = $hasQuantity ? $stock['nrbght'] : 0;
                    $isin = $hasIsin ? $stock['isin'] : '';
                    
                    $depotName = isset($stock['depot']) ? $stock['depot'] : '';
                    $depotIconHtml = getDepotIcon($depotName);
                    
                    $exchangeCode = '';
                    $exchangeDisplay = '';
                    
                    if (!$hasExchange) {
                        $exchangeInfo = fetchExchangeInfo($symbol);
                        $exchangeDisplay = $exchangeInfo['display'];
                        $exchangeCode = $exchangeInfo['code'];
                    } else {
                        $exchangeDisplay = $exchangeMarket;
                        if (isset($marketToCode[$exchangeMarket])) {
                            $exchangeCode = $marketToCode[$exchangeMarket];
                        } else {
                            $exchangeCode = $exchangeMarket;
                        }
                    }
                    
                    $rowClass = $hasPurchaseData ? 'stock-with-data' : '';
                    $googleSymbol = preg_replace('/\.[^.]+$/', '', $symbol);
                ?>
                <tr class="<?php echo $rowClass; ?>" id="row-<?php echo htmlspecialchars($symbol, ENT_QUOTES); ?>">
                    <td style="vertical-align: middle;">
                        <div class="stock-symbol-wrapper">
                            <span class="stock-name-bold"><?php echo htmlspecialchars($symbol); ?></span>
                            <?php echo $depotIconHtml; ?>
                            <?php if ($hasQuantity): ?>
                                <span class="quantity-badge">x<?php echo number_format($quantity); ?></span>
                            <?php endif; ?>
                            <?php if ($hasIsin): ?>
                                <span class="isin-badge">(<?php echo htmlspecialchars($isin); ?>)</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="vertical-align: middle;">
                        <div class="saved-cell">
                            <?php echo formatCurrency($savedPrice, $savedCurrency, $exchangeRates); ?>
                            <span class="saved-original">(saved on <?php echo htmlspecialchars($stock['date']); ?>)</span>
                            <?php if ($hasQuantity): ?>
                                <span class="purchase-price">Purchase: <?php echo formatCurrency($savedPrice, $savedCurrency, $exchangeRates); ?> x <?php echo number_format($quantity); ?> = <?php echo formatCurrency($savedPrice * $quantity, $savedCurrency, $exchangeRates); ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td id="lp-<?php echo htmlspecialchars($symbol, ENT_QUOTES); ?>" style="vertical-align:middle;"><span class="fetching">⏳</span></td>
                    <td id="lc-<?php echo htmlspecialchars($symbol, ENT_QUOTES); ?>" style="vertical-align:middle;"><span class="fetching">⏳</span></td>
                    <td id="pl-<?php echo htmlspecialchars($symbol, ENT_QUOTES); ?>" style="vertical-align:middle;"><?php if (!$hasQuantity): ?><span class="price-neutral" style="font-size:11px;">(use "buy")</span><?php else: ?><span class="fetching">⏳</span><?php endif; ?></td>
                    <td id="tr-<?php echo htmlspecialchars($symbol, ENT_QUOTES); ?>" class="trend-cell" style="vertical-align:middle;"><span class="fetching">⏳</span></td>
                    <td class="timestamp" style="vertical-align: middle;"><?php echo htmlspecialchars($stock['date']); ?></td>
                    <td style="vertical-align: middle;" class="exchange-cell">
                        <?php if ($hasExchange): ?>
                            <span class="exchange-badge"><?php echo htmlspecialchars($exchangeDisplay); ?></span>
                        <?php else: ?>
                            <span class="exchange-badge" style="background:#fff3cd; color:#856404;">
                                <?php echo htmlspecialchars($exchangeDisplay); ?> (temp)
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="action-cell" style="vertical-align: middle;">
                        <div class="action-buttons">
                            <a href="https://www.google.com/finance/quote/<?php echo urlencode($googleSymbol); ?>:<?php echo urlencode($exchangeCode); ?>" target="_blank" class="google-link">G</a>
                            
                            <?php 
                            $searchButtons = getSearchButtons();
                            foreach ($searchButtons as $btn): 
                            ?>
                                <a href="<?php echo htmlspecialchars($btn['file']); ?>?search=<?php echo urlencode($symbol); ?>" target="_blank" class="search-btn"><?php echo htmlspecialchars($btn['label']); ?></a>
                            <?php endforeach; ?>
                            
                            <?php if ($hasExchange): ?>
                                <button class="commit-btn" disabled style="background:#6c757d;">🔒</button>
                            <?php else: ?>
                                <a href="save.php?update=yes&handle=<?php echo urlencode($symbol); ?>&stockexchg=<?php echo urlencode($exchangeDisplay); ?>" class="commit-btn" onclick="return confirm('Save <?php echo htmlspecialchars($exchangeDisplay); ?> as permanent exchange for <?php echo htmlspecialchars($symbol); ?>?')">💾 Commit</a>
                            <?php endif; ?>
                            
                            <a href="buy.php?go=<?php echo urlencode($symbol); ?>" class="gray-buy-link" target="_blank">Buy</a>
                            
                            <button style="background:#f0b90b; color:#fff; padding:4px 10px; border-radius:4px; font-size:12px; border:none; cursor:pointer;" onclick="window.open('https://www.tradingview.com/chart/?symbol=<?php echo urlencode($symbol); ?>', '_blank');">TrdVw</button>
                            
                            <button style="background:#6f42c1; color:white; padding:4px 10px; border-radius:4px; font-size:12px; border:none; cursor:pointer;" onclick="var btn=this; var symbol='<?php echo urlencode($symbol); ?>'; var parent=btn.parentNode; var existing=parent.querySelector('.prdct-iframe-container'); if(existing){existing.remove(); btn.style.display='inline-block';}else{btn.style.display='none'; var container=document.createElement('div'); container.className='prdct-iframe-container'; var iframe=document.createElement('iframe'); iframe.src='stock_rating_growth.php?symbol='+decodeURIComponent(symbol)+'&compact'; iframe.style.border='1px solid #ddd'; iframe.style.borderRadius='4px'; iframe.style.background='white'; iframe.width='110'; iframe.height='30'; container.appendChild(iframe); parent.insertBefore(container, btn.nextSibling);}">prdct</button>

                            <button style="background:#6f02c1; color:white; padding:4px 10px; border-radius:4px; font-size:12px; border:none; cursor:pointer;" onclick="var btn=this; var symbol='<?php echo urlencode($symbol); ?>'; var parent=btn.parentNode; var existing=parent.querySelector('.hnl-iframe-container'); if(existing){existing.remove(); btn.style.display='inline-block';}else{btn.style.display='none'; var container=document.createElement('div'); container.className='hnl-iframe-container'; var iframe=document.createElement('iframe'); iframe.src='highnlows.php?symbol='+decodeURIComponent(symbol)+'&compact'; iframe.style.border='1px solid #ddd'; iframe.style.borderRadius='4px'; iframe.style.background='white'; iframe.width='180'; iframe.height='28'; container.appendChild(iframe); parent.insertBefore(container, btn.nextSibling);}">h&l</button>
                            
                            <button class="delete-btn" onclick="if(confirm('Delete <?php echo htmlspecialchars($symbol); ?>?')) window.location.href='save.php?del=yes&handle=<?php echo urlencode($symbol); ?>'">X</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <footer>
        Live data from Yahoo Finance | Exchange rates from exchangerate-api.com | Green = up, Red = down<br>
        <strong>7-Day Trend column</strong> shows sparkline + percentage change over last 7 trading days (click for 6-month view)<br>
        <strong>Bolded rows</strong> indicate stocks with ISIN and quantity data | Orange badge shows number bought | P&L column shows total profit/loss<br>
        <img src="https://res.cloudinary.com/apideck/image/upload/v1594331712/icons/comdirect-de.jpg" style="width:16px; height:16px; border-radius:50%; vertical-align:middle;"> Comdirect &nbsp;&nbsp;
        <img src="https://e7.pngegg.com/pngimages/396/711/png-clipart-ing-group-ing-vysya-bank-ing-belgium-ing-bank-slaski-bank-mammal-cat-like-mammal-thumbnail.png" style="width:16px; height:16px; border-radius:50%; vertical-align:middle;"> ING-DiBa
    </footer>
</div>
<script>
$(document).ready(function() {
    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.has('bought')) {
        $('#stockTable tbody tr').each(function() {
            if ($(this).find('.quantity-badge').length === 0) {
                $(this).hide();
            }
        });
        
        $('.all-stocks h2').html('All Saved Stocks <span style="font-size:14px; font-weight:normal; color:#ff9800;">(filtered: bought only)</span>');
    }
});
</script>
</body>
</html>
<?php
flush();

$streamedChanges = [];

foreach ($fetchQueue as $qStock) {
    $sym = $qStock['stock'];
    $savedPrice = $qStock['price'];
    $savedCurrency = $qStock['currency'];
    $hasQty = !empty($qStock['nrbght']) && $qStock['nrbght'] > 0;
    $qty = $hasQty ? (int)$qStock['nrbght'] : 0;
    $idSym = htmlspecialchars($sym, ENT_QUOTES);

    // Live price
    $priceData = getLivePriceWithCurrency($sym, $exchangeRates);
    if ($priceData) {
        $priceUSD = $priceData['price_usd'];
        $origCurrency = $priceData['original_currency'];
        $origPrice = $priceData['original_price'];
        $liveConverted = convertCurrency($priceUSD, 'USD', $savedCurrency, $exchangeRates);
        $diff = $liveConverted - $savedPrice;
        $diffPct = $savedPrice > 0 ? ($diff / $savedPrice) * 100 : 0;
        $diffClass = $diff > 0 ? 'price-up' : ($diff < 0 ? 'price-down' : 'price-neutral');
        $diffSign = $diff > 0 ? '+ ' : ($diff < 0 ? '- ' : '');
        $origInfo = getCurrencyInfo($origCurrency);

        $lpHtml = formatCurrency($liveConverted, $savedCurrency, $exchangeRates)
                . ' <span class="saved-original">(' . $origCurrency . ' ' . $origInfo['symbol'] . number_format($origPrice, $origInfo['decimals']) . ')</span>';
        $lcHtml = $diffSign . formatCurrency(abs($diff), $savedCurrency, $exchangeRates) . ' (' . number_format(abs($diffPct), 2) . '%)';

        if ($hasQty) {
            $totalPl = $diff * $qty;
            if ($totalPl > 0)
                $plHtml = '<span class="pl-positive">+' . formatCurrency($totalPl, $savedCurrency, $exchangeRates) . ' (+' . number_format($diffPct, 2) . '%)</span>';
            elseif ($totalPl < 0)
                $plHtml = '<span class="pl-negative">' . formatCurrency($totalPl, $savedCurrency, $exchangeRates) . ' (' . number_format($diffPct, 2) . '%)</span>';
            else
                $plHtml = '<span class="price-neutral">' . formatCurrency(0, $savedCurrency, $exchangeRates) . ' (0%)</span>';
        }

        // Accumulate for winners/losers
        if (!isset($streamedChanges[$sym]) || $qStock['saved_at'] > ($streamedChanges[$sym]['saved_at'] ?? 0)) {
            $streamedChanges[$sym] = [
                'symbol' => $sym,
                'saved_price' => $savedPrice,
                'saved_currency' => $savedCurrency,
                'live_price' => $liveConverted,
                'diff' => $diff,
                'diff_percent' => $diffPct,
                'saved_at' => $qStock['saved_at'] ?? 0,
            ];
        }
    } else {
        $diffClass = 'price-neutral';
        $lpHtml = '<span class="price-neutral">N/A</span>';
        $lcHtml = '--';
        if ($hasQty) $plHtml = '<span class="price-neutral">N/A</span>';
    }

    // 7-day trend
    $trendData = get7DayTrend($sym, $exchangeRates);
    if ($trendData) {
        $trendClass = $trendData['trend'] === 'up' ? 'trend-up' : ($trendData['trend'] === 'down' ? 'trend-down' : '');
        $changeSign = $trendData['change'] > 0 ? '+' : '';
        $trendHtml = '<div class="spark-wrapper" style="cursor:pointer;" onclick="var spark7d=this.querySelector(\'.spark-7d\'); var iframeDiv=this.querySelector(\'.iframe-div\'); var percentSpan=this.querySelector(\'.percent-text\'); if(iframeDiv.style.display===\'none\'){spark7d.style.display=\'none\'; iframeDiv.style.display=\'block\'; percentSpan.innerHTML=\'' . $changeSign . number_format($trendData['change_pct'], 1) . '% \'; iframeDiv.innerHTML=\'<iframe src=\\\'sparkline.php?symbol=' . urlencode($sym) . '&timespan=6month\\\' style=\\\'width:300px; height:100px; border:none; transform:scale(0.50); transform-origin:0 0; overflow:hidden;\\\' scrolling=\\\'no\\\'></iframe>\';}else{spark7d.style.display=\'flex\'; iframeDiv.style.display=\'none\'; percentSpan.innerHTML=\'' . $changeSign . number_format($trendData['change_pct'], 1) . '% ' . $trendData['trend_text'] . '\'; iframeDiv.innerHTML=\'\';}">
                        <div class="spark-7d" style="display:flex; align-items:center; gap:8px;">
                            ' . $trendData['sparkline'] . '
                            <span class="' . $trendClass . ' percent-text" style="font-size: 11px;">
                                ' . $changeSign . number_format($trendData['change_pct'], 1) . '% ' . $trendData['trend_text'] . '
                            </span>
                        </div>
                        <div class="iframe-div" style="display:none;"></div>
                      </div>';
    } else {
        $trendHtml = '<span style="color:#999;">N/A</span>';
    }

    // Emit script to update this row's cells
    $lpJs = json_encode($lpHtml);
    $lcJs = json_encode($lcHtml);
    $plJs = $hasQty ? json_encode($plHtml) : 'null';
    $trJs = json_encode($trendHtml);
    $clsJs = json_encode($diffClass);

    echo "<script>(function(){
  var lp=document.getElementById('lp-{$idSym}'),lc=document.getElementById('lc-{$idSym}'),pl=document.getElementById('pl-{$idSym}'),tr=document.getElementById('tr-{$idSym}');
  if(lp){lp.innerHTML={$lpJs};lp.className={$clsJs};lp.style.verticalAlign='middle';}
  if(lc){lc.innerHTML={$lcJs};lc.className={$clsJs};lc.style.verticalAlign='middle';}
  if(pl && {$plJs}!==null){pl.innerHTML={$plJs};pl.style.verticalAlign='middle';}
  if(tr){tr.innerHTML={$trJs};}
})();</script>\n";
    flush();
}

// Update winners/losers boxes after all symbols are loaded
$winners = array_filter($streamedChanges, function($i){ return $i['diff'] > 0; });
$losers = array_filter($streamedChanges, function($i){ return $i['diff'] < 0; });
usort($winners, function($a,$b){ return $b['diff_percent'] <=> $a['diff_percent']; });
usort($losers, function($a,$b){ return $a['diff_percent'] <=> $b['diff_percent']; });

$winnersHtml = '';
foreach (array_slice($winners, 0, 5) as $w) {
    $winnersHtml .= '<div class="winner-item"><div><strong>' . htmlspecialchars($w['symbol']) . '</strong> <span class="timestamp">'
        . formatCurrency($w['saved_price'], $w['saved_currency'], $exchangeRates) . ' → '
        . formatCurrency($w['live_price'], $w['saved_currency'], $exchangeRates) . '</span></div>'
        . '<div class="winner-change">+' . formatCurrency($w['diff'], $w['saved_currency'], $exchangeRates) . ' (+' . number_format($w['diff_percent'], 2) . '%)</div></div>';
}
if (!$winnersHtml) $winnersHtml = '<div class="no-data">No winners yet.</div>';

$losersHtml = '';
foreach (array_slice($losers, 0, 5) as $l) {
    $losersHtml .= '<div class="loser-item"><div><strong>' . htmlspecialchars($l['symbol']) . '</strong> <span class="timestamp">'
        . formatCurrency($l['saved_price'], $l['saved_currency'], $exchangeRates) . ' → '
        . formatCurrency($l['live_price'], $l['saved_currency'], $exchangeRates) . '</span></div>'
        . '<div class="loser-change">' . formatCurrency($l['diff'], $l['saved_currency'], $exchangeRates) . ' (' . number_format($l['diff_percent'], 2) . '%)</div></div>';
}
if (!$losersHtml) $losersHtml = '<div class="no-data">No losers yet.</div>';

$wJs = json_encode($winnersHtml);
$lJs = json_encode($losersHtml);
echo "<script>
  document.getElementById('winners-content').innerHTML = {$wJs};
  document.getElementById('losers-content').innerHTML = {$lJs};
</script>\n";
flush();
?>
