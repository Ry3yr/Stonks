<?php
/**
 * Finanzen.net Analyst Ratings Parser
 * With query string filtering, infinite scroll, Link column, and Process column
 * Uses browser localStorage for caching instead of server-side cache
 */

class FinanzenRatingsParser {
    private $baseUrl;
    private $userAgent;
    private $timeout;
    
    private $ratingUrls = [
        'buy' => '/analysen/kaufen',
        'hold' => '/analysen/halten',
        'sell' => '/analysen/verkaufen'
    ];
    
    public function __construct($options = []) {
        $this->baseUrl = 'https://www.finanzen.net';
        $this->userAgent = $options['user_agent'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $this->timeout = $options['timeout'] ?? 30;
    }
    
    public function getUrlForRatingType($type) {
        $type = strtolower($type);
        return isset($this->ratingUrls[$type]) 
            ? $this->baseUrl . $this->ratingUrls[$type] 
            : $this->baseUrl . '/analysen/kaufen';
    }
    
    private function fetchHtml($ratingType = 'buy', $page = 1) {
        $url = $this->getUrlForRatingType($ratingType);
        
        if ($page > 1) {
            $url .= '?p=' . $page;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: de-DE,de;q=0.9,en;q=0.8',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP $httpCode: Failed to fetch page - $error");
        }
        
        return $html;
    }
    
    /**
     * Fetch current price from Yahoo Finance
     */
    private function fetchPriceFromYahoo($companyName) {
        // Clean company name for search
        $searchTerm = preg_replace('/\s+(vz\.|vz|pref|preferred|plc|ltd|inc|corp|gmbh|ag|se|adr)$/i', '', $companyName);
        $searchTerm = preg_replace('/\s*\([^)]*\)/', '', $searchTerm);
        
        // First search for the symbol
        $searchUrl = "https://query1.finance.yahoo.com/v1/finance/search?q=" . urlencode($searchTerm) . "&quotesCount=3&newsCount=0";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            if (isset($data['quotes'][0]['symbol'])) {
                $symbol = $data['quotes'][0]['symbol'];
                
                // Now get the price for this symbol
                $priceUrl = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}";
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $priceUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
                
                $priceResponse = curl_exec($ch);
                curl_close($ch);
                
                if ($priceResponse) {
                    $priceData = json_decode($priceResponse, true);
                    
                    if (isset($priceData['chart']['result'][0]['meta'])) {
                        $meta = $priceData['chart']['result'][0]['meta'];
                        $currentPrice = $meta['regularMarketPrice'] ?? null;
                        $previousClose = $meta['previousClose'] ?? null;
                        $change = ($currentPrice && $previousClose) ? $currentPrice - $previousClose : null;
                        $changePercent = ($change && $previousClose) ? ($change / $previousClose) * 100 : null;
                        
                        return [
                            'symbol' => $symbol,
                            'price' => $currentPrice,
                            'change' => $change,
                            'change_percent' => $changePercent,
                            'currency' => $meta['currency'] ?? 'USD'
                        ];
                    }
                }
            }
        }
        
        return null;
    }
    
    private function parseRatingsFromHtml($html) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        $rows = $xpath->query("//tr[contains(@class, 'table__tr')]");
        
        $ratings = [];
        foreach ($rows as $row) {
            $cells = $xpath->query(".//td[contains(@class, 'table__td')]", $row);
            if ($cells->length < 4) continue;
            
            $date = trim($cells->item(0)->textContent);
            $link = $xpath->query(".//a", $cells->item(2));
            
            if ($link->length === 0) continue;
            
            $linkText = trim($link->item(0)->textContent);
            $analyst = trim($cells->item(3)->textContent);
            $linkHref = $link->item(0)->getAttribute('href');
            
            $analysisUrl = $this->baseUrl . $linkHref;
            
            if (preg_match('/^(.+?)\s+(Overweight|Outperform|Kaufen|Buy|Neutral|Sell|Verkaufen|Halten|Hold)$/i', $linkText, $matches)) {
                $companyName = $this->normalizeCompanyName(trim($matches[1]));
                $symbol = urlencode($companyName);
                
                // Fetch price from Yahoo Finance
                $priceInfo = $this->fetchPriceFromYahoo($companyName);
                
                $ratings[] = [
                    'date' => $this->normalizeDate($date),
                    'date_display' => $date,
                    'company' => $companyName,
                    'symbol' => $companyName,
                    'symbol_urlencoded' => $symbol,
                    'rating' => $this->normalizeRating($matches[2]),
                    'rating_original' => $matches[2],
                    'analyst' => $this->normalizeAnalystName($analyst),
                    'analysis_url' => $analysisUrl,
                    'process_url' => "process.php?symbol=" . $symbol,
                    'link_text' => $linkText,
                    'price' => $priceInfo['price'] ?? null,
                    'price_change' => $priceInfo['change'] ?? null,
                    'price_change_percent' => $priceInfo['change_percent'] ?? null,
                    'currency' => $priceInfo['currency'] ?? 'USD',
                    'timestamp' => time()
                ];
            }
        }
        
        return $ratings;
    }
    
    private function hasNextPage($html) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        $nextLinks = $xpath->query("//a[contains(@class, 'next') or contains(text(), 'nächste') or contains(@rel, 'next')]");
        
        return $nextLinks->length > 0;
    }
    
    private function getTotalPages($html) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        $paginationText = $xpath->query("//div[contains(@class, 'pagination')]//text()");
        
        foreach ($paginationText as $text) {
            if (preg_match('/von\s+(\d+)/i', $text->nodeValue, $matches)) {
                return (int)$matches[1];
            }
        }
        
        return null;
    }
    
    public function parsePage($ratingType = 'buy', $page = 1) {
        $html = $this->fetchHtml($ratingType, $page);
        return [
            'ratings' => $this->parseRatingsFromHtml($html),
            'has_next' => $this->hasNextPage($html),
            'page' => $page,
            'total_pages' => $this->getTotalPages($html)
        ];
    }
    
    private function normalizeDate($date) {
        if (preg_match('/(\d{2})\.(\d{2})\.(\d{2})/', $date, $matches)) {
            return '20' . $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
        return $date;
    }
    
    private function normalizeCompanyName($name) {
        return trim(preg_replace('/\s+/', ' ', $name));
    }
    
    private function normalizeRating($rating) {
        $mapping = ['Kaufen' => 'Buy', 'Verkaufen' => 'Sell', 'Halten' => 'Hold', 'Hold' => 'Hold'];
        return $mapping[$rating] ?? $rating;
    }
    
    private function normalizeAnalystName($name) {
        return trim(html_entity_decode(preg_replace('/\s+/', ' ', $name), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}

// ==================== WEB INTERFACE ====================

$action = isset($_GET['action']) ? strtolower($_GET['action']) : 'buy';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$format = isset($_GET['format']) ? $_GET['format'] : 'html';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'price_desc';

$validActions = ['buy', 'hold', 'sell'];
if (!in_array($action, $validActions)) {
    $action = 'buy';
}

$parser = new FinanzenRatingsParser();

// Handle AJAX requests for infinite scroll
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    try {
        $result = $parser->parsePage($action, $page);
        
        // Sort by price if requested
        if (!empty($result['ratings']) && $sortBy === 'price_desc') {
            usort($result['ratings'], function($a, $b) {
                return ($b['price'] ?? 0) <=> ($a['price'] ?? 0);
            });
        } elseif (!empty($result['ratings']) && $sortBy === 'price_asc') {
            usort($result['ratings'], function($a, $b) {
                return ($a['price'] ?? 0) <=> ($b['price'] ?? 0);
            });
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Handle export formats
if ($format === 'json' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="ratings_' . $action . '_' . date('Y-m-d') . '.json"');
    $result = $parser->parsePage($action, 1);
    $allRatings = $result['ratings'];
    $currentPage = 2;
    while ($result['has_next'] && $currentPage <= 10) {
        $result = $parser->parsePage($action, $currentPage);
        $allRatings = array_merge($allRatings, $result['ratings']);
        $currentPage++;
        usleep(300000);
    }
    echo json_encode($allRatings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($format === 'csv' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="ratings_' . $action . '_' . date('Y-m-d') . '.csv"');
    
    $result = $parser->parsePage($action, 1);
    $allRatings = $result['ratings'];
    $currentPage = 2;
    while ($result['has_next'] && $currentPage <= 10) {
        $result = $parser->parsePage($action, $currentPage);
        $allRatings = array_merge($allRatings, $result['ratings']);
        $currentPage++;
        usleep(300000);
    }
    
    $fp = fopen('php://output', 'w');
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, ['Date', 'Company', 'Rating', 'Analyst', 'Price', 'Change', 'Analysis URL', 'Process URL']);
    foreach ($allRatings as $rating) {
        fputcsv($fp, [
            $rating['date_display'],
            $rating['company'],
            $rating['rating'],
            $rating['analyst'],
            $rating['price'] ? $rating['currency'] . ' ' . number_format($rating['price'], 2) : 'N/A',
            $rating['price_change'] ? ($rating['price_change'] > 0 ? '+' : '') . number_format($rating['price_change'], 2) . ' (' . number_format($rating['price_change_percent'], 2) . '%)' : 'N/A',
            $rating['analysis_url'],
            $rating['process_url']
        ]);
    }
    fclose($fp);
    exit;
}

$actionLabels = [
    'buy' => ['Kaufen (Buy)', 'positive', '🟢', '#28a745'],
    'hold' => ['Halten (Hold)', 'neutral', '🟡', '#ffc107'],
    'sell' => ['Verkaufen (Sell)', 'negative', '🔴', '#dc3545']
];

$currentLabel = $actionLabels[$action];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentLabel[0]; ?> - Analyst Ratings | Finanzen.net</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f0f2f5;
            color: #1a1a2e;
        }
        
        .header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            border-bottom: 2px solid rgba(255,255,255,0.2);
            padding-bottom: 10px;
        }
        
        .nav-tab {
            padding: 10px 24px;
            background: rgba(255,255,255,0.1);
            border: none;
            border-radius: 8px 8px 0 0;
            color: white;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-block;
            text-decoration: none;
        }
        
        .nav-tab:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .nav-tab.active {
            background: <?php echo $currentLabel[3]; ?>;
            color: <?php echo $action === 'hold' ? '#1a1a2e' : 'white'; ?>;
        }
        
        .stats-bar {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .stats-info {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #1a1a2e;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .sort-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            justify-content: flex-end;
        }
        
        .sort-btn {
            padding: 8px 16px;
            background: #e9ecef;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }
        
        .sort-btn:hover {
            background: #dee2e6;
        }
        
        .sort-btn.active {
            background: #007bff;
            color: white;
        }
        
        .filters {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filters input, .filters select {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            flex: 1;
            min-width: 150px;
        }
        
        .cache-info {
            background: #e7f3ff;
            border-radius: 12px;
            padding: 12px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 13px;
        }
        
        .cache-badge {
            background: #28a745;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .ratings-table {
            background: white;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }
        
        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .price-up {
            color: #28a745;
        }
        
        .price-down {
            color: #dc3545;
        }
        
        .current-price {
            font-weight: bold;
        }
        
        .company-link {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        
        .company-link:hover {
            text-decoration: underline;
        }
        
        .rating-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .rating-Buy { background: #d4edda; color: #155724; }
        .rating-Overweight { background: #d1ecf1; color: #0c5460; }
        .rating-Outperform { background: #d4edda; color: #155724; }
        .rating-Hold { background: #fff3cd; color: #856404; }
        .rating-Sell { background: #f8d7da; color: #721c24; }
        
        .analyst-link {
            color: #6c757d;
            text-decoration: none;
            font-size: 13px;
        }
        
        .analyst-link:hover {
            color: #007bff;
            text-decoration: underline;
        }
        
        .action-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .link-view {
            background: #e9ecef;
            color: #495057;
        }
        
        .link-view:hover {
            background: #007bff;
            color: white;
        }
        
        .link-process {
            background: #28a745;
            color: white;
        }
        
        .link-process:hover {
            background: #1e7e34;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            display: none;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007bff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .no-more {
            text-align: center;
            padding: 30px;
            color: #666;
            display: none;
        }
        
        .scroll-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #007bff;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .scroll-top.visible {
            opacity: 1;
        }
        
        .clear-cache-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .clear-cache-btn:hover {
            background: #c82333;
        }
        
        @media (max-width: 768px) {
            th, td {
                font-size: 12px;
                padding: 8px;
            }
            
            .stats-info {
                order: 2;
                width: 100%;
                justify-content: space-between;
            }
            
            .export-buttons {
                order: 1;
                width: 100%;
            }
            
            .action-link {
                padding: 4px 8px;
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>📊 Analyst Ratings with Live Prices</h1>
            <div class="nav-tabs">
                <a href="#" data-action="buy" class="nav-tab <?php echo $action === 'buy' ? 'active' : ''; ?>">🟢 Kaufen (Buy)</a>
                <a href="#" data-action="hold" class="nav-tab <?php echo $action === 'hold' ? 'active' : ''; ?>">🟡 Halten (Hold)</a>
                <a href="#" data-action="sell" class="nav-tab <?php echo $action === 'sell' ? 'active' : ''; ?>">🔴 Verkaufen (Sell)</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="stats-bar">
            <div class="stats-info">
                <div class="stat"><div class="stat-value" id="ratingCount">0</div><div class="stat-label">Total Ratings</div></div>
                <div class="stat"><div class="stat-value" id="currentPage">1</div><div class="stat-label">Current Page</div></div>
                <div class="stat"><div class="stat-value" id="loadedCount">0</div><div class="stat-label">Loaded So Far</div></div>
                <div class="stat"><div class="stat-value" id="cacheStatus">-</div><div class="stat-label">Cache Status</div></div>
            </div>
            <div class="export-buttons">
                <a href="#" id="exportJsonBtn" class="btn btn-primary">📥 Export JSON</a>
                <a href="#" id="exportCsvBtn" class="btn btn-success">📥 Export CSV</a>
                <button onclick="clearBrowserCache()" class="btn" style="background:#dc3545;color:white;">🗑️ Clear Cache</button>
            </div>
        </div>
        
        <div class="cache-info" id="cacheInfo">
            <span>💾 <strong>Browser Cache Active</strong> - Data is stored in your browser's localStorage</span>
            <span class="cache-badge" id="cacheSizeBadge">0 items cached</span>
        </div>
        
        <div class="sort-buttons">
            <button class="sort-btn" data-sort="price_desc" onclick="setSort('price_desc')">💰 Price: High to Low</button>
            <button class="sort-btn" data-sort="price_asc" onclick="setSort('price_asc')">💰 Price: Low to High</button>
            <button class="sort-btn" onclick="refreshData()" style="background:#28a745;color:white;">🔄 Refresh Data (Skip Cache)</button>
        </div>
        
        <div class="filters">
            <input type="text" id="companyFilter" placeholder="🔍 Filter by company..." onkeyup="filterTable()">
            <select id="ratingFilter" onchange="filterTable()">
                <option value="">All Ratings</option>
                <option value="Buy">Buy</option>
                <option value="Overweight">Overweight</option>
                <option value="Outperform">Outperform</option>
                <option value="Hold">Hold</option>
                <option value="Sell">Sell</option>
            </select>
            <input type="text" id="analystFilter" placeholder="🔍 Filter by analyst..." onkeyup="filterTable()">
        </div>
        
        <div class="ratings-table">
            <table id="ratingsTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Company / Rating</th>
                        <th>Price</th>
                        <th>Change</th>
                        <th>Analyst</th>
                        <th>Link</th>
                        <th>Process</th>
                    </tr>
                </thead>
                <tbody id="ratingsBody"></tbody>
            </table>
        </div>
        
        <div class="loading" id="loading"><div class="spinner"></div><p style="margin-top: 15px;">Loading more ratings...</p></div>
        <div class="no-more" id="noMore">✨ No more ratings to load</div>
    </div>
    
    <div class="scroll-top" id="scrollTop" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">↑</div>
    
    <script>
        // Cache configuration
        const CACHE_KEY_PREFIX = 'finanzen_ratings_';
        const CACHE_EXPIRY_MS = 60 * 60 * 1000; // 1 hour cache expiry
        
        let currentPage = 1;
        let hasNextPage = true;
        let isLoading = false;
        let allRatings = [];
        let currentAction = '<?php echo $action; ?>';
        let currentSort = 'price_desc';
        let currentTotalPages = null;
        
        // Get cache key for current action
        function getCacheKey() {
            return CACHE_KEY_PREFIX + currentAction;
        }
        
        // Save data to localStorage
        function saveToCache(data, totalPages) {
            const cacheData = {
                timestamp: Date.now(),
                action: currentAction,
                ratings: data,
                totalPages: totalPages,
                version: 1
            };
            localStorage.setItem(getCacheKey(), JSON.stringify(cacheData));
            updateCacheDisplay();
        }
        
        // Load data from localStorage
        function loadFromCache() {
            const cached = localStorage.getItem(getCacheKey());
            if (!cached) return null;
            
            try {
                const cacheData = JSON.parse(cached);
                // Check if cache is expired
                if (Date.now() - cacheData.timestamp > CACHE_EXPIRY_MS) {
                    console.log('Cache expired');
                    return null;
                }
                // Check if it's for the same action
                if (cacheData.action !== currentAction) return null;
                
                console.log('Cache hit! Items:', cacheData.ratings.length);
                return cacheData;
            } catch (e) {
                console.error('Failed to parse cache', e);
                return null;
            }
        }
        
        // Clear browser cache
        function clearBrowserCache() {
            if (confirm('Clear all cached rating data from your browser? This will force a fresh load on next refresh.')) {
                // Clear all related cache keys
                const keysToRemove = ['buy', 'hold', 'sell'].map(a => CACHE_KEY_PREFIX + a);
                keysToRemove.forEach(key => localStorage.removeItem(key));
                updateCacheDisplay();
                
                // Reset and reload current action
                resetState();
                loadData();
                showNotification('Cache cleared! Refreshing data...', 'success');
            }
        }
        
        // Update cache info display
        function updateCacheDisplay() {
            let totalCached = 0;
            ['buy', 'hold', 'sell'].forEach(a => {
                const cached = localStorage.getItem(CACHE_KEY_PREFIX + a);
                if (cached) {
                    try {
                        const data = JSON.parse(cached);
                        totalCached += data.ratings.length;
                    } catch(e) {}
                }
            });
            
            const cacheBadge = document.getElementById('cacheSizeBadge');
            const cacheStatus = document.getElementById('cacheStatus');
            
            if (totalCached > 0) {
                cacheBadge.textContent = totalCached + ' items cached';
                cacheBadge.style.background = '#28a745';
                cacheStatus.textContent = '✅ Cached';
                cacheStatus.style.color = '#28a745';
            } else {
                cacheBadge.textContent = 'No cache';
                cacheBadge.style.background = '#6c757d';
                cacheStatus.textContent = 'No Cache';
                cacheStatus.style.color = '#6c757d';
            }
        }
        
        // Show notification
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 20px;
                background: ${type === 'success' ? '#28a745' : '#007bff'};
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                z-index: 10000;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                animation: fadeInOut 2s ease-in-out;
            `;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 2000);
        }
        
        // Reset state when switching actions
        function resetState() {
            currentPage = 1;
            hasNextPage = true;
            allRatings = [];
            currentTotalPages = null;
            document.getElementById('ratingsBody').innerHTML = '';
            document.getElementById('noMore').style.display = 'none';
            document.getElementById('currentPage').textContent = '1';
            document.getElementById('loadedCount').textContent = '0';
            document.getElementById('ratingCount').textContent = '0';
            
            // Clear filters
            document.getElementById('companyFilter').value = '';
            document.getElementById('ratingFilter').value = '';
            document.getElementById('analystFilter').value = '';
        }
        
        // Refresh data (skip cache)
        function refreshData() {
            // Clear current action's cache
            localStorage.removeItem(getCacheKey());
            updateCacheDisplay();
            resetState();
            loadData(true);
        }
        
        // Change rating type (buy/hold/sell)
        function changeAction(action) {
            if (action === currentAction) return;
            
            currentAction = action;
            
            // Update URL without reloading
            const url = new URL(window.location.href);
            url.searchParams.set('action', action);
            window.history.pushState({}, '', url);
            
            // Update active tab styling
            document.querySelectorAll('.nav-tab').forEach(tab => {
                if (tab.getAttribute('data-action') === action) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
            
            // Reset and load new data
            resetState();
            loadData();
        }
        
        async function loadData(skipCache = false) {
            if (isLoading) return;
            
            // Check cache first (unless skipCache is true or we're loading more pages)
            if (!skipCache && currentPage === 1 && allRatings.length === 0) {
                const cached = loadFromCache();
                if (cached && cached.ratings && cached.ratings.length > 0) {
                    console.log('Using cached data for', currentAction);
                    allRatings = cached.ratings;
                    currentTotalPages = cached.totalPages;
                    hasNextPage = currentPage < (currentTotalPages || 2);
                    sortAndRender();
                    currentPage = allRatings.length > 0 ? 2 : 1;
                    document.getElementById('currentPage').textContent = currentPage - 1;
                    document.getElementById('loadedCount').textContent = allRatings.length;
                    document.getElementById('ratingCount').textContent = allRatings.length;
                    document.getElementById('loading').style.display = 'none';
                    updateCacheDisplay();
                    return;
                }
            }
            
            isLoading = true;
            document.getElementById('loading').style.display = 'block';
            
            const url = `?action=${currentAction}&page=${currentPage}&sort=${currentSort}`;
            
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                
                if (data.ratings && data.ratings.length > 0) {
                    allRatings = [...allRatings, ...data.ratings];
                    currentTotalPages = data.total_pages;
                    sortAndRender();
                    currentPage++;
                    hasNextPage = data.has_next;
                    document.getElementById('currentPage').textContent = currentPage - 1;
                    document.getElementById('loadedCount').textContent = allRatings.length;
                    document.getElementById('ratingCount').textContent = allRatings.length;
                    
                    // Cache the data after first page load
                    if (currentPage === 2 && !skipCache) {
                        saveToCache(allRatings, currentTotalPages);
                    }
                } else if (allRatings.length === 0) {
                    document.getElementById('noMore').style.display = 'block';
                }
                
                if (!hasNextPage || data.ratings.length === 0) {
                    document.getElementById('noMore').style.display = 'block';
                }
                
                document.getElementById('loading').style.display = 'none';
                isLoading = false;
                updateCacheDisplay();
            })
            .catch(error => {
                console.error(error);
                document.getElementById('loading').style.display = 'none';
                isLoading = false;
                showNotification('Error loading data: ' + error.message, 'error');
            });
        }
        
        // Load more pages for infinite scroll
        function loadMore() {
            if (isLoading || !hasNextPage) return;
            loadData();
        }
        
        function setSort(sort) {
            currentSort = sort;
            // Update active button style
            document.querySelectorAll('.sort-btn').forEach(btn => {
                if (btn.getAttribute('data-sort') === sort) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            sortAndRender();
        }
        
        function sortAndRender() {
            const sorted = [...allRatings];
            
            if (currentSort === 'price_desc') {
                sorted.sort((a, b) => (b.price || 0) - (a.price || 0));
            } else if (currentSort === 'price_asc') {
                sorted.sort((a, b) => (a.price || 0) - (b.price || 0));
            }
            
            renderRatings(sorted);
        }
        
        function formatNumber(value, decimals = 2) {
            if (value === null || value === undefined || isNaN(value)) return null;
            return Number(value).toFixed(decimals);
        }
        
        function renderRatings(ratings) {
            const tbody = document.getElementById('ratingsBody');
            tbody.innerHTML = '';
            
            if (!ratings || ratings.length === 0) {
                const row = tbody.insertRow();
                const cell = row.insertCell(0);
                cell.colSpan = 7;
                cell.textContent = 'No ratings found';
                cell.style.textAlign = 'center';
                cell.style.padding = '40px';
                return;
            }
            
            ratings.forEach(rating => {
                const row = tbody.insertRow();
                row.setAttribute('data-company', (rating.company || '').toLowerCase());
                row.setAttribute('data-rating', rating.rating || '');
                row.setAttribute('data-analyst', (rating.analyst || '').toLowerCase());
                
                // Date
                row.insertCell(0).textContent = rating.date_display || '-';
                
                // Company + Rating
                const companyCell = row.insertCell(1);
                const companyLink = document.createElement('a');
                companyLink.href = rating.analysis_url || '#';
                companyLink.className = 'company-link';
                companyLink.target = '_blank';
                companyLink.textContent = rating.company || '-';
                companyCell.appendChild(companyLink);
                const ratingBadge = document.createElement('span');
                const ratingClass = rating.rating || 'Hold';
                ratingBadge.className = `rating-badge rating-${ratingClass}`;
                ratingBadge.textContent = ratingClass;
                companyCell.appendChild(ratingBadge);
                
                // Price
                const priceCell = row.insertCell(2);
                if (rating.price && !isNaN(rating.price)) {
                    const formattedPrice = formatNumber(rating.price, 2);
                    priceCell.innerHTML = `<span class="current-price">${rating.currency || 'USD'} ${formattedPrice}</span>`;
                } else {
                    priceCell.textContent = 'N/A';
                }
                
                // Change
                const changeCell = row.insertCell(3);
                if (rating.price_change !== null && rating.price_change !== undefined && !isNaN(rating.price_change)) {
                    const changeValue = parseFloat(rating.price_change);
                    const changePercent = rating.price_change_percent ? parseFloat(rating.price_change_percent) : 0;
                    const changeClass = changeValue > 0 ? 'price-up' : (changeValue < 0 ? 'price-down' : '');
                    const arrow = changeValue > 0 ? '▲' : (changeValue < 0 ? '▼' : '●');
                    const formattedChange = formatNumber(Math.abs(changeValue), 2);
                    const formattedPercent = formatNumber(Math.abs(changePercent), 2);
                    changeCell.innerHTML = `<span class="${changeClass}">${arrow} ${changeValue > 0 ? '+' : '-'}${formattedChange} (${changePercent > 0 ? '+' : '-'}${formattedPercent}%)</span>`;
                } else {
                    changeCell.textContent = 'N/A';
                }
                
                // Analyst
                const analystCell = row.insertCell(4);
                const analystLink = document.createElement('a');
                analystLink.href = `?action=${currentAction}&analyst=${encodeURIComponent(rating.analyst || '')}`;
                analystLink.className = 'analyst-link';
                analystLink.textContent = rating.analyst || '-';
                analystCell.appendChild(analystLink);
                
                // Link (View)
                const linkCell = row.insertCell(5);
                const viewLink = document.createElement('a');
                viewLink.href = rating.analysis_url || '#';
                viewLink.className = 'action-link link-view';
                viewLink.target = '_blank';
                viewLink.innerHTML = '🔗 View';
                linkCell.appendChild(viewLink);
                
                // Process
                const processCell = row.insertCell(6);
                const processLink = document.createElement('a');
                processLink.href = rating.process_url || `process.php?symbol=${encodeURIComponent(rating.symbol || '')}`;
                processLink.className = 'action-link link-process';
                processLink.innerHTML = '⚙️ Process';
                processLink.target = '_self';
                processCell.appendChild(processLink);
            });
        }
        
        function filterTable() {
            const companyFilter = document.getElementById('companyFilter').value.toLowerCase();
            const ratingFilter = document.getElementById('ratingFilter').value;
            const analystFilter = document.getElementById('analystFilter').value.toLowerCase();
            
            const filtered = allRatings.filter(r => {
                const companyMatch = !companyFilter || (r.company || '').toLowerCase().includes(companyFilter);
                const ratingMatch = !ratingFilter || (r.rating || '') === ratingFilter;
                const analystMatch = !analystFilter || (r.analyst || '').toLowerCase().includes(analystFilter);
                return companyMatch && ratingMatch && analystMatch;
            });
            
            renderRatings(filtered);
            document.getElementById('ratingCount').textContent = filtered.length;
        }
        
        // Export handlers
        document.getElementById('exportJsonBtn').addEventListener('click', (e) => {
            e.preventDefault();
            if (allRatings.length === 0) {
                showNotification('No data to export', 'error');
                return;
            }
            const dataStr = JSON.stringify(allRatings, null, 2);
            const blob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `ratings_${currentAction}_${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            showNotification('JSON exported successfully', 'success');
        });
        
        document.getElementById('exportCsvBtn').addEventListener('click', (e) => {
            e.preventDefault();
            if (allRatings.length === 0) {
                showNotification('No data to export', 'error');
                return;
            }
            
            const headers = ['Date', 'Company', 'Rating', 'Analyst', 'Price', 'Change', 'Analysis URL', 'Process URL'];
            const rows = allRatings.map(r => [
                r.date_display || '',
                r.company || '',
                r.rating || '',
                r.analyst || '',
                r.price ? `${r.currency || 'USD'} ${formatNumber(r.price, 2)}` : 'N/A',
                r.price_change ? `${r.price_change > 0 ? '+' : ''}${formatNumber(r.price_change, 2)} (${formatNumber(r.price_change_percent, 2)}%)` : 'N/A',
                r.analysis_url || '',
                r.process_url || ''
            ]);
            
            const csvContent = [headers, ...rows].map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n');
            const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `ratings_${currentAction}_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            showNotification('CSV exported successfully', 'success');
        });
        
        // Setup event listeners for navigation tabs
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                const action = tab.getAttribute('data-action');
                if (action) {
                    changeAction(action);
                }
            });
        });
        
        // Infinite scroll
        window.addEventListener('scroll', function() {
            if (isLoading || !hasNextPage) return;
            if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 500) {
                loadMore();
            }
        });
        
        // Scroll to top button
        window.addEventListener('scroll', function() {
            const scrollTop = document.getElementById('scrollTop');
            if (window.scrollY > 300) scrollTop.classList.add('visible');
            else scrollTop.classList.remove('visible');
        });
        
        // Initialize
        updateCacheDisplay();
        loadData();
        
        // Add CSS animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInOut {
                0% { opacity: 0; transform: translateY(20px); }
                15% { opacity: 1; transform: translateY(0); }
                85% { opacity: 1; transform: translateY(0); }
                100% { opacity: 0; transform: translateY(-20px); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>