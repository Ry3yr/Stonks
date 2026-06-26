<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Market API</title>
    <script src="/jquery.min.js"></script>
    <script type="text/javascript">
    $(document).ready(function(){
        var navbarUrl = "list.html";
        $("#stocklist").load(navbarUrl)
            .fail(function() {
                console.error("Failed to load navbar from: " + navbarUrl);
                $("#stocklist").html("<p>Navigation failed to load. Please check console for errors.</p>");
            });
    });
    
    // Function to generate sparkline SVG
    function generateSparkline(data, trend) {
        if (!data || data.length < 2) {
            return '<span style="color:#999; font-size:11px;">no data</span>';
        }
        
        const min = Math.min(...data);
        const max = Math.max(...data);
        const range = max - min || 1;
        const height = 30;
        const width = 100;
        const step = width / (data.length - 1);
        
        const points = [];
        for (let i = 0; i < data.length; i++) {
            const x = i * step;
            const y = height - ((data[i] - min) / range * height);
            points.push(`${x},${y}`);
        }
        
        const color = trend === 'up' ? '#28a745' : (trend === 'down' ? '#dc3545' : '#6c757d');
        
        return `<svg width="100" height="30" style="display:inline-block; vertical-align:middle;">
                    <polyline points="${points.join(' ')}" fill="none" stroke="${color}" stroke-width="2"/>
                </svg>`;
    }
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            padding: 20px;
        }
        h1 { margin-bottom: 10px; color: #1a1a2e; }
        .sub { color: #666; margin-bottom: 20px; }
        .search-box {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .search-box input {
            width: 60%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .search-box input.highlight {
            border-color: #28a745;
            border-width: 2px;
            background-color: #f0fff0;
        }
        .search-box button {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        .search-box button:hover { background: #0056b3; }
        
        .query-link {
            background: #e9ecef;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .query-link a {
            color: #007bff;
            text-decoration: none;
            word-break: break-all;
        }
        .copy-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .currency-display {
            background: #e9ecef;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .currency-display label { font-weight: bold; }
        .currency-display select {
            padding: 5px 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .currency-display .current {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .stock-item {
            background: #f8f9fa;
            margin: 10px 0;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #007bff;
            position: relative;
        }
        .symbol-wrapper {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 5px;
        }
        .symbol {
            font-weight: bold;
            font-size: 20px;
            color: #1a1a2e;
        }
        .google-link-container {
            margin-left: 12px;
        }
        .google-link {
            background-color: #4285f4;
            color: white;
            font-weight: bold;
            text-decoration: none;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
            font-family: Arial, sans-serif;
            line-height: 1.5;
        }
        .google-link:hover {
            background-color: #1a5ed4;
            text-decoration: none;
        }
        .price {
            font-size: 28px;
            font-weight: bold;
            margin: 10px 0;
        }
        .price-usd { color: #6c757d; font-size: 14px; }
        .name {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .info {
            color: #666;
            font-size: 12px;
            margin-top: 8px;
        }
        .save-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
        }
        .save-btn:hover { background: #1e7e34; }
        .error {
            color: red;
            padding: 10px;
            background: #f8d7da;
            border-radius: 5px;
        }
        .suggestions {
            margin-top: 10px;
            padding: 10px;
            background: #fff3cd;
            border-radius: 5px;
        }
        .suggestions a {
            display: inline-block;
            margin: 5px 10px 5px 0;
            padding: 5px 10px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
        }
        .example-buttons {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .example-btn {
            padding: 5px 12px;
            background: #e9ecef;
            border: 1px solid #ddd;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            color: #333;
            display: inline-block;
        }
        .example-btn:hover {
            background: #007bff;
            color: white;
        }
        footer {
            margin-top: 30px;
            padding: 10px;
            background: #e9ecef;
            border-radius: 5px;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        .match-info {
            font-size: 12px;
            color: #28a745;
            margin-left: 8px;
        }
        .trend-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 5px;
            flex-wrap: wrap;
        }
        .trend-up { color: #28a745; }
        .trend-down { color: #dc3545; }
        .trend-flat { color: #6c757d; }
        .spark-wrapper {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .company-name-clickable {
            cursor: pointer;
            color: #007bff;
            text-decoration: underline;
            font-weight: bold;
        }
        .company-name-clickable:hover {
            color: #0056b3;
        }
        .ingdiba-data {
            margin-top: 10px;
            padding: 10px;
            background: #e8f4f8;
            border-radius: 5px;
            border-left: 4px solid #17a2b8;
            display: none;
        }
        .ingdiba-data.visible {
            display: block;
        }
        .ingdiba-data .loading {
            color: #666;
        }
        .ingdiba-data .error {
            color: #dc3545;
            background: none;
            padding: 0;
        }
        .ingdiba-data .success {
            font-size: 13px;
            color: #333;
        }
        .ingdiba-data .success strong {
            color: #17a2b8;
        }
        .ingdiba-data .isin-info {
            background: #fff3cd;
            padding: 4px 8px;
            border-radius: 4px;
            margin: 5px 0;
            font-size: 12px;
            color: #856404;
        }
        .ingdiba-result-item {
            padding: 8px;
            margin: 5px 0;
            border-radius: 4px;
            cursor: pointer;
            border: 1px solid #ddd;
            transition: all 0.2s;
        }
        .ingdiba-result-item:hover {
            background: #e9ecef;
        }
        .ingdiba-result-item.best-match {
            background: #d4edda;
            border-color: #28a745;
        }
        .ingdiba-result-item .load-link {
            font-size: 11px;
            color: #007bff;
            text-decoration: underline;
            margin-left: 8px;
        }
        .ingdiba-result-item .load-link:hover {
            color: #0056b3;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="formClass">
        <div id="stocklist">
            <p>Loading navigation...</p>
        </div>
    </div>

    <hr style="border: 0; height: 1px; background: #ddd; margin: 20px 0;">

    <h1>&#128200; Stock Market API</h1>
    <div class="sub"><a target="_blank" href="https://query1.finance.yahoo.com/v1/finance/search?q=AAPL&quotesCount=5&newsCount=0">Yahoo Finance API Reference</a> - [<a target="_blank" href="/other/extra/fetchdata/2026-05-13-Finance/2026-05-13-Stocks/search_investingcom.php?search=U8X0.F">Buy/Sell Prdctr</a>][<a target="_blank" href="stock_rating.php?symbol=">Buy/NoBuy</a>] [<a target="_blank" href="stockattrib.php">Edit</a>]</div>
    
    <div class="search-box">
        <form method="GET" action="" id="stockForm">
            <input type="text" name="symbol" id="symbolInput" placeholder="Enter company name (Nintendo) or symbol (AAPL, NTDOY, 7974.T)" value="<?php echo isset($_GET['symbol']) ? htmlspecialchars($_GET['symbol']) : ''; ?>">
            <button type="submit">Search Stock</button>
        </form>
        <div class="example-buttons">
            <a href="?symbol=Nintendo&currency=USD" class="example-btn">&#127918; Nintendo</a>
            <a href="?symbol=Apple&currency=USD" class="example-btn">Apple</a>
            <a href="?symbol=NVIDIA&currency=USD" class="example-btn">NVIDIA</a>
            <a href="?symbol=Tesla&currency=USD" class="example-btn">Tesla</a>
            <a href="?symbol=AAPL&currency=USD" class="example-btn">AAPL</a>
            <a href="?symbol=WPRT.TO&currency=USD" class="example-btn">WPRT.TO</a>
        </div>
        <small style="color: #666; display: block; margin-top: 10px;">
            &#128161; Type company names like "Nintendo", "Apple" OR symbols like "AAPL", "NTDOY"
        </small>
    </div>
    
    <?php

    function curl_get_search($url) {
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

    function fetchExchangeInfoForGoogle($symbol) {
        if (preg_match('/\.([A-Z]+)$/i', $symbol, $m)) {
            $suffixMap = [
                'DE' => 'ETR', 'PA' => 'EPA', 'L'  => 'LON', 'T'  => 'TYO',
                'HK' => 'HKG', 'AS' => 'AMS', 'BR' => 'EBR', 'MC' => 'BME',
                'MI' => 'BIT', 'ST' => 'STO', 'OL' => 'OTCMKTS', 'TO' => 'TSE',
                'AX' => 'ASX', 'BO' => 'BOM', 'NS' => 'NSE', 'SW' => 'SWX',
                'SZ' => 'SHE', 'SS' => 'SHA',
            ];
            $suffix = strtoupper($m[1]);
            $code   = $suffixMap[$suffix] ?? $suffix;
            return ['display' => $code, 'code' => $code];
        }

        $response    = curl_get_search("https://query1.finance.yahoo.com/v1/finance/search?q=" . urlencode($symbol) . "&quotesCount=5&newsCount=0");
        $rawExchange = 'NMS';

        if ($response) {
            $data = json_decode($response, true);
            foreach ($data['quotes'] ?? [] as $quote) {
                if (($quote['quoteType'] ?? '') === 'EQUITY') {
                    $rawExchange = $quote['exchange'] ?? 'NMS';
                    break;
                }
            }
        }

        $usMap = [
            'NMS' => 'NASDAQ', 'NGM' => 'NASDAQ', 'NCM' => 'NASDAQ',
            'NYQ' => 'NYSE',   'NYM' => 'NYSE',
            'ASE' => 'AMEX',
            'PNK' => 'OTCMKTS', 'OQB' => 'OTCMKTS', 'OQX' => 'OTCMKTS',
        ];
        $code = $usMap[$rawExchange] ?? $rawExchange;
        return ['display' => $code, 'code' => $code];
    }

    // New function to fetch 7-day sparkline data
    function fetchSparklineData($symbol) {
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol) . "?range=7d&interval=1d";
        $response = curl_get_search($url);
        
        if (!$response) return null;
        
        $data = json_decode($response, true);
        if (!isset($data['chart']['result'][0])) return null;
        
        $result = $data['chart']['result'][0];
        $closes = $result['indicators']['quote'][0]['close'] ?? [];
        $validCloses = array_values(array_filter($closes, function($v) { return $v !== null; }));
        
        if (count($validCloses) >= 2) {
            $oldPrice = $validCloses[0];
            $newPrice = $validCloses[count($validCloses) - 1];
            $sevenDayChange = $newPrice - $oldPrice;
            $sevenDayChangePct = ($sevenDayChange / $oldPrice) * 100;
            $trend = $sevenDayChange > 0 ? 'up' : ($sevenDayChange < 0 ? 'down' : 'flat');
            
            return [
                'sparkline' => $validCloses,
                'trend' => $trend,
                'seven_day_change' => $sevenDayChange,
                'seven_day_change_pct' => $sevenDayChangePct
            ];
        }
        
        return null;
    }

    function searchSymbol($query) {
        $searchUrl = "https://query1.finance.yahoo.com/v1/finance/search?q=" . urlencode($query) . "&quotesCount=5&newsCount=0";
        $response  = curl_get_search($searchUrl);

        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['quotes']) && count($data['quotes']) > 0) {
                foreach ($data['quotes'] as $quote) {
                    if (isset($quote['quoteType']) && $quote['quoteType'] === 'EQUITY') {
                        return [
                            'symbol'   => $quote['symbol'],
                            'name'     => $quote['longname'] ?? $quote['shortname'] ?? $quote['symbol'],
                            'exchange' => $quote['exchDisp'] ?? $quote['exchange'] ?? 'N/A'
                        ];
                    }
                }
                return [
                    'symbol'   => $data['quotes'][0]['symbol'],
                    'name'     => $data['quotes'][0]['longname'] ?? $data['quotes'][0]['shortname'] ?? $data['quotes'][0]['symbol'],
                    'exchange' => $data['quotes'][0]['exchDisp'] ?? $data['quotes'][0]['exchange'] ?? 'N/A'
                ];
            }
        }
        return null;
    }

    // -- Currency setup ----------------------------------------------------------
    $currencyParam = isset($_GET['currency']) ? $_GET['currency'] : 'USD';

    $currencySymbols = [
        'USD' => ['symbol' => '$',  'rate' => 1,     'code' => 'USD'],
        'EUR' => ['symbol' => '€',  'rate' => 0.92,  'code' => 'EUR'],
        'JPY' => ['symbol' => '¥',  'rate' => 148.5, 'code' => 'JPY'],
        'GBP' => ['symbol' => '£',  'rate' => 0.79,  'code' => 'GBP']
    ];

    $targetCurrency = $currencySymbols[$currencyParam] ?? $currencySymbols['USD'];
    $targetSymbol   = $targetCurrency['symbol'];
    $targetRate     = $targetCurrency['rate'];
    $targetCode     = $targetCurrency['code'];

    $rateResponse = curl_get_search("https://api.exchangerate-api.com/v4/latest/USD");
    if ($rateResponse) {
        $rateData = json_decode($rateResponse, true);
        if (isset($rateData['rates']['EUR'])) $currencySymbols['EUR']['rate'] = $rateData['rates']['EUR'];
        if (isset($rateData['rates']['JPY'])) $currencySymbols['JPY']['rate'] = $rateData['rates']['JPY'];
        if (isset($rateData['rates']['GBP'])) $currencySymbols['GBP']['rate'] = $rateData['rates']['GBP'];

        if      ($targetCode == 'EUR') $targetRate = $currencySymbols['EUR']['rate'];
        elseif  ($targetCode == 'JPY') $targetRate = $currencySymbols['JPY']['rate'];
        elseif  ($targetCode == 'GBP') $targetRate = $currencySymbols['GBP']['rate'];
        else                           $targetRate = 1;
    }

    // -- Main logic --------------------------------------------------------------
    if (isset($_GET['symbol']) && !empty($_GET['symbol'])) {
        $query           = trim($_GET['symbol']);
        $isProbablySymbol = preg_match('/^[A-Z0-9\.\-]{1,10}$/', $query);

        $symbol      = '';
        $companyName = '';
        $exchangeName = '';
        $wasSearch   = false;

        if (!$isProbablySymbol) {
            $searchResult = searchSymbol($query);
            if ($searchResult) {
                $symbol       = $searchResult['symbol'];
                $companyName  = $searchResult['name'];
                $exchangeName = $searchResult['exchange'];
                $wasSearch    = true;
            } else {
                $symbol    = strtoupper($query);
                $wasSearch = false;
            }
        } else {
            $symbol    = $query;
            $wasSearch = false;
        }

        // Fetch live price with 7-day data for sparkline
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol) . "?range=7d&interval=1d";
        $ch  = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200 && $response) {
            $data = json_decode($response, true);

            if ($data && isset($data['chart']['result'][0])) {
                $result = $data['chart']['result'][0];
                $meta   = $result['meta'];

                $originalPrice        = isset($meta['regularMarketPrice']) ? $meta['regularMarketPrice'] : 'N/A';
                $previousClose        = isset($meta['previousClose'])      ? $meta['previousClose']      : null;
                $originalCurrencyCode = isset($meta['currency'])           ? strtoupper($meta['currency']) : 'USD';

                // Get sparkline data from the same response
                $closes = $result['indicators']['quote'][0]['close'] ?? [];
                $validCloses = array_values(array_filter($closes, function($v) { return $v !== null; }));
                
                $sevenDayChange = null;
                $sevenDayChangePct = null;
                $trend = 'flat';
                $sparklineData = [];
                
                if (count($validCloses) >= 2) {
                    $oldPrice = $validCloses[0];
                    $newPrice = $validCloses[count($validCloses) - 1];
                    if ($oldPrice > 0) {
                        $sevenDayChange = $newPrice - $oldPrice;
                        $sevenDayChangePct = ($sevenDayChange / $oldPrice) * 100;
                        $trend = $sevenDayChange > 0 ? 'up' : ($sevenDayChange < 0 ? 'down' : 'flat');
                    }
                    $sparklineData = $validCloses;
                }

                $localToUsdRate = 1.0;
                if ($originalCurrencyCode !== 'USD' && isset($rateData['rates'][$originalCurrencyCode]) && $rateData['rates'][$originalCurrencyCode] > 0) {
                    $localToUsdRate = 1.0 / $rateData['rates'][$originalCurrencyCode];
                }

                $convertedPrice = 'N/A';
                if ($originalPrice !== 'N/A' && is_numeric($originalPrice)) {
                    $convertedPrice = $originalPrice * $localToUsdRate * $targetRate;
                }

                $changeDisplay      = 'N/A';
                $changePercent      = 'N/A';
                $changeSign         = '';
                $changePercentSign  = '';
                if ($originalPrice !== 'N/A' && $previousClose && is_numeric($originalPrice) && is_numeric($previousClose)) {
                    $changeVal         = $originalPrice - $previousClose;
                    $changePercentVal  = ($changeVal / $previousClose) * 100;
                    $changeConverted   = $changeVal * $localToUsdRate * $targetRate;
                    $changeDisplay     = number_format($changeConverted, 2);
                    $changePercent     = number_format($changePercentVal, 2);
                    $changeSign        = ($changeConverted >= 0) ? '+' : '';
                    $changePercentSign = ($changePercentVal >= 0) ? '+' : '';
                }

                $fetchedName  = isset($meta['longName'])    ? $meta['longName']    : (isset($meta['shortName']) ? $meta['shortName'] : $symbol);
                $exchangeName = isset($meta['exchangeName']) ? $meta['exchangeName'] : 'N/A';
                $displayName         = ($wasSearch && $companyName) ? $companyName : $fetchedName;

                // -- Google Finance link ------------------------------------------
                $googleSymbol  = preg_replace('/\.[A-Z]{2,}$/i', '', $symbol);
                $exchangeInfo  = fetchExchangeInfoForGoogle($symbol);
                $exchangeCode  = $exchangeInfo['code'];
                $googleFinanceUrl = 'https://www.google.com/finance/quote/' . urlencode($googleSymbol) . ':' . urlencode($exchangeCode);

                // Shareable link
                $queryLink = '?symbol=' . urlencode($query) . '&currency=' . urlencode($currencyParam);
                $fullUrl   = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/' . $queryLink;

                echo '<div class="query-link">';
                echo '<span>&#128279; Shareable link:</span>';
                echo '<a href="' . $queryLink . '">' . htmlspecialchars($fullUrl) . '</a>';
                echo '<button class="copy-btn" onclick="copyLink()">&#128203; Copy</button>';
                echo '</div>';

                echo '<div class="currency-display">';
                echo '<label>&#128177; Display currency:</label>';
                echo '<select id="currencySelect" onchange="changeCurrency()">';
                echo '<option value="USD" ' . ($currencyParam == 'USD' ? 'selected' : '') . '>&#127482;&#127480; USD ($)</option>';
                echo '<option value="EUR" ' . ($currencyParam == 'EUR' ? 'selected' : '') . '>&#127466;&#127482; Euro (&euro;)</option>';
                echo '<option value="JPY" ' . ($currencyParam == 'JPY' ? 'selected' : '') . '>&#127471;&#127477; Yen (&yen;)</option>';
                echo '<option value="GBP" ' . ($currencyParam == 'GBP' ? 'selected' : '') . '>&#127468;&#127463; Pound (&pound;)</option>';
                echo '</select>';
                echo '<span class="current">Showing: ' . $targetSymbol . '</span>';
                echo '</div>';

                $today = date('Y-m-d');

                // Special handling for WPRT.TO
                if ($symbol === 'WPRT.TO') {
                    echo '<div class="stock-item wprt-special">';
                    echo '<div class="symbol-wrapper">';
                    echo '<div class="symbol">' . htmlspecialchars($symbol) . '</div>';
                    echo '<div class="google-link-container">';
                    echo '<a href="https://www.google.com/finance/quote/WPRT:TSE" target="_blank" class="google-link" title="View on Google Finance">G</a>';
                    echo '<a href="exchangemrktresolve.php?url=https://www.google.com/finance/quote/WPRT:TSE" target="_blank" class="google-link" title="MrktResolv">Rslv</a>';
                    echo '</div>';
                    echo '<span class="match-info">(matched from "CA9609085076")</span>';
                    echo '</div>';
                    
                    echo '<div class="name company-name-clickable" data-symbol="' . htmlspecialchars($symbol) . '" data-company="' . htmlspecialchars($displayName) . '" data-ingdiba="true">' . htmlspecialchars($displayName) . '</div>';
                    
                    echo '<div class="price" style="color: #28a745;">$ 2.35</div>';
                    echo '<div class="price-usd">(Original: CAD 3.34 @ 1 CAD = 0.7042 USD)</div>';
                    echo '<div class="info">Daily Change: N/A</div>';
                    echo '<div class="info">Exchange: TSE</div>';
                    
                    echo '<div id="ingdiba-data-' . htmlspecialchars($symbol) . '" class="ingdiba-data"></div>';
                    
                    echo '</div>';
                } else {
                    // Normal display for all other stocks
                    echo '<div class="stock-item">';
                    echo '<div class="symbol-wrapper">';
                    echo '<div class="symbol">' . htmlspecialchars($symbol) . '</div>';
                    echo '<div class="google-link-container">';
                    echo '<a href="' . $googleFinanceUrl . '" target="_blank" class="google-link" title="View on Google Finance">G</a>';
                    echo '<a href="exchangemrktresolve.php?url=' . $googleFinanceUrl . '" target="_blank" class="google-link" title="MrktResolv">Rslv</a>';
                    echo '</div>';
                    if ($wasSearch) {
                        echo '<span class="match-info">(matched from "' . htmlspecialchars($query) . '")</span>';
                    }
                    echo '</div>';
                    
                    echo '<div class="name company-name-clickable" data-symbol="' . htmlspecialchars($symbol) . '" data-company="' . htmlspecialchars($displayName) . '" data-ingdiba="true">' . htmlspecialchars($displayName) . '</div>';
                    
                    echo '<div class="price" style="color: #28a745;">' . $targetSymbol . ' ' . number_format((float)$convertedPrice, 2) . '</div>';
                    if ($originalCurrencyCode != $targetCode && $originalPrice !== 'N/A') {
                        $localToTargetRate = $localToUsdRate * $targetRate;
                        echo '<div class="price-usd">(Original: ' . $originalCurrencyCode . ' ' . number_format((float)$originalPrice, 2) . ' @ 1 ' . $originalCurrencyCode . ' = ' . number_format($localToTargetRate, 4) . ' ' . $targetCode . ')</div>';
                    }
                    echo '<div class="info">';
                    echo 'Daily Change: ' . ($changeDisplay != 'N/A' ? $changeSign . $changeDisplay : 'N/A');
                    if ($changePercent != 'N/A') {
                        echo ' (' . $changePercentSign . $changePercent . '%)';
                    }
                    echo '</div>';
                    
                    // Add 7-Day Trend with Sparkline
                    if (!empty($sparklineData)) {
                        $trendText = $trend === 'up' ? '?? UP' : ($trend === 'down' ? '?? DOWN' : '?? FLAT');
                        $change7dSign = $sevenDayChange > 0 ? '+' : '';
                        
                        // Generate SVG directly
                        $min = min($sparklineData);
                        $max = max($sparklineData);
                        $range = $max - $min ?: 1;
                        $height = 30;
                        $width = 100;
                        $step = $width / (count($sparklineData) - 1);
                        $points = [];
                        foreach ($sparklineData as $i => $value) {
                            $x = $i * $step;
                            $y = $height - (($value - $min) / $range * $height);
                            $points[] = "$x,$y";
                        }
                        $color = $trend === 'up' ? '#28a745' : ($trend === 'down' ? '#dc3545' : '#6c757d');
                        
                        echo '<div class="trend-container">';
                        echo '<div class="spark-wrapper" style="display:flex; align-items:center; gap:8px; cursor:pointer;" onclick="window.open(\'sparkline.php?symbol=' . urlencode($symbol) . '&timespan=6month\', \'_blank\')">';
                        echo '<svg width="100" height="30" style="display:inline-block; vertical-align:middle;">';
                        echo '<polyline points="' . implode(' ', $points) . '" fill="none" stroke="' . $color . '" stroke-width="2"/>';
                        echo '</svg>';
                        echo '</div>';
                        echo '<span class="info" style="margin-top:0;">7-Day Change: ' . $change7dSign . number_format(abs($sevenDayChange), 2) . ' (' . $change7dSign . number_format($sevenDayChangePct, 2) . '%) ' . $trendText . '</span>';
                        echo '</div>';
                        
                        // Add the other iframes
                        echo '<div style="display:inline-flex; align-items:center; margin-left:10px; flex-wrap:wrap; gap:10px;">';
                        echo '<iframe src="stock_rating_growth.php?symbol=' . urlencode($symbol) . '&compact" 
                                style="width:250px; height:30px; border:none; overflow:hidden;" 
                                scrolling="no" 
                                frameborder="0">
                        </iframe>';
                        $badge_query = $query;
                        echo '<iframe src="xchangemarket_badge.php?q=' . urlencode($badge_query) . '" style="width:250px; height:30px; border:none; overflow:hidden;" scrolling="no" frameborder="0" sandbox="allow-same-origin allow-scripts allow-popups allow-forms allow-modals" referrerpolicy="no-referrer-when-downgrade"> </iframe>';
                        echo '</div>';
                    } else {
                        echo '<div class="info">7-Day Trend: No data available</div>';
                    }
                    
                    echo '<div class="info">Exchange: ' . htmlspecialchars($exchangeName) . '</div>';
                    
                    echo '<div id="ingdiba-data-' . htmlspecialchars($symbol) . '" class="ingdiba-data"></div>';

                    $saveUrl = 'save.php?stock='         . urlencode($symbol) .
                               '&price='               . urlencode(is_numeric($convertedPrice) ? number_format((float)$convertedPrice, 2) : '0') .
                               '&currency='            . urlencode($targetSymbol) .
                               '&date='                . urlencode($today) .
                               '&exchange_market='     . urlencode($exchangeName);

                    if (is_numeric($convertedPrice)) {
                        echo '<button class="save-btn" onclick="window.open(\'' . $saveUrl . '\', \'_blank\')">&#128190; Save to JSON</button>';
                    } else {
                        echo '<button class="save-btn" style="background:#6c757d; cursor:not-allowed;" disabled title="Price unavailable">&#128190; Save unavailable</button>';
                    }
                    echo '</div>';
                }

            } else {
                echo '<div class="error">&#10060; No data found for "' . htmlspecialchars($query) . '"</div>';
            }
        } else {
            echo '<div class="error">&#9888; Could not fetch data for: ' . htmlspecialchars($query) . '</div>';
            echo '<div class="suggestions">';
            echo '<strong>&#128161; Suggestions from Yahoo Finance API:</strong><br>';
            echo '<a href="?symbol=AAPL&currency='  . urlencode($currencyParam) . '">AAPL (Apple)</a>';
            echo '<a href="?symbol=NVDA&currency='  . urlencode($currencyParam) . '">NVDA (NVIDIA)</a>';
            echo '<a href="?symbol=NTDOY&currency=' . urlencode($currencyParam) . '">NTDOY (Nintendo)</a>';
            echo '<a href="?symbol=TSLA&currency='  . urlencode($currencyParam) . '">TSLA (Tesla)</a>';
            echo '<a href="?symbol=MSFT&currency='  . urlencode($currencyParam) . '">MSFT (Microsoft)</a>';
            echo '</div>';
        }

    } else {
        echo '<div style="padding: 40px; text-align: center; color: #666;">';
        echo '<h3>&#128269; Search for any stock</h3>';
        echo '<p>Try typing: <strong>Nintendo</strong>, <strong>Apple</strong>, <strong>Tesla</strong>, or a symbol like <strong>AAPL</strong>, <strong>NTDOY</strong></p>';
        echo '<p style="margin-top: 20px;"><strong>&#127918; Popular stocks:</strong></p>';
        echo '<div class="example-buttons" style="justify-content: center;">';
        echo '<a href="?symbol=Nintendo&currency=USD"  class="example-btn">Nintendo</a>';
        echo '<a href="?symbol=Apple&currency=USD"     class="example-btn">Apple</a>';
        echo '<a href="?symbol=NVIDIA&currency=USD"    class="example-btn">NVIDIA</a>';
        echo '<a href="?symbol=Tesla&currency=USD"     class="example-btn">Tesla</a>';
        echo '<a href="?symbol=Microsoft&currency=USD" class="example-btn">Microsoft</a>';
        echo '<a href="?symbol=Google&currency=USD"    class="example-btn">Google</a>';
        echo '<a href="?symbol=WPRT.TO&currency=USD"   class="example-btn">WPRT.TO</a>';
        echo '</div>';
        echo '</div>';
    }
    ?>
    
    <footer>
        &#9889; Live data from Yahoo Finance API &bull; Search by company name OR symbol &bull; &#128154; Google Finance "G" link next to each symbol<br>
        ?? <strong>Sparklines show 7-day price trend</strong> (?? green = up, ?? red = down, ?? gray = flat)<br>
        ?? <strong>Click company name</strong> to fetch ING DiBa data (ISIN will be placed in search box)
    </footer>
</div>

<script>
function changeCurrency() {
    const urlParams = new URLSearchParams(window.location.search);
    const symbol   = urlParams.get('symbol') || document.getElementById('symbolInput').value.trim();
    const currency = document.getElementById('currencySelect').value;
    if (symbol) {
        window.location.href = '?symbol=' + encodeURIComponent(symbol) + '&currency=' + encodeURIComponent(currency);
    } else {
        window.location.href = '?currency=' + encodeURIComponent(currency);
    }
}

function copyLink() {
    const link = window.location.href;
    navigator.clipboard.writeText(link).then(function() {
        alert('Link copied to clipboard!');
    }).catch(function() {
        alert('Could not copy link');
    });
}

// Function to fetch ING DiBa data for any symbol
function fetchIngDibaData(symbol, displayElementId, companyName) {
    const displayElement = document.getElementById(displayElementId);
    if (!displayElement) return;
    
    // Show loading
    displayElement.classList.add('visible');
    displayElement.innerHTML = '<div class="loading">Loading ING DiBa data for ' + symbol + '...</div>';
    
    // Build search terms: first word then full name (NO symbol/ticker)
    let searchTerms = [];
    if (companyName && companyName !== symbol) {
        // Get first word before space
        let firstWord = companyName.split(' ')[0];
        if (firstWord && firstWord !== symbol) {
            searchTerms.push(firstWord);
        }
        searchTerms.push(companyName);
    } else {
        searchTerms.push(symbol);
    }
    
    let currentTermIndex = 0;
    let rawJsonData = null;
    let allResults = [];
    
    function tryNextTerm() {
        if (currentTermIndex >= searchTerms.length) {
            if (allResults.length > 0) {
                displayAllResults(allResults);
                return;
            }
            displayElement.innerHTML = '<div class="error">No ING DiBa data available for ' + (companyName || symbol) + '</div>';
            return;
        }
        
        const term = searchTerms[currentTermIndex];
        if (currentTermIndex > 0) {
            displayElement.innerHTML = '<div class="loading">Searching: "' + term + '"...</div>';
        }
        
        fetch('ingdiba.php?symbol=' + encodeURIComponent(term) + '&json')
            .then(response => response.json())
            .then(data => {
                rawJsonData = data;
                
                // Check if we got multiple results
                if (data.status === 'multiple_results' && data.search && data.search.results) {
                    data.search.results.forEach(result => {
                        if (!allResults.some(r => r.isin === result.isin)) {
                            allResults.push(result);
                        }
                    });
                }
                
                // Check if we got a single success
                if (data.status === 'success' && data.data && data.data.price && data.data.price.isin) {
                    allResults.push({
                        isin: data.data.price.isin,
                        name: data.data.price.name || companyName || symbol,
                        price: data.data.price.price,
                        currency: data.data.price.currency
                    });
                }
                
                currentTermIndex++;
                tryNextTerm();
            })
            .catch(error => {
                console.error('Error:', error);
                currentTermIndex++;
                tryNextTerm();
            });
    }
    
    function displayAllResults(results) {
        if (!results || results.length === 0) {
            displayElement.innerHTML = '<div class="error">No results found</div>';
            return;
        }
        
        let html = '<div class="success">';
        html += '<strong>ING DiBa Results</strong> <span style="font-size:11px;color:#6c757d;">(found ' + results.length + ' results)</span><br>';
        html += '<div style="margin:5px 0;font-size:12px;color:#666;">Click any result to load that ISIN into the search box:</div>';
        
        results.forEach((result, index) => {
            html += '<div class="ingdiba-result-item" onclick="loadISIN(\'' + result.isin + '\', \'' + displayElement.id + '\')" style="padding:8px;margin:5px 0;background:#f8f9fa;border-radius:4px;cursor:pointer;border:1px solid #ddd;">';
            html += '<strong>' + (result.name || 'Unknown') + '</strong>';
            html += ' <span class="load-link">[Load ISIN]</span><br>';
            html += '<span style="font-size:12px;color:#666;">ISIN: ' + result.isin + ' | Price: ' + (result.price || 'N/A') + ' ' + (result.currency || '') + '</span>';
            html += '</div>';
        });
        
        // Add JSON link
        if (rawJsonData) {
            html += '<div style="margin-top:8px;font-size:11px;">';
            html += '<a href="#" onclick="viewRawJSON(\'' + encodeURIComponent(JSON.stringify(rawJsonData)) + '\'); return false;" style="color:#007bff;text-decoration:underline;">View Raw JSON Results</a>';
            html += '</div>';
        }
        
        html += '</div>';
        
        displayElement.innerHTML = html;
        
        // Auto-load the first result into the search box
        if (results[0]) {
            const symbolInput = document.getElementById('symbolInput');
            symbolInput.value = results[0].isin;
            symbolInput.classList.add('highlight');
            setTimeout(() => {
                symbolInput.classList.remove('highlight');
            }, 3000);
        }
    }
    
    // Start the search process
    tryNextTerm();
}

// Function to load ISIN into search box
function loadISIN(isin, displayElementId) {
    const symbolInput = document.getElementById('symbolInput');
    symbolInput.value = isin;
    symbolInput.classList.add('highlight');
    setTimeout(() => {
        symbolInput.classList.remove('highlight');
    }, 3000);
    
    // Show confirmation
    const displayElement = document.getElementById(displayElementId);
    if (displayElement) {
        // Find and highlight the selected one
        const items = displayElement.querySelectorAll('.ingdiba-result-item');
        items.forEach(item => {
            item.style.background = '#f8f9fa';
            item.style.borderColor = '#ddd';
        });
        // Add a message
        let html = displayElement.innerHTML;
        html = '<div style="padding:8px;margin-bottom:8px;background:#d4edda;border-radius:4px;border-left:4px solid #28a745;font-size:13px;">? ISIN <strong>' + isin + '</strong> loaded into search box. Click Search to continue.</div>' + html;
        displayElement.innerHTML = html;
    }
}

// Function to view raw JSON
function viewRawJSON(encodedData) {
    const data = JSON.parse(decodeURIComponent(encodedData));
    const win = window.open('', '_blank', 'width=800,height=600');
    win.document.write('<pre style="padding:20px;background:#f8f9fa;font-family:monospace;font-size:12px;">' + JSON.stringify(data, null, 2) + '</pre>');
    win.document.close();
}

// Add click handlers for all company names
$(document).ready(function() {
    $('.company-name-clickable').on('click', function() {
        const symbol = $(this).data('symbol');
        const companyName = $(this).data('company') || $(this).text().trim();
        if (symbol) {
            const containerId = 'ingdiba-data-' + symbol;
            fetchIngDibaData(symbol, containerId, companyName);
        }
    });
});
</script>
</body>
</html>