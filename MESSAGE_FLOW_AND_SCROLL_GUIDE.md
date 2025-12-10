# Message Sending Flow, Scroll Behavior, and CSS Adjustments Guide

## 📨 Message Sending Flow

### 1. **User Input Methods**
```javascript
// Method 1: Press Enter key
messageInput.on('keypress', function(e) {
    if (e.which === 13 || e.keyCode === 13) {
        e.preventDefault();
        handleSendMessage();
    }
});

// Method 2: Click Send Button
sendBtn.on('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    handleSendMessage();
});
```

### 2. **Message Processing Flow**

```
User Input (Enter/Click)
    ↓
handleSendMessage()
    ↓
1. Capture message from input field
2. Clear input field
3. Add user message to conversation history
4. Create new conversation section (if needed)
5. Show typing indicator
6. Send message via streaming API
    ↓
sendMessageWithStreaming()
    ↓
1. Create "Thinking..." indicator
2. Fetch streaming response
3. Process SSE chunks in real-time
4. Update UI as content arrives
5. Handle completion
```

### 3. **Key Functions**

#### `handleSendMessage()` (Line ~350)
- Captures message from input
- Prevents duplicate sends
- Creates conversation section
- Adds user message to UI
- Initiates streaming request

#### `sendMessageWithStreaming()` (Line ~424)
- Handles Server-Sent Events (SSE)
- Updates UI in real-time
- Manages "thinking" indicator
- Processes streaming chunks

#### `addLandingChatMessage()` (Line ~802)
- Adds message to conversation
- Applies animations
- Handles scroll behavior

---

## 📜 Scroll Behavior

### Scroll Logic Overview

The scroll system has **two main scenarios**:

1. **New Conversation Section** (handled in `createNewConversationSection`)
2. **Adding Messages to Existing Conversation** (handled in `addLandingChatMessage`)

### 1. New Conversation Section Scroll

**Location:** `createNewConversationSection()` (Line ~204)

```javascript
// Key scroll logic:
1. Check if content overflows screen
   - documentHeight > viewportHeight

2. Check if conversation is visible
   - Account for header height (40px)
   - Check if conversation is fully visible

3. Only scroll if:
   - Content overflows AND
   - Conversation is not fully visible

4. Scroll to position:
   - targetScrollTop = conversationAbsoluteTop - headerHeight
   - Use smooth scrolling
   - Delay: 300ms (desktop) or 500ms (mobile)
```

**Scroll Delay:**
- Desktop: 300ms
- Mobile: 500ms (allows DOM to settle)

**Scroll Position:**
- Accounts for fixed header (40px)
- Positions conversation at top of viewport

### 2. Message Scroll (Existing Conversation)

**Location:** `addLandingChatMessage()` (Line ~802)

```javascript
// Key scroll logic:
1. Skip if scroll already handled
   - Check scroll-handled flag
   - Prevent duplicate scrolls

2. Only scroll if:
   - Content overflows screen
   - Message is not visible in viewport
   - Container has scrollable content

3. Scroll method:
   - container.scrollTo({ top: scrollHeight, behavior: 'smooth' })
```

### 3. Scroll Prevention Flags

```javascript
// Prevent scroll conflicts
conversationSection.data('scroll-handled', true);

// Check before scrolling
if (conversationSectionRef.data('scroll-handled')) {
    return; // Skip scroll
}
```

### 4. Conditional Scrolling

**Only scrolls if content overflows:**
```javascript
const documentHeight = document.documentElement.scrollHeight;
const viewportHeight = window.innerHeight;
const contentOverflows = documentHeight > viewportHeight;

if (contentOverflows) {
    // Perform scroll
}
```

---

## 🎨 CSS Adjustments Guide

### 1. **Container Heights**

#### Messages Container
```css
#conversation-history,
#boat-landing-messages-container {
    min-height: calc(100vh - 40px - 104px);
    /* Full screen minus header (40px) and footer (104px) */
    overflow-y: auto;
    padding: 20px 0;
}
```

#### Mobile Adjustments
```css
@media (max-width: 768px) {
    #conversation-history,
    #boat-landing-messages-container {
        min-height: calc(100vh - 40px - 90px);
        /* Header (40px) and footer (90px on mobile) */
        padding-top: 30px;
        padding-bottom: 180px;
    }
}
```

### 2. **Conversation Sections**

#### Base Styles
```css
.boat-conversation-section {
    display: flex;
    flex-direction: column;
    min-height: auto; /* Default: no forced height */
    padding: 0;
    margin: 0;
    border-bottom: 1px solid rgba(212, 165, 116, 0.2);
    will-change: opacity, transform; /* Animation optimization */
}
```

#### Last Conversation (Dynamic Height)
```css
/* JavaScript sets min-height dynamically only if content overflows */
.boat-conversation-section:last-child {
    border-bottom: none;
    /* min-height set via JavaScript when needed */
}
```

### 3. **Scroll Behavior**

#### Smooth Scrolling
```css
html {
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch; /* iOS smooth scroll */
}

body {
    scroll-behavior: smooth;
}
```

#### Scroll Margin (for fixed header)
```css
.boat-conversation-section:first-child {
    scroll-margin-top: 40px; /* Account for fixed header */
}
```

### 4. **Animation Styles**

#### Conversation Entrance
```css
.boat-conversation-section.boat-conversation-entering {
    animation: conversationFadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes conversationFadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
```

#### Message Entrance
```css
.boat-message-entering {
    /* Animation applied via inline styles */
    transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1),
                transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
```

### 5. **Height Adjustments**

#### Dynamic Min-Height Calculation
```javascript
// Only set min-height if content overflows
function updateLastConversationMinHeight() {
    const documentHeight = document.documentElement.scrollHeight;
    const viewportHeight = window.innerHeight;
    const contentOverflows = documentHeight > viewportHeight;
    
    if (contentOverflows) {
        // Calculate and set min-height
        lastConversation.css({
            'min-height': minHeight + 'px',
            'padding-bottom': chatbotSectionHeight + 'px'
        });
    } else {
        // Let content define height naturally
        lastConversation.css({
            'min-height': 'auto',
            'padding-bottom': chatbotSectionHeight + 'px'
        });
    }
}
```

### 6. **Mobile-Specific Adjustments**

```css
@media (max-width: 768px) {
    /* Chat container */
    #boat-landing-chat-container,
    #chat-container {
        min-height: 100vh;
        height: auto;
        padding-top: 40px; /* Space for fixed header */
    }
    
    /* Body/HTML adjustments */
    body.chat-active,
    html.chat-active {
        height: auto !important;
    }
    
    /* Main layout */
    .boat-main-layout.chat-active {
        height: auto !important;
        min-height: 100vh;
    }
}
```

### 7. **Common CSS Adjustments**

#### Adjust Header Height
If you change header height from 40px to another value:

```css
/* Update in multiple places: */
.boat-topbar {
    height: 60px; /* New height */
}

/* Update min-height calculations: */
#conversation-history {
    min-height: calc(100vh - 60px - 104px); /* New header height */
}

/* Update scroll margins: */
.boat-conversation-section:first-child {
    scroll-margin-top: 60px; /* New header height */
}
```

#### Adjust Footer Height
```css
/* Desktop footer: 104px */
/* Mobile footer: 90px */

/* Update min-height: */
#conversation-history {
    min-height: calc(100vh - 40px - 120px); /* New footer height */
}
```

#### Adjust Scroll Delays
```javascript
// In createNewConversationSection():
const scrollDelay = isMobile ? 500 : 300; // Adjust these values
```

#### Adjust Animation Duration
```css
/* Conversation fade-in: */
@keyframes conversationFadeIn {
    /* Change 0.4s to desired duration */
    animation: conversationFadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

/* Message slide-in: */
messageDiv.css({
    'transition': 'opacity 0.6s cubic-bezier(0.16, 1, 0.3, 1), transform 0.6s cubic-bezier(0.16, 1, 0.3, 1)'
});
```

---

## 🔧 Quick Reference: Key Values to Adjust

| Element | Current Value | Location |
|---------|--------------|----------|
| Header Height | 40px | CSS: `.boat-topbar` |
| Footer Height (Desktop) | 104px | CSS: `min-height` calc |
| Footer Height (Mobile) | 90px | CSS: `min-height` calc |
| Scroll Delay (Desktop) | 300ms | JS: `createNewConversationSection()` |
| Scroll Delay (Mobile) | 500ms | JS: `createNewConversationSection()` |
| Animation Duration | 0.4s | CSS: `@keyframes` |
| Stagger Delay (Max) | 200ms | JS: `addLandingChatMessage()` |

---

## 📝 Notes

1. **Scroll only happens if content overflows** - prevents unnecessary scrolling
2. **Scroll flags prevent conflicts** - ensures only one scroll happens
3. **Mobile has longer delays** - allows DOM to settle on slower devices
4. **Animations use requestAnimationFrame** - ensures smooth performance
5. **Min-height is dynamic** - only set when content overflows screen

---

## 🐛 Troubleshooting

### Scroll not working?
1. Check if content actually overflows: `documentHeight > viewportHeight`
2. Verify scroll-handled flag isn't blocking: `conversationSection.data('scroll-handled')`
3. Check scroll delays aren't too short
4. Verify container has `overflow-y: auto`

### Height issues?
1. Check min-height calculations account for header/footer
2. Verify `updateLastConversationMinHeight()` is called
3. Check mobile vs desktop height differences

### Animation issues?
1. Verify `requestAnimationFrame` is used
2. Check transition timing functions
3. Ensure will-change is set for performance

