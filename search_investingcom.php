<?php
function searchInvestingCom(string $query): array {
    $url = 'https://api.investing.com/api/search/v2/search?'
         . http_build_query(['q' => $query, 'lang_ID' => 1, 'limit' => 5]);

    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
            . 'AppleWebKit/537.36 (KHTML, like Gecko) '
            . 'Chrome/124.0.0.0 Safari/537.36',
        'Accept: application/json, text/plain, */*',
        'Accept-Language: en-US,en;q=0.9',
        'Domain-Id: www.investing.com',
        'Origin: https://www.investing.com',
        'Referer: https://www.investing.com/',
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET        => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_COOKIEFILE     => '',
        CURLOPT_ENCODING       => '',
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError)        return ['error' => 'cURL error: ' . $curlError];
    if ($httpCode !== 200) return ['error' => 'HTTP ' . $httpCode];

    $data = json_decode($response, true);

    if (
        !is_array($data)
        || empty($data['quotes'])
        || !is_array($data['quotes'][0])
        || empty($data['quotes'][0]['url'])
    ) {
        return ['error' => 'No results found for: ' . $query];
    }

    return ['url' => 'https://www.investing.com' . $data['quotes'][0]['url']];
}

function searchYahooFinance(string $query): array {
    $url = 'https://query1.finance.yahoo.com/v1/finance/search?'
         . http_build_query([
             'q' => $query,
             'count' => 5,
             'lang' => 'en-US',
             'region' => 'US'
         ]);

    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
            . 'AppleWebKit/537.36 (KHTML, like Gecko) '
            . 'Chrome/124.0.0.0 Safari/537.36',
        'Accept: application/json, text/plain, */*',
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET        => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING       => '',
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['error' => 'Yahoo cURL error: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        return ['error' => 'Yahoo HTTP ' . $httpCode];
    }

    $data = json_decode($response, true);

    if (
        !is_array($data) 
        || empty($data['quotes']) 
        || !is_array($data['quotes'][0])
        || empty($data['quotes'][0]['shortname'])
    ) {
        return ['error' => 'No Yahoo Finance results found for: ' . $query];
    }

    $firstQuote = $data['quotes'][0];
    
    return [
        'shortname' => $firstQuote['shortname'],
        'symbol'    => $firstQuote['symbol'] ?? 'N/A',
        'quoteType' => $firstQuote['quoteType'] ?? 'N/A',
        'exchange'  => $firstQuote['exchange'] ?? 'N/A',
        'fullData'  => $firstQuote
    ];
}

function searchWithYahooFallback(string $originalQuery): array {
    // First try Investing.com
    $investingResult = searchInvestingCom($originalQuery);
    
    // If Investing.com succeeded, return it
    if (!isset($investingResult['error'])) {
        return $investingResult;
    }
    
    // Otherwise, fallback to Yahoo Finance
    $yahooResult = searchYahooFinance($originalQuery);
    
    if (isset($yahooResult['error'])) {
        return ['error' => 'Both Investing.com and Yahoo Finance failed. ' 
                . $yahooResult['error']];
    }
    
    // Try retrying Investing.com with the Yahoo shortname
    $retryQuery = $yahooResult['shortname'];
    $retryResult = searchInvestingCom($retryQuery);
    
    if (!isset($retryResult['error'])) {
        return $retryResult; // Success with retry
    }
    
    // Still failed - return suggestion data for the HTML form
    return [
        'needs_manual' => true,
        'suggestion' => $yahooResult['shortname'],
        'symbol' => $yahooResult['symbol'],
        'original_query' => $originalQuery,
        'yahoo_url' => 'https://finance.yahoo.com/quote/' . urlencode($yahooResult['symbol'])
    ];
}

// ── Entry point ───────────────────────────────────────────────────────────────

// Check if this is a retry request (from the form)
$retryQuery = trim($_GET['retry'] ?? '');
if ($retryQuery !== '') {
    $result = searchInvestingCom($retryQuery);
    
    if (!isset($result['error'])) {
        // Success on retry - redirect
        if (!headers_sent()) {
            header('Location: ' . $result['url'], true, 302);
            exit;
        }
        $safeUrl = htmlspecialchars($result['url'], ENT_QUOTES, 'UTF-8');
        echo '<meta http-equiv="refresh" content="0;url=' . $safeUrl . '">';
        exit;
    }
    
    // Retry still failed - show form again with the same suggestion
    $suggestion = htmlspecialchars($retryQuery, ENT_QUOTES, 'UTF-8');
    $originalQuery = htmlspecialchars($_GET['original'] ?? $retryQuery, ENT_QUOTES, 'UTF-8');
    showManualForm($suggestion, $originalQuery, 'Still no results for: ' . htmlspecialchars($retryQuery, ENT_QUOTES, 'UTF-8'));
    exit;
}

// Normal flow
$query = trim($_GET['search'] ?? '');

if ($query === '') {
    http_response_code(400);
    die('Error: ?search= parameter is required. Example: ?search=GME');
}

$result = searchWithYahooFallback($query);

if (isset($result['error'])) {
    http_response_code(404);
    die('Error: ' . htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8'));
}

// If manual input is needed, show the form
if (isset($result['needs_manual']) && $result['needs_manual'] === true) {
    showManualForm($result['suggestion'], $result['original_query']);
    exit;
}

// Normal redirect to Investing.com
if (!headers_sent()) {
    header('Location: ' . $result['url'], true, 302);
    exit;
}

$safeUrl = htmlspecialchars($result['url'], ENT_QUOTES, 'UTF-8');
echo '<meta http-equiv="refresh" content="0;url=' . $safeUrl . '">';
exit;

// ── Helper function to display the manual form ─────────────────────────────────

function showManualForm(string $suggestion, string $originalQuery, string $errorMessage = '') {
    $safeSuggestion = htmlspecialchars($suggestion, ENT_QUOTES, 'UTF-8');
    $safeOriginal = htmlspecialchars($originalQuery, ENT_QUOTES, 'UTF-8');
    $safeError = htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Search Failed - Try Again</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
                max-width: 600px;
                margin: 50px auto;
                padding: 20px;
                background: #f5f5f5;
            }
            .container {
                background: white;
                border-radius: 12px;
                padding: 30px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                text-align: center;
            }
            h1 {
                color: #e67e22;
                font-size: 24px;
                margin-bottom: 10px;
            }
            .warning-icon {
                font-size: 48px;
                margin-bottom: 10px;
            }
            .original-search {
                background: #f8f9fa;
                padding: 10px;
                border-radius: 6px;
                margin: 15px 0;
                color: #666;
                font-size: 14px;
            }
            .suggestion-box {
                margin: 25px 0;
            }
            label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #333;
            }
            input[type="text"] {
                width: 100%;
                padding: 12px;
                font-size: 16px;
                border: 2px solid #ddd;
                border-radius: 8px;
                box-sizing: border-box;
                transition: border-color 0.3s;
            }
            input[type="text"]:focus {
                outline: none;
                border-color: #e67e22;
            }
            .button-group {
                display: flex;
                gap: 10px;
                margin-top: 20px;
                justify-content: center;
            }
            button {
                padding: 10px 24px;
                font-size: 16px;
                font-weight: 600;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.3s;
            }
            .btn-primary {
                background: #e67e22;
                color: white;
            }
            .btn-primary:hover {
                background: #d35400;
                transform: translateY(-1px);
            }
            .btn-secondary {
                background: #95a5a6;
                color: white;
            }
            .btn-secondary:hover {
                background: #7f8c8d;
            }
            .btn-yahoo {
                background: #7c3aed;
                color: white;
            }
            .btn-yahoo:hover {
                background: #6d28d9;
            }
            .error-message {
                background: #fee2e2;
                color: #dc2626;
                padding: 10px;
                border-radius: 6px;
                margin-bottom: 20px;
                font-size: 14px;
            }
            .info-text {
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #eee;
                font-size: 13px;
                color: #888;
            }
            hr {
                margin: 20px 0;
                border: none;
                border-top: 1px solid #eee;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="warning-icon">🔍</div>
            <h1>Could Not Find Match</h1>
            <p>Investing.com couldn't find results for "<strong><?php echo $safeOriginal; ?></strong>"</p>
            
            <?php if ($safeError): ?>
                <div class="error-message">
                    ⚠️ <?php echo $safeError; ?>
                </div>
            <?php endif; ?>
            
            <div class="original-search">
                💡 Yahoo Finance suggests: <strong><?php echo $safeSuggestion; ?></strong>
            </div>
            
            <div class="suggestion-box">
                <label for="searchQuery">Try a different search term:</label>
                <input type="text" id="searchQuery" value="<?php echo $safeSuggestion; ?>" 
                       placeholder="e.g., Apple Inc., TSLA, Bitcoin">
                
                <div class="button-group">
                    <button class="btn-primary" onclick="searchInNewTab()">
                        🔗 Try Again (New Tab)
                    </button>
                    <button class="btn-yahoo" onclick="openYahooFinance()">
                        📈 Open on Yahoo Finance
                    </button>
                    <button class="btn-secondary" onclick="goBack()">
                        ← Go Back
                    </button>
                </div>
            </div>
            
            <hr>
            
            <div class="info-text">
                💡 <strong>Tip:</strong> Try using the full company name (e.g., "Apple Inc." instead of "AAPL")<br>
                The suggested term above comes from Yahoo Finance's search API.
            </div>
        </div>
        
        <script>
            function searchInNewTab() {
                const query = document.getElementById('searchQuery').value.trim();
                if (query === '') {
                    alert('Please enter a search term');
                    return;
                }
                // Open in new tab with retry parameter
                const url = window.location.pathname + '?retry=' + encodeURIComponent(query) 
                          + '&original=' + encodeURIComponent('<?php echo $safeOriginal; ?>');
                window.open(url, '_blank');
            }
            
            function openYahooFinance() {
                const query = document.getElementById('searchQuery').value.trim();
                if (query === '') return;
                // Try to extract symbol or use as-is
                const yahooUrl = 'https://finance.yahoo.com/quote/' + encodeURIComponent(query);
                window.open(yahooUrl, '_blank');
            }
            
            function goBack() {
                window.history.back();
            }
            
            // Allow Enter key to submit
            document.getElementById('searchQuery').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchInNewTab();
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>