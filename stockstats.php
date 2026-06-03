<?php
/**
 * Simplified stock parser
 * Appends results to JSON instead of overwriting
 */

$output_file = 'stockstats.json';

// Possible locations of quickcheck.php
$possible_paths = [
    __DIR__ . '/quickcheck.php',
    __DIR__ . '/other/extra/fetchdata/2026-05-13-Finance/2026-05-13-Stocks/quickcheck.php',
    __DIR__ . '/../quickcheck.php',
    $_SERVER['DOCUMENT_ROOT'] . '/other/extra/fetchdata/2026-05-13-Finance/2026-05-13-Stocks/quickcheck.php',
];

$source_file = null;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $source_file = $path;
        break;
    }
}
if (!$source_file) die("ERROR: Cannot find quickcheck.php.\n");

// Execute PHP file to get HTML output
ob_start();
include $source_file;
$html = ob_get_clean();

// Extract stocks in order
$pattern = '/<tr>.*?<strong>([^<]+)<\/strong>.*?class="number\s+(positive|negative)">\s*<strong>=\s*([+-]?)\$([\d,\.]+)<\/strong>/si';

$stocks = [];
if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $match) {
        $symbol = trim($match[1]);
        $type   = $match[2];
        $value  = (float) str_replace(',', '', $match[4]);
        if ($type === 'negative') $value *= -1;
        $stocks[$symbol] = $value; // keep order by first appearance
    }
}

// Calculate totals
$total_gain = 0;
$total_loss = 0;
foreach ($stocks as $v) {
    if ($v >= 0) $total_gain += $v;
    else $total_loss += $v;
}
$net_result = $total_gain + $total_loss;

// Prepare final output
$entry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'stocks' => $stocks,
    'summary' => [
        'total_gain' => $total_gain,
        'total_loss' => $total_loss,
        'net_result' => $net_result
    ]
];

// Load existing JSON if it exists
$all_data = [];
if (file_exists($output_file)) {
    $existing = file_get_contents($output_file);
    $all_data = json_decode($existing, true);
    if (!is_array($all_data)) $all_data = [];
}

// Append new entry
$all_data[] = $entry;

// Save compact JSON
file_put_contents($output_file, json_encode($all_data, JSON_UNESCAPED_UNICODE));

echo "✓ Data appended to: $output_file\n";
?>