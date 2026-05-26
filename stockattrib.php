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

// Reverse the order of appearance (last item becomes first)
$stocks = array_reverse($stocks);

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
    
    // Loop through all stocks
    foreach ($stocks as $stock) {
        $stockName = $stock['stock'];
        $cleanName = cleanStockSymbol($stockName);
        
        // Initialize array for this stock
        $stockAttrs = [];
        
        // Handle cyclic setting
        if (isset($_POST['cyclic_' . $cleanName])) {
            $cyclicValue = $_POST['cyclic_' . $cleanName];
            if ($cyclicValue !== 'none') {
                $stockAttrs['cyclic'] = $cyclicValue;
            }
        }
        
        // Handle volatile setting
        if (isset($_POST['volatile_' . $cleanName])) {
            $volatileValue = $_POST['volatile_' . $cleanName];
            if ($volatileValue !== 'none') {
                $stockAttrs['volatile'] = $volatileValue;
            }
        }
        
        // Only add stock if it has at least one attribute
        if (!empty($stockAttrs)) {
            $newAttributes[$cleanName] = $stockAttrs;
        }
    }
    
    // Save to stockattributes.json
    file_put_contents($attributesFile, json_encode($newAttributes, JSON_PRETTY_PRINT));
    $attributes = $newAttributes;
    
    echo "<p style='color: green; font-weight: bold;'>? Attributes saved successfully!</p>";
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
    <p>Configure cyclic and volatility settings for each stock</p>
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
                    <th>Exchange Market</th>
                    <th>Cyclic Setting</th>
                    <th>Volatile</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stocks as $stock): ?>
                    <?php 
                    $stockName = $stock['stock'];
                    $cleanName = cleanStockSymbol($stockName);
                    $currentCyclic = isset($attributes[$cleanName]['cyclic']) ? $attributes[$cleanName]['cyclic'] : '';
                    $currentVolatile = isset($attributes[$cleanName]['volatile']) ? $attributes[$cleanName]['volatile'] : '';
                    $hasIsin = isset($stock['isin']) && !empty($stock['isin']);
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
                        <td><?php echo htmlspecialchars($stock['exchange_market']); ?></td>
                        <td>
                            <select name="cyclic_<?php echo htmlspecialchars($cleanName); ?>">
                                <option value="none" <?php echo $currentCyclic == '' ? 'selected' : ''; ?>>-- None --</option>
                                <option value="quarterly" <?php echo $currentCyclic == 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                                <option value="halfyearly" <?php echo $currentCyclic == 'halfyearly' ? 'selected' : ''; ?>>Half-Yearly</option>
                                <option value="yearly" <?php echo $currentCyclic == 'yearly' ? 'selected' : ''; ?>>Yearly</option>
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
        <input type="submit" name="save_attributes" value="Save Attributes">
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