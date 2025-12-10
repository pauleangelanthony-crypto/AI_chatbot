# Pagination Implementation Guide

## Overview

This guide explains how pagination is implemented in the Boat Chatbot plugin, covering both frontend (JavaScript) and backend (PHP) components.

## Architecture

```
User Query
    ↓
Frontend: Send message to /send-message
    ↓
Backend: Process query, determine pagination mode
    ↓
Backend: Return initial listings + pagination flags
    ↓
Frontend: Display listings + "More View" button (if enabled)
    ↓
User clicks "More View"
    ↓
Frontend: Send request to /load-listings with offset
    ↓
Backend: Return next page of listings
    ↓
Frontend: Append new listings + show button again (if more exist)
```

## Pagination Modes

### Mode 1: Specific Count Requested (No Pagination)
**Example:** "Show me 10 boats"

- Backend extracts count: `10`
- Returns exactly 10 items
- `enable_pagination = false`
- Frontend shows NO "More View" button

### Mode 2: No Count Specified (With Pagination)
**Example:** "Show me boats"

- Backend returns 5 items initially
- `enable_pagination = true`
- Frontend shows "More View" button if total > 5
- Each click loads next 5 items

## Frontend Implementation

### 1. Pagination State Management

**Location:** `assets/landing.js` (lines 142-153)

```javascript
let landingListingsState = {
    totalListings: 0,           // Total number of listings matching query
    loadedListings: [],         // Array of all currently loaded listings
    currentQuery: '',           // Original search query (used for pagination)
    isLoadingMore: false,       // Prevents multiple simultaneous requests
    initialItemsCount: 0,       // Number of items in first response
    itemsPerPage: 5,            // Items per page (backend always uses 5)
    enablePagination: true      // Whether pagination is enabled
};
```

### 2. Initial Response Handling

**Location:** `assets/landing.js` (lines 517-546)

```javascript
// When listings are received from send-message endpoint
if (response.data.listings && response.data.listings.length > 0) {
    // Store pagination state
    const totalListings = response.data.total_listings || response.data.listings.length;
    landingListingsState.totalListings = totalListings;
    landingListingsState.loadedListings = response.data.listings.slice();
    landingListingsState.currentQuery = message;
    landingListingsState.initialItemsCount = response.data.listings.length;
    
    // Check if pagination is enabled
    const enablePagination = response.data.enable_pagination !== undefined 
        ? response.data.enable_pagination 
        : true;
    
    // Determine if there are more listings
    const hasMore = enablePagination && (
        response.data.has_more !== undefined 
            ? response.data.has_more 
            : (totalListings > response.data.listings.length)
    );
    
    // Store pagination state
    landingListingsState.enablePagination = enablePagination;
    
    // Display listings with pagination info
    displayLandingListings(response.data.listings, false, totalListings, hasMore, enablePagination);
}
```

### 3. Display Listings Function

**Location:** `assets/landing.js` (lines 720-887)

```javascript
function displayLandingListings(listings, append = false, totalListings = 0, hasMore = false, enablePagination = true) {
    // ... build listings HTML ...
    
    // Add "More View" button if pagination is enabled and there are more listings
    if (!append && enablePagination && totalListings > 0 && totalListings > listings.length) {
        const remaining = totalListings - listings.length;
        listingsHtml += '<div class="boat-chatbot-load-more">';
        listingsHtml += '<button class="boat-chatbot-load-more-btn" data-offset="' + listings.length + '">';
        listingsHtml += 'More View (' + remaining + ' more)';
        listingsHtml += '</button>';
        listingsHtml += '</div>';
    }
    
    // ... append to container ...
    
    // Bind click event for "More View" button
    if (!append && enablePagination && totalListings > 0 && totalListings > listings.length) {
        $('.boat-chatbot-load-more-btn').off('click').on('click', function(e) {
            e.preventDefault();
            const offset = parseInt($(this).data('offset'));
            loadMoreLandingListings(offset);
        });
    }
}
```

### 4. Load More Function

**Location:** `assets/landing.js` (lines 889-1000)

```javascript
function loadMoreLandingListings(offset) {
    // Prevent multiple simultaneous requests
    if (landingListingsState.isLoadingMore) return;
    
    landingListingsState.isLoadingMore = true;
    const $loadMoreBtn = $('.boat-chatbot-load-more-btn');
    $loadMoreBtn.prop('disabled', true).text('Loading...');
    
    // Prepare request data
    const requestData = {
        query: landingListingsState.currentQuery,  // Same query as initial request
        offset: offset,                            // Current number of loaded items
        limit: landingListingsState.itemsPerPage,  // Backend ignores this, always uses 5
        nonce: window.boatChatbot.restNonce
    };
    
    // Send AJAX request
    $.ajax({
        url: window.boatChatbot.restUrl + 'load-listings',
        type: 'POST',
        contentType: 'application/json',  // Important: JSON format
        beforeSend: function(xhr) {
            if (window.boatChatbot.restNonce) {
                xhr.setRequestHeader('X-WP-Nonce', window.boatChatbot.restNonce);
            }
        },
        data: JSON.stringify(requestData),
        success: function(response) {
            landingListingsState.isLoadingMore = false;
            $loadMoreBtn.remove();
            
            if (response.success && response.data && response.data.listings) {
                const newListings = response.data.listings;
                
                // Add new listings to loaded listings array
                landingListingsState.loadedListings = landingListingsState.loadedListings.concat(newListings);
                
                // Update total from response
                landingListingsState.totalListings = response.data.total || landingListingsState.loadedListings.length;
                
                // Display new listings (append mode)
                displayLandingListings(newListings, true, landingListingsState.totalListings, false, landingListingsState.enablePagination);
                
                // If there are more listings, add the "More View" button again
                if (landingListingsState.enablePagination && response.data.total > landingListingsState.loadedListings.length) {
                    const remaining = landingListingsState.totalListings - landingListingsState.loadedListings.length;
                    // ... create and append button ...
                }
            }
        },
        error: function(xhr, status, error) {
            // Handle errors
            landingListingsState.isLoadingMore = false;
            $loadMoreBtn.prop('disabled', false).text('More View');
        }
    });
}
```

## Backend Implementation

### 1. Initial Query Handler

**Location:** `includes/class-chatbot-handler.php` (lines 1428-1580)

```php
private function handle_database_query_optimized($user_message, &$performance_log, $conversation_history = array(), $offset = 0) {
    $db_manager = new Boat_Chatbot_Database_Manager();
    
    // Extract requested item count from user message
    $requested_count = $db_manager->extract_item_count($user_message);
    
    // Sanitize offset
    $offset = max(0, intval($offset));
    
    // Get total count first to determine pagination behavior
    $total_listings = $db_manager->get_total_count($user_message);
    
    // Determine query limit and pagination behavior:
    // - If specific count requested: show only that many items (no pagination)
    // - If no count specified: show 5 items per page (with pagination)
    if ($requested_count !== null) {
        // Specific count requested: show only that many items, no pagination
        $query_limit = min($requested_count, $total_listings);
        $enable_pagination = false;
    } else {
        // No count specified: show 5 items per page with pagination
        $query_limit = 5;
        $enable_pagination = true;
    }
    
    // Query database
    $initial_listings = $db_manager->query_listings($user_message, $query_limit, $offset);
    
    // Process results (deduplication, relevance scoring, sorting)
    $processed_listings = $this->process_results_for_ai($initial_listings, $user_message);
    
    // Re-query total count after processing to ensure accuracy
    $actual_total = $db_manager->get_total_count($user_message);
    if ($actual_total > $total_listings) {
        $total_listings = $actual_total;
    }
    
    // Determine if there are more listings
    if ($enable_pagination) {
        $has_more = $total_listings > count($processed_listings);
    } else {
        $has_more = false; // No pagination for specific count requests
    }
    
    return array(
        'response' => $ai_response,
        'listings' => $processed_listings,
        'total_listings' => $total_listings,
        'has_more' => $has_more,
        'enable_pagination' => $enable_pagination,
        'requested_count' => $requested_count
    );
}
```

**Key Features:**
- Automatically detects if user requested specific count (e.g., "show me 10 boats")
- Returns 5 items per page by default when no count specified
- Handles "show all" queries correctly (returns all listings when no filters)
- Re-queries total count after processing to ensure accuracy

### 2. Load Listings Endpoint

**Location:** `includes/class-chatbot-handler.php` (lines 573-621)

```php
public function handle_load_listings($request) {
    // Handle both JSON and form data requests
    $json_params = $request->get_json_params();
    if (!empty($json_params) && is_array($json_params)) {
        // JSON request (from frontend with contentType: 'application/json')
        $query = isset($json_params['query']) ? sanitize_text_field($json_params['query']) : '';
        $offset = isset($json_params['offset']) ? max(0, intval($json_params['offset'])) : 0;
    } else {
        // Form data request (fallback)
        $query = $request->get_param('query');
        $offset = $request->get_param('offset');
        if (empty($offset)) {
            $offset = 0;
        } else {
            $offset = max(0, intval($offset));
        }
    }
    
    // Validate query
    if (empty($query)) {
        error_log('[Boat Chatbot] load-listings: Missing query parameter');
        return new WP_Error('missing_query', 'Query parameter is required', array('status' => 400));
    }
    
    // Always use 5 items per page for "Load More" button, regardless of what's requested
    $limit = 5;
    
    $db_manager = new Boat_Chatbot_Database_Manager();
    $listings = $db_manager->query_listings($query, $limit, $offset);
    $total = $db_manager->get_total_count($query);
    
    // Log for debugging
    error_log('[Boat Chatbot] load-listings: Query="' . $query . '", Offset=' . $offset . ', Limit=' . $limit . ', Found=' . count($listings) . ', Total=' . $total);
    
    return rest_ensure_response(array(
        'success' => true,
        'data' => array(
            'listings' => $listings,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit
        )
    ));
}
```

**Key Features:**
- Handles both JSON and form data requests
- Always returns 5 items per page (ignores `limit` parameter)
- Uses same query string for consistency
- Includes debug logging for troubleshooting

### 3. REST API Route Registration

**Location:** `includes/class-chatbot-handler.php` (lines 106-131)

```php
register_rest_route('boat-chatbot/v1', '/load-listings', array(
    'methods' => 'POST',
    'callback' => array($this, 'handle_load_listings'),
    'permission_callback' => array($this, 'check_rest_nonce'),
    'args' => array(
        'query' => array(
            'required' => true,
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ),
        'offset' => array(
            'required' => false,
            'type' => 'integer',
            'default' => 0,
        ),
        'limit' => array(
            'required' => false,
            'type' => 'integer',
            'default' => 20,
        ),
    ),
));
```

## Database Query Implementation

**Location:** `includes/class-database-manager.php`

```php
public function query_listings($user_message, $limit = 10, $offset = 0) {
    // Sanitize limit (1-100) and offset (≥0)
    $limit = max(1, min(100, intval($limit)));
    $offset = max(0, intval($offset));
    
    // Build WHERE clause from search terms
    $search_terms = $this->extract_search_terms($user_message);
    $where_data = $this->build_where_clause_prepared($search_terms);
    
    // Build SQL query
    $sql = "SELECT {fields} FROM `{table_name}`";
    if (!empty($where_data['conditions'])) {
        $sql .= " WHERE " . $where_data['conditions'];
    }
    $sql .= " ORDER BY `ID` DESC LIMIT ? OFFSET ?";
    
    // Execute with prepared statement
    // ... bind parameters and execute ...
    
    return $listings;
}

public function get_total_count($user_message) {
    // Similar to query_listings but uses COUNT(*)
    $sql = "SELECT COUNT(*) as total FROM `{table_name}`";
    // ... apply same WHERE conditions ...
    return $total;
}
```

## Key Concepts

### 1. Offset Calculation
- **Initial load:** `offset = 0`
- **First "More View" click:** `offset = 5` (number of items already loaded)
- **Second click:** `offset = 10` (5 + 5)
- **Third click:** `offset = 15` (10 + 5)
- **General formula:** `offset = loadedListings.length`

### 2. "Show All" Query Handling
When a user sends "show me all boats" (or similar):
- **Intent Detection:** Classified as `database_query` (not `hybrid`)
  - Simple listing requests without semantic descriptors use database query
  - Queries with descriptors (e.g., "luxury", "comfortable") may use hybrid search
  
- **Query Processing:**
  - Detects "all" keyword → sets `show_all = true`
  - If no specific filters (type, location, price, etc.) → returns empty WHERE clause
  - Returns **all listings** from database (not filtered)
  
- **Total Count:**
  - Correctly reflects actual database total
  - Example: If database has 150 boats, `total_listings = 150` (not 5)

### 3. Query Consistency
- **Critical:** Always use the same query string for both initial request and pagination
- Stored in `landingListingsState.currentQuery`
- Used in all `/load-listings` requests
- Must match exactly (case-sensitive) for consistent results

### 4. JSON vs Form Data
- **Frontend sends:** JSON (`contentType: 'application/json'`)
- **Backend reads:** `get_json_params()` for JSON, `get_param()` for form data
- **Important:** Must handle both formats for compatibility

### 5. Pagination State
- **Frontend tracks:**
  - Total listings count
  - Currently loaded listings
  - Whether pagination is enabled
  - Current query string
  
- **Backend provides:**
  - Total count
  - Current page listings
  - Pagination enable flag

## Flow Example

### Scenario 1: User searches "show me boats" (50 total results)

1. **Initial Request:**
   ```
   POST /wp-json/boat-chatbot/v1/send-message
   {
     "message": "show me boats"
   }
   ```

2. **Backend Processing:**
   - Intent classification: `database_query` (simple listing request)
   - Extracts search terms: no specific count requested
   - Queries database: `query_listings("show me boats", 5, 0)`
   - Gets total count: `get_total_count("show me boats")` → 50

3. **Backend Response:**
   ```json
   {
     "success": true,
     "data": {
       "listings": [...5 items...],
       "total_listings": 50,
       "has_more": true,
       "enable_pagination": true,
       "requested_count": null
     }
   }
   ```

4. **Frontend:**
   - Displays 5 items
   - Shows "More View (45 more)" button
   - Stores: `currentQuery = "show me boats"`, `loadedListings = [5 items]`

5. **User Clicks "More View":**
   ```
   POST /wp-json/boat-chatbot/v1/load-listings
   {
     "query": "show me boats",
     "offset": 5,
     "limit": 5
   }
   ```

6. **Backend Response:**
   ```json
   {
     "success": true,
     "data": {
       "listings": [...5 items...],
       "total": 50,
       "offset": 5,
       "limit": 5
     }
   }
   ```

7. **Frontend:**
   - Appends 5 new items (now 10 total)
   - Shows "More View (40 more)" button
   - Updates: `loadedListings = [10 items]`

8. **Process repeats** until all 50 items are loaded

### Scenario 2: User searches "show me all boats" (150 total results)

1. **Initial Request:**
   ```
   POST /wp-json/boat-chatbot/v1/send-message
   {
     "message": "show me all boats"
   }
   ```

2. **Backend Processing:**
   - Intent classification: `database_query` (detected as simple listing, not hybrid)
   - Extracts search terms: `show_all = true`, no specific filters
   - Builds WHERE clause: **empty** (returns all listings)
   - Queries database: `query_listings("show me all boats", 5, 0)`
   - Gets total count: `get_total_count("show me all boats")` → 150 (actual total)

3. **Backend Response:**
   ```json
   {
     "success": true,
     "data": {
       "listings": [...5 items...],
       "total_listings": 150,
       "has_more": true,
       "enable_pagination": true
     }
   }
   ```

4. **Frontend:**
   - Displays 5 items
   - Shows "More View (145 more)" button
   - Total count correctly shows 150 (not just 5)

### Scenario 3: User searches "show me 10 boats" (specific count)

1. **Initial Request:**
   ```
   POST /wp-json/boat-chatbot/v1/send-message
   {
     "message": "show me 10 boats"
   }
   ```

2. **Backend Processing:**
   - Extracts count: `requested_count = 10`
   - Queries database: `query_listings("show me 10 boats", 10, 0)`
   - Gets total count: `get_total_count("show me 10 boats")`

3. **Backend Response:**
   ```json
   {
     "success": true,
     "data": {
       "listings": [...10 items...],
       "total_listings": 50,
       "has_more": false,
       "enable_pagination": false,
       "requested_count": 10
     }
   }
   ```

4. **Frontend:**
   - Displays 10 items
   - **NO "More View" button** (pagination disabled)

## Testing Checklist

- [ ] Initial query without count shows pagination button
- [ ] Initial query with specific count shows NO pagination button
- [ ] "More View" button loads next 5 items
- [ ] Button disappears when all items are loaded
- [ ] Button shows correct remaining count
- [ ] Multiple rapid clicks don't cause duplicate requests
- [ ] Query string is preserved across pagination requests
- [ ] Error handling works correctly
- [ ] "Show me all boats" returns correct total count (not just 5)
- [ ] "Show me all boats" is classified as database_query (not hybrid)
- [ ] Total count matches actual database total for "show all" queries

## Troubleshooting

### Issue: Button not appearing
- Check `enable_pagination` flag in response
- Verify `total_listings > listings.length`
- Check browser console for errors

### Issue: Wrong items loaded
- Verify `offset` calculation: should be `loadedListings.length`
- Check that `currentQuery` matches original query exactly
- Verify backend is using same query for `query_listings()` and `get_total_count()`

### Issue: "Show all boats" showing wrong total count
- Check that query is classified as `database_query` (not `hybrid`)
- Verify `show_all` flag is set in search terms
- Check that WHERE clause is empty when no filters present
- Ensure `get_total_count()` uses same query string as `query_listings()`

### Issue: Duplicate listings
- Ensure `offset` is calculated correctly
- Check that new listings are appended, not replaced
- Verify database query uses `LIMIT` and `OFFSET` correctly

### Issue: Backend not receiving data
- Check `contentType: 'application/json'` in frontend
- Verify backend uses `get_json_params()` for JSON requests
- Check WordPress REST API nonce is valid

## Best Practices

1. **Always validate offset** - Ensure it's non-negative integer
2. **Use same query** - Pagination must use identical query string
3. **Handle errors gracefully** - Show user-friendly error messages
4. **Prevent duplicate requests** - Use `isLoadingMore` flag
5. **Update state atomically** - Update all state variables together
6. **Log for debugging** - Include console logs during development

