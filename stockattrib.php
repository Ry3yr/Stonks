<?php
// File paths
$stocksFile = 'stocks.json';
$attributesFile = 'stockattributes.json';

// Load stocks data
if (!file_exists($stocksFile)) {
    die("Error: stocks.json file not found!");
}

$stocks = json_decode(file_get_contents($stocksFile), true);
if ($stocks === null) {
    die("Error: Invalid JSON in stocks.json!");
}

// Function to clean stock symbol (remove dot and everything after it)
function cleanStockSymbol($symbol) {
    $dotPos = strpos($symbol, '.');
    if ($dotPos !== false) {
        return substr($symbol, 0, $dotPos);
    }
    return $symbol;
}

// Function to create safe field name (replace dots with underscores for form fields)
function safeFieldName($symbol) {
    return str_replace('.', '_', $symbol);
}

// Keep original order for saving, use reversed for display
$originalStocks = $stocks;
$displayStocks = array_reverse($originalStocks);

// Load existing attributes
$attributes = [];
if (file_exists($attributesFile)) {
    $attributes = json_decode(file_get_contents($attributesFile), true);
    if ($attributes === null) {
        $attributes = [];
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attributes'])) {
    $newAttributes = [];
    $updatedStocks = $originalStocks; // start with original data
    
    // Loop through all stocks (using original order for reliable mapping)
    foreach ($originalStocks as $index => $stock) {
        $stockName = $stock['stock'];
        $cleanName = cleanStockSymbol($stockName);
        $safeName = safeFieldName($stockName); // FIX: Create safe version for form field
        
        // --- Update exchange market if provided ---
        // FIX: Look for the safe field name (PNG_V instead of PNG.V)
        if (isset($_POST['exchange_' . $safeName])) {
            $newExchange = trim($_POST['exchange_' . $safeName]);
            if ($newExchange !== '') {
                $updatedStocks[$index]['exchange_market'] = $newExchange;
            }
        }
        
        // --- Cyclic & volatile attributes ---
        $stockAttrs = [];
        
        // Cyclic setting (including 'memestock')
        if (isset($_POST['cyclic_' . $cleanName])) {
            $cyclicValue = $_POST['cyclic_' . $cleanName];
            if ($cyclicValue !== 'none') {
                $stockAttrs['cyclic'] = $cyclicValue;
            }
        }
        
        // Volatile setting
        if (isset($_POST['volatile_' . $cleanName])) {
            $volatileValue = $_POST['volatile_' . $cleanName];
            if ($volatileValue !== 'none') {
                $stockAttrs['volatile'] = $volatileValue;
            }
        }
        
        if (!empty($stockAttrs)) {
            $newAttributes[$cleanName] = $stockAttrs;
        }
    }
    
    // Save updated stocks.json
    file_put_contents($stocksFile, json_encode($updatedStocks, JSON_PRETTY_PRINT));
    
    // Save stockattributes.json
    file_put_contents($attributesFile, json_encode($newAttributes, JSON_PRETTY_PRINT));
    
    // Reload data for display
    $originalStocks = $updatedStocks;
    $displayStocks = array_reverse($originalStocks);
    $attributes = $newAttributes;
    
    echo "<p style='color: green; font-weight: bold;'>✓ Attributes and exchange markets saved successfully!</p>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock Attributes Manager</title>
</head>
<body>
    <h1>Stock Attributes Manager</h1>
    <p>Configure cyclic (including <strong>memestock</strong>), volatility, and <strong>exchange market</strong> for each stock.</p>
    <p><u>Underlined stocks</u> have an ISIN field.</p>
    
    <form method="POST" action="">
        <table border="1" cellpadding="10">
            <thead>
                <tr>
                    <th>Stock Symbol</th>
                    <th>Cleaned Symbol</th>
                    <th>Price</th>
                    <th>Currency</th>
                    <th>Date</th>
                    <th>Exchange Market <br> (editable)</th>
                    <th>Cyclic Setting</th>
                    <th>Volatile</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($displayStocks as $stock): ?>
                    <?php 
                    $stockName = $stock['stock'];
                    $cleanName = cleanStockSymbol($stockName);
                    $safeName = safeFieldName($stockName); // FIX: Create safe version for form field
                    $currentCyclic = isset($attributes[$cleanName]['cyclic']) ? $attributes[$cleanName]['cyclic'] : '';
                    $currentVolatile = isset($attributes[$cleanName]['volatile']) ? $attributes[$cleanName]['volatile'] : '';
                    $hasIsin = isset($stock['isin']) && !empty($stock['isin']);
                    $currentExchange = isset($stock['exchange_market']) ? htmlspecialchars($stock['exchange_market']) : '';
                    ?>
                    <tr>
                        <td>
                            <?php if ($hasIsin): ?>
                                <u><strong><?php echo htmlspecialchars($stockName); ?></strong></u>
                            <?php else: ?>
                                <strong><?php echo htmlspecialchars($stockName); ?></strong>
                            <?php endif; ?>
                        </td>
                        <td><em><?php echo htmlspecialchars($cleanName); ?></em></td>
                        <td><?php echo htmlspecialchars($stock['price']); ?></td>
                        <td><?php echo htmlspecialchars($stock['currency']); ?></td>
                        <td><?php echo htmlspecialchars($stock['date']); ?></td>
                        <td>
                            <!-- FIX: Use safe name (PNG_V instead of PNG.V) -->
                            <input type="text" name="exchange_<?php echo htmlspecialchars($safeName); ?>" 
                                   value="<?php echo $currentExchange; ?>" size="15">
                        </td>
                        <td>
                            <select name="cyclic_<?php echo htmlspecialchars($cleanName); ?>">
                                <option value="none" <?php echo $currentCyclic == '' ? 'selected' : ''; ?>>-- None --</option>
                                <option value="quarterly" <?php echo $currentCyclic == 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                                <option value="halfyearly" <?php echo $currentCyclic == 'halfyearly' ? 'selected' : ''; ?>>Half-Yearly</option>
                                <option value="yearly" <?php echo $currentCyclic == 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                                <option value="memestock" <?php echo $currentCyclic == 'memestock' ? 'selected' : ''; ?>>Memestock</option>
                                <option value="ai" <?php echo $currentCyclic == 'ai' ? 'selected' : ''; ?>>AI</option>
                                <option value="safegrowth" <?php echo $currentCyclic == 'safegrowth' ? 'selected' : ''; ?>>SafeGrowth</option>
                            </select>
                        </td>
                        <td>
                            <select name="volatile_<?php echo htmlspecialchars($cleanName); ?>">
                                <option value="none" <?php echo $currentVolatile == '' ? 'selected' : ''; ?>>-- None --</option>
                                <option value="yes" <?php echo $currentVolatile == 'yes' ? 'selected' : ''; ?>>Yes</option>
                                <option value="neutral" <?php echo $currentVolatile == 'neutral' ? 'selected' : ''; ?>>Neutral</option>
                                <option value="no" <?php echo $currentVolatile == 'no' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <br>
        <input type="submit" name="save_attributes" value="Save Attributes & Exchange Markets">
    </form>
    
    <hr>
    
    <h2>Current Stock Attributes (stockattributes.json)</h2>
    <?php if (empty($attributes)): ?>
        <p>No attributes saved yet.</p>
    <?php else: ?>
        <table border="1" cellpadding="5">
            <thead>
                <tr>
                    <th>Stock (Cleaned)</th>
                    <th>Cyclic Setting</th>
                    <th>Volatile</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attributes as $cleanName => $attrs): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cleanName); ?></td>
                        <td><?php echo isset($attrs['cyclic']) ? htmlspecialchars($attrs['cyclic']) : '-'; ?></td>
                        <td><?php echo isset($attrs['volatile']) ? htmlspecialchars($attrs['volatile']) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h3>Raw JSON:</h3>
        <pre><?php echo json_encode($attributes, JSON_PRETTY_PRINT); ?></pre>
    <?php endif; ?>
</body>
</html>
