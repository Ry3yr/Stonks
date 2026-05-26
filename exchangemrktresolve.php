<?php
$inputUrl = $_GET['url'] ?? '';

if (empty($inputUrl)) {
    die('ERROR: No URL provided. Use: ?url=https://www.google.com/finance/beta/quote/STLA:NYSE');
}

preg_match('/quote\/([^:]+):([A-Z]+)/', $inputUrl, $matches);
$ticker = $matches[1] ?? $_GET['ticker'] ?? 'STLA';

$exchanges = ['NYSE', 'NASDAQ', 'LON', 'EPA', 'BIT', 'ETR', 'TSX', 'ASX', 'SIX', 'BME', 'AMS', 'HLSE', 'CPH', 'STO', 'ATX', 'OTC', 'OTCMKTS', 'FRA', 'NYSI'];

$results = [];
$maxSize = 0;
$bestUrl = '';

foreach ($exchanges as $ex) {
    $url = "https://www.google.com/finance/beta/quote/{$ticker}:{$ex}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $html = curl_exec($ch);
    $size = strlen($html);
    curl_close($ch);
    
    $results[] = ['exchange' => $ex, 'url' => $url, 'size' => $size];
    
    if ($size > $maxSize) {
        $maxSize = $size;
        $bestUrl = $url;
    }
}

usort($results, function($a, $b) { return $b['size'] - $a['size']; });
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Stock Exchange Finder - <?php echo htmlspecialchars($ticker); ?></title>
</head>
<body>
    <h1>🔍 Checking: <?php echo htmlspecialchars($ticker); ?></h1>
    
    <?php foreach ($results as $r): ?>
    <div style="padding: 5px; margin: 2px 0; background: <?php echo $r['url'] === $bestUrl ? '#0a3a0a' : '#1a1a2e'; ?>;">
        <?php echo str_pad($r['exchange'], 10); ?>: 
        <?php echo number_format($r['size']); ?> bytes → 
        <?php echo $r['url'] === $bestUrl ? '🏆 BIGGEST WINNER' : ''; ?>
    </div>
    <?php endforeach; ?>
    
    <div style="margin-top: 20px; padding: 15px; background: #0a3a0a; text-align: center;">
        ✅ <strong>BIGGEST PAGE: <?php echo number_format($maxSize); ?> bytes</strong><br>
        Redirecting to <a href="<?php echo htmlspecialchars($bestUrl); ?>"><?php echo htmlspecialchars($bestUrl); ?></a><br>
        <span id="countdown">3</span> seconds...
    </div>
    
    <script>
        let sec = 3;
        setInterval(() => {
            sec--;
            document.getElementById('countdown').innerText = sec;
            if (sec <= 0) location.href = "<?php echo htmlspecialchars($bestUrl); ?>";
        }, 1000);
    </script>
</body>
</html>