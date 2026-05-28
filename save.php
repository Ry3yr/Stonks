<?php


// Handle exchange commit to JSON
if (isset($_GET['update']) && $_GET['update'] == 'yes' && isset($_GET['handle']) && isset($_GET['stockexchg'])) {
    $handle = $_GET['handle'];
    $stockexchg = $_GET['stockexchg'];
    
    $jsonFile = 'stocks.json';
    $updated = false;
    
    if (file_exists($jsonFile)) {
        $stocks = json_decode(file_get_contents($jsonFile), true);
        
        foreach ($stocks as $i => $stock) {
            if ($stock['stock'] === $handle) {
                $stocks[$i]['exchange_market'] = $stockexchg;
                $updated = true;
                break;
            }
        }
        
        if ($updated) {
            file_put_contents($jsonFile, json_encode($stocks, JSON_PRETTY_PRINT));
            // Redirect back to stockvalues.php
            header('Location: stockvalues.php');
            exit;
        }
    }
    header('Location: stockvalues.php');
    exit;
}
// save.php - receives stock data via GET parameters and saves to stocks.json
// Add this at the beginning of save.php

if (isset($_GET['del']) && $_GET['del'] == 'yes' && isset($_GET['handle'])) {
    $handle = $_GET['handle'];
    $jsonFile = 'stocks.json';
    
    if (file_exists($jsonFile)) {
        $stocks = json_decode(file_get_contents($jsonFile), true);
        $newStocks = array_filter($stocks, function($stock) use ($handle) {
            return $stock['stock'] !== $handle;
        });
        $newStocks = array_values($newStocks);
        file_put_contents($jsonFile, json_encode($newStocks, JSON_PRETTY_PRINT));
    }
    
    header('Location: stockvalues.php');
    exit;
}

// Get parameters from URL
$stock           = isset($_GET['stock'])           ? trim($_GET['stock'])           : null;
$price           = isset($_GET['price'])           ? trim($_GET['price'])           : null;
$currency        = isset($_GET['currency'])        ? trim($_GET['currency'])        : null;
$date            = isset($_GET['date'])            ? trim($_GET['date'])            : date('Y-m-d');
$exchange_market = isset($_GET['exchange_market']) ? trim($_GET['exchange_market']) : 'N/A';

// Validate required fields
if (!$stock || !$price) {
    die('Error: Missing required parameters. Use: save.php?stock=SYMBOL&price=PRICE&currency=CURRENCY&date=DATE');
}

// Prepare stock data
$stockData = [
    'stock'           => $stock,
    'price'           => floatval($price),
    'currency'        => $currency,
    'date'            => $date,
    'timestamp'       => date('Y-m-d H:i:s'),
    'saved_at'        => time(),
    'exchange_market' => $exchange_market
];

// Load existing stocks.json
$jsonFile = 'stocks.json';
$stocks = [];

if (file_exists($jsonFile)) {
    $content = file_get_contents($jsonFile);
    $stocks = json_decode($content, true);
    if (!is_array($stocks)) $stocks = [];
}

// Add new stock (keep history, don't overwrite)
$stocks[] = $stockData;

// Save to file
if (file_put_contents($jsonFile, json_encode($stocks, JSON_PRETTY_PRINT))) {
    // Display success page
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head><meta charset="UTF-8"><title>Stock Saved</title>';
    echo '<style>
            body { font-family: Arial; background: #f0f2f5; padding: 20px; text-align: center; }
            .success { background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; max-width: 500px; margin: 50px auto; }
            .details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; text-align: left; }
            button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-top: 10px; }
          </style>';
    echo '</head><body>';
    echo '<div class="success">';
    echo '<h2>✅ Stock Saved Successfully!</h2>';
    echo '<div class="details">';
    echo '<strong>Stock:</strong> ' . htmlspecialchars($stock) . '<br>';
    echo '<strong>Price:</strong> ' . htmlspecialchars($currency) . ' ' . htmlspecialchars($price) . '<br>';
    echo '<strong>Date:</strong> ' . htmlspecialchars($date) . '<br>';
    echo '<strong>Saved at:</strong> ' . date('Y-m-d H:i:s') . '<br>';
    echo '</div>';
    echo '<button onclick="window.close()">Close Tab</button>';
    echo ' <button onclick="window.location.href=\'current.php\'">Search More</button>';
    echo '</div></body></html>';
} else {
    echo '<div style="color: red; padding: 20px;">❌ Error: Could not write to stocks.json</div>';
}
?>
