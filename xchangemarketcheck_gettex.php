<?php
// Increase memory limit to 512MB
ini_set('memory_limit', '512M');

// CORS headers for iframe support
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Content-Type: application/json');

// Custom error handler
function errorHandler($errno, $errstr, $errfile, $errline) {
    echo json_encode(['error' => "$errstr in $errfile on line $errline"]);
    exit;
}
set_error_handler("errorHandler");

/**
 * Stock Symbol Checker API
 * Usage: ?q=SYMBOL (max 6 chars) or ?q=ISIN (8+ chars)
 */

$input = isset($_GET['q']) ? strtoupper(trim($_GET['q'])) : '';

if (empty($input)) {
    echo json_encode(['error' => 'No input provided. Use ?q=SYMBOL or ?q=ISIN']);
    exit;
}

// Read stocks.json from same directory
$jsonFile = __DIR__ . '/stocks.json';
if (!file_exists($jsonFile)) {
    echo json_encode(['error' => 'stocks.json not found', 'path' => $jsonFile]);
    exit;
}

$stocks = json_decode(file_get_contents($jsonFile), true);
if (!$stocks) {
    echo json_encode(['error' => 'stocks.json is invalid']);
    exit;
}

// Determine input type
$isSymbol = strlen($input) <= 6;
$isIsin = strlen($input) >= 8;

$found = null;
$isinToCheck = null;
$inJson = false;

if ($isSymbol) {
    // Search for symbol in stocks.json
    foreach ($stocks as $item) {
        if (strtoupper($item['stock']) === $input) {
            $found = $item;
            $inJson = true;
            $isinToCheck = $found['isin'] ?? null;
            break;
        }
    }
    
    if (!$found) {
        echo json_encode([
            'status' => 'not_found',
            'symbol' => $input,
            'injson' => false,
            'gettex' => 'no'
        ]);
        exit;
    }
} elseif ($isIsin) {
    // Input is ISIN
    $isinToCheck = $input;
    
    // Check if this ISIN exists in stocks.json
    foreach ($stocks as $item) {
        if (!empty($item['isin']) && strtoupper($item['isin']) === $input) {
            $found = $item;
            $inJson = true;
            break;
        }
    }
    
    // Check Gettex for the ISIN
    $inGettex = isIsinInGettex($isinToCheck);
    
    if ($inJson && $found) {
        echo json_encode([
            'status' => 'found_in_json',
            'symbol' => $found['stock'],
            'isin' => $found['isin'],
            'injson' => true,
            'gettex' => $inGettex ? 'yes' : 'no',
            'price' => $found['price'],
            'currency' => $found['currency'],
            'date' => $found['date'],
            'exchange_market' => $found['exchange_market'],
            'depot' => $found['depot'] ?? null,
            'nrbght' => $found['nrbght'] ?? null
        ]);
    } else {
        echo json_encode([
            'status' => 'isin_checked',
            'submitted_isin' => $input,
            'injson' => false,
            'gettex' => $inGettex ? 'yes' : 'no',
            'note' => 'ISIN checked against Gettex data'
        ]);
    }
    exit;
} else {
    echo json_encode([
        'error' => 'Ambiguous input length',
        'input' => $input,
        'length' => strlen($input),
        'note' => 'Symbols should be max 6 chars, ISIN should be 8+ chars'
    ]);
    exit;
}

// Symbol found in JSON
$inGettex = false;
if (!empty($isinToCheck)) {
    $inGettex = isIsinInGettex($isinToCheck);
}

echo json_encode([
    'status' => 'found',
    'symbol' => $found['stock'],
    'injson' => true,
    'isin' => $found['isin'] ?? null,
    'gettex' => $inGettex ? 'yes' : 'no',
    'price' => $found['price'],
    'currency' => $found['currency'],
    'date' => $found['date'],
    'exchange_market' => $found['exchange_market'],
    'depot' => $found['depot'] ?? null,
    'nrbght' => $found['nrbght'] ?? null
]);

/**
 * Check if ISIN exists in Gettex data using streaming (memory optimized)
 */
function isIsinInGettex($isin) {
    $cacheFile = __DIR__ . '/gettex_cache.json';
    $cacheExpiry = 3600; // 1 hour cache
    
    // Check cache
    if (file_exists($cacheFile)) {
        $cacheContent = file_get_contents($cacheFile);
        if ($cacheContent) {
            $cache = json_decode($cacheContent, true);
            if ($cache && isset($cache['timestamp']) && (time() - $cache['timestamp']) < $cacheExpiry) {
                if (isset($cache['data'][$isin])) {
                    return $cache['data'][$isin];
                }
            }
        }
    }
    
    // Fetch Gettex page with cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.gettex.de/handel/delayed-data/posttrade-data/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
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
        // Check if ISIN exists in this CSV file using streaming
        if (streamSearchInGzippedCSV($url, $isin)) {
            // Cache positive result
            $cacheData = [
                'timestamp' => time(), 
                'data' => [$isin => true]
            ];
            file_put_contents($cacheFile, json_encode($cacheData));
            return true;
        }
    }
    
    // Cache negative result
    $cacheData = [
        'timestamp' => time(), 
        'data' => [$isin => false]
    ];
    file_put_contents($cacheFile, json_encode($cacheData));
    
    return false;
}

/**
 * Stream a gzipped CSV file line by line and search for ISIN
 * Memory usage: ~1MB regardless of file size
 */
function streamSearchInGzippedCSV($url, $searchIsin) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Don't buffer in memory
    
    // Write to temporary file
    $tempFile = tmpfile();
    curl_setopt($ch, CURLOPT_FILE, $tempFile);
    
    $success = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if (!$success) {
        if (is_resource($tempFile)) fclose($tempFile);
        return false;
    }
    
    // Get temp file path
    $metaData = stream_get_meta_data($tempFile);
    $tempFilePath = $metaData['uri'];
    
    // Open gzipped file stream
    $gz = gzopen($tempFilePath, 'rb');
    if (!$gz) {
        fclose($tempFile);
        return false;
    }
    
    // Read line by line (each line is one CSV row)
    $found = false;
    while (!gzeof($gz)) {
        $line = gzgets($gz, 4096); // Read 4KB chunks
        if (strpos($line, $searchIsin) !== false) {
            $found = true;
            break;
        }
    }
    
    gzclose($gz);
    fclose($tempFile);
    
    return $found;
}
?>