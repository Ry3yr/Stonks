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
    <div class="sub"><a target="_blank" href="https://query1.finance.yahoo.com/v1/finance/search?q=AAPL&quotesCount=5&newsCount=0">Yahoo Finance API Reference</a> - [<a target="_blank" href="/other/extra/fetchdata/2026-05-13-Finance/2026-05-13-Stocks/search_investingcom.php?search=U8X0.F">Buy/Sell Prdctr</a>] [<a target="_blank" href="stockattrib.php">Edit</a>]</div>
    
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
        // If the symbol has a suffix (.DE, .PA, .L, .T, .HK etc.)
        // derive the Google Finance exchange code directly from it 
        // no API call needed, no hardcoded list for every country.
        if (preg_match('/\.([A-Z]+)$/i', $symbol, $m)) {
            $suffixMap = [
                'DE' => 'ETR',  // XETRA
                'PA' => 'EPA',  // Euronext Paris
                'L'  => 'LON',  // London
                'T'  => 'TYO',  // Tokyo
                'HK' => 'HKG',  // Hong Kong
                'AS' => 'AMS',  // Amsterdam
                'BR' => 'EBR',  // Brussels
                'MC' => 'BME',  // Madrid
                'MI' => 'BIT',  // Milan
                'ST' => 'STO',  // Stockholm
                'OL' => 'OTCMKTS', // Oslo (no Google Finance code)
                'TO' => 'TSE',  // Toronto
                'AX' => 'ASX',  // Australia
                'BO' => 'BOM',  // Bombay
                'NS' => 'NSE',  // NSE India
                'SW' => 'SWX',  // Swiss
                'SZ' => 'SHE',  // Shenzhen
                'SS' => 'SHA',  // Shanghai
            ];
            $suffix = strtoupper($m[1]);
            $code   = $suffixMap[$suffix] ?? $suffix;
            return ['display' => $code, 'code' => $code];
        }

        // No suffix = US stock; look up via Yahoo search to distinguish
        // NYSE / NASDAQ / AMEX / OTC
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
            'NMS' => 'NASDAQ', 
            'NGM' => 'NASDAQ', 
            'NCM' => 'NASDAQ',
            'NYQ' => 'NYSE',   'NYM' => 'NYSE',
            'ASE' => 'AMEX',
            'PNK' => 'OTCMKTS',
            'OQB' => 'OTCMKTS',
            'OQX' => 'OTCMKTS',
        ];
        $code = $usMap[$rawExchange] ?? $rawExchange;
        return ['display' => $code, 'code' => $code];
    }

    // Search Yahoo Finance for a company name, return best EQUITY match
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
                // Fallback: first result even if not EQUITY
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
        'EUR' => ['symbol' => '',  'rate' => 0.92,  'code' => 'EUR'],
        'JPY' => ['symbol' => '',  'rate' => 148.5, 'code' => 'JPY'],
        'GBP' => ['symbol' => '',  'rate' => 0.79,  'code' => 'GBP']
    ];

    $targetCurrency = $currencySymbols[$currencyParam] ?? $currencySymbols['USD'];
    $targetSymbol   = $targetCurrency['symbol'];
    $targetRate     = $targetCurrency['rate'];
    $targetCode     = $targetCurrency['code'];

    // Live exchange rates
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

        // Fetch live price
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol);
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

                // Rate to go from the stock's local currency to USD.
                // $rateData['rates'] is relative to USD (e.g. JPY=>150 means 1 USD = 150 JPY).
                $localToUsdRate = 1.0;
                if ($originalCurrencyCode !== 'USD' && isset($rateData['rates'][$originalCurrencyCode]) && $rateData['rates'][$originalCurrencyCode] > 0) {
                    $localToUsdRate = 1.0 / $rateData['rates'][$originalCurrencyCode];
                }

                // Full conversion: local currency -> USD -> display currency
                $convertedPrice = 'N/A';
                if ($originalPrice !== 'N/A' && is_numeric($originalPrice)) {
                    $convertedPrice = $originalPrice * $localToUsdRate * $targetRate;
                }

                $changeDisplay      = 'N/A';
                $changePercent      = 'N/A';
                $changeSign         = '';
                $changePercentSign  = '';
                if ($originalPrice !== 'N/A' && $previousClose && is_numeric($originalPrice) && is_numeric($previousClose)) {
                    $changeVal         = $originalPrice - $previousClose;             // in local currency
                    $changePercentVal  = ($changeVal / $previousClose) * 100;
                    $changeConverted   = $changeVal * $localToUsdRate * $targetRate;  // local -> USD -> target
                    $changeDisplay     = number_format($changeConverted, 2);
                    $changePercent     = number_format($changePercentVal, 2);
                    $changeSign        = ($changeConverted >= 0) ? '+' : '';
                    $changePercentSign = ($changePercentVal >= 0) ? '+' : '';
                }

                $fetchedName  = isset($meta['longName'])    ? $meta['longName']    : (isset($meta['shortName']) ? $meta['shortName'] : $symbol);
                $exchangeName = isset($meta['exchangeName']) ? $meta['exchangeName'] : 'N/A';
                $displayName         = ($wasSearch && $companyName) ? $companyName : $fetchedName;

                // -- Google Finance link (matches portfolio page logic) ----------
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
                echo '<div class="name">' . htmlspecialchars($displayName) . '</div>';
                echo '<div class="price" style="color: #28a745;">' . $targetSymbol . ' ' . number_format((float)$convertedPrice, 2) . '</div>';
                if ($originalCurrencyCode != $targetCode && $originalPrice !== 'N/A') {
                    $localToTargetRate = $localToUsdRate * $targetRate;
                    echo '<div class="price-usd">(Original: ' . $originalCurrencyCode . ' ' . number_format((float)$originalPrice, 2) . ' @ 1 ' . $originalCurrencyCode . ' = ' . number_format($localToTargetRate, 4) . ' ' . $targetCode . ')</div>';
                }
                echo '<div class="info">';
                echo 'Change: ' . ($changeDisplay != 'N/A' ? $changeSign . $changeDisplay : 'N/A');
                if ($changePercent != 'N/A') {
                    echo ' (' . $changePercentSign . $changePercent . '%)';
                }
                echo '</div>';
                echo '<div class="info">Exchange: ' . htmlspecialchars($exchangeName) . '</div>';

                $saveUrl = 'save.php?stock=' . urlencode($symbol) .
                           '&price='    . urlencode(number_format((float)$convertedPrice, 2)) .
                           '&currency=' . urlencode($targetSymbol) .
                           '&date='     . urlencode($today);

                echo '<button class="save-btn" onclick="window.open(\'' . $saveUrl . '\', \'_blank\')">&#128190; Save to JSON</button>';
                echo '</div>';

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
        echo '</div>';
        echo '</div>';
    }
    ?>
    
    <footer>
        &#9889; Live data from Yahoo Finance API &bull; Search by company name OR symbol &bull; &#128154; Google Finance "G" link next to each symbol
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
</script>
</body>
</html>