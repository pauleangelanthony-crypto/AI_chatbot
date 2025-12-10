# Virtual Yacht Broker Landing Page

## Overview

A bright, effective landing page for the Virtual Yacht Broker feature, accessible at `/virtual-yachtbroker`.

## Features

- **Bright, Modern Design**: Vibrant colors with marina sunset theme
- **Responsive Layout**: Works on desktop, tablet, and mobile devices
- **Video Background**: Full-width video section in the center
- **Interactive Buttons**: 5 action buttons with smooth animations
- **Integrated Chatbot**: Chatbot input at the bottom of the page
- **Mobile Menu**: Hamburger menu for mobile navigation

## Design Elements

### Top Bar
- Hamburger menu button
- boat.com logo with separator
- Navigation links
- Video play button with "DeeVid AI" label

### Main Content
- **Video Section**: Full-width video background (stretched center)
- **Action Buttons**: 5 buttons stacked vertically:
  1. Contact
  2. Show me yourt
  3. Show me your features web boats
  4. Proceed to reguiin webbape
  5. Explore More

### Chatbot Section (Bottom)
- Large input field for queries
- Send button
- Icon buttons (microphone, heart, wave, sun)

## Media Files

The landing page expects the following media files (optional):

### Video
- **Path**: `assets/video/marina-sunset.mp4`
- **Format**: MP4
- **Recommended**: 1920x1080 or higher, optimized for web

### Image (Fallback)
- **Path**: `assets/images/marina-sunset.jpg`
- **Format**: JPG/PNG
- **Recommended**: 1920x1080 or higher

If these files don't exist, the page will use a beautiful gradient background.

## Installation

1. The landing page is automatically registered when the plugin is activated
2. Visit `/virtual-yachtbroker` on your WordPress site
3. If the URL doesn't work, go to WordPress Admin → Settings → Permalinks and click "Save Changes" to flush rewrite rules

## Customization

### Colors
Edit `assets/landing.css` to customize:
- Primary blue: `#1a3a5f`
- Accent gold: `#d4a574`
- Gradient colors in video overlay

### Button Text
Edit the button text in `includes/class-landing-page.php`:
```php
<button class="boat-action-btn" data-action="contact">
    <span>Contact</span>
</button>
```

### Video Source
Replace the video file at `assets/video/marina-sunset.mp4` with your own video.

## Integration with Chatbot

The landing page integrates seamlessly with the chatbot:
- Action buttons automatically open the chatbot with pre-filled messages
- Bottom input field sends messages to the chatbot
- Chatbot widget appears when user interacts

## Browser Support

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Performance

- Optimized CSS with minimal repaints
- Lazy loading for video
- Efficient animations using CSS transforms
- Responsive images

## Troubleshooting

### URL Not Working
1. Go to WordPress Admin → Settings → Permalinks
2. Click "Save Changes" to flush rewrite rules
3. Try accessing `/virtual-yachtbroker` again

### Video Not Showing
- Check that the video file exists at `assets/video/marina-sunset.mp4`
- Check file permissions
- The page will show a gradient background as fallback

### Styles Not Loading
- Clear browser cache
- Check that `assets/landing.css` is being enqueued
- Check browser console for errors

