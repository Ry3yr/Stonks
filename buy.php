<?php
// buy.php - Add ISIN, quantity (nrbght), and depot to a specific stock in stocks.json

$jsonFile = 'stocks.json';

function loadStocks() {
    global $jsonFile;
    if (!file_exists($jsonFile)) {
        return [];
    }
    $content = file_get_contents($jsonFile);
    return json_decode($content, true) ?: [];
}

function saveStocks($stocks) {
    global $jsonFile;
    file_put_contents($jsonFile, json_encode($stocks, JSON_PRETTY_PRINT));
}

$symbol = isset($_GET['go']) ? strtoupper(trim($_GET['go'])) : '';
$message = '';
$messageType = '';

if (empty($symbol)) {
    $message = 'No stock symbol specified. Please use ?go=SYMBOL';
    $messageType = 'error';
}

$stocks = loadStocks();

$stockIndex = null;
foreach ($stocks as $index => $stock) {
    if ($stock['stock'] === $symbol) {
        $stockIndex = $index;
        break;
    }
}

if ($stockIndex === null && !empty($symbol)) {
    $message = "Stock '$symbol' not found in your portfolio.";
    $messageType = 'error';
}

$currentIsin = '';
$currentNrbght = '';
$currentDepot = 'comdirect'; // Default value

if ($stockIndex !== null) {
    $currentIsin = $stocks[$stockIndex]['isin'] ?? '';
    $currentNrbght = $stocks[$stockIndex]['nrbght'] ?? '';
    $currentDepot = $stocks[$stockIndex]['depot'] ?? 'comdirect';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stockIndex !== null) {
    $isin = isset($_POST['isin']) ? strtoupper(trim($_POST['isin'])) : '';
    $nrbght = isset($_POST['nrbght']) ? trim($_POST['nrbght']) : '';
    $depot = isset($_POST['depot']) ? trim($_POST['depot']) : 'comdirect';
    
    $errors = [];
    
    if (empty($isin)) {
        $errors[] = 'ISIN code is required.';
    } elseif (!preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', $isin)) {
        $errors[] = 'Invalid ISIN format. ISIN must be 12 characters: 2 letters, 9 alphanumeric, 1 check digit.';
    }
    
    if (empty($nrbght)) {
        $errors[] = 'Number bought is required.';
    } elseif (!is_numeric($nrbght) || $nrbght <= 0 || floor($nrbght) != $nrbght) {
        $errors[] = 'Number bought must be a positive whole number.';
    }
    
    if (!in_array($depot, ['ingdiba', 'comdirect'])) {
        $errors[] = 'Invalid depot selection.';
    }
    
    if (empty($errors)) {
        $stocks[$stockIndex]['isin'] = $isin;
        $stocks[$stockIndex]['nrbght'] = (int)$nrbght;
        $stocks[$stockIndex]['depot'] = $depot;
        saveStocks($stocks);
        $message = "Successfully added ISIN: $isin, Quantity: $nrbght, and Depot: $depot for $symbol.";
        $messageType = 'success';
        
        $currentIsin = $isin;
        $currentNrbght = $nrbght;
        $currentDepot = $depot;
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy Stock - Add ISIN, Quantity & Depot</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #1e2a3e 0%, #0f1a2a 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .card-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }
        
        .card-header .symbol-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 6px 16px;
            border-radius: 40px;
            font-family: monospace;
            font-size: 20px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1e2a3e;
            font-size: 14px;
        }
        
        label .required {
            color: #dc3545;
            margin-left: 4px;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e6ed;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.2s;
            font-family: monospace;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .help-text {
            font-size: 12px;
            color: #6c757d;
            margin-top: 6px;
        }
        
        .current-values {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
        }
        
        .current-values h4 {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .current-values .value-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .current-values .value-row:last-child {
            border-bottom: none;
        }
        
        .current-values .label {
            font-weight: 500;
            color: #495057;
        }
        
        .current-values .value {
            font-family: monospace;
            font-weight: bold;
            color: #1e2a3e;
        }
        
        .current-values .empty {
            color: #adb5bd;
            font-style: italic;
        }
        
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }
        
        .btn {
            flex: 1;
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #1e2a3e;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            font-size: 12px;
            color: #6c757d;
        }
        
        .info-note {
            background: #e7f3ff;
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            margin-top: 20px;
        }
        
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #e9ecef;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            <h1>?? Add Stock Details</h1>
            <p>Enter ISIN, quantity purchased & depot</p>
            <div class="symbol-badge"><?php echo htmlspecialchars($symbol ?: '?'); ?></div>
        </div>
        
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($stockIndex === null && !empty($symbol)): ?>
                <div class="alert alert-error">
                    Stock '<?php echo htmlspecialchars($symbol); ?>' was not found in your portfolio.<br><br>
                    <a href="javascript:history.back()" style="color: #721c24;">? Go back</a>
                </div>
            <?php elseif (empty($symbol)): ?>
                <div class="alert alert-info">
                    Please provide a stock symbol: <code>buy.php?go=AAPL</code>
                </div>
            <?php else: ?>
                <?php if ($currentIsin || $currentNrbght || $currentDepot): ?>
                    <div class="current-values">
                        <h4>?? Currently Saved</h4>
                        <div class="value-row">
                            <span class="label">ISIN:</span>
                            <span class="value"><?php echo $currentIsin ? htmlspecialchars($currentIsin) : '<span class="empty">Not set</span>'; ?></span>
                        </div>
                        <div class="value-row">
                            <span class="label">Quantity Bought:</span>
                            <span class="value"><?php echo $currentNrbght ? number_format($currentNrbght) : '<span class="empty">Not set</span>'; ?></span>
                        </div>
                        <div class="value-row">
                            <span class="label">Depot:</span>
                            <span class="value"><?php echo $currentDepot ? htmlspecialchars($currentDepot) : '<span class="empty">Not set</span>'; ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label>ISIN Code <span class="required">*</span></label>
                        <input type="text" 
                               name="isin" 
                               value="<?php echo htmlspecialchars($currentIsin); ?>"
                               placeholder="e.g., US0378331005"
                               maxlength="12"
                               pattern="[A-Z]{2}[A-Z0-9]{9}[0-9]"
                               title="ISIN must be 12 characters: 2 letters, then 9 alphanumeric, ending with a number">
                        <div class="help-text">
                            International Securities Identification Number (12 characters: 2 letters + 9 alphanumeric + 1 check digit)
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Number Bought <span class="required">*</span></label>
                        <input type="number" 
                               name="nrbght" 
                               value="<?php echo htmlspecialchars($currentNrbght); ?>"
                               placeholder="e.g., 100"
                               min="1"
                               step="1"
                               required>
                        <div class="help-text">Total quantity of shares purchased</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Depot <span class="required">*</span></label>
                        <select name="depot" required>
                            <option value="comdirect" <?php echo $currentDepot === 'comdirect' ? 'selected' : ''; ?>>comdirect</option>
                            <option value="ingdiba" <?php echo $currentDepot === 'ingdiba' ? 'selected' : ''; ?>>ING DiBa</option>
                        </select>
                        <div class="help-text">Select the depot/broker where this stock is held</div>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">?? Save to JSON</button>
                        <a href="javascript:history.back()" class="btn btn-secondary">? Cancel</a>
                    </div>
                </form>
                
                <hr>
                
                <div class="info-note">
                    <strong>?? What happens next?</strong><br>
                    The ISIN, quantity, and depot will be saved directly to <code>stocks.json</code> under the entry for 
                    <strong><?php echo htmlspecialchars($symbol); ?></strong>. These fields are stored as "isin", "nrbght", and "depot".
                </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <span>?? Data stored in stocks.json | ?? <a href="stocks.json" target="_blank" style="color:#667eea;">View JSON</a></span>
        </div>
    </div>
</div>
</body>
</html>