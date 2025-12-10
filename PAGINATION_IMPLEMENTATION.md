# Backend Pagination Implementation Guide

## Overview

The backend pagination is implemented using **LIMIT** and **OFFSET** SQL clauses, with a REST API endpoint to load additional listings.

## Architecture

### 1. REST API Endpoint

**Endpoint:** `POST /wp-json/boat-chatbot/v1/load-listings`

**Location:** `includes/class-chatbot-handler.php` (lines 71-96, 533-555)

**Parameters:**
- `query` (string, required): The original search query (must match initial query exactly)
- `offset` (integer, optional, default: 0): Number of records to skip
- `limit` (integer, optional, ignored): Backend always uses 5 items per page
- `nonce` (string, optional): Security nonce

**Example Request:**
```json
{
  "query": "Show me all boats",
  "offset": 5,
  "limit": 5,
  "nonce": "abc123..."
}
```

**Response Structure:**
```json
{
  "success": true,
  "data": {
    "listings": [...],  // Array of listing objects (5 items)
    "total": 150,        // Total number of listings matching query
    "offset": 5,         // Current offset
    "limit": 5           // Always 5 items per page
  }
}
```

**Important Notes:**
- Backend **always uses 5 items per page** regardless of `limit` parameter
- `offset` should be the number of items already loaded (e.g., 5, 10, 15, etc.)
- `query` must match the original search query exactly for consistent results

### 2. Database Query Implementation

**Location:** `includes/class-database-manager.php` (line 200)

**Function:** `query_listings($user_message, $limit = 10, $offset = 0)`

**Key Features:**
- Uses prepared statements for security
- Sanitizes limit (1-100) and offset (≥0)
- Applies WHERE clause based on search terms
- Orders by ID DESC
- Uses SQL `LIMIT` and `OFFSET` clauses

**SQL Query Structure:**
```sql
SELECT {fields} 
FROM `{table_name}` 
WHERE {conditions}
ORDER BY `ID` DESC
LIMIT ? OFFSET ?
```

**Code Example:**
```php
// Add LIMIT and OFFSET (already sanitized as integers)
$sql .= ' LIMIT ? OFFSET ?';
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';  // integer, integer

$stmt = $this->db_connection->prepare($sql);
// Bind parameters and execute...
```

### 3. Total Count Function

**Location:** `includes/class-database-manager.php` (line 367)

**Function:** `get_total_count($user_message)`

**Purpose:** Returns the total number of listings matching the query (without pagination)

**SQL Query:**
```sql
SELECT COUNT(*) as total 
FROM `{table_name}` 
WHERE {conditions}
```

**Usage:**
```php
$db_manager = new Boat_Chatbot_Database_Manager();
$total = $db_manager->get_total_count($query);
```

### 4. Initial Response (First Load)

**Location:** `includes/class-chatbot-handler.php` (line 1428-1580)

When a user sends a message, the initial response includes:
- First batch of listings (default: 5 items per page)
- Total count of all matching listings
- `has_more` flag indicating if more listings exist
- `enable_pagination` flag indicating if pagination is enabled

**Pagination Behavior:**
- **If specific count requested** (e.g., "show me 10 boats"): Returns exactly that many items, no pagination
- **If no count specified** (e.g., "show me boats"): Returns 5 items per page with pagination enabled

**Code:**
```php
// Extract requested item count from user message
$requested_count = $db_manager->extract_item_count($user_message);

// Get total count first
$total_listings = $db_manager->get_total_count($user_message);

// Determine pagination behavior
if ($requested_count !== null) {
    // Specific count requested: show only that many items, no pagination
    $query_limit = min($requested_count, $total_listings);
    $enable_pagination = false;
} else {
    // No count specified: show 5 items per page with pagination
    $query_limit = 5;
    $enable_pagination = true;
}

// Get first batch
$initial_listings = $db_manager->query_listings($user_message, $query_limit, 0);

// Process and return
return array(
    'response' => $ai_response,
    'listings' => $processed_listings,
    'total_listings' => $total_listings,
    'has_more' => $enable_pagination && ($total_listings > count($processed_listings)),
    'enable_pagination' => $enable_pagination,
    'requested_count' => $requested_count
);
```

## Implementation Flow

### Step 1: Initial Query
```
User sends: "Show me all boats"
↓
Backend classifies as: database_query (not hybrid - simple listing request)
↓
Backend queries: query_listings("Show me all boats", 5, 0)
Backend gets total: get_total_count("Show me all boats") → e.g., 150
↓
Returns: First 5 listings + total count (150) + enable_pagination: true
↓
Frontend displays: 5 listings + "More View (145 more)" button
```

**Special Case: "Show me all boats"**
- Detected as simple listing request (no semantic descriptors)
- Classified as `database_query` (not `hybrid`)
- Returns all listings when no filters are present (empty WHERE clause)
- Total count reflects actual database total

### Step 2: Load More (Pagination)
```
User clicks "More View" button
↓
Frontend calls: POST /load-listings
  { query: "Show me all boats", offset: 5, limit: 5 }
↓
Backend queries: query_listings("Show me all boats", 5, 5)
↓
Returns: Next 5 listings (6-10) + total count (150)
↓
Frontend appends: New listings to existing ones (now 10 total)
↓
Frontend shows: "More View (140 more)" button
```

### Step 3: Continue Loading
```
User clicks "More View" again
↓
Frontend calls: POST /load-listings
  { query: "Show me all boats", offset: 10, limit: 5 }
↓
Backend queries: query_listings("Show me all boats", 5, 10)
↓
Returns: Next 5 listings (11-15) + total count (150)
↓
Process continues until all 150 listings are loaded
↓
When offset >= total, button disappears
```

## Key Implementation Details

### 1. Security
- ✅ Uses prepared statements (prevents SQL injection)
- ✅ Sanitizes all inputs
- ✅ Validates limit (1-100) and offset (≥0)
- ✅ Uses WordPress nonce verification

### 2. Performance
- ✅ Caching for query results
- ✅ Caching for total count
- ✅ Efficient SQL with proper indexing
- ✅ Limits maximum results per request (100)

### 3. Error Handling
```php
// In handle_load_listings()
try {
    $listings = $db_manager->query_listings($query, $limit, $offset);
    $total = $db_manager->get_total_count($query);
    
    return rest_ensure_response(array(
        'success' => true,
        'data' => array(
            'listings' => $listings,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit
        )
    ));
} catch (Exception $e) {
    return new WP_Error('query_failed', 'Failed to load listings', array('status' => 500));
}
```

## Customization Options

### Change Items Per Page
**Location:** `includes/class-chatbot-handler.php` (line 603 and 1457)

To change from 5 items per page to a different number:

1. **In `handle_load_listings()` method (line 603):**
```php
// Change from 5 to desired number
$limit = 10;  // 10 items per page
```

2. **In `handle_database_query_optimized()` method (line 1457):**
```php
// Change from 5 to desired number
$query_limit = ($offset > 0) ? 10 : 10;  // 10 items per page
```

### Change Maximum Limit (Database Query)
**Location:** `includes/class-database-manager.php` (line 207)

```php
// Change from 100 to desired maximum
$limit = max(1, min(200, intval($limit)));  // Max 200 instead of 100
```

**Note:** This only affects the maximum allowed limit. The default pagination still uses 5 items per page unless changed above.

## Best Practices

1. **Always get total count** - Required for pagination UI
2. **Use consistent limit** - Same limit for initial and paginated requests
3. **Cache total count** - Avoid recalculating on every request
4. **Validate inputs** - Ensure limit and offset are within acceptable ranges
5. **Handle empty results** - Return empty array, not error
6. **Maintain query consistency** - Use same query string for all pagination requests

## Testing Pagination

### Test Case 1: Basic Pagination
```php
// Request 1: Get first 20
$listings1 = $db_manager->query_listings("boats", 20, 0);
$total = $db_manager->get_total_count("boats");

// Request 2: Get next 20
$listings2 = $db_manager->query_listings("boats", 20, 20);

// Verify: count($listings1) + count($listings2) <= $total
```

### Test Case 2: Edge Cases
```php
// Test with offset beyond total
$listings = $db_manager->query_listings("boats", 20, 9999);
// Should return empty array, not error

// Test with limit 0
$listings = $db_manager->query_listings("boats", 0, 0);
// Should return empty array (limit is sanitized to min 1)
```

## Troubleshooting

### Issue: Only showing first batch
**Solution:** Check that `has_more` flag is correctly calculated:
```php
'has_more' => $total_listings > count($processed_listings)
```

### Issue: Duplicate listings
**Solution:** Ensure offset is calculated correctly:
```php
$offset = $this->loadedListings.length; // In frontend
```

### Issue: Wrong total count
**Solution:** Verify query string matches exactly:
```php
// Use same query string for both query_listings and get_total_count
$query = "Show me all boats";
$listings = $db_manager->query_listings($query, 20, 0);
$total = $db_manager->get_total_count($query);
```

## Special Cases

### "Show Me All Boats" Query

When a user sends "show me all boats" (or similar queries):
1. **Intent Classification:** Detected as `database_query` (not `hybrid`)
   - Simple listing requests without semantic descriptors use database query only
   - Queries with descriptors (e.g., "show me all luxury boats") may use hybrid search

2. **Query Processing:** 
   - Detects "all" keyword and sets `show_all = true`
   - If no specific filters present, returns empty WHERE clause (all listings)
   - Total count reflects actual database total

3. **Pagination:**
   - Returns 5 items initially
   - `enable_pagination = true`
   - "More View" button appears if total > 5

**Example:**
```
Query: "Show me all boats"
→ Intent: database_query
→ WHERE clause: (empty - returns all listings)
→ Total: 150 listings
→ Initial: 5 listings
→ Pagination: Enabled (145 more available)
```

## Summary

Backend pagination is implemented using:
1. **REST API endpoint** (`/load-listings`) for loading additional listings
2. **SQL LIMIT/OFFSET** for database queries
3. **Total count function** for pagination UI
4. **Prepared statements** for security
5. **Caching** for performance
6. **Smart intent classification** to use database_query for simple listing requests

The system supports:
- ✅ Initial batch loading (5 items per page)
- ✅ Load more on demand ("More View" button)
- ✅ Total count display
- ✅ Automatic pagination enable/disable based on query type
- ✅ Special handling for "show all" queries
- ✅ Consistent query string across pagination requests

