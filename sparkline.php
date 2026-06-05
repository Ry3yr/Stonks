<?php
// sparkline.php - Proper SVG sparkline only

$symbol = isset($_GET['symbol']) ? strtoupper(trim($_GET['symbol'])) : '';
$timespan = isset($_GET['timespan']) ? $_GET['timespan'] : '';

if (empty($symbol) || empty($timespan)) {
    header('Content-Type: text/plain');
    die("ERROR");
}

if (!in_array($timespan, ['1month', '6month', '12month'])) {
    header('Content-Type: text/plain');
    die("ERROR");
}

$range_map = [
    '1month' => ['range' => '1mo', 'interval' => '1d'],
    '6month' => ['range' => '6mo', 'interval' => '1wk'],
    '12month' => ['range' => '1y', 'interval' => '1mo']
];

$range = $range_map[$timespan]['range'];
$interval = $range_map[$timespan]['interval'];

$url = "https://query1.finance.yahoo.com/v8/finance/chart/$symbol?range=$range&interval=$interval";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (!isset($data['chart']['result'][0])) {
    header('Content-Type: text/plain');
    die("ERROR");
}

$prices = $data['chart']['result'][0]['indicators']['quote'][0]['close'];
$valid_prices = array_filter($prices, function($p) { return $p !== null && $p > 0; });
$valid_prices = array_values($valid_prices);

if (count($valid_prices) < 2) {
    header('Content-Type: text/plain');
    die("ERROR");
}

$first = $valid_prices[0];
$last = end($valid_prices);
$change = (($last - $first) / $first) * 100;
$trend = number_format(abs($change), 1);
$arrow = $change > 0 ? '📈' : ($change < 0 ? '📉' : '➡️');
$direction = $change > 0 ? 'UP' : ($change < 0 ? 'DOWN' : 'FLAT');
$color = $change > 0 ? '#22c55e' : ($change < 0 ? '#ef4444' : '#6b7280');
$sign = $change > 0 ? '+' : '';

// Build SVG sparkline
$width = 300;
$height = 60;
$padding = 2;
$graphWidth = $width - ($padding * 2);
$graphHeight = $height - ($padding * 2);

$min = min($valid_prices);
$max = max($valid_prices);
$range = $max - $min ?: 0.01;

$points = [];
foreach ($valid_prices as $i => $price) {
    $x = $padding + ($i / (count($valid_prices) - 1)) * $graphWidth;
    $y = $padding + $graphHeight - (($price - $min) / $range) * $graphHeight;
    $points[] = "$x,$y";
}

header('Content-Type: image/svg+xml');
?>
<svg width="<?= $width ?>" height="<?= $height + 30 ?>" xmlns="http://www.w3.org/2000/svg">
    <polyline points="<?= implode(' ', $points) ?>" fill="none" stroke="<?= $color ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    <text x="<?= $width / 2 ?>" y="<?= $height + 20 ?>" text-anchor="middle" font-family="system-ui, sans-serif" font-size="14" fill="<?= $color ?>">
        <?= $arrow ?> <?= $sign . $trend ?>% <?= $direction ?> (<?= $timespan ?>)
    </text>
</svg>