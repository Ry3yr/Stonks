// Function to load stock attributes and add indicators
function loadStockAttributes() {
    fetch('stockattributes.json')
        .then(response => {
            if (!response.ok) {
                throw new Error('stockattributes.json not found');
            }
            return response.json();
        })
        .then(attributes => {
            addIndicatorsToTable(attributes);
        })
        .catch(error => {
            console.log('No attributes file found');
        });
}

// Function to add indicators to the stock table
function addIndicatorsToTable(attributes) {
    $('#stockTable tbody tr').each(function() {
        const $row = $(this);
        
        // Get the stock symbol
        let $stockNameElem = null;
        let stockSymbol = '';
        
        if ($row.find('td:first-child .stock-name-bold').length) {
            $stockNameElem = $row.find('td:first-child .stock-name-bold');
            stockSymbol = $stockNameElem.clone().children().remove().end().text().trim();
        } else if ($row.find('td:first-child strong').length) {
            $stockNameElem = $row.find('td:first-child strong');
            stockSymbol = $stockNameElem.text().trim();
        } else {
            $stockNameElem = $row.find('td:first-child');
            stockSymbol = $stockNameElem.text().trim();
        }
        
        // Clean symbol (remove dot and everything after, also remove any numbers like x98)
        let cleanSymbol = stockSymbol.split(' ')[0];
        cleanSymbol = cleanSymbol.split('.')[0];
        
        // Check if this stock has attributes
        if (attributes[cleanSymbol]) {
            const attrs = attributes[cleanSymbol];
            let indicators = [];
            
            // Add cyclic indicator if present
            if (attrs.cyclic) {
                let cyclicText = '';
                switch(attrs.cyclic) {
                    case 'quarterly':
                        cyclicText = 'quarterly';
                        break;
                    case 'halfyearly':
                        cyclicText = 'half-yearly';
                        break;
                    case 'yearly':
                        cyclicText = 'yearly';
                        break;
                    default:
                        cyclicText = attrs.cyclic;
                }
                indicators.push(`<span class="attr-badge cyclic-badge">${cyclicText}</span>`);
            }
            
            // Add volatile indicator only if 'yes'
            if (attrs.volatile === 'yes') {
                indicators.push(`<span class="attr-badge volatile-badge">volatile</span>`);
            }
            
            // Add indicators
            if (indicators.length > 0) {
                $stockNameElem.after(' ' + indicators.join(' '));
            }
        }
    });
}

// Add CSS styles for attributes
function addStyles() {
    const style = `
        <style>
            .attr-badge {
                display: inline-block;
                padding: 2px 8px;
                margin-left: 8px;
                border-radius: 4px;
                font-size: 10px;
                font-weight: normal;
                vertical-align: middle;
            }
            .cyclic-badge {
                background: #e3f2fd;
                color: #1976d2;
                border: 1px solid #90caf9;
            }
            .volatile-badge {
                background: #fff3e0;
                color: #e65100;
                border: 1px solid #ffb74d;
            }
        </style>
    `;
    $('head').append(style);
}

// ========== NEW: EXCHANGE HOURS & TIMER FUNCTIONALITY ==========

// Exchange market hours (all times in Europe/Berlin timezone)
const exchangeMarketHours = {
    'XETRA': { name: 'Xetra', sessions: [{ open: '09:00', close: '17:30' }] },
    'FRA': { name: 'Frankfurt', sessions: [{ open: '09:00', close: '17:30' }] },
    'ETR': { name: 'XETRA', sessions: [{ open: '09:00', close: '17:30' }] },
    'NASDAQ': { name: 'NASDAQ', sessions: [{ open: '15:30', close: '22:00' }] },
    'NYSE': { name: 'NYSE', sessions: [{ open: '15:30', close: '22:00' }] },
    'LON': { name: 'London', sessions: [{ open: '09:00', close: '17:30' }] },
    'LSE': { name: 'London', sessions: [{ open: '09:00', close: '17:30' }] },
    'TYO': { name: 'Tokyo', sessions: [{ open: '01:00', close: '07:00' }] },
    'HKG': { name: 'Hong Kong', sessions: [{ open: '02:30', close: '09:00' }] },
    'Hong Kong': { name: 'Hong Kong', sessions: [{ open: '02:30', close: '09:00' }] },
    'OTC Markets': { name: 'OTC', sessions: [{ open: '15:30', close: '22:00' }] },
    'default': { name: 'US Market', sessions: [{ open: '15:30', close: '22:00' }] }
};

// Get market status (open/closed and next event)
function getMarketStatus(exchangeName) {
    const market = exchangeMarketHours[exchangeName] || exchangeMarketHours['default'];
    const now = new Date();
    
    // Get Berlin time (Germany local)
    const berlinTime = new Date(now.toLocaleString('en-US', { timeZone: 'Europe/Berlin' }));
    const dayOfWeek = berlinTime.getDay(); // 0 = Sunday, 6 = Saturday
    const currentMinutes = berlinTime.getHours() * 60 + berlinTime.getMinutes();
    
    // Weekend check
    if (dayOfWeek === 0 || dayOfWeek === 6) {
        // Calculate days until Monday
        const daysUntilMonday = dayOfWeek === 0 ? 1 : 2;
        const nextOpen = new Date(berlinTime);
        nextOpen.setDate(berlinTime.getDate() + daysUntilMonday);
        const [openHour, openMin] = market.sessions[0].open.split(':').map(Number);
        nextOpen.setHours(openHour, openMin, 0, 0);
        
        const diffMs = nextOpen - berlinTime;
        const hoursLeft = Math.floor(diffMs / (1000 * 60 * 60));
        const minsLeft = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
        
        return {
            status: 'closed',
            statusText: '🔴 CLOSED',
            nextEvent: `Opens Monday ${market.sessions[0].open} (in ${hoursLeft}h ${minsLeft}m)`,
            color: '#dc3545'
        };
    }
    
    // Check each session
    let isOpen = false;
    let closeTime = null;
    let nextOpenTime = null;
    
    for (const session of market.sessions) {
        const [openHour, openMin] = session.open.split(':').map(Number);
        const [closeHour, closeMin] = session.close.split(':').map(Number);
        const openMinutes = openHour * 60 + openMin;
        const closeMinutes = closeHour * 60 + closeMin;
        
        // Check if currently open
        if (currentMinutes >= openMinutes && currentMinutes <= closeMinutes) {
            isOpen = true;
            closeTime = closeMinutes;
            break;
        }
        
        // Find next open (for today)
        if (openMinutes > currentMinutes) {
            if (nextOpenTime === null || openMinutes < nextOpenTime) {
                nextOpenTime = openMinutes;
            }
        }
    }
    
    if (isOpen && closeTime !== null) {
        // Market is OPEN - calculate time until close
        let minsUntilClose = closeTime - currentMinutes;
        if (minsUntilClose < 0) minsUntilClose += 24 * 60;
        
        const hoursLeft = Math.floor(minsUntilClose / 60);
        const minsLeft = minsUntilClose % 60;
        const closeHour = Math.floor(closeTime / 60);
        const closeMin = closeTime % 60;
        
        return {
            status: 'open',
            statusText: '🟢 OPEN',
            nextEvent: `Closes at ${closeHour.toString().padStart(2,'0')}:${closeMin.toString().padStart(2,'0')} (in ${hoursLeft}h ${minsLeft}m)`,
            color: '#28a745'
        };
    }
    
    if (nextOpenTime !== null) {
        // Market is CLOSED but opens later today
        let minsUntilOpen = nextOpenTime - currentMinutes;
        if (minsUntilOpen < 0) minsUntilOpen += 24 * 60;
        
        const hoursLeft = Math.floor(minsUntilOpen / 60);
        const minsLeft = minsUntilOpen % 60;
        const openHour = Math.floor(nextOpenTime / 60);
        const openMin = nextOpenTime % 60;
        
        return {
            status: 'closed',
            statusText: '🔴 CLOSED',
            nextEvent: `Opens at ${openHour.toString().padStart(2,'0')}:${openMin.toString().padStart(2,'0')} (in ${hoursLeft}h ${minsLeft}m)`,
            color: '#ff9800'
        };
    }
    
    // Market closed for the day - next open tomorrow
    const nextSession = market.sessions[0];
    const [nextHour, nextMin] = nextSession.open.split(':').map(Number);
    let minsUntilOpen = (nextHour * 60 + nextMin) + (24 * 60) - currentMinutes;
    const hoursLeft = Math.floor(minsUntilOpen / 60);
    const minsLeft = minsUntilOpen % 60;
    
    return {
        status: 'closed',
        statusText: '🔴 CLOSED',
        nextEvent: `Opens tomorrow at ${nextSession.open} (in ${hoursLeft}h ${minsLeft}m)`,
        color: '#ff9800'
    };
}

// Add exchange status indicators to each row
function addExchangeStatusIndicators() {
    $('#stockTable tbody tr').each(function() {
        const $row = $(this);
        // Find the exchange cell (8th column, index 7)
        const $exchangeCell = $row.find('td').eq(7);
        
        if ($exchangeCell.length) {
            // Get the exchange name from the badge inside
            const $badge = $exchangeCell.find('.exchange-badge');
            let exchangeName = $badge.length ? $badge.text().trim() : $exchangeCell.text().trim();
            
            // Map common exchange names
            if (exchangeName === 'ETR') exchangeName = 'XETRA';
            if (exchangeName === 'FRA') exchangeName = 'XETRA';
            
            const status = getMarketStatus(exchangeName);
            
            // Remove any existing status div
            $exchangeCell.find('.exchange-status').remove();
            
            // Create and append status HTML
            const statusHtml = `
                <div class="exchange-status" style="margin-top: 8px; padding-top: 6px; border-top: 1px dashed #ddd; font-size: 11px;">
                    <span style="color: ${status.color}; font-weight: bold;">${status.statusText}</span>
                    <span style="color: #666; display: block; font-size: 10px; margin-top: 2px;">${status.nextEvent}</span>
                </div>
            `;
            
            $exchangeCell.append(statusHtml);
        }
    });
}

// Update all exchange timers (runs every minute)
let updateInterval = null;

function startExchangeTimerUpdates() {
    if (updateInterval) clearInterval(updateInterval);
    
    function updateAllStatuses() {
        $('#stockTable tbody tr').each(function() {
            const $row = $(this);
            const $exchangeCell = $row.find('td').eq(7);
            
            if ($exchangeCell.length) {
                const $badge = $exchangeCell.find('.exchange-badge');
                let exchangeName = $badge.length ? $badge.text().trim() : $exchangeCell.text().trim();
                
                if (exchangeName === 'ETR') exchangeName = 'XETRA';
                if (exchangeName === 'FRA') exchangeName = 'XETRA';
                
                const status = getMarketStatus(exchangeName);
                
                let $statusDiv = $exchangeCell.find('.exchange-status');
                if ($statusDiv.length === 0) {
                    $statusDiv = $('<div class="exchange-status" style="margin-top: 8px; padding-top: 6px; border-top: 1px dashed #ddd; font-size: 11px;"></div>');
                    $exchangeCell.append($statusDiv);
                }
                
                $statusDiv.html(`
                    <span style="color: ${status.color}; font-weight: bold;">${status.statusText}</span>
                    <span style="color: #666; display: block; font-size: 10px; margin-top: 2px;">${status.nextEvent}</span>
                `);
            }
        });
    }
    
    updateAllStatuses();
    updateInterval = setInterval(updateAllStatuses, 60000); // Update every minute
}

// Add CSS styles for exchange status
function addExchangeStyles() {
    const style = `
        <style>
            .exchange-status {
                animation: fadeIn 0.3s ease;
            }
            .exchange-status span {
                transition: all 0.2s ease;
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-5px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .exchange-cell {
                min-width: 130px;
            }
        </style>
    `;
    $('head').append(style);
}

// ========== MODIFIED DOCUMENT READY ==========

// Run when document is ready
$(document).ready(function() {
    addStyles();
    addExchangeStyles();
    
    // Wait a bit for the table to be fully populated
    setTimeout(function() {
        loadStockAttributes();
        addExchangeStatusIndicators();
        startExchangeTimerUpdates();
    }, 500);
});