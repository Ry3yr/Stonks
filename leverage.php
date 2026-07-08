<?php
/**
 * Portfolio tracker
 * - Reads stocks.json (same directory as this file)
 * - Only considers entries where "nrbght" is set (i.e. shares actually bought)
 * - Entry prices in stocks.json are ALWAYS USD
 * - Pulls live price from Yahoo Finance, converts to USD if the ticker's
 *   listing currency isn't USD (e.g. .HK, .DE tickers)
 * - Shows P&L, % change, and TWO stop-loss suggestions:
 *   - SAFE stop: PROTECTS GAINS (locks in a percentage of your profit)
 *   - SWING stop: wider, based on volatility (lets stock breathe)
 * - Includes next-day prediction sparklines (best, worst, median cases)
 * - Uses 60 days of historical data for institutional stocks
 * - Classifies stocks as: Penny Stock, High-Growth Volatile, Institutional Keeper, Recovery Play, etc.
 * - Gives clear, actionable advice in plain language
 * - Sorted by gain % (highest to lowest)
 *
 * Usage: put this file + stocks.json in the same folder, run via PHP built-in
 * server (php -S localhost:8000) or any PHP-enabled webserver, then open
 * portfolio.php in a browser.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ---------- Config ----------
$jsonFile = __DIR__ . '/stocks.json';
$userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
$minTrimProfitUsd = 10.0;
$historyDays = 60;

// ---------- Helpers ----------

function fetchYahooQuote(string $ticker, string $userAgent, bool $withHistory = false, int $historyDays = 60): ?array {
    if ($withHistory) {
        $range = '3mo';
    } else {
        $range = '5d';
    }
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . rawurlencode($ticker)
         . "?range={$range}&interval=1d";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => $userAgent,
    ]);
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$raw) {
        return null;
    }

    $data = json_decode($raw, true);
    $result = $data['chart']['result'][0] ?? null;
    if (!$result) {
        return null;
    }

    $meta = $result['meta'] ?? [];
    $price = $meta['regularMarketPrice'] ?? null;
    if ($price === null) {
        $price = $result['indicators']['quote'][0]['close'][0] ?? null;
    }
    if ($price === null) {
        return null;
    }

    $prevClose = $meta['chartPreviousClose'] ?? $meta['previousClose'] ?? null;

    $history = [];
    if ($withHistory) {
        $timestamps = $result['timestamp'] ?? [];
        $quote = $result['indicators']['quote'][0] ?? [];
        $highs = $quote['high'] ?? [];
        $lows = $quote['low'] ?? [];
        $closes = $quote['close'] ?? [];

        foreach ($timestamps as $i => $ts) {
            if (!isset($closes[$i], $highs[$i], $lows[$i]) || $closes[$i] === null) {
                continue;
            }
            $history[] = [
                'date'  => date('Y-m-d', $ts),
                'high'  => (float) $highs[$i],
                'low'   => (float) $lows[$i],
                'close' => (float) $closes[$i],
            ];
        }
        $history = array_slice($history, -$historyDays);
    }

    return [
        'price'     => (float) $price,
        'prevClose' => $prevClose !== null ? (float) $prevClose : null,
        'currency'  => strtoupper($meta['currency'] ?? 'USD'),
        'history'   => $history,
    ];
}

function getFxRateToUsd(string $currency, string $userAgent, array &$fxCache): ?float {
    $currency = strtoupper($currency);
    if ($currency === 'USD') {
        return 1.0;
    }
    if (isset($fxCache[$currency])) {
        return $fxCache[$currency];
    }
    $quote = fetchYahooQuote($currency . 'USD=X', $userAgent);
    $rate = $quote['price'] ?? null;
    $fxCache[$currency] = $rate;
    return $rate;
}

function computeRecentStats(array $history): ?array {
    if (count($history) < 3) {
        return null;
    }
    $lows = array_column($history, 'low');
    $highs = array_column($history, 'high');
    $closes = array_column($history, 'close');

    $rangePcts = [];
    $dailyChanges = [];
    foreach ($history as $i => $bar) {
        if ($bar['close'] > 0) {
            $rangePcts[] = (($bar['high'] - $bar['low']) / $bar['close']) * 100;
        }
        if ($i > 0 && $history[$i-1]['close'] > 0) {
            $dailyChanges[] = (($bar['close'] - $history[$i-1]['close']) / $history[$i-1]['close']) * 100;
        }
    }

    return [
        'days'         => count($history),
        'low'          => min($lows),
        'high'         => max($highs),
        'avgClose'     => array_sum($closes) / count($closes),
        'avgRangePct'  => count($rangePcts) ? array_sum($rangePcts) / count($rangePcts) : 0.0,
        'dailyChanges' => $dailyChanges,
        'lastClose'    => end($closes),
    ];
}

/**
 * Classify a stock based on price, volatility, and performance
 */
function classifyStock(float $price, float $avgDailyMove, float $stdDev, float $gainPct, float $avgRangePct): array {
    $classification = [];
    $emoji = '';
    $quip = '';
    $color = '';
    
    // Determine price category
    if ($price < 5) {
        $priceCategory = 'penny';
        $priceLabel = 'Penny Stock';
        $priceEmoji = '🪙';
    } elseif ($price < 20) {
        $priceCategory = 'low';
        $priceLabel = 'Low-Priced';
        $priceEmoji = '💵';
    } elseif ($price < 50) {
        $priceCategory = 'mid';
        $priceLabel = 'Mid-Cap';
        $priceEmoji = '📊';
    } else {
        $priceCategory = 'high';
        $priceLabel = 'Institutional';
        $priceEmoji = '🏛️';
    }
    
    // Determine volatility category
    if ($avgDailyMove > 10 || $stdDev > 8) {
        $volCategory = 'high';
        $volLabel = 'Very Volatile';
        $volEmoji = '⚡';
    } elseif ($avgDailyMove > 5 || $stdDev > 4) {
        $volCategory = 'medium';
        $volLabel = 'Moderate Volatility';
        $volEmoji = '📈';
    } else {
        $volCategory = 'low';
        $volLabel = 'Low Volatility';
        $volEmoji = '🐢';
    }
    
    // Determine performance category
    if ($gainPct > 50) {
        $perfCategory = 'star';
        $perfLabel = 'Star Performer';
        $perfEmoji = '⭐';
    } elseif ($gainPct > 20) {
        $perfCategory = 'gainer';
        $perfLabel = 'Solid Gainer';
        $perfEmoji = '📈';
    } elseif ($gainPct > 5) {
        $perfCategory = 'steady';
        $perfLabel = 'Steady Growth';
        $perfEmoji = '🌱';
    } elseif ($gainPct > -5) {
        $perfCategory = 'flat';
        $perfLabel = 'Flat';
        $perfEmoji = '➡️';
    } elseif ($gainPct > -20) {
        $perfCategory = 'dip';
        $perfLabel = 'In a Dip';
        $perfEmoji = '📉';
    } else {
        $perfCategory = 'crash';
        $perfLabel = 'Heavy Loss';
        $perfEmoji = '🔥';
    }
    
    // Combine for final classification
    if ($priceCategory == 'penny' && $volCategory == 'high') {
        $classification['type'] = 'penny_volatile';
        $classification['label'] = 'High-Risk Penny Stock';
        $emoji = '🎰';
        $quip = 'High risk, high reward — trade with caution';
        $color = '#f44336';
    } elseif ($priceCategory == 'penny') {
        $classification['type'] = 'penny';
        $classification['label'] = 'Penny Stock';
        $emoji = '🪙';
        $quip = 'Cheap but unpredictable — manage risk tightly';
        $color = '#ff9800';
    } elseif ($priceCategory == 'high' && $volCategory == 'low') {
        $classification['type'] = 'keeper';
        $classification['label'] = 'Institutional Keeper';
        $emoji = '🏛️';
        $quip = 'Solid, stable, hold for the long term';
        $color = '#4caf50';
    } elseif ($priceCategory == 'high' && $volCategory == 'medium') {
        $classification['type'] = 'growth';
        $classification['label'] = 'Quality Growth';
        $emoji = '🚀';
        $quip = 'Growing with institutional backing — hold through volatility';
        $color = '#8bc34a';
    } elseif ($volCategory == 'high' && $perfCategory == 'star') {
        $classification['type'] = 'rocket';
        $classification['label'] = 'Rocket Ship 🚀';
        $emoji = '🚀';
        $quip = 'Massive gains but extremely volatile — lock in profits';
        $color = '#ff6f00';
    } elseif ($volCategory == 'high' && $perfCategory == 'crash') {
        $classification['type'] = 'risky';
        $classification['label'] = 'High-Risk Recovery';
        $emoji = '🎲';
        $quip = 'Selling off hard — only for high-risk tolerance';
        $color = '#d32f2f';
    } elseif ($volCategory == 'medium' && $perfCategory == 'gainer') {
        $classification['type'] = 'momentum';
        $classification['label'] = 'Momentum Play';
        $emoji = '⚡';
        $quip = 'Good momentum with moderate risk — let it ride with a stop';
        $color = '#ffa726';
    } elseif ($volCategory == 'low' && $perfCategory == 'steady') {
        $classification['type'] = 'dividend';
        $classification['label'] = 'Steady Eddi';
        $emoji = '🐢';
        $quip = 'Slow and steady — set it and forget it';
        $color = '#66bb6a';
    } elseif ($perfCategory == 'dip' && $volCategory == 'high') {
        $classification['type'] = 'bounce';
        $classification['label'] = 'Bounce Candidate';
        $emoji = '🏀';
        $quip = 'High volatility dip — could bounce back strongly';
        $color = '#ff9800';
    } elseif ($perfCategory == 'dip') {
        $classification['type'] = 'value';
        $classification['label'] = 'Value Play';
        $emoji = '💎';
        $quip = 'Temporarily down but fundamentally sound — consider averaging down';
        $color = '#4caf50';
    } else {
        $classification['type'] = 'mixed';
        $classification['label'] = 'Mixed Signals';
        $emoji = '🤔';
        $quip = 'No clear pattern — wait for confirmation';
        $color = '#9e9e9e';
    }
    
    $classification['emoji'] = $emoji;
    $classification['quip'] = $quip;
    $classification['color'] = $color;
    $classification['priceLabel'] = $priceLabel;
    $classification['volLabel'] = $volLabel;
    $classification['perfLabel'] = $perfLabel;
    
    return $classification;
}

function generatePredictions(float $currentPrice, array $history, float $fxRate): array {
    $dataPoints = count($history);
    
    if ($dataPoints < 3) {
        return [
            'best' => $currentPrice * 1.05,
            'worst' => $currentPrice * 0.95,
            'median' => $currentPrice,
            'bestPct' => 5.0,
            'worstPct' => -5.0,
            'medianPct' => 0.0,
            'confidence' => 'low',
            'confidenceScore' => 20,
            'trend' => 'flat',
            'reasoning' => 'Not enough historical data (need at least 3 days) for reliable predictions.',
            'dataPoints' => $dataPoints,
            'isInstitutional' => false
        ];
    }

    $changes = [];
    for ($i = 1; $i < count($history); $i++) {
        if ($history[$i-1]['close'] > 0) {
            $changes[] = (($history[$i]['close'] - $history[$i-1]['close']) / $history[$i-1]['close']) * 100;
        }
    }

    if (count($changes) < 2) {
        return [
            'best' => $currentPrice * 1.05,
            'worst' => $currentPrice * 0.95,
            'median' => $currentPrice,
            'bestPct' => 5.0,
            'worstPct' => -5.0,
            'medianPct' => 0.0,
            'confidence' => 'low',
            'confidenceScore' => 15,
            'trend' => 'flat',
            'reasoning' => 'Only ' . count($changes) . ' daily changes available.',
            'dataPoints' => $dataPoints,
            'isInstitutional' => false
        ];
    }

    $sortedChanges = $changes;
    sort($sortedChanges);
    $count = count($sortedChanges);
    
    $overallAvg = array_sum($sortedChanges) / $count;
    
    $stdDev = 0;
    foreach ($sortedChanges as $change) {
        $stdDev += pow($change - $overallAvg, 2);
    }
    $stdDev = sqrt($stdDev / $count);
    
    $isInstitutional = ($currentPrice > 20 && $stdDev < 8);
    
    $recentDays = $isInstitutional ? 10 : 5;
    $recentDays = min($recentDays, $count);
    $recentAvg = 0;
    for ($i = $count - $recentDays; $i < $count; $i++) {
        $recentAvg += $sortedChanges[$i];
    }
    $recentAvg = $recentAvg / $recentDays;
    
    $trendWeight = $isInstitutional ? 0.6 : 0.7;
    $trendFactor = ($recentAvg * $trendWeight) + ($overallAvg * (1 - $trendWeight));
    
    $confidenceScore = 50;
    
    if ($stdDev < 2) {
        $confidenceScore += 25;
    } elseif ($stdDev < 4) {
        $confidenceScore += 15;
    } elseif ($stdDev < 8) {
        $confidenceScore += 5;
    } else {
        $confidenceScore -= 10;
    }
    
    $trendStrength = abs($trendFactor);
    if ($trendStrength > 5) {
        $confidenceScore += 15;
    } elseif ($trendStrength > 2) {
        $confidenceScore += 8;
    } else {
        $confidenceScore -= 5;
    }
    
    $trendDiff = abs($recentAvg - $overallAvg);
    if ($trendDiff < 1) {
        $confidenceScore += 10;
    } elseif ($trendDiff < 3) {
        $confidenceScore += 5;
    } else {
        $confidenceScore -= 10;
    }
    
    if ($count >= 40) {
        $confidenceScore += 20;
    } elseif ($count >= 30) {
        $confidenceScore += 15;
    } elseif ($count >= 20) {
        $confidenceScore += 10;
    } elseif ($count >= 10) {
        $confidenceScore += 5;
    } else {
        $confidenceScore -= 5;
    }
    
    $range = max($sortedChanges) - min($sortedChanges);
    if ($range > 20) {
        $confidenceScore -= 15;
    } elseif ($range > 15) {
        $confidenceScore -= 8;
    } elseif ($range > 10) {
        $confidenceScore -= 3;
    } else {
        $confidenceScore += 5;
    }
    
    $avgMove = array_sum(array_map('abs', $sortedChanges)) / $count;
    if ($avgMove > 10) {
        $confidenceScore -= 10;
    } elseif ($avgMove > 6) {
        $confidenceScore -= 5;
    } elseif ($avgMove < 3) {
        $confidenceScore += 5;
    }
    
    if ($isInstitutional && $stdDev < 6) {
        $confidenceScore += 10;
    }
    
    $reversals = 0;
    for ($i = 1; $i < count($sortedChanges); $i++) {
        if (($sortedChanges[$i] > 0 && $sortedChanges[$i-1] < 0) || 
            ($sortedChanges[$i] < 0 && $sortedChanges[$i-1] > 0)) {
            $reversals++;
        }
    }
    $reversalRate = $reversals / (count($sortedChanges) - 1);
    if ($reversalRate > 0.5) {
        $confidenceScore -= 10;
    } elseif ($reversalRate > 0.3) {
        $confidenceScore -= 5;
    } else {
        $confidenceScore += 5;
    }
    
    $confidenceScore = max(0, min(100, $confidenceScore));
    
    if ($confidenceScore >= 70) {
        $confidence = 'high';
    } elseif ($confidenceScore >= 45) {
        $confidence = 'medium';
    } else {
        $confidence = 'low';
    }
    
    $maxDailyMovePct = $isInstitutional ? 12.0 : 20.0;
    
    $bestPct = min($maxDailyMovePct, max(0, $sortedChanges[floor($count * 0.85)] + ($trendFactor * 0.15)));
    if ($trendFactor > 1) {
        $bestPct = max($bestPct, 1.5);
    }
    
    $worstPct = max(-$maxDailyMovePct, min(0, $sortedChanges[floor($count * 0.15)] + ($trendFactor * 0.15)));
    if ($trendFactor < -1) {
        $worstPct = min($worstPct, -1.5);
    }
    
    $medianPct = max(-$maxDailyMovePct * 0.5, min($maxDailyMovePct * 0.5, $trendFactor));
    $bestPct = max($bestPct, $medianPct + 0.5);
    $worstPct = min($worstPct, $medianPct - 0.5);
    
    $trend = 'flat';
    if ($trendFactor > 0.5) $trend = 'up';
    elseif ($trendFactor < -0.5) $trend = 'down';
    
    $reasoningParts = [];
    if ($confidenceScore >= 70) {
        $reasoningParts[] = "High confidence (" . round($confidenceScore) . "%)";
    } elseif ($confidenceScore >= 45) {
        $reasoningParts[] = "Moderate confidence (" . round($confidenceScore) . "%)";
    } else {
        $reasoningParts[] = "Low confidence (" . round($confidenceScore) . "%)";
    }
    
    if ($count >= 40) {
        $reasoningParts[] = round($count) . " days of data (excellent)";
    } elseif ($count >= 30) {
        $reasoningParts[] = round($count) . " days of data (very good)";
    } elseif ($count >= 20) {
        $reasoningParts[] = round($count) . " days of data (good)";
    } elseif ($count >= 10) {
        $reasoningParts[] = round($count) . " days of data (decent)";
    } else {
        $reasoningParts[] = "only " . round($count) . " data points";
    }
    
    if ($isInstitutional) {
        $reasoningParts[] = "institutional stock with stable pattern";
    }
    
    if ($stdDev < 2) {
        $reasoningParts[] = "very consistent moves (std dev " . number_format($stdDev, 1) . "%)";
    } elseif ($stdDev < 4) {
        $reasoningParts[] = "fairly consistent (std dev " . number_format($stdDev, 1) . "%)";
    } elseif ($stdDev < 8) {
        $reasoningParts[] = "moderate volatility (std dev " . number_format($stdDev, 1) . "%)";
    } else {
        $reasoningParts[] = "highly volatile (std dev " . number_format($stdDev, 1) . "%)";
    }
    
    if ($trendStrength > 5) {
        $reasoningParts[] = "strong " . $trend . "ward trend (" . number_format($trendFactor, 1) . "% avg)";
    } elseif ($trendStrength > 2) {
        $reasoningParts[] = "moderate " . $trend . "ward trend";
    } else {
        $reasoningParts[] = "no clear trend direction";
    }
    
    $lastChange = end($changes);
    if (abs($lastChange) > 5) {
        $reasoningParts[] = "recent momentum: " . ($lastChange > 0 ? '+' : '') . number_format($lastChange, 1) . "%";
    }
    
    if ($reversalRate > 0.5) {
        $reasoningParts[] = "many reversals (" . round($reversalRate * 100) . "% of days change direction)";
    } elseif ($reversalRate < 0.2) {
        $reasoningParts[] = "consistent direction (few reversals)";
    }
    
    $reasoning = implode(" • ", $reasoningParts) . ".";
    
    return [
        'best' => $currentPrice * (1 + $bestPct / 100),
        'worst' => $currentPrice * (1 + $worstPct / 100),
        'median' => $currentPrice * (1 + $medianPct / 100),
        'bestPct' => $bestPct,
        'worstPct' => $worstPct,
        'medianPct' => $medianPct,
        'confidence' => $confidence,
        'confidenceScore' => $confidenceScore,
        'trend' => $trend,
        'trendStrength' => $trendStrength,
        'stdDev' => $stdDev,
        'reasoning' => $reasoning,
        'avgDailyMove' => $avgMove ?? 0,
        'dataPoints' => $count,
        'reversalRate' => $reversalRate,
        'isInstitutional' => $isInstitutional
    ];
}

function generateActionableAdvice(array $row, array $predictions, array $classification): string {
    $gainPct = $row['gainPct'] ?? 0;
    $gainUsd = $row['gainUsd'] ?? 0;
    $currentPrice = $row['currentPriceUsd'] ?? 0;
    $entry = $row['entry'] ?? 0;
    $ticker = $row['ticker'] ?? 'Unknown';
    $volatility = $predictions['stdDev'] ?? 0;
    $trend = $predictions['trend'] ?? 'flat';
    $confScore = $predictions['confidenceScore'] ?? 0;
    $dataPoints = $predictions['dataPoints'] ?? 0;
    $avgMove = $predictions['avgDailyMove'] ?? 0;
    
    $advice = [];
    
    // Start with classification emoji and type
    $advice[] = $classification['emoji'] . " " . $classification['label'] . ": " . $classification['quip'];
    
    // Current situation
    if ($gainPct > 0) {
        $advice[] = "📈 " . number_format($gainPct, 0) . "% gain (+\$" . number_format($gainUsd, 2) . "/share) from \$" . number_format($entry, 2);
    } else {
        $advice[] = "📉 " . number_format(abs($gainPct), 0) . "% loss (-\$" . number_format(abs($gainUsd), 2) . "/share) from \$" . number_format($entry, 2);
    }
    
    // Volatility assessment
    if ($avgMove > 10) {
        $advice[] = "⚡ Very volatile — expect large swings";
    } elseif ($avgMove > 5) {
        $advice[] = "📊 Moderately volatile";
    } else {
        $advice[] = "🐢 Low volatility — stable";
    }
    
    // Trend assessment
    if ($trend == 'up' && $gainPct > 0) {
        $advice[] = "📈 Strong momentum — trend is your friend";
    } elseif ($trend == 'up' && $gainPct < 0) {
        $advice[] = "🔄 Recovering — upward trend forming";
    } elseif ($trend == 'down' && $gainPct > 0) {
        $advice[] = "⚠️ Potential reversal — uptrend losing steam";
    } elseif ($trend == 'down' && $gainPct < 0) {
        $advice[] = "📉 Downward trend — protect capital";
    } else {
        $advice[] = "➡️ No clear trend — range-bound";
    }
    
    // Prediction
    $bestPct = $predictions['bestPct'] ?? 0;
    $worstPct = $predictions['worstPct'] ?? 0;
    $advice[] = "🔮 Tomorrow: -" . number_format(abs($worstPct), 0) . "% to +" . number_format($bestPct, 0) . "% (median: " . number_format($predictions['medianPct'] ?? 0, 1) . "%)";
    
    // Specific action based on classification
    $type = $classification['type'] ?? 'mixed';
    
    switch ($type) {
        case 'penny_volatile':
        case 'rocket':
            $advice[] = "🎯 ACTION: Take partial profits now, set tight SAFE stop at \$" . number_format($row['safeStopUsd'] ?? 0, 2);
            break;
        case 'keeper':
        case 'dividend':
            $advice[] = "🎯 ACTION: Hold long-term — use wide SWING stop at \$" . number_format($row['swingStopUsd'] ?? 0, 2) . " to avoid getting shaken out";
            break;
        case 'growth':
        case 'momentum':
            $advice[] = "🎯 ACTION: Let it ride with SWING stop at \$" . number_format($row['swingStopUsd'] ?? 0, 2) . " — or SAFE at \$" . number_format($row['safeStopUsd'] ?? 0, 2) . " to protect gains";
            break;
        case 'bounce':
        case 'value':
            $advice[] = "🎯 ACTION: Consider averaging down — use SWING stop at \$" . number_format($row['swingStopUsd'] ?? 0, 2) . " for protection";
            break;
        case 'risky':
            $advice[] = "🎯 ACTION: High risk — cut losses or use tight SAFE stop at \$" . number_format($row['safeStopUsd'] ?? 0, 2);
            break;
        default:
            $advice[] = "🎯 ACTION: Use SAFE stop at \$" . number_format($row['safeStopUsd'] ?? 0, 2) . " or SWING at \$" . number_format($row['swingStopUsd'] ?? 0, 2);
    }
    
    // Confidence context
    if ($confScore >= 70) {
        $advice[] = "✅ " . round($confScore) . "% confidence — " . $dataPoints . " days of consistent data";
    } elseif ($confScore >= 45) {
        $advice[] = "📈 " . round($confScore) . "% confidence — " . $dataPoints . " days shows patterns";
    } else {
        $advice[] = "⚠️ " . round($confScore) . "% confidence — highly unpredictable";
    }
    
    return implode(" | ", $advice);
}

function buildAdviceWithTwoStops(float $shares, float $entry, float $currentUsd, float $fxRate,
                                  ?float $dayChangePct, ?array $stats, float $minTrimProfitUsd): array {
    $gainPct = $entry > 0 ? (($currentUsd - $entry) / $entry) * 100 : 0.0;
    $gainUsd = $currentUsd - $entry;

    if ($stats === null) {
        $swingStop = $currentUsd * 0.85;
        $safeStop = $entry + ($gainUsd * 0.5);
        if ($gainPct <= 0) {
            $safeStop = $currentUsd * 0.90;
        }
        $note = 'Not enough recent trading history — using generic levels.';
        return [
            'action' => "Hold all " . rtrim(rtrim(number_format($shares, 2), '0'), '.') . " shares",
            'swingStopUsd' => $swingStop,
            'safeStopUsd' => $safeStop,
            'swingStopPct' => 15.0,
            'safeStopPct' => (($currentUsd - $safeStop) / $currentUsd) * 100,
            'sharesTrim' => 0,
            'sharesHold' => $shares,
            'note' => $note,
        ];
    }

    $lowUsd = $stats['low'] * $fxRate;
    $avgRangePct = $stats['avgRangePct'];
    $days = $stats['days'];

    if ($avgRangePct < 2) {
        $volLabel = 'low';
        $swingBufferPct = 8;
    } elseif ($avgRangePct <= 5) {
        $volLabel = 'moderate';
        $swingBufferPct = 12;
    } else {
        $volLabel = 'high';
        $swingBufferPct = 18;
    }

    $swingStopUsd = $currentUsd * (1 - $swingBufferPct / 100);
    $swingStopUsd = max($swingStopUsd, $lowUsd * 0.95);
    $swingStopPct = (($currentUsd - $swingStopUsd) / $currentUsd) * 100;

    if ($gainPct >= 100) {
        $profitProtectPct = 0.70;
    } elseif ($gainPct >= 50) {
        $profitProtectPct = 0.60;
    } elseif ($gainPct >= 20) {
        $profitProtectPct = 0.50;
    } elseif ($gainPct >= 10) {
        $profitProtectPct = 0.40;
    } elseif ($gainPct > 0) {
        $profitProtectPct = 0.30;
    } else {
        $safeStopUsd = $swingStopUsd;
        $safeStopPct = $swingStopPct;
        $profitProtectPct = 0;
    }

    if ($gainPct > 0) {
        $safeStopUsd = $entry + ($gainUsd * $profitProtectPct);
        $supportBasedStop = $lowUsd * 0.98;
        if ($supportBasedStop > $safeStopUsd && $supportBasedStop > $entry) {
            $safeStopUsd = $supportBasedStop;
        }
        $safeStopUsd = max($safeStopUsd, $entry);
        $safeStopUsd = min($safeStopUsd, $currentUsd * 0.995);
        $protectedGainPct = (($safeStopUsd - $entry) / $gainUsd) * 100;
        if ($gainUsd > 0) {
            $protectedGainPct = min(100, max(0, $protectedGainPct));
        } else {
            $protectedGainPct = 0;
        }
    } else {
        $safeStopUsd = $swingStopUsd;
        $protectedGainPct = 0;
    }
    
    $safeStopPct = (($currentUsd - $safeStopUsd) / $currentUsd) * 100;

    if ($gainPct >= 100) {
        $protectFrac = 0.5;
    } elseif ($gainPct >= 50) {
        $protectFrac = 0.35;
    } elseif ($gainPct >= 20) {
        $protectFrac = 0.2;
    } else {
        $protectFrac = 0.0;
    }

    $sharesTrim = $protectFrac > 0 ? (float) round($shares * $protectFrac) : 0.0;
    $sharesHold = $shares - $sharesTrim;
    $fmt = fn($n) => rtrim(rtrim(number_format($n, 2), '0'), '.');

    $tooSmallToTrim = false;
    if ($sharesTrim > 0) {
        $profitCheck = $sharesTrim * ($currentUsd - $entry);
        if ($profitCheck < $minTrimProfitUsd) {
            $tooSmallToTrim = true;
            $sharesTrim = 0.0;
            $sharesHold = $shares;
        }
    }

    $gainDesc = "";
    if ($gainPct > 0 && $gainUsd > 0) {
        $gainDesc = " 🔒 " . number_format($protectedGainPct, 0) . "% of $" . number_format($gainUsd, 2) . " gain protected";
    }
    
    if ($sharesTrim > 0) {
        $proceedsUsd = $sharesTrim * $currentUsd;
        $profitUsd = $sharesTrim * ($currentUsd - $entry);
        $action = "SELL {$fmt($sharesTrim)} of {$fmt($shares)} shares now "
                . "(~$" . number_format($proceedsUsd, 0) . " proceeds, ~$" . number_format($profitUsd, 0) . " profit); "
                . "hold remaining {$fmt($sharesHold)} with SAFE stop at $" . number_format($safeStopUsd, 2) 
                . " (" . number_format($safeStopPct, 1) . "% below current, " . number_format($protectedGainPct, 0) . "% of gain protected)"
                . " or SWING stop at $" . number_format($swingStopUsd, 2)
                . " (" . number_format($swingStopPct, 1) . "% below)";
    } else {
        $action = "Hold all {$fmt($shares)} shares. SAFE stop: $" . number_format($safeStopUsd, 2)
                . " (" . number_format($safeStopPct, 1) . "% below current)" . $gainDesc
                . " — SWING stop: $" . number_format($swingStopUsd, 2)
                . " (" . number_format($swingStopPct, 1) . "% below) to let it breathe through volatility";
    }

    $note = "Last {$days}d: support $" . number_format($lowUsd, 2) 
          . ", avg range " . number_format($avgRangePct, 1) . "%/day ({$volLabel} volatility).";

    if ($gainPct > 0) {
        $note .= " SAFE stop locks in " . number_format($protectedGainPct, 0) . "% of your $" . number_format($gainUsd, 2) . " profit.";
    }
    
    $note .= " SWING stop is wider to avoid normal pullbacks.";

    if ($tooSmallToTrim) {
        $note = "Position too small to justify partial exit — trimming would realize < $" 
              . number_format($minTrimProfitUsd, 0) . " in profit. " . $note;
    }

    if ($dayChangePct !== null && $dayChangePct <= -8) {
        $note = '⚠️ SHARP DROP TODAY (' . number_format($dayChangePct, 1) . '%). ' . $note;
    }

    if ($gainPct > 50) {
        $note .= " With " . number_format($gainPct, 0) . "% gain, SAFE stop is recommended to lock in profits.";
    } elseif ($gainPct > 20) {
        $note .= " With " . number_format($gainPct, 0) . "% gain, consider SAFE stop to protect your profits.";
    } elseif ($gainPct < 0) {
        $note .= " Currently down " . number_format(abs($gainPct), 0) . "% — SWING stop may be safer to avoid whipsaw.";
    }

    return [
        'action' => $action,
        'swingStopUsd' => $swingStopUsd,
        'safeStopUsd' => $safeStopUsd,
        'swingStopPct' => $swingStopPct,
        'safeStopPct' => $safeStopPct,
        'protectedGainPct' => $protectedGainPct,
        'sharesTrim' => $sharesTrim,
        'sharesHold' => $sharesHold,
        'note' => $note,
    ];
}

// ---------- Load positions ----------
$positions = [];
$loadError = null;

if (!file_exists($jsonFile)) {
    $loadError = "stocks.json not found at {$jsonFile}";
} else {
    $raw = file_get_contents($jsonFile);
    $decoded = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $loadError = 'stocks.json is not valid JSON: ' . json_last_error_msg();
    } else {
        $items = $decoded['positions'] ?? $decoded;

        foreach ($items as $item) {
            if (!isset($item['nrbght'], $item['stock'], $item['price']) || $item['nrbght'] <= 0) {
                continue;
            }
            $positions[] = [
                'ticker' => $item['stock'],
                'shares' => (float) $item['nrbght'],
                'entry'  => (float) $item['price'],
                'depot'  => $item['depot'] ?? '',
                'isin'   => $item['isin'] ?? '',
                'date'   => $item['date'] ?? '',
            ];
        }
    }
}

// ---------- Fetch quotes + build rows ----------
$rows = [];
$totalCost = 0.0;
$totalValue = 0.0;
$fxCache = [];

foreach ($positions as $pos) {
    $quote = fetchYahooQuote($pos['ticker'], $userAgent, true, $historyDays);

    $cost = $pos['shares'] * $pos['entry'];
    $totalCost += $cost;

    if ($quote === null) {
        $rows[] = ['currentPriceUsd' => null, 'nativeCurrency' => 'N/A',
                   'fxRate' => null, 'dayChangePct' => null, 'value' => null,
                   'gainUsd' => null, 'gainPct' => null, 'action' => '—',
                   'swingStopUsd' => null, 'safeStopUsd' => null,
                   'swingStopPct' => null, 'safeStopPct' => null,
                   'protectedGainPct' => null,
                   'predictions' => null,
                   'classification' => null,
                   'advice' => 'Price fetch failed',
                   'flag' => 'Price fetch failed'] + $pos;
        continue;
    }

    $nativePrice = $quote['price'];
    $nativeCurrency = $quote['currency'];

    $fxRate = getFxRateToUsd($nativeCurrency, $userAgent, $fxCache);
    if ($fxRate === null) {
        $rows[] = ['currentPriceUsd' => null, 'nativeCurrency' => $nativeCurrency,
                   'fxRate' => null, 'dayChangePct' => null, 'value' => null,
                   'gainUsd' => null, 'gainPct' => null, 'action' => '—',
                   'swingStopUsd' => null, 'safeStopUsd' => null,
                   'swingStopPct' => null, 'safeStopPct' => null,
                   'protectedGainPct' => null,
                   'predictions' => null,
                   'classification' => null,
                   'advice' => 'FX rate unavailable',
                   'flag' => "FX rate {$nativeCurrency}->USD unavailable"] + $pos;
        continue;
    }

    $currentUsd = round($nativePrice * $fxRate, 4);
    $value = $pos['shares'] * $currentUsd;
    $totalValue += $value;

    $gainUsd = $value - $cost;
    $gainPct = $cost > 0 ? ($gainUsd / $cost) * 100 : 0;

    $dayChangePct = null;
    if ($quote['prevClose']) {
        $dayChangePct = (($nativePrice - $quote['prevClose']) / $quote['prevClose']) * 100;
    }

    $stats = computeRecentStats($quote['history']);
    $advice = buildAdviceWithTwoStops($pos['shares'], $pos['entry'], $currentUsd, $fxRate, $dayChangePct, $stats, $minTrimProfitUsd);
    $predictions = generatePredictions($currentUsd, $quote['history'], $fxRate);
    
    // Classify the stock
    $avgMove = $predictions['avgDailyMove'] ?? 5;
    $stdDev = $predictions['stdDev'] ?? 5;
    $avgRangePct = $stats['avgRangePct'] ?? 5;
    $classification = classifyStock($currentUsd, $avgMove, $stdDev, $gainPct, $avgRangePct);
    
    // Generate actionable advice
    $rowData = [
        'ticker' => $pos['ticker'],
        'entry' => $pos['entry'],
        'currentPriceUsd' => $currentUsd,
        'gainPct' => $gainPct,
        'gainUsd' => $gainUsd,
        'safeStopUsd' => $advice['safeStopUsd'],
        'safeStopPct' => $advice['safeStopPct'],
        'swingStopUsd' => $advice['swingStopUsd'],
        'protectedGainPct' => $advice['protectedGainPct'] ?? 0
    ];
    $actionableAdvice = generateActionableAdvice($rowData, $predictions, $classification);

    $rows[] = [
        'currentPriceUsd' => $currentUsd,
        'nativeCurrency'  => $nativeCurrency,
        'fxRate'          => $fxRate,
        'dayChangePct'    => $dayChangePct,
        'value'           => $value,
        'gainUsd'         => $gainUsd,
        'gainPct'         => $gainPct,
        'action'          => $advice['action'],
        'swingStopUsd'    => $advice['swingStopUsd'],
        'safeStopUsd'     => $advice['safeStopUsd'],
        'swingStopPct'    => $advice['swingStopPct'],
        'safeStopPct'     => $advice['safeStopPct'],
        'protectedGainPct'=> $advice['protectedGainPct'] ?? 0,
        'predictions'     => $predictions,
        'classification'  => $classification,
        'advice'          => $actionableAdvice,
        'flag'            => $advice['note'],
    ] + $pos;
}

usort($rows, function($a, $b) {
    if ($a['gainPct'] === null && $b['gainPct'] === null) return 0;
    if ($a['gainPct'] === null) return 1;
    if ($b['gainPct'] === null) return -1;
    return $b['gainPct'] <=> $a['gainPct'];
});

$totalGainUsd = $totalValue - $totalCost;
$totalGainPct = $totalCost > 0 ? ($totalGainUsd / $totalCost) * 100 : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Portfolio Tracker</title>
<style>
  body { font-family: monospace; background: #0e0e0e; color: #ddd; padding: 20px; }
  table { border-collapse: collapse; width: 100%; margin-top: 10px; font-size: 10px; }
  th, td { border: 1px solid #333; padding: 3px 5px; text-align: right; }
  th { background: #1b1b1b; text-align: center; position: sticky; top: 0; }
  td.ticker { text-align: left; font-weight: bold; }
  td.flag, td.action, td.advice { text-align: left; white-space: normal; max-width: 200px; font-size: 9px; }
  td.advice { max-width: 350px; font-size: 9px; color: #aaa; line-height: 1.3; }
  td.classification { text-align: center; font-weight: bold; font-size: 10px; }
  .pos { color: #4caf50; }
  .neg { color: #f44336; }
  .summary { margin-top: 20px; font-size: 15px; }
  .error { color: #f44336; }
  .stop-row { font-size: 8px; color: #888; }
  .swing { color: #ffa726; }
  .safe { color: #66bb6a; }
  .rank { text-align: center; font-weight: bold; color: #888; }
  .gain-protect { color: #ffd54f; font-size: 8px; }
  
  .sparkline-container { 
    display: flex; 
    flex-direction: column; 
    align-items: center;
    gap: 2px;
    padding: 2px 0;
  }
  .sparkline-row {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 7px;
    width: 100%;
  }
  .sparkline-label {
    min-width: 10px;
    font-weight: bold;
    text-align: right;
    font-size: 6px;
  }
  .sparkline-bar {
    height: 5px;
    border-radius: 1px;
    transition: width 0.3s;
    min-width: 2px;
  }
  .sparkline-best { background: #4caf50; }
  .sparkline-median { background: #ffa726; }
  .sparkline-worst { background: #f44336; }
  .sparkline-price {
    font-size: 7px;
    min-width: 28px;
    text-align: right;
    color: #aaa;
  }
  .confidence-indicator {
    font-size: 6px;
    color: #888;
    margin-top: 2px;
    text-align: center;
    max-width: 100px;
  }
  .confidence-high { color: #4caf50; }
  .confidence-medium { color: #ffa726; }
  .confidence-low { color: #f44336; }
  
  .prediction-cell {
    min-width: 100px;
    padding: 2px 4px;
  }
  
  .prediction-reasoning {
    font-size: 7px;
    color: #666;
    max-width: 120px;
    text-align: center;
    margin-top: 3px;
    padding-top: 3px;
    border-top: 1px dashed #333;
    line-height: 1.2;
  }
  
  .confidence-bar {
    width: 100%;
    height: 2px;
    background: #333;
    border-radius: 2px;
    margin-top: 2px;
    overflow: hidden;
  }
  .confidence-fill {
    height: 100%;
    border-radius: 2px;
    transition: width 0.5s;
  }
  .conf-high { background: #4caf50; }
  .conf-medium { background: #ffa726; }
  .conf-low { background: #f44336; }
  
  .data-points {
    font-size: 6px;
    color: #555;
    text-align: center;
  }
  
  .classification-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 8px;
    font-weight: bold;
    color: #fff;
  }
  
  .classification-quip {
    font-size: 7px;
    color: #888;
    display: block;
    margin-top: 2px;
  }
  
  @media (max-width: 1200px) {
    .sparkline-price { min-width: 18px; font-size: 6px; }
    td.flag, td.action { max-width: 120px; }
    td.advice { max-width: 180px; font-size: 8px; }
    .prediction-reasoning { max-width: 80px; font-size: 6px; }
  }
</style>
</head>
<body>
<h2>📈 Portfolio Tracker — Actionable Advice</h2>

<?php if ($loadError): ?>
  <p class="error"><?= htmlspecialchars($loadError) ?></p>
<?php else: ?>

<table>
<thead>
<tr>
  <th>#</th>
  <th>Ticker</th>
  <th>Depot</th>
  <th>Shares</th>
  <th>Entry (USD)</th>
  <th>Current (USD)</th>
  <th>Gain %</th>
  <th>Classification</th>
  <th>Next Day Predictions</th>
  <th>Best Course of Action</th>
  <th>Stops</th>
</tr>
</thead>
<tbody>
<?php 
$rank = 1;
foreach ($rows as $r): 
  $gainPct = $r['gainPct'] ?? null;
  $isPositive = $gainPct !== null && $gainPct >= 0;
  $protectedPct = $r['protectedGainPct'] ?? 0;
  $pred = $r['predictions'] ?? null;
  $confScore = $pred['confidenceScore'] ?? 0;
  $dataPoints = $pred['dataPoints'] ?? 0;
  $advice = $r['advice'] ?? 'No advice available';
  $class = $r['classification'] ?? null;
?>
  <tr>
    <td class="rank"><?= $rank++ ?></td>
    <td class="ticker"><?= htmlspecialchars($r['ticker']) ?></td>
    <td><?= htmlspecialchars($r['depot']) ?></td>
    <td><?= number_format($r['shares'], 2) ?></td>
    <td><?= number_format($r['entry'], 2) ?></td>
    <td><?= $r['currentPriceUsd'] !== null ? number_format($r['currentPriceUsd'], 2) : '—' ?></td>
    <td class="<?= $isPositive ? 'pos' : 'neg' ?>">
      <?= $gainPct !== null ? number_format($gainPct, 2) . '%' : '—' ?>
      <?php if ($protectedPct > 0 && $gainPct > 0): ?>
        <div class="gain-protect">🔒 <?= number_format($protectedPct, 0) ?>%</div>
      <?php endif; ?>
    </td>
    <td class="classification">
      <?php if ($class): ?>
        <span class="classification-badge" style="background: <?= $class['color'] ?>;">
          <?= $class['emoji'] ?> <?= $class['label'] ?>
        </span>
        <span class="classification-quip"><?= $class['quip'] ?></span>
      <?php else: ?>
        <span style="color:#666;">—</span>
      <?php endif; ?>
    </td>
    <td class="prediction-cell">
      <?php if ($pred !== null && $r['currentPriceUsd'] !== null): ?>
        <div class="sparkline-container">
          <div class="sparkline-row">
            <span class="sparkline-label" style="color:#4caf50;">▲</span>
            <div class="sparkline-bar sparkline-best" style="width: <?= min(100, max(0, $pred['bestPct'] * 2 + 20)) ?>%;"></div>
            <span class="sparkline-price">$<?= number_format($pred['best'], 2) ?></span>
          </div>
          <div class="sparkline-row">
            <span class="sparkline-label" style="color:#ffa726;">■</span>
            <div class="sparkline-bar sparkline-median" style="width: <?= min(100, max(10, $pred['medianPct'] * 2 + 20)) ?>%;"></div>
            <span class="sparkline-price">$<?= number_format($pred['median'], 2) ?></span>
          </div>
          <div class="sparkline-row">
            <span class="sparkline-label" style="color:#f44336;">▼</span>
            <div class="sparkline-bar sparkline-worst" style="width: <?= min(100, max(0, abs($pred['worstPct']) * 2 + 20)) ?>%;"></div>
            <span class="sparkline-price">$<?= number_format($pred['worst'], 2) ?></span>
          </div>
          <div class="confidence-indicator">
            <?php 
              $confText = ['high' => '📊 High', 'medium' => '📈 Mod', 'low' => '📉 Low'];
              $confClass = 'confidence-' . ($pred['confidence'] ?? 'low');
            ?>
            <span class="<?= $confClass ?>">
              <?= $confText[$pred['confidence'] ?? 'low'] ?? 'Low' ?> (<?= round($confScore) ?>%)
            </span>
            <?php if ($pred['trend'] ?? 'flat'): ?>
              • <?= $pred['trend'] === 'up' ? '📈' : ($pred['trend'] === 'down' ? '📉' : '➡️') ?>
              <?= $pred['trend'] ?>
            <?php endif; ?>
          </div>
          <div class="confidence-bar">
            <div class="confidence-fill <?= $confScore >= 70 ? 'conf-high' : ($confScore >= 45 ? 'conf-medium' : 'conf-low') ?>" 
                 style="width: <?= $confScore ?>%;"></div>
          </div>
          <div class="data-points"><?= $dataPoints ?> days</div>
        </div>
      <?php else: ?>
        <span style="color:#666;">No data</span>
      <?php endif; ?>
    </td>
    <td class="advice"><?= htmlspecialchars($advice) ?></td>
    <td class="flag">
      <?php if ($r['swingStopUsd'] !== null && $r['safeStopUsd'] !== null): ?>
      <div class="stop-row">
        <span class="safe">🛡️ SAFE: $<?= number_format($r['safeStopUsd'], 2) ?> (<?= number_format($r['safeStopPct'], 1) ?>%)</span>
        <?php if ($protectedPct > 0): ?>
        <span class="gain-protect">— <?= number_format($protectedPct, 0) ?>%</span>
        <?php endif; ?>
        <br>
        <span class="swing">🔄 SWING: $<?= number_format($r['swingStopUsd'], 2) ?> (<?= number_format($r['swingStopPct'], 1) ?>%)</span>
      </div>
      <?php else: ?>
        <span style="color:#666;">—</span>
      <?php endif; ?>
    </td>
  </tr>
<?php endforeach; ?>
</tbody>
</table>

<div class="summary">
  <strong>Total cost:</strong> $<?= number_format($totalCost, 2) ?> &nbsp;|&nbsp;
  <strong>Total value:</strong> $<?= number_format($totalValue, 2) ?> &nbsp;|&nbsp;
  <strong>Total gain:</strong> <span class="<?= $totalGainUsd >= 0 ? 'pos' : 'neg' ?>">
    $<?= number_format($totalGainUsd, 2) ?> (<?= number_format($totalGainPct, 2) ?>%)
  </span>
  <br><br>
 
</div>

<?php endif; ?>

</body>
</html>
