# How to Access the Landing Page from WordPress Admin

## Quick Access Methods

### Method 1: From WordPress Admin Menu (Easiest)
1. Log in to your WordPress admin dashboard
2. In the left sidebar, find **"Boat Chatbot"** menu
3. Click on **"View Landing Page"** - this will open the landing page directly in a new tab
4. Or click **"Landing Page Info"** for detailed information and a link

### Method 2: Direct URL
1. Copy this URL: `yoursite.com/virtual-yachtbroker`
2. Replace `yoursite.com` with your actual WordPress site URL
3. Paste it in your browser's address bar
4. Press Enter

### Method 3: From Admin Settings Page
1. Go to **Boat Chatbot → Landing Page Info**
2. Click the large **"Open Landing Page in New Tab"** button
3. The page will open in a new browser tab

## If the URL Doesn't Work (404 Error)

If you get a 404 error when accessing `/virtual-yachtbroker`, follow these steps:

1. **Go to WordPress Admin**
2. **Navigate to:** Settings → Permalinks
3. **Click "Save Changes"** (you don't need to change anything)
4. This flushes the rewrite rules and registers the custom route
5. **Try accessing the URL again**

## What You'll See

When the landing page loads, you'll see:

- **Top Bar**: Hamburger menu, boat.com logo, navigation links
- **Video Section**: Full-width video in the center (or gradient if no video file)
- **5 Action Buttons**: Stacked vertically on the right side
- **Chatbot Input**: At the bottom of the page

## Testing the Page

1. Click any of the 5 action buttons - they should open the chatbot
2. Type a message in the bottom input field and press Enter
3. The chatbot widget should appear with your message
4. Test the hamburger menu on mobile/tablet view

## Admin Menu Structure

After installation, you'll see this in WordPress Admin:

```
Boat Chatbot
├── Settings
├── Chat Logs
├── View Landing Page  ← Opens landing page directly
└── Landing Page Info  ← Information page with link
```

## Quick Links

- **Landing Page URL**: `yoursite.com/virtual-yachtbroker`
- **Admin Info Page**: `yoursite.com/wp-admin/admin.php?page=boat-chatbot-landing-page-info`
- **Settings Page**: `yoursite.com/wp-admin/admin.php?page=boat-chatbot-settings`

## Need Help?

If you're still having issues:
1. Check that the plugin is activated
2. Verify rewrite rules are flushed (Settings → Permalinks → Save)
3. Check browser console for JavaScript errors
4. Ensure WordPress permalinks are enabled (not "Plain")

