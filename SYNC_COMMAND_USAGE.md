# Boat Chatbot - Sync Records Command Line Usage

This document explains how to sync records using the command line to avoid timeout issues.

## Method 1: Standalone PHP Script (Recommended)

The `sync-records.php` script can be run directly with PHP CLI without requiring WP-CLI.

### Prerequisites

- PHP CLI installed
- Access to command line (SSH, terminal, or command prompt)
- WordPress and plugin installed and activated

### Usage

**Navigate to your WordPress root directory:**
```bash
cd /path/to/your/wordpress
# For example:
cd D:\work\boat\wordpress-6.8.3\wordpress
```

**Or navigate to the plugin directory:**
```bash
cd wp-content/plugins/boat-chatbot
```

### Commands

#### 1. Sync All Records
```bash
php sync-records.php all
```

#### 2. Sync All Records with Limit and Offset (for large datasets)
```bash
# First batch
php sync-records.php all --limit=1000 --offset=0

# Second batch
php sync-records.php all --limit=1000 --offset=1000

# Continue until all records are synced
php sync-records.php all --limit=1000 --offset=2000
```

#### 3. Sync Pending Records
```bash
# Sync up to 100 pending records (default)
php sync-records.php pending

# Sync up to 500 pending records
php sync-records.php pending --limit=500
```

#### 4. Sync Specific Records by ID
```bash
php sync-records.php records 123 456 789
```

### Example Output

```
Starting sync of all records...
Found 1500 records to sync.
Syncing records...

=== Sync Results ===
Total records: 1500
Successful: 1485
Failed: 15
Duration: 245.32 seconds
Average time per record: 0.164 seconds

=== Error Summary ===
embedding_failed: 10
pinecone_upsert_failed: 5

SUCCESS: All records synced successfully!
```

### Running in Background (Linux/Mac)

```bash
# Run in background and save output to log file
nohup php sync-records.php all --limit=5000 > sync.log 2>&1 &

# Check progress
tail -f sync.log
```

### Running in Background (Windows PowerShell)

```powershell
# Run in background
Start-Process -NoNewWindow php -ArgumentList "sync-records.php all --limit=5000" -RedirectStandardOutput "sync.log"
```

## Method 2: WP-CLI (If WP-CLI is working)

If your WP-CLI installation is working correctly, you can use:

```bash
wp boat-chatbot sync all
wp boat-chatbot sync pending --limit=500
wp boat-chatbot sync records 123 456 789
```

## Troubleshooting

### Error: "Could not find wp-load.php"

**Solution:** Make sure you're running the script from the WordPress root directory, or edit the script to set the correct path to `wp-load.php`.

Open `sync-records.php` and update the `$wp_load_paths` array with your WordPress installation path.

### Error: "Vector Sync Manager class not found"

**Solution:** 
1. Make sure the plugin is activated in WordPress
2. Check that all plugin files are present in `wp-content/plugins/boat-chatbot/`

### Error: "Configuration error: Missing required configuration"

**Solution:** 
1. Go to WordPress Admin → Settings → Boat Chatbot
2. Ensure all API keys are configured:
   - Groq API key
   - Pinecone API key
   - Pinecone index name
   - Database credentials

### Timeout Issues

The standalone PHP script doesn't have HTTP timeout limits, but if you encounter PHP timeout:

1. **Increase PHP max_execution_time** in `php.ini`:
   ```ini
   max_execution_time = 3600  ; 1 hour
   ```

2. **Or run with increased timeout:**
   ```bash
   php -d max_execution_time=3600 sync-records.php all
   ```

3. **Use batch processing with limit/offset:**
   ```bash
   php sync-records.php all --limit=500 --offset=0
   php sync-records.php all --limit=500 --offset=500
   # Continue in batches
   ```

## Best Practices

1. **For large datasets:** Use `--limit` and `--offset` to process in batches
2. **Monitor progress:** Check the output or log files regularly
3. **Start with pending records:** Use `pending` command first to sync only records that need it
4. **Test with small batches:** Test with `--limit=10` first to ensure everything works

## Script Location

The `sync-records.php` file is located in:
```
wp-content/plugins/boat-chatbot/sync-records.php
```

You can copy this file to your WordPress root directory for easier access, or run it from the plugin directory.

