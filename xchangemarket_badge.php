<?php
$symbol = isset($_GET['q']) ? strtoupper(trim($_GET['q'])) : '';
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        .gettex-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: normal;
            cursor: help;
            transition: all 0.2s ease;
            text-decoration: none;
            font-family: Arial, sans-serif;
        }
        .gettex-available {
            background: #d4edda;
            color: #155724;
            border-left: 3px solid #28a745;
        }
        .gettex-not-in-json {
            background: #fff3cd;
            color: #856404;
            border-left: 3px solid #ffc107;
        }
        .gettex-not-in-json button {
            background: #f0b90b;
            color: #fff;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            margin-left: 6px;
        }
        .gettex-not-in-json button:hover {
            background: #d4a00a;
        }
        .gettex-missing-isin {
            background: #f8d7da;
            color: #721c24;
            border-left: 3px solid #dc3545;
        }
        .gettex-unavailable {
            background: #e2e3e5;
            color: #383d41;
            border-left: 3px solid #6c757d;
        }
        .gettex-loading {
            background: #e2e3e5;
            color: #383d41;
            animation: pulse 1.5s ease-in-out infinite;
        }
        .gettex-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 3px solid #dc3545;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
    </style>
</head>
<body>
<span id="gettex-badge">⏳ checking gettex...</span>

<script>
function checkGettex(symbol) {
    const badge = document.getElementById('gettex-badge');
    if (!badge) return;
    
    badge.innerHTML = '<span class="gettex-badge gettex-loading">⏳ checking gettex...</span>';
    
    // Use absolute path to fix iframe issues
    const apiUrl = window.location.protocol + '//' + window.location.host + 
                   window.location.pathname.replace('xchangemarket_badge.php', 'xchangemarketcheck_gettex.php') + 
                   '?q=' + encodeURIComponent(symbol);
    
    console.log('Fetching:', apiUrl);
    
    fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            console.log('gettex response for', symbol, ':', data);
            
            // CASE 1: Symbol found in JSON AND on gettex (gettex = yes)
            if (data && data.status === "found" && data.gettex === "yes") {
                let tooltipText = '✓ Available on gettex for Pure Depot trading';
                if (data.isin) tooltipText += ' | ISIN: ' + data.isin;
                if (data.price) tooltipText += ' | Price: ' + data.price;
                badge.innerHTML = '<span class="gettex-badge gettex-available" title="' + tooltipText + '">🏛️ ✓ Pure Depot (gettex)</span>';
            }
            // CASE 2: Symbol found in JSON, missing ISIN
            else if (data && data.status === "found" && data.gettex === "no" && !data.isin) {
                const buyUrl = 'buy.php?go=' + encodeURIComponent(symbol);
                badge.innerHTML = '<a href="' + buyUrl + '" target="_blank" class="gettex-badge gettex-missing-isin" title="ISIN missing - Click to add">🏛️ ✗ Missing ISIN</a>';
            }
            // CASE 3: Symbol found in JSON, has ISIN but NOT on gettex
            else if (data && data.status === "found" && data.gettex === "no" && data.isin) {
                badge.innerHTML = '<span class="gettex-badge gettex-unavailable" title="Not traded on gettex">🏛️ ✗ Not on gettex</span>';
            }
            // CASE 4: ISIN checked (not in JSON) - gettex = yes
            else if (data && data.status === "isin_checked" && data.gettex === "yes") {
                badge.innerHTML = '<span class="gettex-badge gettex-available" title="ISIN found on Gettex (not in your JSON yet)">🏛️ ✓ ISIN on Gettex</span>';
            }
            // CASE 5: ISIN checked (not in JSON) - gettex = no
            else if (data && data.status === "isin_checked" && data.gettex === "no") {
                badge.innerHTML = '<span class="gettex-badge gettex-unavailable" title="ISIN not found on Gettex">🏛️ ✗ ISIN not on Gettex</span>';
            }
 // CASE 6: Symbol NOT IN JSON - with clickable TradingView button using top-level navigation
else if (data && data.status === "not_found") {
    const tradingViewUrl = 'https://www.tradingview.com/chart/?symbol=' + encodeURIComponent(symbol);
    badge.innerHTML = '<span class="gettex-badge gettex-not-in-json" title="Symbol not found in stocks.json">📋 ✗ NOT IN JSON <button onclick="window.top.open(\'' + tradingViewUrl + '\', \'_blank\'); event.stopPropagation();">TrdVw</button></span>';
}
            // CASE 7: Symbol found in JSON via ISIN lookup
            else if (data && data.status === "found_in_json") {
                if (data.gettex === 'yes') {
                    badge.innerHTML = '<span class="gettex-badge gettex-available" title="Found in JSON and on Gettex">🏛️ ✓ Pure Depot (gettex)</span>';
                } else {
                    badge.innerHTML = '<span class="gettex-badge gettex-unavailable" title="Found in JSON but not on Gettex">🏛️ ✗ Not on gettex</span>';
                }
            }
            // CASE 8: Unknown
            else {
                badge.innerHTML = '<span class="gettex-badge gettex-error" title="' + JSON.stringify(data) + '">❌ Unknown: ' + (data.status || 'no status') + '</span>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            badge.innerHTML = '<span class="gettex-badge gettex-error">❌ Check failed: ' + error.message + '</span>';
        });
}

<?php if ($symbol): ?>
checkGettex('<?php echo addslashes($symbol); ?>');
<?php else: ?>
document.getElementById('gettex-badge').innerHTML = '<span class="gettex-badge gettex-error">❌ No symbol provided</span>';
<?php endif; ?>
</script>
</body>
</html>