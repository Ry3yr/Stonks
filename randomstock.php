<?php
// Increase memory limit to 512MB
ini_set('memory_limit', '512M');

// Custom error handler
function errorHandler($errno, $errstr, $errfile, $errline) {
    echo json_encode(['error' => "$errstr in $errfile on line $errline"]);
    exit;
}
set_error_handler("errorHandler");

/**
 * Stock Symbol Checker - Picks random valid ISIN and opens process.php
 */

// Check if ISIN cache exists locally
$cacheFile = __DIR__ . '/gettex_isins_cache.json';
$notAStockFile = __DIR__ . '/notastock.json';
$stocksFile = __DIR__ . '/stocks.json';

// If cache doesn't exist or is older than 24 hours, update it
$needUpdate = true;
if (file_exists($cacheFile)) {
    $cache = json_decode(file_get_contents($cacheFile), true);
    if ($cache && isset($cache['timestamp']) && (time() - $cache['timestamp']) < 86400) {
        $needUpdate = false;
    }
}

if ($needUpdate) {
    updateAllIsinsCache();
}

// Get all ISINs from local storage
$allIsins = getAllStoredIsins();

if (empty($allIsins)) {
    die("No ISINs found in cache");
}

// Load existing valid ISINs from stocks.json
$existingIsins = [];
if (file_exists($stocksFile)) {
    $stocks = json_decode(file_get_contents($stocksFile), true);
    if ($stocks) {
        foreach ($stocks as $item) {
            if (!empty($item['isin'])) {
                $existingIsins[] = $item['isin'];
            }
        }
    }
}

// Load invalid ISINs from notastock.json
$invalidIsins = [];
if (file_exists($notAStockFile)) {
    $invalidIsins = json_decode(file_get_contents($notAStockFile), true);
    if (!$invalidIsins) {
        $invalidIsins = [];
    }
}

// Filter out ISINs that are already used or known invalid
$candidateIsins = array_diff($allIsins, $existingIsins, $invalidIsins);
$candidateIsins = array_values($candidateIsins); // Reindex

if (empty($candidateIsins)) {
    die("No candidate ISINs available. All ISINs are either used or marked invalid.");
}

// Find a valid ISIN by checking Yahoo Finance
$validIsin = null;
$checkedIsins = [];
$invalidFound = [];

// Shuffle candidates to randomize
shuffle($candidateIsins);

foreach ($candidateIsins as $testIsin) {
    $checkedIsins[] = $testIsin;
    
    // Check Yahoo Finance
    $isValid = checkYahooFinance($testIsin);
    
    if ($isValid) {
        $validIsin = $testIsin;
        break;
    } else {
        // Add to invalid list and save immediately
        if (!in_array($testIsin, $invalidIsins)) {
            $invalidIsins[] = $testIsin;
            file_put_contents($notAStockFile, json_encode($invalidIsins, JSON_PRETTY_PRINT));
        }
        $invalidFound[] = $testIsin;
    }
}

if (!$validIsin) {
    die("No valid ISIN found after checking " . count($checkedIsins) . " candidates.");
}

// Output HTML with the valid ISIN
$processUrl = "process.php?symbol=" . urlencode($validIsin) . "&currency=USD";

echo '<!DOCTYPE html>
<html>
<head>
    <title>Valid ISIN Found</title>
    <style>
        body { font-family: monospace; margin: 50px; text-align: center; }
        .isin { font-size: 28px; font-weight: bold; margin: 20px; padding: 15px; background: #d4edda; color: #155724; border-radius: 5px; }
        .message { color: #28a745; margin: 10px; font-size: 18px; }
        .invalid-list { margin: 20px auto; padding: 10px; background: #f8d7da; color: #721c24; border-radius: 5px; max-width: 600px; }
        .invalid-item { text-decoration: line-through; margin: 5px; display: inline-block; }
        .button { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 20px; cursor: pointer; border: none; font-size: 16px; }
        .button:hover { background: #0056b3; }
        .count { margin: 10px; color: #666; }
        .info { background: #e8f4f8; padding: 15px; border-radius: 5px; margin: 20px auto; max-width: 600px; text-align: left; }
    </style>
</head>
<body>
    <h1>Valid ISIN Found!</h1>
    <div class="info">
        <div class="count">Total ISINs in cache: ' . count($allIsins) . '</div>
        <div class="count">ISINs already in stocks.json: ' . count($existingIsins) . '</div>
        <div class="count">Invalid ISINs (notastock.json): ' . count($invalidIsins) . '</div>
        <div class="count">Checked this session: ' . count($checkedIsins) . '</div>
    </div>
    
    ' . (count($invalidFound) > 0 ? '
    <div class="invalid-list">
        <strong>Skipped (not valid):</strong><br>
        ' . implode(', ', array_map(function($i) { return '<span class="invalid-item">' . $i . '</span>'; }, $invalidFound)) . '
    </div>
    ' : '') . '
    
    <div class="message">✓ Valid ISIN found and ready to open</div>
    <div class="isin">' . $validIsin . '</div>
    
    <form id="redirectForm" action="process.php" method="get" target="_blank">
        <input type="hidden" name="symbol" value="' . $validIsin . '">
        <input type="hidden" name="currency" value="USD">
        <input type="submit" value="Open in process.php" class="button">
    </form>
    
    <br>
    <a href="" onclick="location.reload(); return false;" class="button">Find Another Random ISIN</a>
    
    <script>
        // Auto-submit the form on load
        document.getElementById("redirectForm").submit();
    </script>
</body>
</html>';

/**
 * Check if ISIN is valid via Yahoo Finance
 */
function checkYahooFinance($isin) {
    $url = "https://query1.finance.yahoo.com/v1/finance/search?q=" . urlencode($isin) . "&quotesCount=5&newsCount=0";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        return false;
    }
    
    $data = json_decode($response, true);
    
    // Check if quotes array is empty or count is 0
    if (isset($data['quotes']) && count($data['quotes']) > 0) {
        return true;
    }
    
    return false;
}

/**
 * Get all stored ISINs from local cache file
 */
function getAllStoredIsins() {
    $cacheFile = __DIR__ . '/gettex_isins_cache.json';
    
    if (!file_exists($cacheFile)) {
        return [];
    }
    
    $cache = json_decode(file_get_contents($cacheFile), true);
    if (!$cache || !isset($cache['isins'])) {
        return [];
    }
    
    return $cache['isins'];
}

/**
 * Update all ISINs cache from Gettex
 */
function updateAllIsinsCache() {
    $cacheFile = __DIR__ . '/gettex_isins_cache.json';
    
    // Fetch Gettex page
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.gettex.de/handel/delayed-data/posttrade-data/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$html) {
        return false;
    }
    
    // Extract CSV file URLs
    preg_match_all('/href="(https:\/\/erdk\.bayerische-boerse\.de:8000\/delayed-data\/[^"]+\.csv\.gz)"/', $html, $matches);
    if (empty($matches[1])) {
        return false;
    }
    
    $allIsins = [];
    
    foreach ($matches[1] as $url) {
        $isins = extractAllIsinsFromGzippedCSV($url);
        $allIsins = array_merge($allIsins, $isins);
    }
    
    // Remove duplicates
    $allIsins = array_values(array_unique($allIsins));
    
    // Save to cache
    $cacheData = [
        'timestamp' => time(),
        'count' => count($allIsins),
        'isins' => $allIsins
    ];
    
    file_put_contents($cacheFile, json_encode($cacheData));
    
    return $allIsins;
}

/**
 * Extract all ISINs from gzipped CSV file
 */
function extractAllIsinsFromGzippedCSV($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    
    $tempFile = tmpfile();
    curl_setopt($ch, CURLOPT_FILE, $tempFile);
    
    $success = curl_exec($ch);
    curl_close($ch);
    
    if (!$success) {
        if (is_resource($tempFile)) fclose($tempFile);
        return [];
    }
    
    $metaData = stream_get_meta_data($tempFile);
    $tempFilePath = $metaData['uri'];
    
    $gz = gzopen($tempFilePath, 'rb');
    if (!$gz) {
        fclose($tempFile);
        return [];
    }
    
    $isins = [];
    
    // Skip header
    gzgets($gz);
    
    // Read all ISINs
    while (!gzeof($gz)) {
        $line = gzgets($gz, 4096);
        if (trim($line) === '') continue;
        
        $columns = str_getcsv($line);
        if (!empty($columns[0]) && preg_match('/^[A-Z]{2}[A-Z0-9]{10}$/', $columns[0])) {
            $isins[] = $columns[0];
        }
    }
    
    gzclose($gz);
    fclose($tempFile);
    
    return $isins;
}
?>