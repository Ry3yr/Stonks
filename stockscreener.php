<?php
/**
 * Momentum Screener — Real-time Streaming (Ultra-Optimized)
 * Sources:
 * 1. TradingView   — Most Volatile (US stocks)
 * 2. NASDAQ        — Market Cap screener (Medium/Small/Micro/Nano)
 * 3. NASDAQ Gainers — Top % Gainers list (api.nasdaq.com)
 * 4. Yahoo Trending — Trending symbols (query1.finance.yahoo.com)
 */

// ─── Config ──────────────────────────────────────────────────────────────────

$MIN_AVG_VOLUME = 10000;      // Reduced from 100,000 - more inclusive
$MIN_PRICE = 0.50;
$MAX_PRICE = 1.00;           // CHANGED: Reduced from 50 to 20 - focus on cheaper stocks
$MIN_PCT_CHANGE = 3.0;        // Reduced from 5.0 - catch more movers

$LOOKBACK_DAYS = 20;

$BREAKOUT_THRESHOLD = 1.02;
$VOLUME_SPIKE = 1.2;          // Reduced from 1.5
$MAX_RSI_BREAKOUT = 80;

$VOL_SURGE_MIN = 2.0;         // Reduced from 3.0
$VOL_SURGE_PRICE_MIN = 1.02;  // Reduced from 1.03

$REVERSAL_MAX_RSI = 35;
$REVERSAL_MIN_VOL = 1.5;      // Reduced from 2.0
$REVERSAL_MIN_BOUNCE = 1.05;

// NEW: Momentum-focused thresholds
$HIGH_MOMENTUM_THRESHOLD = 15;  // 15%+ in 5 days
$MOM_GAP_THRESHOLD = 10;        // 10%+ in 3 days with low volume

$TICKER_BASE_URL = 'https://alceawis.de/other/extra/fetchdata/2026-05-13-Finance/2026-05-13-Stocks/process.php?symbol=';

// ─── NASDAQ Cap Band Definitions ────────────────────────────────────────────

define('CAP_MEDIUM_MIN', 2_000_000_000);
define('CAP_MEDIUM_MAX', 10_000_000_000);
define('CAP_SMALL_MIN',    300_000_000);
define('CAP_SMALL_MAX',  2_000_000_000);
define('CAP_MICRO_MIN',     50_000_000);
define('CAP_MICRO_MAX',   300_000_000);
define('CAP_NANO_MAX',      50_000_000);

function capBand(float $cap): ?string {
    if ($cap >= CAP_MEDIUM_MIN && $cap < CAP_MEDIUM_MAX) return 'Medium';
    if ($cap >= CAP_SMALL_MIN  && $cap < CAP_SMALL_MAX)  return 'Small';
    if ($cap >= CAP_MICRO_MIN  && $cap < CAP_MICRO_MAX)  return 'Micro';
    if ($cap > 0 && $cap < CAP_NANO_MAX)                 return 'Nano';
    return null;
}

// ─── Check if this is a streaming request ──────────────────────────────────

$isStream = isset($_GET['stream']) && $_GET['stream'] === '1';

if ($isStream) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    ob_end_flush();

    runStreamingScan();
    exit;
}

// ─── Run streaming scan ─────────────────────────────────────────────────────

function runStreamingScan() {
    global $MIN_AVG_VOLUME, $MIN_PRICE, $MAX_PRICE, $MIN_PCT_CHANGE, $LOOKBACK_DAYS;
    global $BREAKOUT_THRESHOLD, $VOLUME_SPIKE, $MAX_RSI_BREAKOUT;
    global $VOL_SURGE_MIN, $VOL_SURGE_PRICE_MIN;
    global $REVERSAL_MAX_RSI, $REVERSAL_MIN_VOL, $REVERSAL_MIN_BOUNCE;
    global $HIGH_MOMENTUM_THRESHOLD, $MOM_GAP_THRESHOLD;

    $seenSymbols = [];
    $stats = [
        'tv_checked' => 0, 'tv_signals' => 0, 'tv_total' => 0,
        'nas_checked' => 0, 'nas_signals' => 0, 'nas_total' => 0,
        'nas_skipped_price' => 0, 'nas_skipped_change' => 0, 'nas_skipped_cap' => 0,
        'nasg_checked' => 0, 'nasg_signals' => 0, 'nasg_total' => 0,
        'ytrend_checked' => 0, 'ytrend_signals' => 0, 'ytrend_total' => 0,
    ];

    // ── Run 1: TradingView ──
    sendEvent('status', ['message' => 'Fetching TradingView volatile stocks...', 'type' => 'info']);
    $tvCandidates = fetchTradingViewVolatile();
    runSimpleSourceScan('TradingView', 'tv', $tvCandidates, $seenSymbols, $stats);

    // ── Run 2: NASDAQ (ULTRA FILTERED!) ──
    sendEvent('status', ['message' => 'Fetching NASDAQ screener data...', 'type' => 'info']);

    $allNasdaqStocks = fetchNasdaqScreener();

    // ── AGGRESSIVE PRE-FILTERING USING NASDAQ DATA ──
    $filteredCandidates = [];
    $skipReasons = [
        'price_too_high' => 0,
        'price_too_low' => 0,
        'cap_too_big' => 0,
        'pct_change_too_low' => 0,
        'no_data' => 0
    ];

    foreach ($allNasdaqStocks as $cand) {
        $price = (float) str_replace(['$', ','], '', $cand['lastsale'] ?? '0');
        $pctChangeRaw = str_replace('%', '', $cand['pctchange'] ?? '0');
        $pctChange = (float) $pctChangeRaw;
        $cap = $cand['marketCap'] ?? 0;

        if ($price > $MAX_PRICE) { $skipReasons['price_too_high']++; continue; }
        if ($price < $MIN_PRICE) { $skipReasons['price_too_low']++; continue; }
        if ($cap > 10_000_000_000) { $skipReasons['cap_too_big']++; continue; }
        // Relaxed: only skip if change is extremely small
        if (abs($pctChange) < 1.0) { $skipReasons['pct_change_too_low']++; continue; }
        if ($price <= 0 || $cap <= 0) { $skipReasons['no_data']++; continue; }

        $filteredCandidates[] = $cand;
    }

    $stats['nas_total'] = count($filteredCandidates);
    $stats['nas_original'] = count($allNasdaqStocks);
    $stats['nas_skipped_price'] = $skipReasons['price_too_high'] + $skipReasons['price_too_low'];
    $stats['nas_skipped_cap'] = $skipReasons['cap_too_big'];
    $stats['nas_skipped_change'] = $skipReasons['pct_change_too_low'];
    $stats['nas_skipped_total'] = array_sum($skipReasons);

    $totalSkipped = $stats['nas_skipped_total'];
    $keptPercent = $stats['nas_total'] > 0 ? round(($stats['nas_total'] / max($stats['nas_original'], 1)) * 100, 1) : 0;

    sendEvent('status', [
        'message' => "NASDAQ: {$stats['nas_original']} → {$stats['nas_total']} candidates (price≤$".$MAX_PRICE.", skipped {$totalSkipped}) - Price too high: {$skipReasons['price_too_high']}, Change<1%: {$skipReasons['pct_change_too_low']}, Cap: {$skipReasons['cap_too_big']}",
        'type' => 'info'
    ]);

    foreach ($filteredCandidates as $idx => $cand) {
        $stats['nas_checked']++;
        $sym = $cand['symbol'];

        if ($idx % 5 === 0) {
            sendEvent('progress', [
                'source' => 'NASDAQ',
                'checked' => $stats['nas_checked'],
                'total' => $stats['nas_total'],
                'signals' => $stats['nas_signals'],
                'symbol' => $sym,
                'skipped' => $stats['nas_skipped_total'],
                'kept_pct' => $keptPercent
            ]);
        }

        if (isset($seenSymbols[$sym])) continue;

        $hist = fetchHistory($sym);
        if (!$hist) continue;

        if ($sig = screen($hist, $sym)) {
            $sig['source'] = 'NASDAQ';
            $sig['marketCap'] = $cand['marketCap'];
            $sig['sector'] = $cand['sector'];
            $sig['band'] = $cand['band'];
            $sig['companyName'] = $cand['name'] ?? $sym; // Store company name
            $stats['nas_signals']++;
            $seenSymbols[$sym] = true;

            sendEvent('signal', array_merge($sig, ['stats' => $stats]));
        }

        usleep(50000);  // Faster: 50ms instead of 100ms
    }

    sendEvent('status', [
        'message' => "NASDAQ done: {$stats['nas_signals']} signals found",
        'type' => 'info'
    ]);

    // ── Run 3: NASDAQ Top Gainers ──
    sendEvent('status', ['message' => 'Fetching NASDAQ top gainers...', 'type' => 'info']);
    $nasgCandidates = fetchNasdaqGainers();
    runSimpleSourceScan('NasdaqGainers', 'nasg', $nasgCandidates, $seenSymbols, $stats);

    // ── Run 4: Yahoo Trending ──
    sendEvent('status', ['message' => 'Fetching Yahoo Finance trending symbols...', 'type' => 'info']);
    $ytrendCandidates = fetchYahooTrending();
    runSimpleSourceScan('YahooTrending', 'ytrend', $ytrendCandidates, $seenSymbols, $stats);

    $totalSignals = $stats['tv_signals'] + $stats['nas_signals'] + $stats['nasg_signals']
        + $stats['ytrend_signals'];

    sendEvent('done', [
        'message' => "✅ Complete! Found {$totalSignals} signals across 4 sources.",
        'stats' => $stats
    ]);
}

// ─── Generic scan loop for "flat symbol list" sources ──────────────────────

function runSimpleSourceScan(string $sourceLabel, string $statPrefix, array $candidates, array &$seenSymbols, array &$stats) {
    $totalKey = "{$statPrefix}_total";
    $checkedKey = "{$statPrefix}_checked";
    $signalsKey = "{$statPrefix}_signals";

    $stats[$totalKey] = count($candidates);

    sendEvent('status', [
        'message' => "Found {$stats[$totalKey]} candidates from {$sourceLabel}, screening...",
        'type' => 'info'
    ]);

    foreach ($candidates as $idx => $sym) {
        $stats[$checkedKey]++;

        if ($idx % 5 === 0) {
            sendEvent('progress', [
                'source' => $sourceLabel,
                'checked' => $stats[$checkedKey],
                'total' => $stats[$totalKey],
                'signals' => $stats[$signalsKey],
                'symbol' => $sym
            ]);
        }

        if (isset($seenSymbols[$sym])) continue;

        $hist = fetchHistory($sym);
        if (!$hist) continue;

        if ($sig = screen($hist, $sym)) {
            $sig['source'] = $sourceLabel;
            $sig['marketCap'] = null;
            $sig['sector'] = null;
            $sig['band'] = null;
            $sig['companyName'] = $sym; // Use symbol as fallback
            $stats[$signalsKey]++;
            $seenSymbols[$sym] = true;

            sendEvent('signal', array_merge($sig, ['stats' => $stats]));
        }

        usleep(50000);  // Faster: 50ms
    }

    sendEvent('status', [
        'message' => "{$sourceLabel} done: {$stats[$signalsKey]} signals found",
        'type' => 'info'
    ]);
}

// ─── Helper to send SSE event ──────────────────────────────────────────────

function sendEvent($event, $data) {
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    flush();

    if (connection_aborted()) {
        exit;
    }
}

// ─── Discovery Functions ────────────────────────────────────────────────────

function fetchTradingViewVolatile(): array {
    $url = 'https://www.tradingview.com/markets/stocks-usa/market-movers-most-volatile/';
    $opts = [
        'http' => [
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => 20
        ]
    ];
    $html = @file_get_contents($url, false, stream_context_create($opts));
    if (!$html) return [];
    preg_match_all('/data-rowkey="([A-Z]+:[A-Z]+)"/', $html, $matches);
    $symbols = [];
    foreach ($matches[1] as $rowkey) {
        $parts = explode(':', $rowkey);
        $symbols[] = $parts[1] ?? $rowkey;
    }
    return array_values(array_unique($symbols));
}

function fetchNasdaqScreener(): array {
    $url = 'https://api.nasdaq.com/api/screener/stocks?' . http_build_query([
        'tableonly' => 'true',
        'limit'     => 0,
        'offset'    => 0,
        'download'  => 'true',
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: en-US,en;q=0.9',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                . '(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'Origin: https://www.nasdaq.com',
            'Referer: https://www.nasdaq.com/market-activity/stocks/screener',
        ],
        CURLOPT_ENCODING => '',
        CURLOPT_TIMEOUT  => 60,
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch) || curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
        curl_close($ch);
        return [];
    }
    curl_close($ch);

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }

    $allRows = $data['data']['rows']
        ?? $data['data']['table']['rows']
        ?? $data['rows']
        ?? $data['table']['rows']
        ?? [];

    if (empty($allRows)) return [];

    $wanted = ['Medium', 'Small', 'Micro', 'Nano'];
    $filtered = [];

    foreach ($allRows as $row) {
        $capRaw = str_replace(',', '', $row['marketCap'] ?? '0');
        $cap = is_numeric($capRaw) ? (float) $capRaw : 0.0;
        $band = capBand($cap);
        if ($band !== null && in_array($band, $wanted, true)) {
            $filtered[] = [
                'symbol' => $row['symbol'] ?? '',
                'name' => $row['name'] ?? '',
                'marketCap' => $cap,
                'sector' => $row['sector'] ?? '',
                'lastsale' => $row['lastsale'] ?? '0',
                'pctchange' => $row['pctchange'] ?? '0%',
                'band' => $band,
            ];
        }
    }

    return $filtered;
}

function fetchNasdaqGainers(): array {
    $url = 'https://api.nasdaq.com/api/quote/list-type-extended/gainers?' . http_build_query([
        'assetclass' => 'stocks',
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: en-US,en;q=0.9',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                . '(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'Origin: https://www.nasdaq.com',
            'Referer: https://www.nasdaq.com/market-activity/stocks/gainers',
        ],
        CURLOPT_ENCODING => '',
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $httpCode !== 200) {
        error_log("NASDAQ Gainers fetch failed: HTTP {$httpCode}, curl_error: {$curlErr}");
        return [];
    }

    $data = json_decode($resp, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("NASDAQ Gainers JSON decode failed: " . json_last_error_msg());
        return [];
    }

    $rows = $data['data']['gainers']['rows']
        ?? $data['data']['rows']
        ?? $data['data']['table']['rows']
        ?? [];

    if (empty($rows)) {
        $topKeys = is_array($data['data'] ?? null) ? implode(',', array_keys($data['data'])) : 'no data key';
        error_log("NASDAQ Gainers: no rows found. Top-level data keys: {$topKeys}");
    }

    $symbols = [];
    foreach ($rows as $row) {
        if (!empty($row['symbol'])) $symbols[] = $row['symbol'];
    }
    return array_values(array_unique($symbols));
}

function fetchYahooTrending(): array {
    $url = 'https://query1.finance.yahoo.com/v1/finance/trending/US?' . http_build_query([
        'count' => 50,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                . '(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        ],
        CURLOPT_ENCODING => '',
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $httpCode !== 200) {
        error_log("Yahoo Trending fetch failed: HTTP {$httpCode}, curl_error: {$curlErr}");
        return [];
    }

    $data = json_decode($resp, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Yahoo Trending JSON decode failed: " . json_last_error_msg());
        return [];
    }

    $quotes = $data['finance']['result'][0]['quotes'] ?? [];
    $symbols = [];
    foreach ($quotes as $q) {
        if (!empty($q['symbol'])) $symbols[] = $q['symbol'];
    }
    return array_values(array_unique($symbols));
}

function fetchHistory(string $symbol): ?array {
    $p1 = strtotime("-90 days");
    $p2 = time();
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}?period1={$p1}&period2={$p2}&interval=1d&events=history&includeAdjustedClose=true";
    $opts = ['http' => ['header' => "User-Agent: Mozilla/5.0\r\n", 'timeout' => 12]];
    $json = @file_get_contents($url, false, stream_context_create($opts));
    if (!$json) return null;
    $d = json_decode($json, true);
    if (empty($d['chart']['result'][0])) return null;
    $r = $d['chart']['result'][0];
    $ts = $r['timestamp'] ?? [];
    $q = $r['indicators']['quote'][0] ?? [];
    $adj = $r['indicators']['adjclose'][0]['adjclose'] ?? [];
    $hist = [];
    for ($i = 0; $i < count($ts); $i++) {
        if (!isset($adj[$i]) || $adj[$i] === null) continue;
        $hist[] = [
            'date' => date('Y-m-d', $ts[$i]),
            'open' => $q['open'][$i] ?? null,
            'high' => $q['high'][$i] ?? null,
            'low' => $q['low'][$i] ?? null,
            'close' => $adj[$i],
            'volume' => $q['volume'][$i] ?? 0,
        ];
    }
    return $hist;
}

// ─── Technical Helpers ───────────────────────────────────────────────────────

function calcRSI(array $closes, int $period = 14): float {
    if (count($closes) < $period + 1) return 50;
    $gains = $losses = [];
    for ($i = count($closes) - $period; $i < count($closes); $i++) {
        $change = $closes[$i] - $closes[$i - 1];
        $gains[] = max($change, 0);
        $losses[] = max(-$change, 0);
    }
    $avgGain = array_sum($gains) / $period;
    $avgLoss = array_sum($losses) / $period;
    if ($avgLoss == 0) return 100;
    return 100 - (100 / (1 + ($avgGain / $avgLoss)));
}

function screen(array $h, string $sym): ?array {
    global $LOOKBACK_DAYS, $MIN_AVG_VOLUME, $MIN_PRICE, $MAX_PRICE;
    global $BREAKOUT_THRESHOLD, $VOLUME_SPIKE, $MAX_RSI_BREAKOUT;
    global $VOL_SURGE_MIN, $VOL_SURGE_PRICE_MIN;
    global $REVERSAL_MAX_RSI, $REVERSAL_MIN_VOL, $REVERSAL_MIN_BOUNCE;
    global $HIGH_MOMENTUM_THRESHOLD, $MOM_GAP_THRESHOLD;

    if (count($h) < $LOOKBACK_DAYS + 5) return null;

    $today = end($h);
    $price = $today['close'];
    $vol = $today['volume'];

    if ($price < $MIN_PRICE) return null;
    if ($price > $MAX_PRICE) return null;  // Auto-filter: skip stocks over $20

    $past = array_slice($h, -($LOOKBACK_DAYS + 1), $LOOKBACK_DAYS);
    $high20 = max(array_column($past, 'high'));
    $low20 = min(array_column($past, 'low'));
    $avgVol = array_sum(array_column($past, 'volume')) / count($past);

    // Relaxed volume check - only skip if extremely low
    if ($avgVol < 5000) return null;  // Absolute minimum

    $closes = array_column($h, 'close');
    $rsi = calcRSI($closes);

    $threeAgo = $h[count($h) - 4]['close'] ?? $price;
    $mom3d = (($price - $threeAgo) / $threeAgo) * 100;

    // ── NEW: HIGH MOMENTUM (5-day) ──
    if (count($h) >= 6) {
        $fiveDaysAgo = $h[count($h) - 6]['close'] ?? $price;
        $mom5d = (($price - $fiveDaysAgo) / $fiveDaysAgo) * 100;
        
        if ($mom5d >= $HIGH_MOMENTUM_THRESHOLD && $rsi < 85) {
            return [
                'symbol' => $sym, 
                'strategy' => 'HIGH-MOMENTUM',
                'price' => $price, 
                'trigger' => $mom5d.'% in 5d',
                'trigger_pct' => round($mom5d, 1),
                'rsi' => round($rsi,1), 
                'spike' => round($vol/max($avgVol,1),1),
                'vol' => $vol, 
                'avg_vol' => round($avgVol),
                'mom3d' => round($mom3d, 1),
            ];
        }
    }

    // ── NEW: MOMENTUM GAP (big move, low volume) ──
    if ($mom3d >= $MOM_GAP_THRESHOLD && $vol < $avgVol * 0.5 && $rsi < 80) {
        return [
            'symbol' => $sym, 
            'strategy' => 'MOM-GAP',
            'price' => $price, 
            'trigger' => $mom3d.'% in 3d (low vol)',
            'trigger_pct' => round($mom3d, 1),
            'rsi' => round($rsi,1), 
            'spike' => round($vol/max($avgVol,1),1),
            'vol' => $vol, 
            'avg_vol' => round($avgVol),
            'mom3d' => round($mom3d, 1),
        ];
    }

    // ── Breakout ──
    if ($price >= $high20 * $BREAKOUT_THRESHOLD && $vol >= $avgVol * $VOLUME_SPIKE && $rsi <= $MAX_RSI_BREAKOUT) {
        return [
            'symbol' => $sym, 'strategy' => 'BREAKOUT',
            'price' => $price, 'trigger' => '$'.number_format($high20,2).' high',
            'trigger_pct' => round((($price - $high20) / $high20) * 100, 1),
            'rsi' => round($rsi,1), 'spike' => round($vol/max($avgVol,1),1),
            'vol' => $vol, 'avg_vol' => round($avgVol),
            'mom3d' => round($mom3d, 1),
        ];
    }

    // ── Volume Surge ──
    $yesterday = $h[count($h) - 2] ?? $today;
    $priceChange = (($price - $yesterday['close']) / $yesterday['close']);
    if ($vol >= $avgVol * $VOL_SURGE_MIN && $priceChange >= 0.02 && $rsi < 75) {
        return [
            'symbol' => $sym, 'strategy' => 'VOL-SURGE',
            'price' => $price, 'trigger' => '+'.round($priceChange*100,1).'% today',
            'trigger_pct' => round($priceChange*100, 1),
            'rsi' => round($rsi,1), 'spike' => round($vol/max($avgVol,1),1),
            'vol' => $vol, 'avg_vol' => round($avgVol),
            'mom3d' => round($mom3d, 1),
        ];
    }

    // ── Oversold Bounce ──
    if ($rsi <= $REVERSAL_MAX_RSI && $vol >= $avgVol * $REVERSAL_MIN_VOL && $price >= $low20 * $REVERSAL_MIN_BOUNCE) {
        return [
            'symbol' => $sym, 'strategy' => 'REVERSAL',
            'price' => $price, 'trigger' => 'RSI '.round($rsi,1).' + bounce',
            'trigger_pct' => round((($price - $low20) / $low20) * 100, 1),
            'rsi' => round($rsi,1), 'spike' => round($vol/max($avgVol,1),1),
            'vol' => $vol, 'avg_vol' => round($avgVol),
            'mom3d' => round($mom3d, 1),
        ];
    }

    return null;
}

// ─── If not streaming, show the UI ──────────────────────────────────────────

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Momentum Screener — Real-time</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0d1117; color: #c9d1d9; padding: 24px; margin: 0; }
        h2 { color: #58a6ff; margin: 0 0 6px 0; font-size: 22px; }
        .meta { color: #8b949e; font-size: 13px; margin-bottom: 24px; }
        .stats { display: flex; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-box { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 12px 18px; min-width: 110px; }
        .stat-label { color: #8b949e; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { color: #fff; font-size: 20px; font-weight: 700; margin-top: 4px; }
        .stat-value.loading { color: #d29922; animation: pulse 1s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .source-badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-left: 6px; vertical-align: middle; }
        .source-tv { background: #1f6feb; color: #fff; }
        .source-nas { background: #8957e5; color: #fff; }
        .source-nasg { background: #d29922; color: #000; }
        .source-ytrend { background: #39c5cf; color: #000; }
        .band-badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 8px; font-weight: 700; background: #21262d; color: #8b949e; margin-left: 4px; }
        .band-medium { background: #238636; color: #fff; }
        .band-small { background: #d29922; color: #000; }
        .band-micro { background: #da3633; color: #fff; }
        .band-nano { background: #8957e5; color: #fff; }
        .isin-link { 
            color: #58a6ff; 
            text-decoration: none; 
            font-size: 11px;
            cursor: pointer;
            padding: 2px 8px;
            border: 1px solid #30363d;
            border-radius: 4px;
            background: #0d1117;
            display: inline-block;
            transition: all 0.2s;
        }
        .isin-link:hover { 
            background: #1c2128;
            border-color: #58a6ff;
        }
        .isin-link.loading {
            opacity: 0.5;
            cursor: wait;
        }
        .isin-result {
            font-size: 11px;
            margin-top: 4px;
            padding: 4px 8px;
            border-radius: 4px;
            background: #161b22;
            border: 1px solid #30363d;
            display: none;
        }
        .isin-result.show {
            display: block;
        }
        .isin-result .found {
            color: #3fb950;
        }
        .isin-result .not-found {
            color: #f85149;
        }
        .isin-result .multiple {
            color: #d29922;
            cursor: pointer;
            text-decoration: underline;
        }
        table { border-collapse: collapse; width: 100%; font-size: 13px; background: #161b22; border: 1px solid #30363d; border-radius: 8px; overflow: hidden; }
        th { 
            background: #21262d; 
            color: #58a6ff; 
            padding: 12px 14px; 
            text-align: right; 
            font-weight: 600; 
            font-size: 11px; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            border-bottom: 1px solid #30363d; 
            cursor: pointer; 
            user-select: none; 
            transition: background 0.15s; 
            white-space: nowrap; 
            position: sticky; 
            top: 0; 
            z-index: 10; 
        }
        th:hover { background: #30363d; }
        th:first-child, td:first-child { text-align: left; }
        th .arrow { 
            margin-left: 6px; 
            opacity: 0.3; 
            font-size: 10px;
            display: inline-block;
            transition: opacity 0.2s;
        }
        th.sorted .arrow { opacity: 1; }
        th.sorted-asc .arrow::after { content: '▲'; }
        th.sorted-desc .arrow::after { content: '▼'; }
        td { padding: 10px 14px; border-bottom: 1px solid #21262d; text-align: right; vertical-align: middle; }
        tr:hover { background: #1c2128; }
        tr:last-child td { border-bottom: none; }
        .sym a { font-weight: 700; color: #58a6ff; font-size: 14px; text-decoration: none; }
        .sym a:hover { text-decoration: underline; color: #79c0ff; }
        .sym .company-name { color: #8b949e; font-size: 11px; font-weight: 400; display: block; }
        .strat { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .strat-breakout { background: #238636; color: #fff; }
        .strat-vol { background: #8957e5; color: #fff; }
        .strat-rev { background: #d29922; color: #000; }
        .strat-high { background: #f0883e; color: #000; }
        .strat-gap { background: #da3633; color: #fff; }
        .spike { color: #f85149; font-weight: 700; }
        .results-container { max-height: 600px; overflow-y: auto; }
        .results-container::-webkit-scrollbar { width: 8px; }
        .results-container::-webkit-scrollbar-track { background: #0d1117; }
        .results-container::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }
        .results-container::-webkit-scrollbar-thumb:hover { background: #484f58; }
        .source-header { display: flex; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
        .source-pill { padding: 4px 12px; border-radius: 16px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid #30363d; background: #161b22; color: #8b949e; }
        .source-pill.active { background: #21262d; color: #58a6ff; border-color: #58a6ff; }
        .source-pill:hover { border-color: #58a6ff; }
        .scan-btn { background: #238636; color: #fff; border: none; padding: 8px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px; }
        .scan-btn:hover { background: #2ea043; }
        .scan-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .scan-btn.scanning { background: #d29922; }
        .status-bar { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; font-size: 13px; color: #8b949e; min-height: 44px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        .status-dot.idle { background: #8b949e; }
        .status-dot.scanning { background: #d29922; animation: pulse 1s infinite; }
        .status-dot.done { background: #3fb950; }
        .status-dot.error { background: #f85149; }
        .signal-flash { animation: flashGreen 0.5s; }
        @keyframes flashGreen { 0% { background: rgba(63, 185, 80, 0.3); } 100% { background: transparent; } }
        .price-filter-info {
            background: #1c2128;
            border: 1px solid #30363d;
            border-radius: 6px;
            padding: 8px 14px;
            font-size: 12px;
            color: #8b949e;
            margin-bottom: 12px;
            display: inline-block;
        }
        .price-filter-info strong {
            color: #58a6ff;
        }
        .isin-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
        }
        .isin-modal-content {
            background-color: #161b22;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #30363d;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
            max-height: 500px;
            overflow-y: auto;
        }
        .isin-modal-content h3 {
            color: #58a6ff;
            margin-top: 0;
        }
        .isin-modal-item {
            padding: 10px;
            margin: 8px 0;
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .isin-modal-item:hover {
            background: #1c2128;
            border-color: #58a6ff;
        }
        .isin-modal-item .isin-code {
            font-weight: 700;
            color: #58a6ff;
        }
        .isin-modal-close {
            float: right;
            color: #8b949e;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .isin-modal-close:hover {
            color: #f85149;
        }
        .market-badge-iframe {
            width: 100%;
            height: 60px;
            border: none;
            margin-top: 6px;
            display: block;
        }
    </style>
</head>
<body>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <div>
            <h2>🎯 Momentum Signals <span style="font-size:12px;color:#8b949e;font-weight:400;">(live streaming)</span></h2>
            <div class="meta" id="timestamp">Click "Scan Now" to start</div>
        </div>
        <div>
            <button class="scan-btn" id="scanBtn" onclick="startScan()">🔍 Scan Now</button>
        </div>
    </div>

    <div class="price-filter-info">
        ⚡ Auto-filter: Only stocks <strong>under $x</strong> — focusing on high-momentum small caps
    </div>

    <div class="status-bar" id="statusBar">
        <span class="status-dot idle" id="statusDot"></span>
        <span id="statusMessage">Ready to scan</span>
    </div>

    <div class="stats">
        <div class="stat-box"><div class="stat-label">Total Signals</div><div class="stat-value" id="totalCount">0</div></div>
        <div class="stat-box"><div class="stat-label">TradingView</div><div class="stat-value" id="tvCount" style="color: #58a6ff">0</div></div>
        <div class="stat-box"><div class="stat-label">NASDAQ</div><div class="stat-value" id="nasCount" style="color: #8957e5">0</div></div>
        <div class="stat-box"><div class="stat-label">NASDAQ Gainers</div><div class="stat-value" id="nasgCount" style="color: #d29922">0</div></div>
        <div class="stat-box"><div class="stat-label">Yahoo Trending</div><div class="stat-value" id="ytrendCount" style="color: #39c5cf">0</div></div>
        <div class="stat-box"><div class="stat-label">Breakouts</div><div class="stat-value" id="boCount">0</div></div>
        <div class="stat-box"><div class="stat-label">Vol Surge</div><div class="stat-value" id="vsCount">0</div></div>
        <div class="stat-box"><div class="stat-label">Reversals</div><div class="stat-value" id="rvCount">0</div></div>
        <div class="stat-box"><div class="stat-label">High Mom</div><div class="stat-value" id="hmCount" style="color: #f0883e">0</div></div>
        <div class="stat-box"><div class="stat-label">Mom Gap</div><div class="stat-value" id="mgCount" style="color: #da3633">0</div></div>
        <div class="stat-box"><div class="stat-label">Progress</div><div class="stat-value" id="progressCount" style="font-size:14px;">0%</div></div>
    </div>

    <div class="source-header">
        <div class="source-pill active" data-filter="all" onclick="filterSource('all')">All Sources</div>
        <div class="source-pill" data-filter="TradingView" onclick="filterSource('TradingView')">TradingView</div>
        <div class="source-pill" data-filter="NASDAQ" onclick="filterSource('NASDAQ')">NASDAQ</div>
        <div class="source-pill" data-filter="NasdaqGainers" onclick="filterSource('NasdaqGainers')">NASDAQ Gainers</div>
        <div class="source-pill" data-filter="YahooTrending" onclick="filterSource('YahooTrending')">Yahoo Trending</div>
    </div>

    <div class="results-container" id="resultsContainer">
        <table id="signalTable">
            <thead>
                <tr>
                    <th data-col="symbol" data-type="string" onclick="sortTable('symbol')">Symbol<span class="arrow"></span></th>
                    <th data-col="source" data-type="string" onclick="sortTable('source')">Source<span class="arrow"></span></th>
                    <th data-col="strategy" data-type="string" onclick="sortTable('strategy')">Strategy<span class="arrow"></span></th>
                    <th data-col="trigger" data-type="string" onclick="sortTable('trigger')">Trigger<span class="arrow"></span></th>
                    <th data-col="trigger_pct" data-type="number" onclick="sortTable('trigger_pct')">Trigger %<span class="arrow"></span></th>
                    <th data-col="price" data-type="number" onclick="sortTable('price')">Price<span class="arrow"></span></th>
                    <th data-col="vol" data-type="number" onclick="sortTable('vol')">Volume<span class="arrow"></span></th>
                    <th data-col="avg_vol" data-type="number" onclick="sortTable('avg_vol')">Avg Vol<span class="arrow"></span></th>
                    <th data-col="spike" data-type="number" onclick="sortTable('spike')">Spike<span class="arrow"></span></th>
                    <th data-col="rsi" data-type="number" onclick="sortTable('rsi')">RSI<span class="arrow"></span></th>
                    <th data-col="mom3d" data-type="number" onclick="sortTable('mom3d')">3d Mom<span class="arrow"></span></th>
                    <th data-col="band" data-type="string" onclick="sortTable('band')">Band<span class="arrow"></span></th>
                    <th data-col="isin" data-type="string" onclick="sortTable('isin')">ISIN<span class="arrow"></span></th>
                </tr>
            </thead>
            <tbody id="signalBody">
                </tbody>
        </table>
    </div>

    <div id="isinModal" class="isin-modal">
        <div class="isin-modal-content">
            <span class="isin-modal-close" onclick="closeISINModal()">&times;</span>
            <h3>Select ISIN for <span id="isinModalSymbol"></span></h3>
            <div id="isinModalResults"></div>
        </div>
    </div>

    <script>
    let currentFilter = 'all';
    let allSignals = [];
    let eventSource = null;
    let isinCache = {};
    let stats = {
        tv_checked: 0, tv_signals: 0, tv_total: 0,
        nas_checked: 0, nas_signals: 0, nas_total: 0,
        nas_skipped_price: 0, nas_skipped_change: 0, nas_skipped_cap: 0,
        nas_skipped_total: 0,
        nasg_checked: 0, nasg_signals: 0, nasg_total: 0,
        ytrend_checked: 0, ytrend_signals: 0, ytrend_total: 0,
    };

    // ─── Sorting State ────────────────────────────────────────────────────────
    let sortColumn = 'trigger_pct';
    let sortDirection = 'desc';
    let sortType = 'number';

    const SOURCE_KEY_MAP = {
        'TradingView': 'tv',
        'NASDAQ': 'nas',
        'NasdaqGainers': 'nasg',
        'YahooTrending': 'ytrend',
    };

    // ─── ISIN Lookup Function ──────────────────────────────────────────────

    async function lookupISIN(symbol, companyName, rowElement) {
        const isinCell = rowElement.querySelector('td:last-child');
        const link = isinCell.querySelector('.isin-link');
        
        // Show loading state
        link.textContent = '⏳ Searching...';
        link.className = 'isin-link loading';
        
        try {
            // Build search terms: first word, then full name, then symbol
            let searchTerms = [];
            if (companyName && companyName !== symbol) {
                let firstWord = companyName.split(' ')[0];
                if (firstWord && firstWord !== symbol) {
                    searchTerms.push(firstWord);
                }
                searchTerms.push(companyName);
            }
            searchTerms.push(symbol);
            
            let allResults = [];
            let foundIsin = null;
            
            // Try each search term
            for (let term of searchTerms) {
                const response = await fetch('ingdiba.php?symbol=' + encodeURIComponent(term) + '&json');
                const data = await response.json();
                
                // Check for multiple results
                if (data.status === 'multiple_results' && data.search && data.search.results) {
                    data.search.results.forEach(result => {
                        if (!allResults.some(r => r.isin === result.isin)) {
                            allResults.push(result);
                        }
                    });
                }
                
                // Check for single success
                if (data.status === 'success' && data.data && data.data.price && data.data.price.isin) {
                    const isin = data.data.price.isin;
                    if (!allResults.some(r => r.isin === isin)) {
                        allResults.push({
                            isin: isin,
                            name: data.data.price.name || companyName || symbol,
                            price: data.data.price.price,
                            currency: data.data.price.currency
                        });
                    }
                }
                
                // If we found exactly one result, stop searching
                if (allResults.length === 1) {
                    foundIsin = allResults[0].isin;
                    break;
                }
            }
            
            // Display results
            if (foundIsin) {
                // Single result found
                link.textContent = foundIsin;
                link.className = 'isin-link';
                link.style.color = '#3fb950';
                link.style.borderColor = '#3fb950';
                isinCache[symbol] = foundIsin;
                
                // Inject visual market connection badge below the link
                appendMarketIframe(isinCell, foundIsin);
                
                // Update the signal data
                allSignals.forEach(sig => {
                    if (sig.symbol === symbol) {
                        sig.isin = foundIsin;
                    }
                });
            } else if (allResults.length > 1) {
                // Multiple results - show selection modal
                link.textContent = 'Multiple (' + allResults.length + ') ▼';
                link.className = 'isin-link';
                link.style.color = '#d29922';
                link.style.borderColor = '#d29922';
                
                isinCache[symbol] = allResults;
                
                // Store for modal
                link.onclick = function(e) {
                    e.stopPropagation();
                    openISINModal(symbol, allResults);
                };
            } else {
                // No results found
                link.textContent = '❌ Not found';
                link.className = 'isin-link';
                link.style.color = '#f85149';
                link.style.borderColor = '#f85149';
                isinCache[symbol] = null;
            }
            
        } catch (error) {
            console.error('ISIN lookup error:', error);
            link.textContent = '❌ Error';
            link.className = 'isin-link';
            link.style.color = '#f85149';
            link.style.borderColor = '#f85149';
        }
    }

    // ─── ISIN Modal Functions ──────────────────────────────────────────────

    function openISINModal(symbol, isinData) {
        const modal = document.getElementById('isinModal');
        const symbolSpan = document.getElementById('isinModalSymbol');
        const resultsDiv = document.getElementById('isinModalResults');
        
        symbolSpan.textContent = symbol;
        resultsDiv.innerHTML = '';
        
        if (Array.isArray(isinData)) {
            isinData.forEach(result => {
                const div = document.createElement('div');
                div.className = 'isin-modal-item';
                div.innerHTML = `
                    <div><span class="isin-code">${result.isin}</span></div>
                    <div style="font-size:12px;color:#8b949e;">${result.name || 'Unknown'}</div>
                    <div style="font-size:11px;color:#484f58;">Price: ${result.price || 'N/A'} ${result.currency || ''}</div>
                `;
                div.onclick = function() {
                    selectISIN(symbol, result.isin);
                    closeISINModal();
                };
                resultsDiv.appendChild(div);
            });
        }
        
        modal.style.display = 'block';
    }

    function closeISINModal() {
        document.getElementById('isinModal').style.display = 'none';
    }

    function selectISIN(symbol, isin) {
        // Update cache
        isinCache[symbol] = isin;
        
        // Update all rows for this symbol
        const rows = document.querySelectorAll(`#signalBody tr[data-symbol="${symbol}"]`);
        rows.forEach(row => {
            const isinCell = row.querySelector('td:last-child');
            const link = isinCell.querySelector('.isin-link');
            if (link) {
                link.textContent = isin;
                link.className = 'isin-link';
                link.style.color = '#3fb950';
                link.style.borderColor = '#3fb950';
                link.onclick = null; // Remove click handler
                
                // Inject visual market connection badge below selection box
                appendMarketIframe(isinCell, isin);
            }
        });
        
        // Update the signal data
        allSignals.forEach(sig => {
            if (sig.symbol === symbol) {
                sig.isin = isin;
            }
        });
    }

    // ─── Embed market iframe badge inside cell container ───────────────────

    function appendMarketIframe(containerElement, isin) {
        // Clean alternative badge if already generated
        const existingIframe = containerElement.querySelector('.market-badge-iframe');
        if (existingIframe) {
            existingIframe.remove();
        }
        
        const iframe = document.createElement('iframe');
        iframe.className = 'market-badge-iframe';
        iframe.src = `xchangemarket_badge.php?q=${encodeURIComponent(isin)}`;
        
        containerElement.appendChild(iframe);
    }

    // ─── Start Scan with EventSource ──────────────────────────────────────

    function startScan() {
        const btn = document.getElementById('scanBtn');
        btn.disabled = true;
        btn.textContent = '⏳ Scanning...';
        btn.className = 'scan-btn scanning';

        allSignals = [];
        document.getElementById('signalBody').innerHTML = '';
        document.getElementById('totalCount').textContent = '...';
        document.getElementById('totalCount').className = 'stat-value loading';

        setStatus('scanning', 'Initializing scan...');

        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }

        eventSource = new EventSource('?stream=1');

        eventSource.addEventListener('status', function(e) {
            const data = JSON.parse(e.data);
            setStatus('scanning', data.message);
        });

        eventSource.addEventListener('progress', function(e) {
            const data = JSON.parse(e.data);
            const source = data.source;
            const checked = data.checked;
            const total = data.total;
            const skipped = data.skipped || 0;
            const keptPct = data.kept_pct || 0;

            const key = SOURCE_KEY_MAP[source];
            if (key) {
                stats[`${key}_checked`] = checked;
                stats[`${key}_total`] = total;
                if (skipped) stats[`${key}_skipped_total`] = skipped;
            }

            const totalItems = Object.keys(SOURCE_KEY_MAP).reduce((sum, s) => sum + (stats[`${SOURCE_KEY_MAP[s]}_total`] || 0), 0);
            const checkedItems = Object.keys(SOURCE_KEY_MAP).reduce((sum, s) => sum + (stats[`${SOURCE_KEY_MAP[s]}_checked`] || 0), 0);
            const progress = totalItems > 0 ? Math.round((checkedItems / totalItems) * 100) : 0;

            document.getElementById('progressCount').textContent = progress + '%';

            let msg = `${source}: ${checked}/${total}`;
            if (skipped > 0) msg += ` (kept ${keptPct}% of original)`;
            if (data.signals) msg += `, ${data.signals} signals`;
            setStatus('scanning', msg);
        });

        eventSource.addEventListener('signal', function(e) {
            const sig = JSON.parse(e.data);

            if (sig.stats) {
                stats = sig.stats;
                updateStats();
            }

            // Add to array
            allSignals.push(sig);
            
            // Render with sorting
            renderAllSignals();

            document.getElementById('totalCount').textContent = allSignals.length;
            document.getElementById('totalCount').className = 'stat-value';

            const container = document.getElementById('resultsContainer');
            container.scrollTop = container.scrollHeight;
        });

        eventSource.addEventListener('done', function(e) {
            const data = JSON.parse(e.data);
            setStatus('done', data.message);

            btn.disabled = false;
            btn.textContent = '🔄 Scan Again';
            btn.className = 'scan-btn';

            if (eventSource) {
                eventSource.close();
                eventSource = null;
            }

            if (data.stats) {
                stats = data.stats;
                updateStats();
            }

            document.getElementById('progressCount').textContent = '100%';
        });

        eventSource.addEventListener('error', function(e) {
            console.log('EventSource error:', e);

            if (eventSource && eventSource.readyState === EventSource.CLOSED) {
                btn.disabled = false;
                btn.textContent = '🔄 Scan Again';
                btn.className = 'scan-btn';
                setStatus('error', 'Connection closed unexpectedly');
            }
        });

        eventSource.addEventListener('close', function(e) {
            btn.disabled = false;
            btn.textContent = '🔄 Scan Again';
            btn.className = 'scan-btn';
        });
    }

    // ─── Sort Table Function ──────────────────────────────────────────────

    function sortTable(column) {
        if (sortColumn === column) {
            sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            sortColumn = column;
            sortDirection = 'desc';
        }

        const th = document.querySelector(`th[data-col="${column}"]`);
        sortType = th ? th.dataset.type : 'string';

        document.querySelectorAll('th').forEach(h => {
            h.classList.remove('sorted', 'sorted-asc', 'sorted-desc');
        });
        th.classList.add('sorted', `sorted-${sortDirection}`);

        renderAllSignals();
    }

    // ─── Render All Signals (sorted) ─────────────────────────────────────

    function renderAllSignals() {
        const tbody = document.getElementById('signalBody');
        tbody.innerHTML = '';

        const sorted = [...allSignals].sort((a, b) => {
            let valA = a[sortColumn];
            let valB = b[sortColumn];
            
            if (valA === null || valA === undefined) valA = '';
            if (valB === null || valB === undefined) valB = '';
            
            if (sortType === 'string') {
                valA = String(valA).toLowerCase();
                valB = String(valB).toLowerCase();
            }
            
            if (sortType === 'number') {
                valA = parseFloat(valA) || 0;
                valB = parseFloat(valB) || 0;
            }
            
            if (valA < valB) return sortDirection === 'asc' ? -1 : 1;
            if (valA > valB) return sortDirection === 'asc' ? 1 : -1;
            return 0;
        });

        sorted.forEach(sig => {
            const row = createSignalRow(sig);
            tbody.appendChild(row);
        });
    }

    // ─── Create a single signal row ──────────────────────────────────────

    function createSignalRow(sig) {
        const rsiClass = sig.rsi > 70 ? 'rsi-hot' : (sig.rsi > 50 ? 'rsi-warn' : 'rsi-ok');
        const stratClass = {
            'BREAKOUT': 'strat-breakout',
            'VOL-SURGE': 'strat-vol',
            'REVERSAL': 'strat-rev',
            'HIGH-MOMENTUM': 'strat-high',
            'MOM-GAP': 'strat-gap',
        }[sig.strategy] || '';
        const sourceClassMap = {
            'TradingView': 'source-tv',
            'NASDAQ': 'source-nas',
            'NasdaqGainers': 'source-nasg',
            'YahooTrending': 'source-ytrend',
        };
        const sourceLabelMap = {
            'TradingView': 'TV',
            'NASDAQ': 'NAS',
            'NasdaqGainers': 'NASG',
            'YahooTrending': 'YTR',
        };
        const sourceClass = sourceClassMap[sig.source] || '';
        const sourceLabel = sourceLabelMap[sig.source] || sig.source;
        const bandClass = sig.band ? 'band-' + sig.band.toLowerCase() : '';

        // ISIN cell with lookup link
        let isinHtml = '';
        if (sig.isin) {
            // Already have ISIN from cache
            isinHtml = `<span class="isin-link" style="color:#3fb950;border-color:#3fb950;">${sig.isin}</span>
                        <iframe class="market-badge-iframe" src="xchangemarket_badge.php?q=${encodeURIComponent(sig.isin)}"></iframe>`;
        } else {
            // Show lookup link
            const companyName = sig.companyName || sig.symbol;
            isinHtml = `<span class="isin-link" onclick="lookupISIN('${sig.symbol}', '${companyName.replace(/'/g, "\\'")}', this.closest('tr'))">🔍 Lookup ISIN</span>`;
        }

        const display = (currentFilter === 'all' || sig.source === currentFilter) ? '' : 'style="display:none;"';

        const tr = document.createElement('tr');
        tr.className = 'signal-flash';
        tr.setAttribute('data-symbol', escapeHtml(sig.symbol));
        tr.setAttribute('data-source', escapeHtml(sig.source));
        tr.setAttribute('data-strategy', escapeHtml(sig.strategy));
        tr.setAttribute('data-trigger', escapeHtml(sig.trigger));
        tr.setAttribute('data-trigger_pct', sig.trigger_pct);
        tr.setAttribute('data-price', sig.price);
        tr.setAttribute('data-vol', sig.vol);
        tr.setAttribute('data-avg_vol', sig.avg_vol);
        tr.setAttribute('data-spike', sig.spike);
        tr.setAttribute('data-rsi', sig.rsi);
        tr.setAttribute('data-mom3d', sig.mom3d);
        tr.setAttribute('data-band', sig.band || '');
        tr.setAttribute('data-isin', sig.isin || '');
        tr.setAttribute('data-company', sig.companyName || sig.symbol);
        tr.setAttribute('style', display);

        tr.innerHTML = `
            <td class="sym">
                <a href="<?= $TICKER_BASE_URL ?>${escapeHtml(sig.symbol)}" target="_blank" rel="noopener">${escapeHtml(sig.symbol)}</a>
                <span class="company-name">${escapeHtml(sig.companyName || sig.symbol)}</span>
            </td>
            <td><span class="source-badge ${sourceClass}">${sourceLabel}</span></td>
            <td><span class="strat ${stratClass}">${sig.strategy}</span></td>
            <td style="color:#8b949e;font-size:12px;">${escapeHtml(sig.trigger)}</td>
            <td>${sig.trigger_pct >= 0 ? '+' : ''}${sig.trigger_pct}%</td>
            <td>$${sig.price.toFixed(2)}</td>
            <td>${formatVol(sig.vol)}</td>
            <td>${formatVol(sig.avg_vol)}</td>
            <td class="spike">${sig.spike}x</td>
            <td class="${rsiClass}">${sig.rsi}</td>
            <td class="${sig.mom3d >= 0 ? 'mom-pos' : 'mom-neg'}">${sig.mom3d > 0 ? '+' : ''}${sig.mom3d}%</td>
            <td>${sig.band ? `<span class="band-badge ${bandClass}">${sig.band}</span>` : ''}</td>
            <td>${isinHtml}</td>
        `;

        return tr;
    }

    // ─── Update Stats ──────────────────────────────────────────────────────

    function updateStats() {
        document.getElementById('tvCount').textContent = stats.tv_signals || 0;
        document.getElementById('nasCount').textContent = stats.nas_signals || 0;
        document.getElementById('nasgCount').textContent = stats.nasg_signals || 0;
        document.getElementById('ytrendCount').textContent = stats.ytrend_signals || 0;

        const boCount = allSignals.filter(s => s.strategy === 'BREAKOUT').length;
        const vsCount = allSignals.filter(s => s.strategy === 'VOL-SURGE').length;
        const rvCount = allSignals.filter(s => s.strategy === 'REVERSAL').length;
        const hmCount = allSignals.filter(s => s.strategy === 'HIGH-MOMENTUM').length;
        const mgCount = allSignals.filter(s => s.strategy === 'MOM-GAP').length;

        document.getElementById('boCount').textContent = boCount;
        document.getElementById('vsCount').textContent = vsCount;
        document.getElementById('rvCount').textContent = rvCount;
        document.getElementById('hmCount').textContent = hmCount;
        document.getElementById('mgCount').textContent = mgCount;

        const totalItems = Object.keys(SOURCE_KEY_MAP).reduce((sum, s) => sum + (stats[`${SOURCE_KEY_MAP[s]}_total`] || 0), 0);
        const checkedItems = Object.keys(SOURCE_KEY_MAP).reduce((sum, s) => sum + (stats[`${SOURCE_KEY_MAP[s]}_checked`] || 0), 0);
        const progress = totalItems > 0 ? Math.round((checkedItems / totalItems) * 100) : 0;
        document.getElementById('progressCount').textContent = progress + '%';
    }

    // ─── Status Bar ────────────────────────────────────────────────────────

    function setStatus(type, message) {
        const dot = document.getElementById('statusDot');
        const msg = document.getElementById('statusMessage');

        dot.className = 'status-dot ' + type;
        msg.textContent = message;
    }

    // ─── Filtering ──────────────────────────────────────────────────────────

    function filterSource(source) {
        currentFilter = source;

        document.querySelectorAll('.source-pill').forEach(p => {
            p.classList.toggle('active', p.dataset.filter === source);
        });

        const rows = document.querySelectorAll('#signalBody tr');
        rows.forEach(row => {
            if (source === 'all' || row.dataset.source === source) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatVol(v) {
        return v >= 1000000 ? (v/1000000).toFixed(1) + 'M' : (v/1000).toFixed(1) + 'K';
    }

    // ─── Initialize with default sort on load ──────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
        const defaultTh = document.querySelector('th[data-col="trigger_pct"]');
        if (defaultTh) {
            defaultTh.classList.add('sorted', 'sorted-desc');
        }
        sortColumn = 'trigger_pct';
        sortDirection = 'desc';
        sortType = 'number';
        
        // Close modal on click outside
        window.onclick = function(event) {
            const modal = document.getElementById('isinModal');
            if (event.target === modal) {
                closeISINModal();
            }
        };
    });
    </script>

</body>
</html>