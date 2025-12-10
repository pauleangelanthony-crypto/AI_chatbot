# PHP Parallel Extension Installation Guide

The Parallel extension enables parallel processing in PHP, which improves performance for intent classification when the message cache misses.

## Installation Methods

### Method 1: PECL Installation (Recommended)

#### On Linux/Unix (Ubuntu/Debian):
```bash
# Install required dependencies
sudo apt-get update
sudo apt-get install php-dev php-pear build-essential

# Install Parallel extension
sudo pecl install parallel

# Enable the extension
echo "extension=parallel.so" | sudo tee -a /etc/php/8.1/cli/php.ini
echo "extension=parallel.so" | sudo tee -a /etc/php/8.1/fpm/php.ini

# Restart PHP-FPM (if using)
sudo systemctl restart php8.1-fpm

# Verify installation
php -m | grep parallel
```

#### On Linux/Unix (CentOS/RHEL):
```bash
# Install required dependencies
sudo yum install php-devel php-pear gcc make

# Install Parallel extension
sudo pecl install parallel

# Enable the extension
echo "extension=parallel.so" | sudo tee -a /etc/php.ini

# Restart web server
sudo systemctl restart httpd

# Verify installation
php -m | grep parallel
```

#### On macOS (using Homebrew):
```bash
# Install PHP if not already installed
brew install php

# Install Parallel extension
pecl install parallel

# Enable the extension
echo "extension=parallel.so" >> $(php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||")

# Verify installation
php -m | grep parallel
```

### Method 2: Manual Compilation

If PECL installation fails, you can compile manually:

```bash
# Download the source
git clone https://github.com/krakjoe/parallel.git
cd parallel

# Compile
phpize
./configure
make
sudo make install

# Enable the extension
echo "extension=parallel.so" | sudo tee -a /etc/php/8.1/cli/php.ini
echo "extension=parallel.so" | sudo tee -a /etc/php/8.1/fpm/php.ini

# Restart services
sudo systemctl restart php8.1-fpm
```

### Method 3: Windows Installation

#### Using XAMPP/WAMP:
1. Download the pre-compiled DLL from PECL: https://pecl.php.net/package/parallel
2. Download the appropriate version for your PHP version and architecture (thread-safe or non-thread-safe)
3. Copy `php_parallel.dll` to your PHP `ext` directory (e.g., `C:\xampp\php\ext\`)
4. Edit `php.ini` and add:
   ```
   extension=php_parallel.dll
   ```
5. Restart Apache/web server

#### Using Composer (Alternative):
The Parallel extension cannot be installed via Composer as it's a PHP extension, not a library.

## Verification

After installation, verify the extension is loaded:

```bash
# Check if extension is loaded
php -m | grep parallel

# Or check PHP info
php -i | grep parallel

# Or create a test script
php -r "var_dump(extension_loaded('parallel'));"
```

## WordPress Integration

The plugin automatically detects if the Parallel extension is available:

- **If available**: Uses parallel processing for intent classification
- **If not available**: Falls back to sequential processing (no errors)

## Requirements

- PHP 7.2 or higher
- Thread-safe PHP build (ZTS enabled)
- pthreads library (usually included with PHP)

## Troubleshooting

### Error: "parallel extension not found"
- Ensure the extension is enabled in `php.ini`
- Check that you're using the correct PHP version
- Verify the extension file exists in the `ext` directory

### Error: "Class 'parallel\Runtime' not found"
- The extension is not loaded
- Check `php.ini` configuration
- Restart your web server/PHP-FPM

### Error: "ZTS build required"
- PHP must be compiled with ZTS (Zend Thread Safety)
- Recompile PHP with `--enable-maintainer-zts` flag
- Or use a pre-built PHP with ZTS support

### Check PHP Thread Safety:
```bash
php -i | grep "Thread Safety"
# Should show: Thread Safety => enabled
```

## Performance Notes

- Parallel processing is most beneficial when:
  - Message cache misses frequently
  - Keyword/regex checks are slow
  - Processing many requests simultaneously

- Overhead considerations:
  - Thread creation has a small overhead
  - Best for operations that take >10ms
  - Intent classification typically benefits from parallelization

## Alternative: Without Parallel Extension

If you cannot install the Parallel extension, the plugin will automatically use sequential processing. Performance will be slightly slower but still functional.

