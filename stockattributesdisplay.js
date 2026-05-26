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

// Add CSS styles
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

// Run when document is ready
$(document).ready(function() {
    addStyles();
    setTimeout(loadStockAttributes, 300);
});