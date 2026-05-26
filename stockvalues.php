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
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        h1 { margin-bottom: 10px; color: #1a1a2e; }
        .sub { color: #666; margin-bottom: 20px; }
        
        .highlights {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .winners-box, .losers-box {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .winners-box h2 { color: #28a745; border-bottom: 2px solid #28a745; padding-bottom: 10px; margin-bottom: 15px; }
        .losers-box h2 { color: #dc3545; border-bottom: 2px solid #dc3545; padding-bottom: 10px; margin-bottom: 15px; }
        .winner-item, .loser-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .winner-change { color: #28a745; font-weight: bold; }
        .loser-change { color: #dc3545; font-weight: bold; }
        .no-data { color: #666; text-align: center; padding: 20px; }
        
        .all-stocks {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .price-up { color: #28a745; font-weight: bold; }
        .price-down { color: #dc3545; font-weight: bold; }
        .price-neutral { color: #666; }
        .google-link {
            background: #4285f4;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            display: inline-block;
        }
        .google-link:hover { background: #3367d6; }
        
        .gray-buy-link {
            background: #6c757d;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            display: inline-block;
            margin-left: 5px;
        }
        .gray-buy-link:hover {
            background: #5a6268;
        }
        
        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
        }
        .delete-btn:hover { background: #c82333; }
        .commit-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 10px;
            margin-left: 5px;
        }
        .commit-btn:hover { background: #1e7e34; }
        .commit-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        .refresh-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .refresh-btn:hover { background: #0056b3; }
        footer {
            margin-top: 30px;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        .timestamp {
            font-size: 11px;
            color: #999;
        }
        .action-cell {
            white-space: nowrap;
        }
        .saved-cell {
            display: flex;
            flex-direction: column;
        }
        .saved-original {
            font-size: 10px;
            color: #999;
        }
        .exchange-badge {
            font-size: 10px;
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 10px;
        }
        .fetching {
            font-size: 10px;
            color: #ffc107;
            margin-left: 5px;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .stock-name-bold {
            font-weight: bold;
            color: #2c3e50;
        }
        .stock-with-data {
            background-color: #fff8e7;
        }
        .quantity-badge {
            background: #ff9800;
            color: white;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 8px;
            display: inline-block;
        }
        .pl-positive {
            color: #28a745;
            font-weight: bold;
        }
        .pl-negative {
            color: #dc3545;
            font-weight: bold;
        }
        .isin-badge {
            font-size: 10px;
            color: #6c757d;
            margin-left: 8px;
            font-family: monospace;
        }
        .purchase-price {
            font-size: 11px;
            color: #856404;
            background: #fff3cd;
            padding: 2px 6px;
            border-radius: 4px;
            margin-top: 4px;
            display: inline-block;
        }
        .search-btn {
            background: #17a2b8;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 11px;
            display: inline-block;
        }
        .search-btn:hover {
            background: #138496;
        }
        .depot-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            object-fit: cover;
            margin-left: 6px;
            vertical-align: middle;
            display: inline-block;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .stock-symbol-wrapper {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Stock Portfolio</h1>
    <div class="sub">Live prices vs saved values</div>
    
    <button class="refresh-btn" onclick="location.reload()">Refresh Live Prices</button>
    
    <?php
    
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
        $rateUrl = "https://api.exchangerate-api.com/v4/latest/USD";
        $response = curl_get($rateUrl);
        
        $defaultRates = ['USD' => 1, 'EUR' => 0.92, 'JPY' => 148.5, 'GBP' => 0.79];
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['rates'])) {
                return [
                    'USD' => 1,
                    'EUR' => $data['rates']['EUR'] ?? 0.92,
                    'JPY' => $data['rates']['JPY'] ?? 148.5,
                    'GBP' => $data['rates']['GBP'] ?? 0.79
                ];
            }
        }
        return $defaultRates;
    }
    
    function getCurrencyInfo($currencyCode) {
        $currencies = [
            'USD' => ['symbol' => '$', 'code' => 'USD'],
            '$' => ['symbol' => '$', 'code' => 'USD'],
            'EUR' => ['symbol' => '€', 'code' => 'EUR'],
            '€' => ['symbol' => '€', 'code' => 'EUR'],
            'JPY' => ['symbol' => '¥', 'code' => 'JPY'],
            '¥' => ['symbol' => '¥', 'code' => 'JPY'],
            'GBP' => ['symbol' => '£', 'code' => 'GBP'],
            '£' => ['symbol' => '£', 'code' => 'GBP']
        ];
        return $currencies[$currencyCode] ?? ['symbol' => '$', 'code' => 'USD'];
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
        $code = $info['code'];
        
        if ($code == 'JPY') {
            return $symbol . number_format($amount, 0);
        }
        return $symbol . number_format($amount, 2);
    }
    
    function getLivePriceUSD($symbol) {
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol);
        $response = curl_get($url);
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['chart']['result'][0]['meta']['regularMarketPrice'])) {
                return $data['chart']['result'][0]['meta']['regularMarketPrice'];
            }
        }
        return null;
    }
    
    function fetchExchangeInfo($symbol) {
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
                            'PNK' => 'OTCMKTS',
                            'NYQ' => 'NYSE',
                            'NYM' => 'NYSE',
                            'ASE' => 'AMEX',
                            'TYO' => 'TYO',
                            'LON' => 'LON',
                            'FRA' => 'FRA',
                            'HKG' => 'HKG',
                            'MEX' => 'MEX'
                        ];
                        
                        $exchangeCode = $exchangeMap[$rawExchange] ?? $rawExchange;
                        break;
                    }
                }
            }
        }
        
        return ['display' => $exchangeDisplay, 'code' => $exchangeCode];
    }
    
    // Read search buttons from searchengs.txt
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
    
    // Helper function to get depot icon HTML
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
    
    $uniqueSymbols = array_unique(array_column($stocks, 'stock'));
    $livePricesUSD = [];
    foreach ($uniqueSymbols as $symbol) {
        $price = getLivePriceUSD($symbol);
        if ($price) $livePricesUSD[$symbol] = $price;
        usleep(100000);
    }
    
    $latestBySymbol = [];
    foreach ($stocks as $stock) {
        $symbol = $stock['stock'];
        if (!isset($latestBySymbol[$symbol]) || $stock['saved_at'] > $latestBySymbol[$symbol]['saved_at']) {
            $latestBySymbol[$symbol] = $stock;
        }
    }
    
    $changes = [];
    foreach ($latestBySymbol as $symbol => $saved) {
        if (isset($livePricesUSD[$symbol])) {
            $savedPrice = $saved['price'];
            $savedCurrency = $saved['currency'];
            $livePriceUSD = $livePricesUSD[$symbol];
            
            $livePriceConverted = convertCurrency($livePriceUSD, 'USD', $savedCurrency, $exchangeRates);
            
            $diff = $livePriceConverted - $savedPrice;
            $diffPercent = ($savedPrice > 0) ? ($diff / $savedPrice) * 100 : 0;
            
            $changes[$symbol] = [
                'symbol' => $symbol,
                'saved_price' => $savedPrice,
                'saved_currency' => $savedCurrency,
                'live_price' => $livePriceConverted,
                'live_price_usd' => $livePriceUSD,
                'diff' => $diff,
                'diff_percent' => $diffPercent
            ];
        }
    }
    
    $winners = array_filter($changes, function($item) {
        return $item['diff'] > 0;
    });
    $losers = array_filter($changes, function($item) {
        return $item['diff'] < 0;
    });
    
    usort($winners, function($a, $b) {
        return $b['diff_percent'] <=> $a['diff_percent'];
    });
    usort($losers, function($a, $b) {
        return $a['diff_percent'] <=> $b['diff_percent'];
    });
    ?>
    
    <div class="highlights">
        <div class="winners-box">
            <h2>Biggest Winners</h2>
            <?php if (empty($winners)): ?>
                <div class="no-data">No winners yet.</div>
            <?php else: ?>
                <?php foreach (array_slice($winners, 0, 5) as $winner): ?>
                    <div class="winner-item">
                        <div>
                            <strong><?php echo htmlspecialchars($winner['symbol']); ?></strong>
                            <span class="timestamp">
                                <?php echo formatCurrency($winner['saved_price'], $winner['saved_currency'], $exchangeRates); ?> 
                                ? 
                                <?php echo formatCurrency($winner['live_price'], $winner['saved_currency'], $exchangeRates); ?>
                            </span>
                        </div>
                        <div class="winner-change">
                            +<?php echo formatCurrency($winner['diff'], $winner['saved_currency'], $exchangeRates); ?> (+<?php echo number_format($winner['diff_percent'], 2); ?>%)
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="losers-box">
            <h2>Biggest Losers</h2>
            <?php if (empty($losers)): ?>
                <div class="no-data">No losers yet.</div>
            <?php else: ?>
                <?php foreach (array_slice($losers, 0, 5) as $loser): ?>
                    <div class="loser-item">
                        <div>
                            <strong><?php echo htmlspecialchars($loser['symbol']); ?></strong>
                            <span class="timestamp">
                                <?php echo formatCurrency($loser['saved_price'], $loser['saved_currency'], $exchangeRates); ?> 
                                ? 
                                <?php echo formatCurrency($loser['live_price'], $loser['saved_currency'], $exchangeRates); ?>
                            </span>
                        </div>
                        <div class="loser-change">
                            <?php echo formatCurrency($loser['diff'], $loser['saved_currency'], $exchangeRates); ?> (<?php echo number_format($loser['diff_percent'], 2); ?>%)
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="all-stocks">
        <h2>All Saved Stocks - [<a href="stockvalues.php?bought">BOUGHT</a>]</h2>
        <?php
        // Debug: Check if buttons are being loaded
        $debugButtons = getSearchButtons();
        if (count($debugButtons) > 0) {
            echo '<div style="background:#e8f4f8; padding:5px 10px; margin-bottom:10px; font-size:12px; border-radius:5px;">?? Custom search buttons loaded: ' . count($debugButtons) . ' (<strong>' . htmlspecialchars($debugButtons[0]['label']) . '</strong>)</div>';
        } else {
            echo '<div style="background:#fff3cd; padding:5px 10px; margin-bottom:10px; font-size:12px; border-radius:5px;">?? No searchengs.txt found or empty. Create file with format: filename.php#buttonlabel</div>';
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
                    $livePriceUSD = isset($livePricesUSD[$symbol]) ? $livePricesUSD[$symbol] : null;
                    $livePriceConverted = null;
                    $diff = null;
                    $diffClass = '';
                    $diffIndicator = '';
                    $hasExchange = isset($stock['exchange_market']) && !empty($stock['exchange_market']);
                    $exchangeMarket = $hasExchange ? $stock['exchange_market'] : '';
                    
                    $hasIsin = isset($stock['isin']) && !empty($stock['isin']);
                    $hasQuantity = isset($stock['nrbght']) && !empty($stock['nrbght']) && $stock['nrbght'] > 0;
                    $hasPurchaseData = $hasIsin && $hasQuantity;
                    $quantity = $hasQuantity ? $stock['nrbght'] : 0;
                    $isin = $hasIsin ? $stock['isin'] : '';
                    
                    // Get depot value for icon
                    $depotName = isset($stock['depot']) ? $stock['depot'] : '';
                    $depotIconHtml = getDepotIcon($depotName);
                    
                    $exchangeCode = '';
                    $exchangeDisplay = '';
                    $isExchangeCached = $hasExchange;
                    
                    if (!$hasExchange) {
                        $exchangeInfo = fetchExchangeInfo($symbol);
                        $exchangeDisplay = $exchangeInfo['display'];
                        $exchangeCode = $exchangeInfo['code'];
                    } else {
                        $exchangeDisplay = $exchangeMarket;
                        
                        $marketToCode = [
                            'OTC Markets' => 'OTCMKTS',
                            'NASDAQ' => 'NASDAQ',
                            'NYSE' => 'NYSE',
                            'Tokyo' => 'TYO',
                            'London' => 'LON',
                            'XETRA' => 'FRA',
                            'Hong Kong' => 'HKG',
                            'Mexico' => 'MEX'
                        ];
                        
                        if (isset($marketToCode[$exchangeMarket])) {
                            $exchangeCode = $marketToCode[$exchangeMarket];
                        } else {
                            $exchangeCode = $exchangeMarket;
                        }
                    }
                    
                    $totalPl = 0;
                    $totalPlPercent = 0;
                    
                    if ($livePriceUSD && $hasQuantity) {
                        $livePriceConverted = convertCurrency($livePriceUSD, 'USD', $savedCurrency, $exchangeRates);
                        $diff = $livePriceConverted - $savedPrice;
                        $totalPl = $diff * $quantity;
                        $totalPlPercent = ($savedPrice > 0) ? ($diff / $savedPrice) * 100 : 0;
                        
                        if ($diff > 0) {
                            $diffClass = 'price-up';
                            $diffIndicator = '+ ';
                        } elseif ($diff < 0) {
                            $diffClass = 'price-down';
                            $diffIndicator = '- ';
                        }
                    } elseif ($livePriceUSD) {
                        $livePriceConverted = convertCurrency($livePriceUSD, 'USD', $savedCurrency, $exchangeRates);
                        $diff = $livePriceConverted - $savedPrice;
                        if ($diff > 0) {
                            $diffClass = 'price-up';
                            $diffIndicator = '+ ';
                        } elseif ($diff < 0) {
                            $diffClass = 'price-down';
                            $diffIndicator = '- ';
                        }
                    }
                    
                    $rowClass = $hasPurchaseData ? 'stock-with-data' : '';
                    
                    // Remove .de or .DE from symbol for Google Finance link
                    $googleSymbol = preg_replace('/\.[^.]+$/', '', $symbol);
                ?>
                <tr class="<?php echo $rowClass; ?>">
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
                    <td class="<?php echo $diffClass; ?>" style="vertical-align: middle;">
                        <?php if ($livePriceConverted !== null): ?>
                            <?php echo formatCurrency($livePriceConverted, $savedCurrency, $exchangeRates); ?>
                            <span class="saved-original">(USD $<?php echo number_format($livePriceUSD, 2); ?>)</span>
                        <?php else: ?>
                            <span class="price-neutral">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td class="<?php echo $diffClass; ?>" style="vertical-align: middle;">
                        <?php if ($diff !== null): ?>
                            <?php echo $diffIndicator . formatCurrency(abs($diff), $savedCurrency, $exchangeRates) . ' (' . number_format(abs(($diff / $savedPrice) * 100), 2) . '%)'; ?>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>
                    <td style="vertical-align: middle;">
                        <?php if ($hasQuantity && $diff !== null): ?>
                            <?php if ($totalPl > 0): ?>
                                <span class="pl-positive">+<?php echo formatCurrency($totalPl, $savedCurrency, $exchangeRates); ?> (+<?php echo number_format($totalPlPercent, 2); ?>%)</span>
                            <?php elseif ($totalPl < 0): ?>
                                <span class="pl-negative"><?php echo formatCurrency($totalPl, $savedCurrency, $exchangeRates); ?> (<?php echo number_format($totalPlPercent, 2); ?>%)</span>
                            <?php else: ?>
                                <span class="price-neutral"><?php echo formatCurrency(0, $savedCurrency, $exchangeRates); ?> (0%)</span>
                            <?php endif; ?>
                        <?php elseif ($hasQuantity && $diff === null): ?>
                            <span class="price-neutral">N/A</span>
                        <?php else: ?>
                            <span class="price-neutral" style="font-size:11px;">(use "buy")</span>
                        <?php endif; ?>
                    </td>
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
                            // Add buttons from searchengs.txt
                            $searchButtons = getSearchButtons();
                            foreach ($searchButtons as $btn): 
                            ?>
                                <a href="<?php echo htmlspecialchars($btn['file']); ?>?search=<?php echo urlencode($symbol); ?>" 
                                   target="_blank" 
                                   class="search-btn">
                                    <?php echo htmlspecialchars($btn['label']); ?>
                                </a>
                            <?php endforeach; ?>
                            
                            <?php if ($hasExchange): ?>
                                <button class="commit-btn" disabled style="background:#6c757d;">?</button>
                            <?php else: ?>
                                <a href="save.php?update=yes&handle=<?php echo urlencode($symbol); ?>&stockexchg=<?php echo urlencode($exchangeDisplay); ?>" class="commit-btn" onclick="return confirm('Save <?php echo htmlspecialchars($exchangeDisplay); ?> as permanent exchange for <?php echo htmlspecialchars($symbol); ?>?')">? Commit</a>
                            <?php endif; ?>
                            
                            <a href="buy.php?go=<?php echo urlencode($symbol); ?>" class="gray-buy-link" target="_blank">Buy</a>
                            
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