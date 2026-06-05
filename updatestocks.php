<?php
$jsonFile = 'stocks.json';
$stocks   = json_decode(file_get_contents($jsonFile), true);

function yahooFetch($symbol) {
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol);
    $ctx = stream_context_create(['http' => [
        'header'  => "User-Agent: Mozilla/5.0\r\n",
        'timeout' => 10,
    ]]);
    $raw  = @file_get_contents($url, false, $ctx);
    $data = $raw ? json_decode($raw, true) : null;
    return $data['chart']['result'][0]['meta'] ?? null;
}

// ── AJAX ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // Fetch one price, auto-convert to USD if needed
    if ($action === 'fetch') {
        $symbol = $_POST['symbol'];
        $meta   = yahooFetch($symbol);

        if (!$meta || !isset($meta['regularMarketPrice'])) {
            echo json_encode(['price' => null, 'error' => 'no data']);
            exit;
        }

        $price    = (float)$meta['regularMarketPrice'];
        $currency = strtoupper($meta['currency'] ?? 'USD');
        $usdPrice = $price;
        $fxRate   = 1.0;

        if ($currency !== 'USD') {
            $fxMeta = yahooFetch($currency . 'USD=X');
            if ($fxMeta && isset($fxMeta['regularMarketPrice'])) {
                $fxRate   = (float)$fxMeta['regularMarketPrice'];
                $usdPrice = round($price * $fxRate, 4);
            } else {
                // Can't convert — return null so UI marks it failed
                echo json_encode(['price' => null, 'error' => 'fx_failed', 'currency' => $currency]);
                exit;
            }
        }

        echo json_encode([
            'price'        => $usdPrice,
            'raw_price'    => $price,
            'currency'     => $currency,
            'fx_rate'      => $fxRate,
        ]);
        exit;
    }

    // Save confirmed prices (only price field, nothing else)
    if ($action === 'save') {
        $updates = json_decode($_POST['updates'], true);
        foreach ($updates as $u) {
            $i = (int)$u['index'];
            if (isset($stocks[$i])) {
                $stocks[$i]['price'] = (float)$u['price'];
            }
        }
        file_put_contents($jsonFile, json_encode($stocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        // Verify
        $check = json_decode(file_get_contents($jsonFile), true);
        $ok = true;
        foreach ($updates as $u) {
            if ((float)$check[(int)$u['index']]['price'] !== (float)$u['price']) { $ok = false; break; }
        }
        echo json_encode(['ok' => $ok]);
        exit;
    }

    // Delete a stock
    if ($action === 'delete') {
        $i      = (int)$_POST['index'];
        $symbol = $_POST['symbol'];
        array_splice($stocks, $i, 1);
        file_put_contents($jsonFile, json_encode($stocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $check = json_decode(file_get_contents($jsonFile), true);
        $gone  = !array_filter($check, fn($s) => $s['stock'] === $symbol);
        echo json_encode(['ok' => (bool)$gone]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Stock Updater</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#f0f2f5;padding:20px}
.wrap{max-width:1150px;margin:0 auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.1)}
h1{color:#1a73e8;margin-bottom:16px}
.bar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
button{padding:8px 16px;border:none;border-radius:4px;cursor:pointer;font-weight:600;font-size:13px}
.b-blue{background:#4285f4;color:#fff}.b-blue:hover{background:#3367d6}
.b-green{background:#34a853;color:#fff}.b-green:hover{background:#2d8e47}
.b-red{background:#dc3545;color:#fff}.b-red:hover{background:#c82333}
.b-gray{background:#e0e0e0;color:#333}.b-gray:hover{background:#ccc}
button:disabled{opacity:.4;cursor:not-allowed}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{padding:9px 10px;border-bottom:1px solid #eee;text-align:left}
th{background:#f8f9fa;font-size:11px;text-transform:uppercase;color:#555}
tr.isin td{color:#aaa}
.up{color:#28a745}.dn{color:#dc3545}
.tag{display:inline-block;padding:2px 6px;border-radius:3px;font-size:11px;font-weight:600}
.tag-ok{background:#d4edda;color:#155724}
.tag-fail{background:#f8d7da;color:#721c24}
.tag-isin{background:#e2e3e5;color:#555}
.tag-wait{background:#fff3cd;color:#856404}
.tag-fx{background:#cce5ff;color:#004085;font-size:10px;margin-left:4px}
.spin{display:inline-block;width:12px;height:12px;border:2px solid #ccc;border-top-color:#4285f4;border-radius:50%;animation:spin .8s linear infinite;vertical-align:middle}
@keyframes spin{to{transform:rotate(360deg)}}
#msg{padding:10px;border-radius:4px;margin-bottom:12px;display:none}
.msg-ok{background:#d4edda;color:#155724;display:block!important}
.msg-err{background:#f8d7da;color:#721c24;display:block!important}
.msg-info{background:#d1ecf1;color:#0c5460;display:block!important}
small{color:#999}
</style>
</head>
<body>
<div class="wrap">
  <h1>📈 Stock Updater</h1>
  <div id="msg"></div>
  <div class="bar">
    <button class="b-blue" onclick="fetchAll()">🔄 Fetch All</button>
    <button class="b-green" id="btnSave" onclick="saveAll()" disabled>💾 Save All</button>
    <button class="b-gray" onclick="reset()">↺ Reset</button>
  </div>
  <table>
    <thead>
      <tr><th>#</th><th>Symbol</th><th>Exchange</th><th>Saved Price</th><th>New Price (USD)</th><th>Change</th><th>Status</th><th></th></tr>
    </thead>
    <tbody id="tbody"></tbody>
  </table>
</div>

<script>
const stocks = <?php echo json_encode(array_values($stocks)); ?>;
const newPrices = {};   // index -> USD price
const fxInfo    = {};   // index -> { currency, raw_price, fx_rate }

function fmt(p) { return p != null ? p.toFixed(3) : '—'; }

function changeHtml(oldP, newP) {
    if (newP == null) return '—';
    const d = newP - oldP, pct = (d / oldP * 100).toFixed(2);
    if (d > 0) return `<span class="up">▲ +${d.toFixed(3)} (+${pct}%)</span>`;
    if (d < 0) return `<span class="dn">▼ ${d.toFixed(3)} (${pct}%)</span>`;
    return '0.000 (0%)';
}

function render() {
    document.getElementById('tbody').innerHTML = stocks.map((s, i) => {
        const hasIsin = !!(s.isin && s.isin.trim());
        const np  = newPrices[i] ?? null;
        const fx  = fxInfo[i] ?? null;

        // New price cell — show raw + converted if FX was involved
        let npCell = '—';
        if (np != null) {
            npCell = `$${fmt(np)}`;
            if (fx && fx.currency !== 'USD') {
                npCell += ` <span class="tag tag-fx">${fx.currency} ${fmt(fx.raw_price)} × ${fx.fx_rate.toFixed(5)}</span>`;
            }
        }

        let statusHtml, actionsHtml;
        if (hasIsin) {
            statusHtml  = '<span class="tag tag-isin">🔒 ISIN</span>';
            actionsHtml = `<button class="b-red" style="font-size:11px;padding:4px 8px" onclick="del(${i})">🗑</button>`;
        } else {
            statusHtml  = np != null
                ? '<span class="tag tag-ok">✓ Fetched</span>'
                : '<span class="tag tag-wait">Pending</span>';
            actionsHtml = `
                <button class="b-blue" style="font-size:11px;padding:4px 8px" onclick="fetchOne(${i})">🔍</button>
                <button class="b-red"  style="font-size:11px;padding:4px 8px" onclick="del(${i})">🗑</button>`;
        }

        return `<tr class="${hasIsin ? 'isin' : ''}">
            <td>${i + 1}</td>
            <td><strong>${s.stock}</strong></td>
            <td>${s.exchange_market}</td>
            <td>$${fmt(s.price)}<br><small>${s.date}</small></td>
            <td id="np-${i}">${npCell}</td>
            <td id="ch-${i}">${changeHtml(s.price, np)}</td>
            <td id="st-${i}">${statusHtml}</td>
            <td>${actionsHtml}</td>
        </tr>`;
    }).join('');

    document.getElementById('btnSave').disabled = Object.keys(newPrices).length === 0;
}

function msg(type, text) {
    const el = document.getElementById('msg');
    el.className = 'msg-' + type;
    el.textContent = text;
    clearTimeout(msg._t);
    msg._t = setTimeout(() => el.style.display = 'none', 5000);
}

async function fetchOne(i) {
    const s = stocks[i];
    document.getElementById('st-' + i).innerHTML = '<span class="spin"></span>';

    const fd = new FormData();
    fd.append('action', 'fetch');
    fd.append('symbol', s.stock);

    try {
        const r = await fetch('', { method: 'POST', body: fd });
        const d = await r.json();

        if (d.price != null) {
            newPrices[i] = d.price;
            fxInfo[i]    = { currency: d.currency, raw_price: d.raw_price, fx_rate: d.fx_rate };

            let npCell = `$${fmt(d.price)}`;
            if (d.currency !== 'USD') {
                npCell += ` <span class="tag tag-fx">${d.currency} ${fmt(d.raw_price)} × ${d.fx_rate.toFixed(5)}</span>`;
            }

            document.getElementById('np-' + i).innerHTML = npCell;
            document.getElementById('ch-' + i).innerHTML = changeHtml(s.price, d.price);
            document.getElementById('st-' + i).innerHTML = '<span class="tag tag-ok">✓ Fetched</span>';
            document.getElementById('btnSave').disabled  = false;
        } else {
            const reason = d.error === 'fx_failed' ? `✗ FX failed (${d.currency})` : '✗ Failed';
            document.getElementById('st-' + i).innerHTML = `<span class="tag tag-fail">${reason}</span>`;
            document.getElementById('np-' + i).textContent = '—';
            msg('err', `✗ ${s.stock}: ${reason}`);
        }
    } catch (e) {
        document.getElementById('st-' + i).innerHTML = '<span class="tag tag-fail">✗ Error</span>';
        msg('err', 'Error: ' + e.message);
    }
}

async function fetchAll() {
    const indices = stocks.map((_, i) => i).filter(i => !(stocks[i].isin && stocks[i].isin.trim()));
    let fetched = 0, failed = 0;
    for (const i of indices) {
        await fetchOne(i);
        await new Promise(r => setTimeout(r, 350));
        if (newPrices[i] != null) fetched++; else failed++;
    }
    msg('ok', `Done — ✓ ${fetched} fetched, ✗ ${failed} failed`);
}

async function saveAll() {
    const updates = Object.entries(newPrices).map(([i, p]) => ({ index: parseInt(i), price: p }));
    if (!updates.length) return;

    const lines = updates.map(u => {
        const s  = stocks[u.index];
        const fx = fxInfo[u.index];
        const fxNote = fx && fx.currency !== 'USD' ? ` (${fx.currency} ${fmt(fx.raw_price)} × ${fx.fx_rate.toFixed(4)})` : '';
        return `${s.stock}: $${fmt(s.price)} → $${fmt(u.price)}${fxNote}`;
    });

    if (!confirm(`Save ${updates.length} price(s)?\n\n${lines.join('\n')}`)) return;

    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('updates', JSON.stringify(updates));
    const r = await fetch('', { method: 'POST', body: fd });
    const d = await r.json();

    if (d.ok) {
        updates.forEach(u => { stocks[u.index].price = u.price; });
        for (const k in newPrices) delete newPrices[k];
        for (const k in fxInfo)    delete fxInfo[k];
        render();
        msg('ok', `✓ Saved ${updates.length} price(s)`);
    } else {
        msg('err', '✗ Save verification failed — check file permissions');
    }
}

async function del(i) {
    if (!confirm(`Delete ${stocks[i].stock}?`)) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('index', i);
    fd.append('symbol', stocks[i].stock);
    const r = await fetch('', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) {
        stocks.splice(i, 1);
        const shifted = {};
        for (const [k, v] of Object.entries(newPrices)) {
            const n = parseInt(k);
            if (n === i) continue;
            shifted[n < i ? n : n - 1] = v;
        }
        for (const k in newPrices) delete newPrices[k];
        Object.assign(newPrices, shifted);
        const shiftedFx = {};
        for (const [k, v] of Object.entries(fxInfo)) {
            const n = parseInt(k);
            if (n === i) continue;
            shiftedFx[n < i ? n : n - 1] = v;
        }
        for (const k in fxInfo) delete fxInfo[k];
        Object.assign(fxInfo, shiftedFx);
        render();
        msg('ok', `✓ Deleted`);
    } else {
        msg('err', '✗ Delete failed');
    }
}

function reset() {
    for (const k in newPrices) delete newPrices[k];
    for (const k in fxInfo)    delete fxInfo[k];
    render();
    msg('info', 'Reset');
}

render();
</script>
</body>
</html>