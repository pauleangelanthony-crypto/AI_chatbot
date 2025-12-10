# Fix 404 Error for Landing Page

## Quick Fix Steps

### Step 1: Flush Rewrite Rules (Easiest Method)

1. **Go to WordPress Admin**
2. **Navigate to:** Boat Chatbot → Landing Page Info
3. **Click the button:** "🔄 Flush Rewrite Rules Now"
4. **Wait for success message**
5. **Click the "Open Landing Page" button** that appears

### Step 2: Alternative - Via Permalinks Settings

1. **Go to WordPress Admin**
2. **Navigate to:** Settings → Permalinks
3. **Click "Save Changes"** (don't change anything)
4. **This flushes rewrite rules automatically**
5. **Try accessing:** `http://localhost/virtual-yachtbroker`

### Step 3: Deactivate/Reactivate Plugin

1. **Go to:** Plugins → Installed Plugins
2. **Find "Boat Chatbot AI"**
3. **Click "Deactivate"**
4. **Wait 2 seconds**
5. **Click "Activate"**
6. **This will run the activation hook and flush rewrite rules**

## Verify It's Working

After flushing rewrite rules, you should be able to access:
- `http://localhost/virtual-yachtbroker`

If it still doesn't work, check:

1. **WordPress Permalinks Setting:**
   - Go to Settings → Permalinks
   - Make sure it's NOT set to "Plain"
   - Should be "Post name" or any other option

2. **Check .htaccess file:**
   - If using Apache, make sure `.htaccess` is writable
   - WordPress should be able to write rewrite rules

3. **Check Server Configuration:**
   - If using Nginx, you may need to add rewrite rules manually
   - If using IIS, check web.config

## Manual Fix (Advanced)

If the above doesn't work, you can manually add the rewrite rule:

### For Apache (.htaccess):
Add this line before `# BEGIN WordPress`:
```
RewriteRule ^virtual-yachtbroker/?$ /index.php?boat_chatbot_landing=1 [L]
```

### For Nginx:
Add to server block:
```
location /virtual-yachtbroker {
    try_files $uri $uri/ /index.php?boat_chatbot_landing=1;
}
```

## Test URL

After fixing, test with:
- `http://localhost/virtual-yachtbroker`
- Should show the landing page with video, buttons, and chatbot input

