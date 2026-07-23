
<?php
ini_set('memory_limit', '512M');
set_time_limit(0);

function errorHandler($errno, $errstr, $errfile, $errline) {
    echo json_encode(['error' => "$errstr in $errfile on line $errline"]);
    exit;
}
set_error_handler("errorHandler");

define('SYMBOL_RE', '[A-Z]{1,6}(?:-[A-Z]{1,3})?');

$ajaxMode = $_GET['ajax'] ?? '';

$cacheFile     = __DIR__ . '/symbols_cache.json';
$notAStockFile = __DIR__ . '/notastock2.json';
$stocksFile    = __DIR__ . '/stocks.json';
$progressFile  = __DIR__ . '/progress.json';
$foundFile     = __DIR__ . '/found_prebreakout.json';

if ($ajaxMode === 'start') {
    header('Content-Type: application/json');
    $maxPrice = isset($_GET['maxPrice']) ? (float)$_GET['maxPrice'] : 4.0;
    file_put_contents($progressFile, json_encode([
        'status' => 'starting',
        'checked' => 0,
        'validFound' => 0,
        'notReady' => 0,
        'invalid' => 0,
        'currentSymbol' => '',
        'done' => false,
        'result' => null,
        'maxPrice' => $maxPrice,
        'started' => time()
    ]));
    echo json_encode(['ok' => true]);
    exit;
}

if ($ajaxMode === 'poll') {
    header('Content-Type: application/json');
    if (!file_exists($progressFile)) {
        echo json_encode(['error' => 'Progress file not found']);
        exit;
    }
    readfile($progressFile);
    exit;
}

if ($ajaxMode === 'run') {
    ignore_user_abort(true);
    set_time_limit(0);
    header('Content-Type: text/plain');
    echo "0\n";
    ob_flush();
    flush();
    runPreBreakoutSearch($progressFile);
    exit;
}

if ($ajaxMode === 'foundlist') {
    header('Content-Type: application/json');
    if (!file_exists($foundFile)) {
        echo json_encode(['found' => []]);
        exit;
    }
    $data = json_decode(file_get_contents($foundFile), true);
    echo json_encode(['found' => $data ?: []]);
    exit;
}

if ($ajaxMode === 'clearfound') {
    header('Content-Type: application/json');
    if (file_exists($foundFile)) unlink($foundFile);
    echo json_encode(['ok' => true]);
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Pre-Breakout Scanner</title>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 30px 20px; background: #0f172a; color: #e2e8f0; min-height: 100vh; }
        h1 { font-size: 1.6rem; margin: 0 0 6px 0; color: #f8fafc; text-align: center; }
        .subtitle { color: #94a3b8; margin-bottom: 20px; font-size: 0.9rem; text-align: center; }
        .controls { display: flex; gap: 12px; align-items: center; justify-content: center; margin-bottom: 20px; flex-wrap: wrap; }
        .controls label { color: #94a3b8; font-size: 0.85rem; }
        .controls input[type="number"] { background: #1e293b; border: 1px solid #334155; color: #f8fafc; padding: 8px 12px; border-radius: 8px; width: 80px; font-size: 0.9rem; }
        .controls input[type="number"]:focus { outline: none; border-color: #3b82f6; }
        .btn { display: inline-block; padding: 10px 24px; background: #22c55e; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 0.9rem; border: none; cursor: pointer; transition: background 0.2s; }
        .btn:hover { background: #16a34a; }
        .btn:disabled { background: #334155; color: #64748b; cursor: not-allowed; }
        .btn-stop { background: #ef4444; }
        .btn-stop:hover { background: #dc2626; }
        .btn-clear { background: #475569; }
        .btn-clear:hover { background: #334155; }
        .card { background: #1e293b; border-radius: 16px; padding: 24px; width: 100%; max-width: 700px; margin: 0 auto 20px auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .progress-container { margin: 15px 0; }
        .progress-bar-bg { background: #334155; border-radius: 12px; height: 20px; overflow: hidden; position: relative; }
        .progress-bar-fill { background: linear-gradient(90deg, #22c55e, #16a34a); height: 100%; border-radius: 12px; width: 0%; transition: width 0.4s ease; position: relative; }
        .progress-bar-fill::after { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent); animation: shimmer 1.5s infinite; }
        @keyframes shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
        .progress-text { text-align: center; margin-top: 6px; font-size: 0.85rem; color: #94a3b8; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 15px 0; }
        .stat-box { background: #0f172a; border-radius: 10px; padding: 12px; text-align: center; border: 1px solid #334155; }
        .stat-value { font-size: 1.3rem; font-weight: 700; color: #f8fafc; }
        .stat-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 3px; }
        .stat-value.green { color: #22c55e; }
        .stat-value.red { color: #ef4444; }
        .stat-value.yellow { color: #eab308; }
        .current-activity { background: #0f172a; border-radius: 10px; padding: 12px 16px; margin: 12px 0; font-family: 'SF Mono', monospace; font-size: 0.8rem; color: #94a3b8; border-left: 3px solid #3b82f6; }
        .current-activity .label { color: #64748b; }
        .current-activity .symbol { color: #60a5fa; font-weight: 600; }
        .found-section { width: 100%; max-width: 700px; margin: 0 auto; }
        .found-section h2 { font-size: 1.1rem; color: #f8fafc; margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px; }
        .found-section h2 .count { background: #22c55e; color: #fff; font-size: 0.75rem; padding: 2px 8px; border-radius: 10px; }
        .found-list { display: flex; flex-direction: column; gap: 10px; }
        .found-item { background: #1e293b; border-radius: 12px; padding: 16px; border: 1px solid #334155; display: grid; grid-template-columns: 1fr auto auto auto; gap: 16px; align-items: center; }
        .found-item:hover { border-color: #3b82f6; }
        .found-ticker { font-size: 1.2rem; font-weight: 700; color: #f8fafc; }
        .found-ticker a { color: #60a5fa; text-decoration: none; }
        .found-ticker a:hover { color: #3b82f6; text-decoration: underline; }
        .found-price { font-size: 1rem; color: #22c55e; font-weight: 600; }
        .found-rating { font-size: 0.8rem; padding: 4px 10px; border-radius: 12px; font-weight: 600; }
        .rating-strong { background: #14532d; color: #86efac; }
        .rating-good { background: #1e3a5f; color: #93c5fd; }
        .rating-weak { background: #451a03; color: #fcd34d; }
        .sparkline { width: 120px; height: 40px; }
        .sparkline svg { width: 100%; height: 100%; }
        .sparkline polyline { fill: none; stroke: #22c55e; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }
        .sparkline .area { fill: rgba(34,197,94,0.15); stroke: none; }
        .empty-state { text-align: center; color: #64748b; padding: 30px; font-size: 0.9rem; }
        .error-box { background: #7f1d1d; border: 1px solid #ef4444; color: #fecaca; padding: 14px; border-radius: 10px; margin-top: 12px; display: none; font-size: 0.85rem; }
        #workerFrame { position: absolute; width: 1px; height: 1px; left: -9999px; top: -9999px; border: none; opacity: 0; }
    </style>
</head>
<body>
    <h1>Pre-Breakout Scanner</h1>
    <div class="subtitle">Finding stocks coiling near resistance with rising volume</div>

    <div class="controls">
        <label for="maxPrice">Max Price $</label>
        <input type="number" id="maxPrice" value="4" step="0.01" min="0.5">
        <button class="btn" id="startBtn" onclick="startSearch()">Start Scan</button>
        <button class="btn btn-stop" id="stopBtn" onclick="stopSearch()" disabled>Stop</button>
        <button class="btn btn-clear" id="clearBtn" onclick="clearFound()">Clear List</button>
    </div>

    <div class="card">
        <div class="progress-container">
            <div class="progress-bar-bg"><div class="progress-bar-fill" id="progressBar"></div></div>
            <div class="progress-text" id="progressText">Ready to scan</div>
        </div>
        <div class="stats-grid">
            <div class="stat-box"><div class="stat-value" id="statChecked">0</div><div class="stat-label">Checked</div></div>
            <div class="stat-box"><div class="stat-value green" id="statValid">0</div><div class="stat-label">Valid</div></div>
            <div class="stat-box"><div class="stat-value yellow" id="statNotReady">0</div><div class="stat-label">Not Ready</div></div>
            <div class="stat-box"><div class="stat-value red" id="statInvalid">0</div><div class="stat-label">Invalid</div></div>
        </div>
        <div class="current-activity" id="currentActivity">
            <span class="label">Status:</span> <span id="statusText">Idle</span><br>
            <span class="label">Current:</span> <span class="symbol" id="currentSymbol">—</span>
        </div>
        <div class="error-box" id="errorBox"></div>
    </div>

    <div class="found-section">
        <h2>Found Stocks <span class="count" id="foundCount">0</span></h2>
        <div class="found-list" id="foundList">
            <div class="empty-state">No stocks found yet. Start scanning to discover pre-breakout candidates.</div>
        </div>
    </div>

    <iframe id="workerFrame"></iframe>

    <script>
    (function() {
        var AJAX_URL = window.location.href.split('?')[0];
        var els = {
            bar: document.getElementById('progressBar'),
            text: document.getElementById('progressText'),
            checked: document.getElementById('statChecked'),
            valid: document.getElementById('statValid'),
            notReady: document.getElementById('statNotReady'),
            invalid: document.getElementById('statInvalid'),
            status: document.getElementById('statusText'),
            current: document.getElementById('currentSymbol'),
            error: document.getElementById('errorBox'),
            foundList: document.getElementById('foundList'),
            foundCount: document.getElementById('foundCount'),
            startBtn: document.getElementById('startBtn'),
            stopBtn: document.getElementById('stopBtn'),
            maxPrice: document.getElementById('maxPrice'),
            workerFrame: document.getElementById('workerFrame')
        };
        var pollInterval = null;
        var foundTickers = new Set();
        var isRunning = false;

        function loadExistingFound() {
            fetch(AJAX_URL + '?ajax=foundlist').then(function(r){return r.json();}).then(function(data){
                if (data.found && data.found.length > 0) {
                    data.found.forEach(function(item){
                        if (item.ticker) {
                            foundTickers.add(item.ticker);
                            appendFoundItem(item, false);
                        }
                    });
                    updateFoundCount();
                }
            }).catch(function(){});
        }
        loadExistingFound();

        window.startSearch = function() {
            if (isRunning) return;
            isRunning = true;
            els.startBtn.disabled = true;
            els.stopBtn.disabled = false;
            els.maxPrice.disabled = true;
            els.error.style.display = 'none';
            els.status.textContent = 'Starting...';

            var maxPrice = parseFloat(els.maxPrice.value) || 4.0;
            fetch(AJAX_URL + '?ajax=start&maxPrice=' + maxPrice)
                .then(function(r){return r.json();})
                .then(function(data){
                    if(data.error) throw new Error(data.error);
                    pollInterval = setInterval(pollProgress, 800);
                    els.workerFrame.src = AJAX_URL + '?ajax=run';
                })
                .catch(function(e){
                    els.error.style.display = 'block';
                    els.error.textContent = e.message;
                    resetButtons();
                });
        };

        window.stopSearch = function() {
            isRunning = false;
            if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
            els.workerFrame.src = 'about:blank';
            els.status.textContent = 'Stopped';
            resetButtons();
        };

        window.clearFound = function() {
            fetch(AJAX_URL + '?ajax=clearfound').then(function(){ return; });
            foundTickers.clear();
            els.foundList.innerHTML = '<div class="empty-state">No stocks found yet. Start scanning to discover pre-breakout candidates.</div>';
            updateFoundCount();
        };

        function resetButtons() {
            els.startBtn.disabled = false;
            els.stopBtn.disabled = true;
            els.maxPrice.disabled = false;
        }

        function updateFoundCount() {
            els.foundCount.textContent = foundTickers.size;
        }

        function buildSparkline(prices) {
            if (!prices || prices.length < 2) return '';
            var min = Math.min.apply(null, prices);
            var max = Math.max.apply(null, prices);
            var range = max - min;
            if (range === 0) range = 1;
            var w = 120, h = 40;
            var pad = 2;
            var pts = [];
            for (var i = 0; i < prices.length; i++) {
                var x = pad + (i / (prices.length - 1)) * (w - pad * 2);
                var y = h - pad - ((prices[i] - min) / range) * (h - pad * 2);
                pts.push(x + ',' + y);
            }
            var areaPts = pts.slice();
            areaPts.push((w - pad) + ',' + (h - pad));
            areaPts.push(pad + ',' + (h - pad));
            return '<svg viewBox="0 0 ' + w + ' ' + h + '"><polygon class="area" points="' + areaPts.join(' ') + '"/><polyline points="' + pts.join(' ') + '"/></svg>';
        }

        function appendFoundItem(item, animate) {
            var empty = els.foundList.querySelector('.empty-state');
            if (empty) empty.remove();

            var div = document.createElement('div');
            div.className = 'found-item';
            if (animate) div.style.animation = 'fadeIn 0.4s ease';

            var ratingClass = 'rating-good';
            var ratingText = 'Good';
            if (item.volRatio >= 4) { ratingClass = 'rating-strong'; ratingText = 'Strong'; }
            else if (item.volRatio < 2.5) { ratingClass = 'rating-weak'; ratingText = 'Weak'; }

            var sparklineHtml = buildSparkline(item.sparklinePrices || []);

            var link = 'https://alceawis.de/other/extra/fetchdata/2026-05-13-Finance/2026-05-13-Stocks/process.php?symbol=' + encodeURIComponent(item.ticker) + '&currency=USD';

            div.innerHTML =
                '<div class="found-ticker"><a href="' + link + '" target="_blank">' + item.ticker + '</a></div>' +
                '<div class="found-price">$' + item.price.toFixed(2) + '</div>' +
                '<div class="found-rating ' + ratingClass + '">' + ratingText + '</div>' +
                '<div class="sparkline">' + sparklineHtml + '</div>';

            els.foundList.insertBefore(div, els.foundList.firstChild);
            updateFoundCount();
        }

        function pollProgress(){
            fetch(AJAX_URL + '?ajax=poll').then(function(r){return r.json();}).then(function(data){
                if(data.error){ els.error.style.display='block'; els.error.textContent=data.error; return; }
                els.checked.textContent=data.checked;
                els.valid.textContent=data.validFound;
                els.notReady.textContent=data.notReady;
                els.invalid.textContent=data.invalid;
                els.bar.style.width=(data.done?100:Math.min(95,(data.checked/3000)*100))+'%';
                if(data.currentSymbol){ els.current.textContent=data.currentSymbol; els.status.textContent='Analyzing...'; }
                els.text.textContent='Checked '+data.checked+' symbols · '+data.validFound+' valid · '+data.notReady+' not ready';

                if(data.result && data.result.ticker && !foundTickers.has(data.result.ticker)){
                    foundTickers.add(data.result.ticker);
                    appendFoundItem(data.result, true);
                    // Auto-restart for next find
                    if (isRunning) {
                        setTimeout(function(){
                            if (isRunning) {
                                els.workerFrame.src = 'about:blank';
                                setTimeout(function(){
                                    if (isRunning) els.workerFrame.src = AJAX_URL + '?ajax=run';
                                }, 200);
                            }
                        }, 500);
                    }
                }

                if(data.done && !data.result){
                    els.status.textContent = 'Finished — no more candidates';
                    resetButtons();
                    isRunning = false;
                    if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
                }
            }).catch(function(){});
        }
    })();
    </script>
</body>
</html>
<?php exit; ?>

<?php

function runPreBreakoutSearch($progressFile) {
    global $cacheFile, $notAStockFile, $stocksFile, $foundFile;
    ignore_user_abort(true);

    $needUpdate = true;
    if (file_exists($cacheFile)) {
        $fh = fopen($cacheFile, 'r');
        if ($fh) {
            $head = fread($fh, 100);
            fclose($fh);
            if (preg_match('/"timestamp":(\d+)/', $head, $m)) {
                if ((time() - (int)$m[1]) < 86400) $needUpdate = false;
            }
        }
    }
    if ($needUpdate) updateAllSymbolsCache();

    $excludeSet = loadExcludeSet($stocksFile, $notAStockFile);

    // Load already-found tickers to avoid duplicates
    $alreadyFound = [];
    if (file_exists($foundFile)) {
        $foundData = json_decode(file_get_contents($foundFile), true);
        if (is_array($foundData)) {
            foreach ($foundData as $item) {
                if (isset($item['ticker'])) $alreadyFound[$item['ticker']] = 1;
            }
        }
    }
    foreach ($alreadyFound as $ticker => $v) $excludeSet[$ticker] = 1;

    $progress = json_decode(file_get_contents($progressFile), true);
    $maxPrice = isset($progress['maxPrice']) ? (float)$progress['maxPrice'] : 4.0;

    $validSymbol = null; $validTicker = null; $validPrice = null; $validVolRatio = null; $validSparkline = [];
    $checkedSymbols = []; $invalidFound = []; $notReadySymbols = [];
    $validFound = 0;
    $maxTotal = 3000; $batchSize = 40; $sampleSize = 200;

    $invalidTypes = ['ETF','MUTUALFUND','INDEX','CRYPTOCURRENCY','FUTURE','OPTION','BOND','PENNY_STOCK','PREFERRED_STOCK','REIT','UNIT','RIGHT','WARRANT','STRUCTURED','CURRENCY'];

    writeProgress($progressFile, [
        'status' => 'searching', 'checked' => 0, 'validFound' => 0, 'notReady' => 0,
        'invalid' => 0, 'currentSymbol' => '', 'done' => false, 'result' => null
    ]);

    while (!$validSymbol && count($checkedSymbols) < $maxTotal) {
        $candidates = streamSampleSymbolsFast($cacheFile, $excludeSet, $sampleSize);
        if (empty($candidates)) {
            $candidates = streamSampleSymbolsFast($cacheFile, [], $sampleSize);
            if (empty($candidates)) break;
        }

        $offset = 0;
        while ($offset < count($candidates) && !$validSymbol) {
            $batch = array_slice($candidates, $offset, $batchSize);
            $offset += $batchSize;

            $batch = array_filter($batch, function($symbol) use ($checkedSymbols) {
                return !in_array($symbol, $checkedSymbols);
            });
            if (empty($batch)) continue;

            array_push($checkedSymbols, ...$batch);

            writeProgress($progressFile, [
                'checked' => count($checkedSymbols),
                'currentSymbol' => $batch[0] ?? ''
            ]);

            $mh = curl_multi_init(); $handles = [];
            foreach ($batch as $symbol) {
                $ch = curl_init("https://query1.finance.yahoo.com/v1/finance/search?q=" . urlencode($symbol) . "&quotesCount=1&newsCount=0");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => false, CURLOPT_USERAGENT => 'Mozilla/5.0', CURLOPT_FOLLOWLOCATION => true
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[$symbol] = $ch;
            }
            do { curl_multi_exec($mh, $running); if ($running > 0) curl_multi_select($mh, 0.05); } while ($running > 0);

            $validBatch = []; $newInvalid = [];
            foreach ($handles as $symbol => $ch) {
                $body = curl_multi_getcontent($ch); curl_multi_remove_handle($mh, $ch); curl_close($ch);
                $data = json_decode($body, true); $quote = $data['quotes'][0] ?? null; $quoteType = $quote['quoteType'] ?? '';
                if (!$quote || $quoteType !== 'EQUITY' || in_array($quoteType, $invalidTypes)) {
                    $newInvalid[] = $symbol; $invalidFound[] = $symbol; continue;
                }
                $ticker = $quote['symbol'] ?? null; if ($ticker) $validBatch[$symbol] = $ticker;
            }
            curl_multi_close($mh);

            if (!empty($newInvalid)) {
                appendToNotAStock($notAStockFile, $newInvalid);
                foreach ($newInvalid as $symbol) $excludeSet[$symbol] = 1;
            }

            writeProgress($progressFile, ['invalid' => count($invalidFound)]);

            if (empty($validBatch)) continue;

            $validFound += count($validBatch);
            writeProgress($progressFile, ['validFound' => $validFound]);

            $mh2 = curl_multi_init(); $chartHandles = [];
            foreach ($validBatch as $symbol => $ticker) {
                $ch = curl_init("https://query1.finance.yahoo.com/v8/finance/chart/{$ticker}?interval=1d&range=3mo");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 12,
                    CURLOPT_SSL_VERIFYPEER => false, CURLOPT_USERAGENT => 'Mozilla/5.0', CURLOPT_FOLLOWLOCATION => true
                ]);
                curl_multi_add_handle($mh2, $ch);
                $chartHandles[$symbol] = ['ch' => $ch, 'ticker' => $ticker];
            }
            do { curl_multi_exec($mh2, $running2); if ($running2 > 0) curl_multi_select($mh2, 0.05); } while ($running2 > 0);

            foreach ($chartHandles as $symbol => $info) {
                $body = curl_multi_getcontent($info['ch']); curl_multi_remove_handle($mh2, $info['ch']); curl_close($info['ch']);
                if (!$validSymbol) {
                    $result = hasPreBreakoutFast($body, $maxPrice);
                    if ($result) {
                        $validSymbol = $symbol;
                        $validTicker = $info['ticker'];
                        $validPrice = $result['price'];
                        $validVolRatio = $result['volRatio'];
                        $validSparkline = $result['sparkline'];

                        // Save to found file
                        $foundEntry = [
                            'ticker' => $validTicker,
                            'price' => $validPrice,
                            'volRatio' => round($validVolRatio, 2),
                            'sparklinePrices' => $validSparkline,
                            'foundAt' => time()
                        ];
                        $foundData = [];
                        if (file_exists($foundFile)) {
                            $foundData = json_decode(file_get_contents($foundFile), true) ?: [];
                        }
                        $foundData[] = $foundEntry;
                        file_put_contents($foundFile, json_encode($foundData), LOCK_EX);

                        writeProgress($progressFile, [
                            'status' => 'found',
                            'result' => [
                                'ticker' => $validTicker,
                                'price' => $validPrice,
                                'volRatio' => round($validVolRatio, 2),
                                'sparklinePrices' => $validSparkline
                            ]
                        ]);
                        curl_multi_close($mh2);
                        break 3;
                    } else {
                        $notReadySymbols[] = $symbol;
                    }
                } else {
                    $notReadySymbols[] = $symbol;
                }
            }
            curl_multi_close($mh2);

            writeProgress($progressFile, ['notReady' => count($notReadySymbols)]);
        }
        unset($candidates);
    }

    if (!$validSymbol) {
        writeProgress($progressFile, [
            'done' => true, 'status' => 'notfound',
            'message' => "No pre-breakout stock found after checking " . count($checkedSymbols) . " candidates. " . count($notReadySymbols) . " valid symbols were not in pre-breakout setup."
        ]);
    }
}

function writeProgress($file, array $updates) {
    $data = [];
    if (file_exists($file)) $data = json_decode(file_get_contents($file), true) ?: [];
    $data = array_merge($data, $updates);
    file_put_contents($file, json_encode($data), LOCK_EX);
}

function loadExcludeSet($stocksFile, $notAStockFile) {
    $excludeSet = [];
    if (file_exists($stocksFile)) {
        $fh = fopen($stocksFile, 'r');
        $buf = '';
        while (!feof($fh)) {
            $buf .= fread($fh, 8192);
            preg_match_all('/"symbol"\s*:\s*"(' . SYMBOL_RE . ')"/', $buf, $m);
            foreach ($m[1] as $symbol) $excludeSet[$symbol] = 1;
            $buf = substr($buf, -50);
        }
        fclose($fh);
    }
    if (file_exists($notAStockFile)) {
        $fh = fopen($notAStockFile, 'r');
        $buf = '';
        while (!feof($fh)) {
            $buf .= fread($fh, 8192);
            preg_match_all('/(?:[\[,])\s*"(' . SYMBOL_RE . ')"\s*(?:,|\])/', $buf, $m);
            foreach ($m[1] as $symbol) $excludeSet[$symbol] = 1;
            $buf = substr($buf, -60);
        }
        fclose($fh);
    }
    return $excludeSet;
}

function streamSampleSymbolsFast($cacheFile, array $excludeSet, $sampleSize = 200) {
    if (!file_exists($cacheFile)) return [];
    $exclude = $excludeSet;
    $fh = fopen($cacheFile, 'r');
    if (!$fh) return [];
    $reservoir = []; $count = 0; $buf = '';
    while (!feof($fh)) {
        $buf .= fread($fh, 8192);
        preg_match_all('/(?:[\[,])\s*"(' . SYMBOL_RE . ')"\s*(?:,|\])/', $buf, $matches);
        foreach ($matches[1] as $symbol) {
            if (isset($exclude[$symbol])) continue;
            $count++;
            if (count($reservoir) < $sampleSize) {
                $reservoir[] = $symbol;
            } else {
                $j = random_int(0, $count - 1);
                if ($j < $sampleSize) $reservoir[$j] = $symbol;
            }
        }
        $buf = substr($buf, -60);
    }
    fclose($fh);
    return $reservoir;
}

function hasPreBreakoutFast($responseBody, $maxPrice = 4.0) {
    if (!$responseBody) return false;
    $data = json_decode($responseBody, true);
    $result = $data['chart']['result'][0] ?? null;
    if (!$result) return false;

    $closes = $result['indicators']['quote'][0]['close'] ?? [];
    $volumes = $result['indicators']['quote'][0]['volume'] ?? [];
    $prices = []; $vols = [];
    foreach ($closes as $i => $c) {
        if (isset($c) && $c > 0) {
            $prices[] = $c;
            $vols[] = isset($volumes[$i]) ? (int)$volumes[$i] : 0;
        }
    }
    $n = count($prices);
    if ($n < 40) return false;

    $currentPrice = $prices[$n - 1];

    // Max price filter
    if ($currentPrice > $maxPrice) return false;

    // 1. NOT already gone off: no +50% in 3 months
    $totalReturn = (($currentPrice - $prices[0]) / $prices[0]) * 100;
    if ($totalReturn > 50) return false;

    // 2. NOT recently spiked: no +15% in last 7 days
    $last7 = array_slice($prices, -7);
    $before7 = array_slice($prices, 0, -7);
    if (!empty($before7)) {
        $avgBefore7 = array_sum($before7) / count($before7);
        $maxLast7 = max($last7);
        $spikePct = (($maxLast7 - $avgBefore7) / $avgBefore7) * 100;
        if ($spikePct > 15) return false;
    }

    // 3. Volume building in recent window
    $recentWindow = min(5, $n);
    $quietWindow = $n - $recentWindow;
    if ($quietWindow < 10) return false;

    $recentVols = array_slice($vols, -$recentWindow);
    $quietVols = array_slice($vols, 0, $quietWindow);

    $recentAvgVol = array_sum($recentVols) / count($recentVols);
    $quietAvgVol = array_sum($quietVols) / count($quietVols);
    $volRatio = $quietAvgVol > 0 ? ($recentAvgVol / $quietAvgVol) : 0;

    if ($volRatio < 2.0) return false;

    // 4. Price near recent highs (pressing resistance)
    $recentPrices = array_slice($prices, -$recentWindow);
    $recentHigh = max($recentPrices);
    $recentLow = min($recentPrices);

    $distanceFromHigh = $recentHigh > 0 ? (($recentHigh - $currentPrice) / $recentHigh) * 100 : 100;
    if ($distanceFromHigh > 5) return false;

    // 5. Tight consolidation
    $recentRangePct = $recentLow > 0 ? (($recentHigh - $recentLow) / $recentLow) * 100 : 100;
    if ($recentRangePct > 12) return false;

    // 6. Slight upward tilt (accumulation, not distribution)
    $quietPrices = array_slice($prices, 0, $quietWindow);
    $quietMid = (int)(count($quietPrices) / 2);
    $quietFirstHalf = array_slice($quietPrices, 0, $quietMid);
    $quietSecondHalf = array_slice($quietPrices, $quietMid);

    $avgFirst = array_sum($quietFirstHalf) / max(1, count($quietFirstHalf));
    $avgSecond = array_sum($quietSecondHalf) / max(1, count($quietSecondHalf));

    if ($avgFirst > 0 && (($avgSecond - $avgFirst) / $avgFirst) * 100 < -5) return false;

    // 7. Recent days trending up
    $last3 = array_slice($prices, -3);
    if (count($last3) >= 3) {
        $trendingUp = ($last3[2] > $last3[0]);
        if (!$trendingUp) return false;
    }

    // 8. Minimum volume
    if ($recentAvgVol < 50000) return false;

    // 9. Price floor
    if ($currentPrice < 0.5) return false;

    // Build sparkline data (last 30 days)
    $sparklinePrices = array_slice($prices, -30);

    return [
        'price' => $currentPrice,
        'volRatio' => $volRatio,
        'sparkline' => $sparklinePrices
    ];
}

function appendToNotAStock($file, array $newSymbols) {
    if (empty($newSymbols)) return;
    if (!file_exists($file) || filesize($file) < 3) {
        $fh = fopen($file, 'w');
        fwrite($fh, '["' . implode('","', $newSymbols) . '"]');
        fclose($fh);
        return;
    }
    $fh = fopen($file, 'r+');
    if (!$fh) return;
    $size = filesize($file); $pos = $size - 1;
    while ($pos >= 0) {
        fseek($fh, $pos); $c = fread($fh, 1);
        if ($c === ']') break;
        $pos--;
    }
    fseek($fh, $pos);
    fwrite($fh, ',"' . implode('","', $newSymbols) . '"]');
    fclose($fh);
}

function updateAllSymbolsCache() {
    $cacheFile = __DIR__ . '/symbols_cache.json';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.sec.gov/files/company_tickers.json',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'StockScanner research-tool contact@example.com',
        CURLOPT_TIMEOUT => 30
    ]);
    $json = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || !$json) return false;

    $data = json_decode($json, true);
    if (!is_array($data)) return false;

    $seen = [];
    $symbols = [];
    foreach ($data as $row) {
        $symbol = strtoupper(trim($row['ticker'] ?? ''));
        if ($symbol === '' || !preg_match('/^' . SYMBOL_RE . '$/', $symbol)) continue;
        if (isset($seen[$symbol])) continue;
        $seen[$symbol] = 1;
        $symbols[] = $symbol;
    }

    $total = count($symbols);
    $outFh = fopen($cacheFile, 'w');
    fwrite($outFh, '{"timestamp":' . time() . ',"count":' . $total . ',"symbols":[');
    foreach ($symbols as $i => $s) {
        fwrite($outFh, ($i === 0 ? '' : ',') . '"' . $s . '"');
    }
    fwrite($outFh, ']}');
    fclose($outFh);
    return true;
}
