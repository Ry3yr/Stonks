#!/bin/sh

URL="https://alceawis.de/other/extra/fetchdata/2026-05-13-Finance/2026-05-13-Stocks/quickcheck.php?info"

fetch_data() {
    if command -v wget > /dev/null 2>&1; then
        wget -q -O - "$URL" 2>/dev/null
        return $?
    fi
    if command -v busybox > /dev/null 2>&1; then
        busybox wget -q -O - "$URL" 2>/dev/null
        return $?
    fi
    if command -v curl > /dev/null 2>&1; then
        curl -s "$URL"
        return $?
    fi
    echo "ERROR: No HTTP client found" >&2
    return 1
}

extract_summary() {
    local html="$1"
    
    total_invested=$(echo "$html" | grep -o '\$[0-9.]*' | head -1 | sed 's/\$//')
    total_loss=$(echo "$html" | grep -o '\$[0-9.]*' | sed -n '2p' | sed 's/\$//')
    current_value=$(echo "$html" | grep -o '\$[0-9.]*' | sed -n '3p' | sed 's/\$//')
    total_return=$(echo "$html" | grep -o '[+-][0-9.]*%' | head -1)
    total_gains=$(echo "$html" | grep 'Total Gains' | grep -o '\$[0-9.]*' | sed 's/\$//')
    total_losses=$(echo "$html" | grep 'Total Losses' | grep -o '\$[0-9.]*' | sed 's/\$//')
    
    echo "========================================="
    echo "PORTFOLIO SUMMARY"
    echo "========================================="
    echo "Total Invested:   \$ $total_invested"
    echo "Total Loss:       \$ $total_loss"
    echo "Current Value:    \$ $current_value"
    echo "Total Return:     $total_return"
    echo ""
    echo "Win/Loss Breakdown:"
    echo "  Gains:  +\$${total_gains:-0}"
    echo "  Losses: -\$${total_losses:-0}"
    echo ""
}

parse_individual() {
    local html="$1"
    
    echo "========================================="
    echo "INDIVIDUAL POSITIONS"
    echo "========================================="
    
    echo "📈 WINNING POSITIONS:"
    echo "-----------------------------------------"
    
    # Extract each winning stock by looking for <strong> in the winning tbody
    winning_tbody=$(echo "$html" | sed -n '/RISEN STOCKS/,/FALLEN STOCKS/p' | sed -n '/<tbody>/,/<\/tbody>/p')
    
    echo "$winning_tbody" | grep '<td><strong>' | while read -r line; do
        symbol=$(echo "$line" | sed 's/.*<strong>\([^<]*\)<\/strong>.*/\1/')
        
        # Get the next 8 lines to extract all data
        rest=$(echo "$winning_tbody" | grep -A 8 "$symbol" | head -8)
        
        shares=$(echo "$rest" | grep 'number"><strong>' | head -1 | sed 's/.*<strong>\([0-9]*\).*/\1/')
        buy=$(echo "$rest" | grep '\$[0-9.]*' | head -1 | sed 's/.*\$\([0-9.]*\).*/\1/')
        curr=$(echo "$rest" | grep '\$[0-9.]*' | sed -n '2p' | sed 's/.*\$\([0-9.]*\).*/\1/')
        gain=$(echo "$rest" | grep '= +\$' | sed 's/.*= +\$\([0-9.]*\).*/\1/')
        
        if [ -n "$symbol" ] && [ -n "$gain" ]; then
            printf "  %-8s %3s shares @ \$%-6s → \$%-6s = +\$%s\n" "$symbol" "$shares" "$buy" "$curr" "$gain"
        fi
    done
    
    echo ""
    echo "📉 LOSING POSITIONS:"
    echo "-----------------------------------------"
    
    # Extract losing stocks from the details section
    losing_tbody=$(echo "$html" | sed -n '/FALLEN STOCKS/,/TOTAL PORTFOLIO/p' | sed -n '/<tbody>/,/<\/tbody>/p')
    
    echo "$losing_tbody" | grep '<td><strong>' | while read -r line; do
        symbol=$(echo "$line" | sed 's/.*<strong>\([^<]*\)<\/strong>.*/\1/')
        
        rest=$(echo "$losing_tbody" | grep -A 8 "$symbol" | head -8)
        
        shares=$(echo "$rest" | grep 'number"><strong>' | head -1 | sed 's/.*<strong>\([0-9]*\).*/\1/')
        buy=$(echo "$rest" | grep '\$[0-9.]*' | head -1 | sed 's/.*\$\([0-9.]*\).*/\1/')
        curr=$(echo "$rest" | grep '\$[0-9.]*' | sed -n '2p' | sed 's/.*\$\([0-9.]*\).*/\1/')
        loss=$(echo "$rest" | grep '= -\$' | sed 's/.*= -\$\([0-9.]*\).*/\1/')
        
        if [ -n "$symbol" ] && [ -n "$loss" ]; then
            printf "  %-8s %3s shares @ \$%-6s → \$%-6s = -\$%s\n" "$symbol" "$shares" "$buy" "$curr" "$loss"
        fi
    done
    
    echo ""
    echo "-----------------------------------------"
}

main() {
    echo "Fetching portfolio data..."
    html=$(fetch_data)
    
    if [ -z "$html" ]; then
        echo "ERROR: Failed to fetch data"
        exit 1
    fi
    
    extract_summary "$html"
    parse_individual "$html"
}

main