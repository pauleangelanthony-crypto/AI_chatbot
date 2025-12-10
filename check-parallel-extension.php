<?php
/**
 * Parallel Extension Checker Script
 * 
 * Run this script to check if the Parallel extension is installed and configured correctly
 * 
 * Usage: php check-parallel-extension.php
 */

echo "PHP Parallel Extension Checker\n";
echo "==============================\n\n";

// Check PHP version
$php_version = phpversion();
echo "PHP Version: {$php_version}\n";
if (version_compare($php_version, '7.2.0', '<')) {
    echo "⚠️  WARNING: PHP 7.2+ is required for Parallel extension\n";
} else {
    echo "✓ PHP version is compatible\n";
}
echo "\n";

// Check if extension is loaded
if (extension_loaded('parallel')) {
    echo "✓ Parallel extension is loaded\n";
    
    // Check version
    if (function_exists('parallel\bootstrap')) {
        echo "✓ Parallel extension functions are available\n";
    }
    
    // Check thread safety
    $thread_safety = ini_get('zend.thread_safety');
    if ($thread_safety) {
        echo "✓ Thread Safety: Enabled\n";
    } else {
        echo "⚠️  WARNING: Thread Safety may not be enabled\n";
        echo "   Run: php -i | grep 'Thread Safety'\n";
    }
    
    // Test basic functionality
    echo "\nTesting Parallel functionality...\n";
    try {
        $runtime = new \parallel\Runtime();
        $future = $runtime->run(function() {
            return "Hello from parallel thread!";
        });
        $result = $future->value();
        echo "✓ Parallel test successful: {$result}\n";
    } catch (Exception $e) {
        echo "✗ Parallel test failed: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        echo "✗ Parallel test failed: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "✗ Parallel extension is NOT loaded\n";
    echo "\n";
    echo "Installation steps:\n";
    echo "1. Install via PECL: sudo pecl install parallel\n";
    echo "2. Add to php.ini: extension=parallel.so\n";
    echo "3. Restart PHP/web server\n";
    echo "\n";
    echo "For detailed instructions, see: PARALLEL_EXTENSION_INSTALLATION.md\n";
}

echo "\n";
echo "PHP Configuration Info:\n";
echo "----------------------\n";
echo "Loaded php.ini: " . php_ini_loaded_file() . "\n";
echo "Additional .ini files: " . php_ini_scanned_files() . "\n";
echo "Extension directory: " . ini_get('extension_dir') . "\n";

echo "\n";
echo "All loaded extensions:\n";
$extensions = get_loaded_extensions();
sort($extensions);
foreach ($extensions as $ext) {
    echo "  - {$ext}\n";
}

echo "\n";
echo "Check complete!\n";

