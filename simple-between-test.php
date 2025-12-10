<?php
/**
 * Simple Test for "Between" Keyword Regex Patterns
 */

// Test regex patterns directly
echo "=== Testing Between Keyword Regex Patterns ===\n\n";

// Length patterns
$length_tests = array(
    '10 boats in los angeles between 10-14 ft',
    'boats between 15 and 20 feet',
    'between 20-25 meters',
    'between 25 and 20 ft' // Wrong order
);

echo "Length Pattern Tests:\n";
echo "Pattern: /\\bbetween\\s+(\\d+(?:\\.\\d+)?)\\s*(?:and|-)\\s*(\\d+(?:\\.\\d+)?)\\s*(?:ft|foot|feet|meter|metre|m|meters|metres)\\b/i\n\n";

foreach ($length_tests as $test) {
    echo "Testing: \"$test\"\n";
    if (preg_match('/\bbetween\s+(\d+(?:\.\d+)?)\s*(?:and|-)\s*(\d+(?:\.\d+)?)\s*(?:ft|foot|feet|meter|metre|m|meters|metres)\b/i', $test, $matches)) {
        echo "  ✓ Matched: $matches[1] - $matches[2]\n";
    } else {
        echo "  ✗ No match\n";
    }
    echo "\n";
}

// Price patterns
$price_tests = array(
    'boats between $100k and $200k',
    'between $500k-$1M yachts',
    'between 100000 and 200000'
);

echo "Price Pattern Tests:\n";
echo "Pattern: /\\bbetween\\s*\\$?\\s*(\\d+(?:,\\d{3})*(?:\\.\\d{2})?)\\s*(?:and|-)\\s*\\$?\\s*(\\d+(?:,\\d{3})*(?:\\.\\d{2})?)\\s*(?:k|dollars?|usd)?/i\n\n";

foreach ($price_tests as $test) {
    echo "Testing: \"$test\"\n";
    if (preg_match('/\bbetween\s*\$?\s*(\d+(?:,\d{3})*(?:\.\d{2})?)\s*(?:and|-)\s*\$?\s*(\d+(?:,\d{3})*(?:\.\d{2})?)\s*(?:k|dollars?|usd)?/i', $test, $matches)) {
        echo "  ✓ Matched: $matches[1] - $matches[2]\n";
    } else {
        echo "  ✗ No match\n";
    }
    echo "\n";
}

// Year patterns
$year_tests = array(
    'boats between 2000 and 2010',
    'between 2015-2020 models',
    'between 2020-2000' // Wrong order
);

echo "Year Pattern Tests:\n";
echo "Pattern: /\\bbetween\\s+(19\\d{2}|20[0-9]{2})\\s*(?:and|-)\\s*(19\\d{2}|20[0-9]{2})/i\n\n";

foreach ($year_tests as $test) {
    echo "Testing: \"$test\"\n";
    if (preg_match('/\bbetween\s+(19\d{2}|20[0-9]{2})\s*(?:and|-)\s*(19\d{2}|20[0-9]{2})/i', $test, $matches)) {
        echo "  ✓ Matched: $matches[1] - $matches[2]\n";
    } else {
        echo "  ✗ No match\n";
    }
    echo "\n";
}
