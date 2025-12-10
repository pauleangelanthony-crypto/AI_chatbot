# Boat Chatbot AI - Comprehensive Documentation

## Table of Contents
1. [Project Description](#project-description)
2. [Admin Settings Page Features](#admin-settings-page-features)
3. [Workflows and API Endpoints](#workflows-and-api-endpoints)
4. [Detailed Workflow Implementation](#detailed-workflow-implementation)
5. [Future Planning](#future-planning)
6. [Scraper Documentation](#scraper-documentation)
7. [Recent Updates & Enhancements](#7-recent-updates--enhancements)

---

## 1. Project Description

### Overview
**Boat Chatbot AI** is a WordPress plugin that provides an AI-powered chatbot for boat listings and general boating knowledge. The plugin integrates with external databases, vector search capabilities, and AI services to deliver intelligent responses about boat listings, specifications, and boating information.

### Key Features
- **AI-Powered Chat Interface**: Uses Grok AI (X.AI) for natural language processing and responses
- **Hybrid Search System**: Combines semantic vector search (Pinecone) with keyword-based SQL queries
- **Vector Database Integration**: Syncs boat listings to Pinecone for semantic search
- **Landing Page**: Custom landing page at `/virtual-yachtbroker` with video background and interactive chatbot
- **Full Chatbot Page**: Dedicated full-page chatbot interface at `/boat-chatbot`
- **Widget Mode**: Embeddable chatbot widget for any WordPress page
- **Caching System**: Redis and WordPress transients for performance optimization
- **Admin Dashboard**: Comprehensive settings and monitoring interface
- **Logging System**: Detailed conversation logs with performance metrics
- **External Sync API**: REST API endpoints for Python scraper integration

### Technology Stack
- **Backend**: PHP (WordPress Plugin)
- **Frontend**: JavaScript (jQuery), CSS3
- **AI Service**: Grok AI (X.AI) - `grok-4-fast-reasoning` model
- **Embeddings**: Groq API - `nomic-embed-text-v1.5` model
- **Vector Database**: Pinecone
- **Cache**: Redis (optional) / WordPress Transients
- **Database**: External MySQL database for boat listings

### Architecture
```
WordPress Plugin
├── Frontend (JavaScript/CSS)
│   ├── Landing Page (/virtual-yachtbroker)
│   ├── Full Chatbot Page (/boat-chatbot)
│   └── Widget Mode (Shortcode)
├── Backend (PHP)
│   ├── Chatbot Handler (REST API + AJAX)
│   ├── Database Manager (SQL queries)
│   ├── Vector Sync Manager (Pinecone sync)
│   ├── Admin Settings
│   └── Landing Page Handler
└── External Services
    ├── Grok AI (Chat completions)
    ├── Groq API (Embeddings)
    └── Pinecone (Vector search)
```

---

## 2. Admin Settings Page Features

### Access
- **Menu Location**: WordPress Admin → `Boat Chatbot`
- **Main Page**: `Boat Chatbot → Settings`
- **Submenus**: 
  - Settings
  - Chat Logs
  - Landing Page Info
  - View Landing Page (external link)

### Settings Sections

#### 2.1 API Configuration
**Location**: `includes/class-admin-settings.php` (lines 347-366)

**Settings**:
- **Grok API Key**: API key for X.AI Grok service
- **Grok API URL**: Endpoint URL (default: `https://api.x.ai/v1/chat/completions`)

**Code Reference**:
```php
register_setting('boat_chatbot_settings', 'boat_chatbot_grok_api_key');
register_setting('boat_chatbot_settings', 'boat_chatbot_grok_api_url');
```

#### 2.2 Tone of Voice
**Location**: `includes/class-admin-settings.php` (lines 368-380)

**Settings**:
- **Chatbot Persona**: Textarea for defining chatbot personality and communication style
- Default: "You are a friendly, expert sailing enthusiast. Use casual language and nautical terms where appropriate..."

**Code Reference**:
```php
register_setting('boat_chatbot_settings', 'boat_chatbot_tone_of_voice');
```

#### 2.3 Website Restrictions
**Location**: `includes/class-admin-settings.php` (lines 382-393)

**Settings**:
- **Blocked Websites**: List of websites that Groq will not be allowed to check (competitor websites)
- Format: One website per line or comma-separated

**Code Reference**:
```php
register_setting('boat_chatbot_settings', 'boat_chatbot_blocked_websites', array(
    'sanitize_callback' => array($this, 'sanitize_blocked_websites')
));
```

#### 2.4 AI Prompt Configuration
**Location**: `includes/class-admin-settings.php` (lines 395-423)

**Settings**:
- **Listing Format Template**: Format template for displaying listings to AI
  - Placeholders: `{title}`, `{type}`, `{length}`, `{price}`, `{location}`, `{description}`, `{url}`, `{manufacturer}`, `{model}`, `{year}`
  - Default: `- {title} | {type} | {length}' | ${price} | {location}`
- **Token Limit for Listings**: Maximum tokens for listing data in AI prompt (default: 450)

**Code Reference**:
```php
register_setting('boat_chatbot_settings', 'boat_chatbot_listing_format', array(
    'sanitize_callback' => array($this, 'sanitize_listing_format')
));
register_setting('boat_chatbot_settings', 'boat_chatbot_token_limit', array(
    'sanitize_callback' => 'absint'
));
```

#### 2.5 Database Configuration
**Location**: `includes/class-admin-settings.php` (lines 425-477)

**Settings**:
- **Database Host**: MySQL host (default: `localhost`)
- **Database Name**: Database name
- **Database User**: MySQL username
- **Database Password**: MySQL password
- **Listings Table Name**: Table name (default: `listings`)
- **Allowed Fields**: Comma-separated list of database fields the AI can access

**Code Reference**:
```php
register_setting('boat_chatbot_settings', 'boat_chatbot_db_host');
register_setting('boat_chatbot_settings', 'boat_chatbot_db_name');
register_setting('boat_chatbot_settings', 'boat_chatbot_db_user');
register_setting('boat_chatbot_settings', 'boat_chatbot_db_password');
register_setting('boat_chatbot_settings', 'boat_chatbot_db_table');
register_setting('boat_chatbot_settings', 'boat_chatbot_allowed_fields');
```

#### 2.6 Groq Embeddings Configuration
**Location**: `includes/class-admin-settings.php` (lines 479-531)

**Settings**:
- **Groq API Key**: API key for Groq embeddings service
- **Embeddings API URL**: Endpoint URL (default: `https://api.groq.com/openai/v1/embeddings`)
- **Embedding Model**: Model name (default: `nomic-embed-text-v1.5`)
- **Embedding Dimensions**: Number of dimensions (default: 768, must match Pinecone index)

**Code Reference**:
```php
register_setting('boat_chatbot_settings', 'boat_chatbot_groq_api_key');
register_setting('boat_chatbot_settings', 'boat_chatbot_groq_embeddings_url');
register_setting('boat_chatbot_settings', 'boat_chatbot_groq_embedding_model');
register_setting('boat_chatbot_settings', 'boat_chatbot_groq_embedding_dimensions', array(
    'sanitize_callback' => 'absint'
));
```

#### 2.7 Pinecone Vector Database Configuration
**Location**: `includes/class-admin-settings.php` (lines 533-590)

**Settings**:
- **Pinecone API Key**: Pinecone API key
- **Pinecone Database URL**: Full URL (e.g., `https://your-index.svc.us-east1-aws.pinecone.io`)
- **Environment**: Pinecone environment/region (default: `us-east1-aws`)
- **Production Index Name**: Production index name (default: `boat-chatbot-prod`)
- **Staging Index Name**: Staging index name (default: `boat-chatbot-staging`)
- **Current Environment**: Dropdown to select `prod` or `staging`

**Code Reference**:
```php
register_setting('boat_chatbot_settings', 'boat_chatbot_pinecone_api_key');
register_setting('boat_chatbot_settings', 'boat_chatbot_pinecone_url');
register_setting('boat_chatbot_settings', 'boat_chatbot_pinecone_environment');
register_setting('boat_chatbot_settings', 'boat_chatbot_pinecone_prod_index');
register_setting('boat_chatbot_settings', 'boat_chatbot_pinecone_staging_index');
register_setting('boat_chatbot_settings', 'boat_chatbot_pinecone_current_env');
```

#### 2.8 Parallel Search Configuration
**Location**: `includes/class-admin-settings.php` (lines 592-634)

**Settings**:
- **Enable Parallel Search**: Checkbox to enable parallel hybrid search
- **Search Mode**: Dropdown (`hybrid`, `semantic`, `keyword`)
- **Vector Search Weight (α)**: Weight for semantic search (0.0 to 1.0, default: 0.5)
- **Keyword Search Weight (β)**: Weight for keyword search (0.0 to 1.0, default: 0.5)
- Formula: `final_score = α × vector_score + β × keyword_score`

**Code Reference**:
```php
register_setting('boat_chatbot_settings', 'boat_chatbot_use_parallel_search', array(
    'sanitize_callback' => function($value) { return $value === '1' || $value === true; }
));
register_setting('boat_chatbot_settings', 'boat_chatbot_search_mode', array(
    'sanitize_callback' => function($value) {
        $allowed = array('semantic', 'keyword', 'hybrid');
        return in_array($value, $allowed) ? $value : 'hybrid';
    }
));
register_setting('boat_chatbot_settings', 'boat_chatbot_vector_weight', array(
    'sanitize_callback' => function($value) {
        $float = floatval($value);
        return max(0.0, min(1.0, $float));
    }
));
register_setting('boat_chatbot_settings', 'boat_chatbot_keyword_weight', array(
    'sanitize_callback' => function($value) {
        $float = floatval($value);
        return max(0.0, min(1.0, $float));
    }
));
```

#### 2.9 UI Settings
**Location**: `includes/class-admin-settings.php` (lines 785-840)

**Settings**:
- **Chatbot Input Placeholder**: Placeholder text shown in the chatbot input field (default: `Type your query here...`)
- **Help Modal Description**: HTML content displayed in the help modal when users click the help button
  - Editable via textarea in admin settings
  - Supports HTML tags: `<div>`, `<h3>`, `<ul>`, `<li>`, `<p>`, `<strong>`
  - Use class `boat-help-section` for each section div
  - Default content includes usage instructions, example queries, and tips

**Code Reference**:
```php
register_setting('boat_chatbot_settings', 'boat_chatbot_input_placeholder');
register_setting('boat_chatbot_settings', 'boat_chatbot_help_description', array(
    'sanitize_callback' => array($this, 'sanitize_help_description')
));
```

**Help Modal Features**:
- Opens when user clicks the help button
- Closes via close button, overlay click, or Escape key
- Prevents body scroll when open
- Fully responsive design
- Content is customizable from admin settings

#### 2.10 Redis Cache Configuration
**Location**: `includes/class-admin-settings.php` (lines 636-685)

**Settings**:
- **Enable Redis Cache**: Checkbox to enable Redis caching
- **Redis Host**: Hostname or IP (default: `localhost`)
- **Redis Port**: Port number (default: `6379`)
- **Redis Password**: Password (optional)
- **Redis Database**: Database number 0-15 (default: `0`)

**Code Reference**:
```php
register_setting('boat_chatbot_settings', 'boat_chatbot_redis_enabled', array(
    'sanitize_callback' => function($value) { return $value === '1' || $value === true; }
));
register_setting('boat_chatbot_settings', 'boat_chatbot_redis_host');
register_setting('boat_chatbot_settings', 'boat_chatbot_redis_port', array(
    'sanitize_callback' => 'absint'
));
register_setting('boat_chatbot_settings', 'boat_chatbot_redis_password');
register_setting('boat_chatbot_settings', 'boat_chatbot_redis_database', array(
    'sanitize_callback' => 'absint'
));
```

#### 2.11 Sync Configuration
**Location**: `includes/class-admin-settings.php` (lines 687-699)

**Settings**:
- **Sync API Key**: API key for external sync requests (Python scraper)
- **Sync Endpoint**: `<?php echo esc_url(rest_url('boat-chatbot/v1/sync-records')); ?>`

**Code Reference**:
```php
register_setting('boat_chatbot_settings', 'boat_chatbot_sync_api_key');
```

### Test Connections Section
**Location**: `includes/class-admin-settings.php` (lines 704-714)

**Test Buttons**:
1. **Test Database**: Tests MySQL connection
2. **Test Grok API**: Tests Grok AI API connection
3. **Test Groq Embeddings**: Tests Groq embeddings API
4. **Test Pinecone**: Tests Pinecone connection
5. **Test Pinecone Upsert**: Tests vector upsert functionality
6. **Test Redis**: Tests Redis connection

**AJAX Handlers**:
- `boat_chatbot_test_db_connection` (line 21)
- `boat_chatbot_test_api_connection` (line 22)
- `boat_chatbot_test_groq_embeddings` (line 24)
- `boat_chatbot_test_pinecone` (line 25)
- `boat_chatbot_test_pinecone_upsert` (line 26)
- `boat_chatbot_test_redis` (line 30)

### Vector Database Sync Section
**Location**: `includes/class-admin-settings.php` (lines 716-723)

**Sync Buttons**:
1. **Sync All Records**: Syncs all records from SQL to Pinecone
2. **Sync Pending Records**: Syncs only records that need updating
3. **Test Sync (100 Records)**: Test sync with 100 records

**AJAX Handlers**:
- `boat_chatbot_sync_all_records` (line 27)
- `boat_chatbot_sync_pending_records` (line 28)
- `boat_chatbot_test_sync_100` (line 29)

### Chat Logs Page
**Location**: `includes/class-admin-settings.php` (lines 728-781)

**Features**:
- Displays last 100 conversation logs
- Columns: ID, Timestamp, User Message, Intent, Response Time, Actions
- **View Details** button: Opens modal with full log details
- AJAX handler: `boat_chatbot_get_log_details` (line 23)

**Log Fields**:
- `id`: Log ID
- `timestamp`: Timestamp
- `user_message`: User's message
- `classified_intent`: Intent classification
- `sql_query_used`: SQL query (if applicable)
- `ai_prompt_sent`: Full AI prompt
- `ai_response_received`: AI response
- `full_response_sent_to_user`: Final response
- `response_time`: Response time in seconds
- `performance_metrics`: JSON performance data

---

## 3. Workflows and API Endpoints

### 3.1 Workflow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        USER INTERACTION                          │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                    FRONTEND (JavaScript)                        │
│  - Landing Page (/virtual-yachtbroker)                          │
│  - Full Chatbot Page (/boat-chatbot)                            │
│  - Widget Mode (Shortcode)                                       │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             │ POST /wp-json/boat-chatbot/v1/send-message
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│              CHATBOT HANDLER (class-chatbot-handler.php)        │
│                                                                  │
│  1. Check Cache                                                 │
│  2. Classify Intent (general/database_query/hybrid)            │
│  3. Route to Handler:                                           │
│     ├─ General Query → handle_general_query_optimized()        │
│     ├─ Database Query → handle_database_query_optimized()      │
│     └─ Hybrid → handle_parallel_hybrid_search()               │
└─────┬───────────────────────────────────────────────────────────┘
      │
      ├──────────────────────────────────────────────────────────┐
      │                                                           │
      ▼                                                           ▼
┌─────────────────────────────┐              ┌──────────────────────────────┐
│   GROK AI API                │              │   DATABASE MANAGER            │
│   (Chat Completions)         │              │   (SQL Queries)               │
│                              │              │                               │
│   - Generate embeddings      │              │   - Query listings            │
│   - Generate responses        │              │   - Filter by criteria       │
│   - Natural language          │              │   - Return results            │
└─────────────────────────────┘              └──────────────────────────────┘
      │                                                           │
      │                                                           │
      ▼                                                           ▼
┌─────────────────────────────┐              ┌──────────────────────────────┐
│   GROQ EMBEDDINGS API        │              │   PINECONE VECTOR DB          │
│   (Generate Embeddings)      │              │   (Semantic Search)            │
│                              │              │                               │
│   - Convert text to vectors │              │   - Vector similarity search    │
│   - 768 dimensions          │              │   - Return similar listings    │
└─────────────────────────────┘              └──────────────────────────────┘
      │                                                           │
      └───────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    RESPONSE PROCESSING                           │
│  - Combine results (if hybrid)                                   │
│  - Format listings                                               │
│  - Generate AI response                                          │
│  - Cache response                                                │
│  - Log interaction                                               │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                    FRONTEND DISPLAY                              │
│  - Render AI response                                            │
│  - Display listings (if any)                                     │
│  - Handle pagination                                             │
└─────────────────────────────────────────────────────────────────┘
```

### 3.2 API Endpoints

#### 3.2.1 Send Message
**Endpoint**: `POST /wp-json/boat-chatbot/v1/send-message`

**Location**: `includes/class-chatbot-handler.php` (lines 80-103)

**Request**:
```json
{
    "message": "Show me boats under $100,000",
    "conversation_history": [
        {"role": "user", "content": "..."},
        {"role": "assistant", "content": "..."}
    ],
    "nonce": "security_nonce"
}
```

**Response**:
```json
{
    "success": true,
    "data": {
        "response": "AI generated response...",
        "intent": "database_query",
        "listings": [
            {
                "ID": 123,
                "VesselName": "Example Boat",
                "Type_": "Yacht",
                "DisplayLengthFeet": 45,
                "PriceUSD": 95000,
                "City": "Miami",
                "State": "FL",
                "Description": "..."
            }
        ],
        "total_listings": 15,
        "response_time": 0.234,
        "performance_log": {
            "cache_hit": false,
            "intent_time": 0.05,
            "db_query_time": 0.12,
            "ai_time": 0.064,
            "total_time": 0.234
        },
        "cached": false
    }
}
```

**Handler Function**: `handle_rest_message()` (line 454)

#### 3.2.2 Load Listings (Pagination)
**Endpoint**: `POST /wp-json/boat-chatbot/v1/load-listings`

**Location**: `includes/class-chatbot-handler.php` (lines 106-131)

**Request**:
```json
{
    "query": "luxury yachts",
    "offset": 0,
    "limit": 5,
    "nonce": "security_nonce"
}
```

**Response**:
```json
{
    "success": true,
    "data": {
        "listings": [...],
        "total": 150,
        "offset": 0,
        "limit": 5,
        "has_more": true
    }
}
```

**Handler Function**: `handle_load_listings()` (line 557)
**Note**: Always returns 5 items per page (hardcoded at line 591)

#### 3.2.3 Vector Search
**Endpoint**: `POST /wp-json/boat-chatbot/v1/vector-search`

**Location**: `includes/class-chatbot-handler.php` (lines 134-154)

**Request**:
```json
{
    "query": "comfortable family boat",
    "top_k": 10,
    "include_records": true,
    "nonce": "security_nonce"
}
```

**Response**:
```json
{
    "success": true,
    "data": {
        "results": [
            {
                "id": "123",
                "score": 0.89,
                "metadata": {
                    "record_id": 123,
                    "text": "..."
                }
            }
        ],
        "listings": [...],
        "total": 10
    }
}
```

**Handler Function**: `handle_vector_search()` (line 600+)

#### 3.2.4 Sync Records (External)
**Endpoint**: `POST /wp-json/boat-chatbot/v1/sync-records`

**Location**: `includes/class-chatbot-handler.php` (lines 158-178)

**Authentication**: API Key (header or body parameter)

**Request**:
```json
{
    "api_key": "sync_api_key",
    "record_ids": [123, 456, 789],
    "sync_all": false
}
```

**Response**:
```json
{
    "success": true,
    "message": "Sync completed: 10 successful, 0 failed out of 10 total",
    "results": {
        "success": 10,
        "failed": 0,
        "total": 10
    }
}
```

**Handler Function**: `handle_sync_records()` (line 300)

#### 3.2.5 Delete Records (External)
**Endpoint**: `POST /wp-json/boat-chatbot/v1/delete-records`

**Location**: `includes/class-chatbot-handler.php` (lines 182-199)

**Authentication**: API Key (header or body parameter)

**Request**:
```json
{
    "api_key": "sync_api_key",
    "record_ids": [123, 456]
}
```

**Response**:
```json
{
    "success": true,
    "message": "Deletion completed: 2 successful, 0 failed out of 2 total",
    "results": {
        "success": 2,
        "failed": 0,
        "total": 2,
        "errors": []
    }
}
```

**Handler Function**: `handle_delete_records()` (line 360)

### 3.3 AJAX Endpoints (Legacy)

#### Send Message (AJAX)
**Action**: `boat_chatbot_send_message`
**Method**: POST
**Location**: `includes/class-chatbot-handler.php` (line 21-22)

**Data**:
```javascript
{
    action: 'boat_chatbot_send_message',
    message: 'user message',
    nonce: 'security_nonce'
}
```

---

## 4. Detailed Workflow Implementation

### 4.1 Message Processing Workflow

#### Step 1: Request Reception
**File**: `includes/class-chatbot-handler.php`
**Function**: `handle_rest_message()` (line 454)

```php
public function handle_rest_message($request) {
    $user_message = $request->get_param('message');
    $conversation_history = $request->get_param('conversation_history');
    $offset = $request->get_param('offset');
    
    // Sanitize conversation history
    // ... (lines 460-475)
    
    $performance_log = array();
    $start_time = microtime(true);
```

**Key Operations**:
- Extract and sanitize user message
- Sanitize conversation history
- Initialize performance logging

#### Step 2: Cache Check
**File**: `includes/class-chatbot-handler.php`
**Function**: `handle_rest_message()` (lines 480-499)

```php
// Check cache first (only for single messages without history)
$cache_key = 'message_' . md5($user_message);
$cached_response = $this->get_cache($cache_key);

if ($cached_response !== false && empty($conversation_history)) {
    $performance_log['cache_hit'] = true;
    return rest_ensure_response(array(
        'success' => true,
        'data' => array_merge($cached_response, array(
            'cached' => true
        ))
    ));
}
```

**Cache Implementation**:
- Uses Redis if enabled, otherwise WordPress transients
- Cache key: `message_{md5_hash}`
- Cache expiration: 5 minutes (300 seconds)
- Only caches single messages (no conversation history)

#### Step 3: Intent Classification
**File**: `includes/class-chatbot-handler.php`
**Function**: `classify_intent_parallel()` (line 505)

```php
$intent_start = microtime(true);
$intent = $this->classify_intent_parallel($user_message);
$performance_log['intent_time'] = microtime(true) - $intent_start;
```

**Intent Types**:
- `general`: General boating questions (no database query needed)
- `database_query`: Specific boat listing queries
- `hybrid`: Requires both semantic and keyword search

**Classification Logic**:
- Uses Grok AI to analyze user message
- Prompts AI to classify intent
- Returns one of the three intent types

#### Step 4: Route to Handler

##### 4.4.1 General Query Handler
**File**: `includes/class-chatbot-handler.php`
**Function**: `handle_general_query_optimized()` (line 528)

**Workflow**:
1. Build AI prompt with tone of voice
2. Send to Grok AI API
3. Parse response
4. Return formatted response

**Code Snippet**:
```php
public function handle_general_query_optimized($user_message, &$performance_log, $conversation_history = array()) {
    $tone_of_voice = get_option('boat_chatbot_tone_of_voice', '...');
    
    $messages = array(
        array('role' => 'system', 'content' => $tone_of_voice),
        array('role' => 'user', 'content' => $user_message)
    );
    
    // Add conversation history if provided
    if (!empty($conversation_history)) {
        $messages = array_merge(
            array(array('role' => 'system', 'content' => $tone_of_voice)),
            $conversation_history,
            array(array('role' => 'user', 'content' => $user_message))
        );
    }
    
    $ai_start = microtime(true);
    $response = $this->call_grok_api($messages);
    $performance_log['ai_time'] = microtime(true) - $ai_start;
    
    return array(
        'response' => $response,
        'listings' => array(),
        'total_listings' => 0
    );
}
```

##### 4.4.2 Database Query Handler
**File**: `includes/class-chatbot-handler.php`
**Function**: `handle_database_query_optimized()` (line 526)

**Workflow**:
1. Extract search criteria from user message using AI
2. Build SQL query with validated fields
3. Execute query via Database Manager
4. Format listings using template
5. Generate AI response with listings
6. Return response with listings

**Code Snippet**:
```php
public function handle_database_query_optimized($user_message, &$performance_log, $conversation_history = array(), $offset = 0) {
    // Extract search criteria using AI
    $criteria = $this->extract_search_criteria($user_message);
    
    // Query database
    $db_start = microtime(true);
    $db_manager = new Boat_Chatbot_Database_Manager();
    $listings = $db_manager->search_listings($criteria, $offset, 20);
    $performance_log['db_query_time'] = microtime(true) - $db_start;
    
    // Format listings
    $formatted_listings = $this->format_listings($listings);
    
    // Generate AI response
    $ai_start = microtime(true);
    $response = $this->generate_response_with_listings($user_message, $formatted_listings, $conversation_history);
    $performance_log['ai_time'] = microtime(true) - $ai_start;
    
    return array(
        'response' => $response,
        'listings' => $listings,
        'total_listings' => count($listings)
    );
}
```

**Database Manager Functions**:
- `search_listings()`: Executes SQL query with criteria
- `validate_field_names()`: Validates fields against whitelist
- `sanitize_string()`: Sanitizes input to prevent SQL injection
- `extract_search_terms()`: Extracts search criteria from user message

**Price Extraction Patterns**:
**File**: `includes/class-database-manager.php` (lines 776-826)

The system uses comprehensive regex patterns to extract price information:

1. **Pattern 1**: "under $X" or "under X dollars" → `max_price`
2. **Pattern 2**: "less than $X" → `max_price`
3. **Pattern 3**: "max $X" or "maximum $X" → `max_price`
4. **Pattern 4**: "up to $X" → `max_price`
5. **Pattern 5**: "over $X" or "more than $X" → `min_price`
6. **Pattern 6**: Price range "$X to $Y" or "$X-$Y" → `min_price` and `max_price`
7. **Pattern 6.5**: Budget/price/cost without direction words → `max_price` (treated as "under")
   - Examples: "budget is 200k", "price is 500", "cost is 1000", "my budget is 200k"
   - **Special Behavior**: When price is mentioned with "budget", "price", or "cost" without direction words like "more than" or "under", it is automatically interpreted as "under" (max_price)
   - This allows natural queries like "budget is 200k" to be treated as "budget is under 200k"
8. **Pattern 7**: Standalone price "$X" → `max_price` (if no other context)

**Number Normalization**:
- Handles abbreviations: "200k" → 200000, "2.5m" → 2500000
- Function: `normalize_unit_abbreviations()` (line 636)
- Supports: k/K (thousand), m/M (million), b/B (billion)

##### 4.4.3 Hybrid Search Handler
**File**: `includes/class-chatbot-handler.php`
**Function**: `handle_parallel_hybrid_search()` (line 519)

**Workflow**:
1. Check if parallel search is enabled
2. **Parallel Execution**:
   - Generate embedding for query (Groq API)
   - Query Pinecone vector database (semantic search)
   - Query SQL database (keyword search)
3. Combine results with weighted scores
4. Sort by combined score
5. Format listings
6. Generate AI response

**Code Snippet**:
```php
public function handle_parallel_hybrid_search($user_message, &$performance_log, $conversation_history = array(), $offset = 0) {
    $use_parallel = get_option('boat_chatbot_use_parallel_search', true);
    $search_mode = get_option('boat_chatbot_search_mode', 'hybrid');
    $vector_weight = get_option('boat_chatbot_vector_weight', 0.5);
    $keyword_weight = get_option('boat_chatbot_keyword_weight', 0.5);
    
    // Generate embedding
    $embedding_start = microtime(true);
    $groq_manager = Boat_Chatbot_Groq_Embeddings_Manager::get_instance();
    $query_embedding = $groq_manager->generate_embedding($user_message);
    $performance_log['embedding_time'] = microtime(true) - $embedding_start;
    
    // Parallel search
    if ($use_parallel && function_exists('parallel\run')) {
        // Use PHP Parallel extension
        $vector_future = parallel\run(function() use ($query_embedding) {
            $pinecone_manager = Boat_Chatbot_Pinecone_Manager::get_instance();
            return $pinecone_manager->query($query_embedding, 20);
        });
        
        $keyword_future = parallel\run(function() use ($user_message) {
            $db_manager = new Boat_Chatbot_Database_Manager();
            return $db_manager->search_listings($user_message, 0, 20);
        });
        
        $vector_results = $vector_future->value();
        $keyword_results = $keyword_future->value();
    } else {
        // Sequential execution
        $vector_results = $pinecone_manager->query($query_embedding, 20);
        $keyword_results = $db_manager->search_listings($user_message, 0, 20);
    }
    
    // Combine and score results
    $combined_results = $this->combine_search_results(
        $vector_results,
        $keyword_results,
        $vector_weight,
        $keyword_weight
    );
    
    // Sort by combined score
    usort($combined_results, function($a, $b) {
        return $b['combined_score'] <=> $a['combined_score'];
    });
    
    // Format and return
    return $this->format_hybrid_response($combined_results, $user_message, $conversation_history);
}
```

**Score Combination Formula**:
```php
$combined_score = ($vector_weight * $vector_score) + ($keyword_weight * $keyword_score);
```

#### Step 5: Response Formatting
**File**: `includes/class-chatbot-handler.php`
**Function**: `handle_rest_message()` (lines 531-554)

```php
$performance_log['total_time'] = microtime(true) - $start_time;

// Cache the response (only for single messages without history)
if (empty($conversation_history)) {
    $this->set_cache($cache_key, array(
        'response' => $response_data['response'],
        'intent' => $intent,
        'listings' => $response_data['listings'] ?? array(),
        'total_listings' => $response_data['total_listings'] ?? 0,
    ));
}

// Log the interaction
$this->log_interaction($user_message, $intent, $response_data['response'], 
    $performance_log['total_time'], $performance_log);

return rest_ensure_response(array(
    'success' => true,
    'data' => array_merge($response_data, array(
        'intent' => $intent,
        'response_time' => $performance_log['total_time'],
        'performance_log' => $performance_log,
        'cached' => false
    ))
));
```

### 4.2 Vector Sync Workflow

#### Overview
Syncs boat listings from SQL database to Pinecone vector database for semantic search with hybrid sparse + dense vector support.

**File**: `includes/class-vector-sync-manager.php`

#### Workflow Steps

1. **Identify Records Needing Sync**
   - Check `wp_boat_chatbot_vector_sync` table
   - Find records with status `pending` or `updated`
   - Function: `get_records_needing_sync()` (line ~100)

2. **Fetch Record Data**
   - Query SQL database for record details
   - Function: `get_record_data()` (line ~150)

3. **Build Text for Embedding**
   - **Function**: `build_text_from_record()` in `class-groq-embeddings-manager.php` (line 317)
   - **Process**: 
     - Gets ALL fields from database record (dynamically)
     - Excludes internal fields: ID, ListingOwnerID, SecondaryListingOwnerID, ThirdListingOwnerID, ListingOwnerBrokerageID, SecondaryListingOwnerBrokerageID, ThirdListingOwnerBrokerageID, ListingOwnerOfficeID, SecondaryListingOwnerOfficeID, ThirdListingOwnerOfficeID, CreatedTimestamp, UpdatedTimestamp
     - Formats as: `Field Name: value` (one per line)
     - Example output:
       ```
       VesselName: Luxury Yacht
       Type : Sailboat
       Manufacturer: Beneteau
       Model: Oceanis 45
       DisplayLengthFeet: 45
       PriceUSD: 250000
       City: Miami
       State: FL
       ...
       ```

4. **Generate Dense Vector (Embedding)**
   - **Function**: `generate_embedding()` in `class-groq-embeddings-manager.php`
   - **Input**: Text built from record (all fields except excluded ones)
   - **Output**: Dense vector array (768 or 1024 dimensions, depending on model)
   - **Model**: `nomic-embed-text-v1.5` (default) or configured model
   - **API**: Groq Embeddings API

5. **Generate Sparse Vector (Optional, for Hybrid Search)**
   - **Function**: `generate_document_sparse_vector()` in `class-sparse-vector-generator.php`
   - **Input**: Same text used for dense embedding
   - **Output**: Sparse vector with format: `{'indices': [1, 5, 10, ...], 'values': [0.8, 0.6, 0.4, ...]}`
   - **Requirements**: Vocabulary must be built first (via "Build Vocabulary" button in admin)
   - **Generation Methods**:
     - **BM25 Statistical Method** (default, faster, no API cost): Uses term frequency and inverse document frequency
     - **Embedding-Based Method** (optional): Uses embedding API to identify semantically important terms
   - **Settings**:
     - `boat_chatbot_sparse_use_embedding`: Enable embedding-based sparse generation
     - `boat_chatbot_sparse_threshold`: Percentage of top terms to keep (default: 0.1 = 10%)

6. **Prepare Metadata**
   - **Function**: `prepare_metadata()` in `class-vector-sync-manager.php` (line 1012)
   - **Process**:
     - Includes ALL database fields from record (except excluded internal fields)
     - Converts values based on SQL column types (int, float, string, bool)
     - Excludes: ListingOwnerID, SecondaryListingOwnerID, ThirdListingOwnerID, ListingOwnerBrokerageID, SecondaryListingOwnerBrokerageID, ThirdListingOwnerBrokerageID, ListingOwnerOfficeID, SecondaryListingOwnerOfficeID, ThirdListingOwnerOfficeID, CreatedTimestamp, UpdatedTimestamp
   - **Metadata Cleaning** (in `class-pinecone-manager.php`, line 427):
     - Removes large fields: Description, Summary, NotableUpgrades, Tenders, PriceHeadline
     - Aggressively truncates: ListingOwnerOfficeDisplayPicture (100 chars)
     - Standard truncation: 500 characters per field
     - Strips HTML tags
     - Converts arrays/objects to JSON strings (max 500 chars)
     - Total metadata size limit: 30KB (Pinecone limit is 40KB, but we use 30KB as safe buffer)

7. **Upsert to Pinecone**
   - **Function**: `upsert_vectors()` in `class-pinecone-manager.php` (line 251)
   - **Vector Structure**:
     ```php
     array(
         'id' => (string)$record_id,           // Record ID as string
         'values' => $embedding,               // Dense vector (array of floats)
         'sparseValues' => $sparse_vector,     // Sparse vector (optional, for hybrid search)
         'metadata' => $cleaned_metadata       // All record fields (cleaned and truncated)
     )
     ```
   - **Dense Vector (`values`)**:
     - Semantic representation of the entire record
     - Generated from all fields (except excluded ones)
     - Dimensions: 768 (nomic-embed-text-v1.5) or configured dimension
     - Used for semantic similarity search
   
   - **Sparse Vector (`sparseValues`)** (Optional):
     - Format: `{'indices': [array of integers], 'values': [array of floats]}`
     - Represents important keywords/terms from the record
     - Generated from same text as dense vector
     - Only added if vocabulary is built and sparse generator is available
     - Used for keyword-based search in hybrid mode
   
   - **Metadata**:
     - Contains ALL database fields (except excluded ones)
     - Used for filtering and retrieval
     - Fields are type-preserved (int, float, string, bool)
     - Large text fields are truncated or removed
     - Example fields: VesselName, Type_, Manufacturer, Model, DisplayLengthFeet, PriceUSD, City, State, Country, Year, etc.

8. **Update Sync Status**
   - Mark record as `synced` in tracking table
   - Update `last_synced` timestamp
   - Function: `update_sync_status()` (line 1087)

#### What Gets Stored in Pinecone

**For Each Boat Listing Record:**

1. **Vector ID**: Record ID (e.g., "12345")

2. **Dense Vector (`values`)**:
   - 768-dimensional (or configured) float array
   - Semantic representation of ALL record fields combined
   - Generated from text: `Field Name: value` format for each field
   - Example: Represents the semantic meaning of "Luxury Yacht, Sailboat, Beneteau, Oceanis 45, 45 feet, $250000, Miami, FL..."

3. **Sparse Vector (`sparseValues`)** (if vocabulary built):
   - Keyword-based representation
   - Format: `{'indices': [term_ids], 'values': [term_weights]}`
   - Contains top 10% of most important terms (by default)
   - Example: `{'indices': [45, 123, 456], 'values': [0.85, 0.72, 0.68]}`
   - Represents important keywords like "yacht", "sailboat", "miami", etc.

4. **Metadata** (all database fields):
   - **Boat Information**: VesselName, Type_, Manufacturer, Model, Year
   - **Specifications**: DisplayLengthFeet, PriceUSD, DisplayLengthMeters
   - **Location**: City, State, Country, Zip
   - **Features**: Stabilizers, Elevator, SeaKeeper, Helideck, etc.
   - **Condition & Status**: Condition, Status, ListingType
   - **Owner Info**: ListingOwnerName, ListingOwnerEmail, ListingOwnerPhone (truncated)
   - **And many more fields** (all fields from database except excluded ones)
   - **Note**: Large text fields (Description, Summary) are removed to keep metadata under 30KB limit

#### Hybrid Search Configuration

**Settings** (Admin → Settings):
- **Hybrid Alpha (α)**: Weight for dense vectors (default: 0.7 = 70% dense, 30% sparse)
- **Use Embedding API for Sparse Vectors**: Enable semantic-aware sparse generation
- **Sparse Threshold**: Percentage of top terms to keep (default: 0.1 = 10%)

**How Hybrid Search Works**:
- When querying, if sparse vector is available, Pinecone combines:
  - Dense vector similarity (semantic meaning)
  - Sparse vector similarity (keyword matching)
- Formula: `final_score = α × dense_score + (1-α) × sparse_score`
- Default: 70% semantic, 30% keyword

**Code Flow**:
```php
public function sync_records_batch($record_ids) {
    $success = 0;
    $failed = 0;
    
    foreach ($record_ids as $record_id) {
        try {
            // 1. Get record data
            $record_data = $this->get_record_data($record_id);
            if (!$record_data) {
                $failed++;
                continue;
            }
            
            // 2. Generate embedding
            $embedding = $this->generate_record_embedding($record_data);
            if (!$embedding) {
                $failed++;
                continue;
            }
            
            // 3. Upsert to Pinecone
            $vector = array(
                'id' => (string)$record_id,
                'values' => $embedding,
                'metadata' => $this->prepare_metadata($record_data)
            );
            
            $result = $this->pinecone_manager->upsert_vectors(array($vector));
            if ($result) {
                $success++;
                $this->update_sync_status($record_id, 'synced');
            } else {
                $failed++;
            }
        } catch (Exception $e) {
            $failed++;
            error_log("Sync failed for record {$record_id}: " . $e->getMessage());
        }
    }
    
    return array(
        'success' => $success,
        'failed' => $failed,
        'total' => count($record_ids)
    );
}
```

### 4.3 Landing Page Workflow

#### Page Rendering
**File**: `includes/class-landing-page.php`
**Function**: `render_landing_page()` (line 666)

**Components**:
1. **Top Bar**: Logo and navigation
2. **Video Section**: Full-width video background
3. **Chat Container**: Hidden initially, shown when user interacts
4. **Action Buttons**: 5 interactive buttons (Home, Search, Translate, Settings, Help)
5. **Chatbot Input**: Bottom input section with voice, image, action buttons, and send button
6. **Help Modal**: Modal dialog with customizable help content

#### Action Buttons

**Available Buttons**:
1. **Home Button**: Navigates to home page
2. **Search Button**: Opens search interface
3. **Translate Button**: Shows "This feature coming soon" message (auto-dismisses after 2 seconds)
4. **Settings Button**: Opens settings interface
5. **Help Button**: Opens help modal with customizable content

**Help Modal Features**:
- **Location**: `includes/class-landing-page.php` (lines 659-703)
- **Content**: Editable from admin settings (`boat_chatbot_help_description`)
- **Opening**: Click help button
- **Closing**: Close button, overlay click, or Escape key
- **Styling**: Responsive modal with gradient background matching theme
- **Content Structure**: Uses `boat-help-section` class for consistent styling

**Translation Button**:
- Shows temporary "This feature coming soon" message
- Auto-dismisses after 2 seconds
- Smooth fade-in/fade-out animation

#### Responsive Button Layout

**Desktop (≥769px)**:
- Action buttons positioned outside input wrapper (as sibling)
- Buttons maintain full size and spacing

**Mobile (<769px)**:
- All buttons wrapped in `.boat-chatbot-buttons-row` container
- Buttons arranged in single row: Voice → Image → Action Buttons → Send
- Send button aligned to right using `margin-left: auto`
- Action buttons maintain size (no shrinking) with `flex-shrink: 0`
- Smaller padding and font-size for mobile optimization

**JavaScript Handler**: `handleActionButtonsPosition()` in `assets/landing.js` (line 98)
- Runs on page load and window resize
- Dynamically reorganizes buttons based on screen size

#### Mobile Keyboard Dismissal

**Feature**: Input field automatically loses focus after message is sent
**Implementation**: `assets/landing.js` (lines 287-290, 299-300, 310-312, 321-322)

**Triggers**:
1. **Send Button Click**: Calls `blur()` immediately after `sendMessage()`
2. **Enter Key Press**: Calls `blur()` immediately after `sendMessage()`

**Benefits**:
- Dismisses mobile virtual keyboard automatically
- Improves user experience on mobile devices
- Works for both input field selectors (`#message-input` and `#boat-chatbot-message`)

#### User Interaction Flow

1. **Page Load**
   - Video autoplays (muted, loop)
   - Action buttons visible
   - Chat container hidden
   - Buttons positioned based on screen size

2. **User Clicks Action Button**
   - JavaScript handler: `assets/landing.js` (line 85)
   - Opens chat container
   - Sends predefined message
   - Triggers chatbot response

3. **User Types Message**
   - Input handler: `assets/landing.js` (line 294+)
   - Sends POST to `/wp-json/boat-chatbot/v1/send-message`
   - Input field loses focus (keyboard dismisses on mobile)
   - Displays response
   - Shows listings if any

4. **Pagination**
   - "Load More" button appears if more listings available
   - Calls `/wp-json/boat-chatbot/v1/load-listings`
   - Appends new listings to conversation

**JavaScript Key Functions**:
- `sendMessage()`: Sends message to API and handles blur
- `sendMessageWithStreaming()`: Handles streaming responses
- `handleStreamComplete()`: Processes completed streams
- `displayListings()`: Renders listings with pagination
- `loadMoreLandingListings()`: Loads additional listings
- `handleActionButtonsPosition()`: Responsive button positioning

### 4.4 Caching System

#### Cache Implementation
**File**: `includes/class-chatbot-handler.php`
**Functions**: `get_cache()`, `set_cache()` (lines ~100-150)

**Cache Strategy**:
- **Primary**: Redis (if enabled)
- **Fallback**: WordPress Transients
- **Cache Keys**:
  - Messages: `message_{md5_hash}`
  - Hybrid search: `hybrid_sorted_ids_{md5_hash}`
- **Expiration**: 5 minutes (300 seconds)

**Code**:
```php
private function get_cache($key) {
    $redis_enabled = get_option('boat_chatbot_redis_enabled', false);
    
    if ($redis_enabled) {
        $redis_manager = Boat_Chatbot_Redis_Cache_Manager::get_instance();
        return $redis_manager->get($key);
    } else {
        return get_transient($this->cache_group . '_' . $key);
    }
}

private function set_cache($key, $value, $expiration = null) {
    if ($expiration === null) {
        $expiration = $this->cache_expiration;
    }
    
    $redis_enabled = get_option('boat_chatbot_redis_enabled', false);
    
    if ($redis_enabled) {
        $redis_manager = Boat_Chatbot_Redis_Cache_Manager::get_instance();
        return $redis_manager->set($key, $value, $expiration);
    } else {
        return set_transient($this->cache_group . '_' . $key, $value, $expiration);
    }
}
```

### 4.5 Logging System

#### Log Structure
**Table**: `wp_boat_chatbot_logs`

**Fields**:
- `id`: Auto-increment ID
- `timestamp`: DateTime
- `user_message`: User's message
- `classified_intent`: Intent type
- `sql_query_used`: SQL query (if applicable)
- `ai_prompt_sent`: Full AI prompt
- `ai_response_received`: Raw AI response
- `full_response_sent_to_user`: Final formatted response
- `response_time`: Response time in seconds
- `performance_metrics`: JSON performance data

**Logging Function**:
**File**: `includes/class-chatbot-handler.php`
**Function**: `log_interaction()` (line ~2000+)

```php
private function log_interaction($user_message, $intent, $response, $response_time, $performance_log) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'boat_chatbot_logs';
    
    // Use async logging to avoid blocking
    do_action('boat_chatbot_async_log', 
        $user_message,
        $intent,
        $response,
        $response_time,
        $performance_log
    );
}
```

**Async Logging**:
- Uses WordPress action hook: `boat_chatbot_async_log`
- Prevents blocking main request
- Handler: `async_log_interaction()` (line 25)

---

## 5. Future Planning

### 5.1 Priority 1: Advanced Intent Identification System ()

#### 5.1.1 Overview
Transform the current basic intent classification into a sophisticated, trained system that accurately understands user queries and routes them to the most appropriate search method (SQL, Pinecone, or Hybrid). This will be built using Python, which provides superior capabilities for AI model development, training, and deployment.

#### 5.1.2 Why Python for AI Model Development
- **Rich AI/ML Ecosystem**: Python has the most comprehensive libraries (TensorFlow, PyTorch, scikit-learn, transformers)
- **Advanced NLP Libraries**: NLTK, spaCy, Hugging Face Transformers for state-of-the-art NLP
- **Model Training**: Superior tools for data preprocessing, feature engineering, and model training
- **Research & Experimentation**: Easy to prototype, test, and iterate on models
- **Production Deployment**: Frameworks like FastAPI, Flask for API deployment
- **Community & Resources**: Largest AI/ML community with extensive documentation and pre-trained models

#### 5.1.3 Business Value
- **95%+ Intent Accuracy**: Reduce misclassified queries from ~15% to <5%
- **Faster Response Times**: Correct intent classification reduces unnecessary API calls
- **Better User Experience**: Users get more relevant results on first try
- **Cost Reduction**: Fewer API calls = lower operational costs
- **Scalability**: Python models can be easily scaled and optimized

#### 5.1.4 Technical Approach

##### Phase 1: Data Collection & Training Dataset Creation (Month 1)

**A. Collect User Query Patterns**
- Extract all user queries from WordPress database (`wp_boat_chatbot_logs` table)
- Export data to Python-compatible format (CSV, JSON, Parquet)
- Categorize queries by actual intent (manual review + automated labeling)
- Build training dataset with 10,000+ query examples
- Include edge cases and ambiguous queries
- Create validation and test sets (80/10/10 split)

**Python Tools & Libraries**:
- **pandas**: Data manipulation and analysis
- **numpy**: Numerical computing
- **sqlalchemy**: Database connectivity
- **scikit-learn**: Data preprocessing and splitting

**B. Train SQL Query Structure Recognition**
- Analyze successful SQL queries from logs
- Identify patterns: price ranges, length filters, location queries, type categories
- Create SQL query templates for common patterns
- Train model to recognize when SQL is most appropriate
- Build feature extraction pipeline for SQL-relevant queries

**Python Approach**:
- Use **spaCy** or **NLTK** for natural language processing
- **scikit-learn** for pattern recognition and classification
- **regex** for extracting numeric values, locations, and keywords
- Build custom feature extractors for boat-specific terminology

**C. Train Pinecone Query Structure Recognition**
- Analyze successful Pinecone/vector queries
- Identify semantic patterns: descriptive queries, feature-based searches, lifestyle queries
- Map query types to embedding effectiveness
- Train model to recognize semantic search opportunities
- Analyze embedding similarity scores and query success rates

**Python Approach**:
- Use **sentence-transformers** for embedding analysis
- **scikit-learn** clustering algorithms to identify query patterns
- Analyze vector similarity distributions
- Build semantic pattern classifiers

##### Phase 2: Model Training & Implementation (Month 2)

**Option A: Custom Neural Network Model (Recommended)**

**Approach**:
1. **Build Custom Intent Classification Model**
   - Use **PyTorch** or **TensorFlow** for deep learning
   - Design neural network architecture (LSTM, BERT-based, or Transformer)
   - Fine-tune pre-trained language models (BERT, RoBERTa, DistilBERT)
   - Train on boat-specific query dataset
   - Implement multi-class classification (general, database_query, hybrid)

**Python Implementation Strategy**:
- **Hugging Face Transformers**: Use pre-trained models (bert-base-uncased, roberta-base)
- **PyTorch Lightning**: For structured training pipeline
- **Weights & Biases (wandb)**: For experiment tracking and hyperparameter tuning
- **Optuna**: For automated hyperparameter optimization

**Model Architecture Options**:
- **BERT-based Classifier**: Fine-tune BERT for intent classification
- **LSTM/GRU Networks**: For sequence-based classification
- **Ensemble Methods**: Combine multiple models for better accuracy
- **Transformer Models**: Use GPT-style models fine-tuned for classification

**Option B: Fine-tune OpenAI Models via Python**

**Approach**:
1. **Fine-tune OpenAI GPT Models**
   - Use OpenAI's Python SDK for fine-tuning
   - Prepare training data in OpenAI format
   - Fine-tune GPT-3.5 or GPT-4 on boat-specific queries
   - Deploy fine-tuned model via API
   - Create Python wrapper for model inference

**Python Tools**:
- **openai** Python SDK: For API interactions
- **pandas**: For data preparation
- **json**: For formatting training data
- **requests**: For API calls

**Option C: Hybrid Approach (Best of Both Worlds)**

**Approach**:
1. **Combine Multiple Models**
   - Use fine-tuned BERT for fast classification
   - Use OpenAI GPT-4 for complex/ambiguous queries
   - Implement confidence scoring
   - Route queries based on confidence thresholds
   - Fallback mechanisms for edge cases

**Python Implementation**:
- **FastAPI**: Create API endpoints for model inference
- **Redis**: Cache model predictions
- **Celery**: For async model inference
- **MLflow**: For model versioning and deployment

##### Phase 3: SQL Query Structure Training (Month 2-3)

**A. SQL Query Pattern Recognition**
- Train model to extract search criteria from natural language
- Map user queries to SQL WHERE clauses
- Handle complex queries with multiple criteria
- Optimize query generation for performance
- Build Named Entity Recognition (NER) model for boat-specific entities

**Python Approach**:
- **spaCy NER**: Train custom NER model for boat entities (prices, lengths, locations, types)
- **spaCy Matcher**: Pattern matching for common query structures
- **scikit-learn**: For criteria extraction classification
- **SQLAlchemy**: For safe SQL query generation
- **Rule-based + ML**: Combine rule-based extraction with ML models

**Training Data Structure**:
- Input: Natural language query
- Output: Structured JSON with extracted criteria (price_min, price_max, length_min, location, type, etc.)

**B. SQL Query Generation Model**
- Build sequence-to-sequence model for SQL generation
- Use **T5** or **GPT-2** fine-tuned for SQL generation
- Implement query validation and safety checks
- Optimize generated queries for performance

**Python Tools**:
- **Hugging Face Transformers**: T5, GPT-2 models
- **sqlparse**: For SQL parsing and validation
- **pandas**: For query result validation

##### Phase 4: Pinecone Structure Training (Month 3)

**A. Embedding Optimization**
- Analyze which queries work best with semantic search
- Optimize embedding generation for boat-specific terms
- Create specialized embeddings for boat features
- Test and refine vector search accuracy
- Build domain-specific embedding model

**Python Approach**:
- **sentence-transformers**: Fine-tune embedding models on boat data
- **FAISS**: For efficient similarity search and evaluation
- **numpy**: For vector operations and analysis
- **matplotlib/seaborn**: For visualization and analysis
- **scikit-learn**: For clustering and pattern analysis

**Embedding Model Training**:
- Collect boat listing descriptions and user queries
- Fine-tune **sentence-transformers** models (all-MiniLM-L6-v2, all-mpnet-base-v2)
- Train on boat-specific corpus
- Evaluate embedding quality with similarity metrics
- A/B test different embedding models

**B. Vector Search Optimization**
- Analyze query-embedding similarity distributions
- Optimize Pinecone index configuration
- Implement query expansion techniques
- Build hybrid search scoring algorithms

**Python Tools**:
- **pinecone-client**: For Pinecone operations
- **numpy**: For vector calculations
- **scipy**: For statistical analysis
- **pandas**: For data analysis

##### Phase 5: Model Deployment & Integration (Month 4)

**A. Model Deployment**
- Deploy Python models as REST API service
- Use **FastAPI** or **Flask** for API endpoints
- Containerize with **Docker** for easy deployment
- Integrate with WordPress plugin via REST API calls
- Implement model versioning and rollback capabilities

**Python Deployment Stack**:
- **FastAPI**: High-performance API framework
- **Docker**: Containerization
- **Gunicorn/Uvicorn**: ASGI/WSGI servers
- **Redis**: Caching and queue management
- **PostgreSQL/MySQL**: Model metadata storage

**B. Integration with WordPress**
- Create REST API endpoints in WordPress to call Python service
- Implement fallback mechanisms if Python service unavailable
- Add monitoring and logging
- Cache model predictions in WordPress

**C. A/B Testing & Monitoring**
- Implement A/B testing framework
- Monitor model accuracy in production
- Track prediction confidence scores
- Collect user feedback on predictions
- Gradual rollout (10% → 50% → 100%)

**Python Monitoring Tools**:
- **MLflow**: Model tracking and monitoring
- **Prometheus**: Metrics collection
- **Grafana**: Visualization dashboards
- **Weights & Biases**: Experiment tracking

**D. Continuous Improvement**
- Collect feedback on misclassifications
- Retrain model monthly with new data
- Update patterns based on user behavior
- Maintain accuracy above 95%
- Implement active learning for difficult cases

**Python ML Ops Pipeline**:
- **Apache Airflow**: Workflow orchestration
- **Kubeflow**: ML pipeline management
- **DVC**: Data version control
- **GitHub Actions**: CI/CD for model training

#### 5.1.5 Expected Outcomes
- **Intent Accuracy**: 95%+ (up from ~85%)
- **Response Time**: 15% faster (fewer incorrect routes)
- **User Satisfaction**: 20% improvement
- **Cost Reduction**: 10-15% fewer API calls
- **Model Performance**: <100ms inference time
- **Scalability**: Handle 1000+ requests/second

---

### 5.2 Priority 2: Audio Recognition & Voice Integration ()

#### 5.2.1 Overview
Enable users to interact with the chatbot using voice commands, making the search experience more natural and accessible, especially on mobile devices. Python provides superior speech recognition and audio processing capabilities.

#### 5.2.2 Why Python for Audio Processing
- **Advanced Speech Libraries**: Whisper, SpeechRecognition, DeepSpeech
- **Audio Processing**: librosa, soundfile, pydub for audio manipulation
- **ML Models**: Access to state-of-the-art speech recognition models
- **Real-time Processing**: Efficient streaming audio processing
- **Model Training**: Ability to fine-tune speech models on boat-specific terminology

#### 5.2.3 Business Value
- **Mobile-First Experience**: 60% of users search on mobile - voice is natural
- **Accessibility**: Makes the platform accessible to users with disabilities
- **User Engagement**: Voice interaction increases engagement by 40%
- **Competitive Advantage**: Few boat search platforms offer voice search

#### 5.2.4 Technical Approach

##### Phase 1: Frontend Voice Input (Month 1)

**A. Web Speech API Integration**
- Implement browser-based speech recognition (JavaScript)
- Add voice input button to chat interface
- Handle real-time transcription
- Support multiple languages
- Send audio to Python backend for processing

**B. UI/UX Enhancements**
- Visual feedback during voice input
- Waveform animation
- Clear visual indicators
- Error handling and retry options

##### Phase 2: Python Backend Audio Processing (Month 2)

**A. Speech-to-Text Service (Python)**

**Approach**:
1. **OpenAI Whisper Integration**
   - Use OpenAI Whisper API via Python SDK
   - Support multiple audio formats (WAV, MP3, M4A, OGG)
   - Handle various languages and accents
   - Implement audio preprocessing for better accuracy

**Python Implementation Strategy**:
- **openai** Python SDK: For Whisper API integration
- **pydub**: Audio format conversion and preprocessing
- **librosa**: Advanced audio analysis and feature extraction
- **soundfile**: Audio file I/O operations
- **FastAPI**: REST API endpoints for audio processing

**B. Self-Hosted Whisper Model (Advanced Option)**

**Approach**:
1. **Deploy Whisper Model Locally**
   - Use OpenAI's open-source Whisper model
   - Deploy on GPU server for faster processing
   - Fine-tune on boat-specific terminology
   - Reduce API costs for high-volume usage

**Python Tools**:
- **whisper** (OpenAI): Pre-trained speech recognition model
- **torch**: PyTorch for model inference
- **transformers**: Hugging Face transformers library
- **ffmpeg-python**: Audio preprocessing
- **onnxruntime**: Optimized inference engine

**C. Alternative Speech Recognition Options**
- **Google Speech-to-Text API**: Via Python client library
- **Azure Speech Services**: Python SDK integration
- **AssemblyAI**: Python SDK for transcription
- **DeepSpeech**: Open-source speech recognition

##### Phase 3: Text-to-Speech Service (Month 3)

**A. Python TTS Implementation**

**Approach**:
1. **OpenAI TTS API**
   - Use OpenAI Text-to-Speech API via Python
   - Support multiple voices (alloy, echo, fable, onyx, nova, shimmer)
   - Cache audio responses for performance
   - Stream audio for long responses

**Python Tools**:
- **openai** Python SDK: For TTS API
- **pydub**: Audio format conversion
- **redis**: Caching audio responses
- **FastAPI**: API endpoints for TTS

**B. Self-Hosted TTS Models (Advanced)**

**Approach**:
1. **Deploy TTS Models Locally**
   - Use **Coqui TTS** or **Mozilla TTS**
   - Fine-tune voices for boat-specific terminology
   - Reduce API costs
   - Custom voice options

**Python Tools**:
- **TTS** (Coqui): Text-to-speech library
- **pyttsx3**: Offline TTS engine
- **gTTS**: Google Text-to-Speech
- **torch**: For neural TTS models

**C. Audio Response Optimization**
- Implement audio caching strategy
- Compress audio files for faster delivery
- Support multiple audio formats
- Implement streaming for long responses

#### 5.2.5 Expected Outcomes
- **Voice Input Adoption**: 30-40% of mobile users
- **Accessibility Score**: 95+ (WCAG 2.1 AA compliant)
- **User Engagement**: 40% increase in mobile sessions
- **Response Time**: <2 seconds for voice transcription
- **Transcription Accuracy**: 95%+ for clear audio

---

### 5.3 Priority 3: Image Recognition & Visual Search ()

#### 5.3.1 Overview
Enable users to search for boats by uploading images, making the platform more intuitive and allowing visual similarity searches. Python provides the most advanced computer vision and image processing capabilities.

#### 5.3.2 Why Python for Image Recognition
- **Computer Vision Libraries**: OpenCV, PIL/Pillow, scikit-image
- **Deep Learning Frameworks**: PyTorch, TensorFlow for vision models
- **Pre-trained Models**: CLIP, ResNet, EfficientNet, Vision Transformers
- **Image Processing**: Advanced image manipulation and preprocessing
- **Model Training**: Fine-tune models on boat-specific images

#### 5.3.3 Business Value
- **Visual Search**: Users can find boats by uploading photos
- **Reverse Image Search**: Find similar boats to uploaded images
- **Feature Recognition**: Identify boat features from images
- **Competitive Edge**: Unique feature in boat search market

#### 5.3.4 Technical Approach

##### Phase 1: Image Upload & Processing (Month 1)

**A. Image Upload API**
- Accept image uploads via REST API (WordPress)
- Support multiple formats (JPG, PNG, WebP)
- Validate and optimize images
- Store temporarily for processing
- Forward to Python service for analysis

**B. Python Image Processing Service**

**Approach**:
1. **Image Preprocessing**
   - Resize and normalize images
   - Handle different formats and sizes
   - Optimize for model input
   - Extract image metadata

**Python Tools**:
- **PIL/Pillow**: Image manipulation and processing
- **OpenCV**: Advanced image processing
- **numpy**: Image array operations
- **scikit-image**: Image processing algorithms

##### Phase 2: AI Vision Analysis (Month 2)

**A. OpenAI GPT-4 Vision Integration (Python)**

**Approach**:
1. **Image Analysis with GPT-4 Vision**
   - Use OpenAI Python SDK for vision API
   - Analyze uploaded images
   - Extract boat features, type, style
   - Generate search query from image
   - Identify boat characteristics

**Python Implementation**:
- **openai** Python SDK: For GPT-4 Vision API
- **base64**: Image encoding
- **PIL**: Image preprocessing
- **json**: Response parsing

**B. Self-Hosted Vision Models (Advanced)**

**Approach**:
1. **Deploy CLIP or Vision Transformer Models**
   - Use OpenAI CLIP for image understanding
   - Fine-tune on boat images
   - Reduce API costs
   - Faster inference

**Python Tools**:
- **transformers** (Hugging Face): CLIP, Vision Transformers
- **torch**: PyTorch for model inference
- **sentence-transformers**: CLIP models
- **onnxruntime**: Optimized inference

**C. Custom Boat Classification Model**

**Approach**:
1. **Train Custom Vision Model**
   - Collect boat images dataset
   - Label images by type, features, style
   - Train CNN or Vision Transformer
   - Deploy for real-time classification

**Python Tools**:
- **PyTorch/TensorFlow**: Model training
- **torchvision**: Pre-trained models and utilities
- **albumentations**: Image augmentation
- **wandb**: Experiment tracking

##### Phase 3: Visual Similarity Search (Month 3)

**A. Image Embedding Generation (Python)**

**Approach**:
1. **Generate Image Embeddings**
   - Use CLIP or ResNet for embeddings
   - Generate embeddings for uploaded images
   - Generate embeddings for all listing images
   - Store in Pinecone vector database
   - Compare similarities

**Python Implementation**:
- **sentence-transformers**: CLIP models for image embeddings
- **torch**: Model inference
- **numpy**: Vector operations
- **pinecone-client**: Vector database operations

**B. Visual Similarity Search Pipeline**

**Approach**:
1. **Similarity Search System**
   - Generate embedding for query image
   - Search Pinecone for similar images
   - Rank results by similarity score
   - Return matching boat listings

**Python Tools**:
- **FAISS**: Efficient similarity search (alternative to Pinecone)
- **scipy**: Distance calculations
- **pandas**: Result processing

**C. Image Indexing Service**

**Approach**:
1. **Batch Image Processing**
   - Process all listing images
   - Generate embeddings in batches
   - Index in Pinecone
   - Update when new images added

**Python Tools**:
- **multiprocessing**: Parallel image processing
- **celery**: Async task processing
- **redis**: Task queue management

##### Phase 4: Feature Recognition (Month 4)

**A. Boat Feature Detection Model (Python)**

**Approach**:
1. **Train Feature Detection Model**
   - Collect images with labeled features
   - Train object detection model (YOLO, Faster R-CNN)
   - Detect boat features: helipad, jacuzzi, flybridge, etc.
   - Match features to database listings

**Python Tools**:
- **ultralytics** (YOLO): Object detection
- **detectron2**: Facebook's object detection framework
- **torchvision**: Pre-trained detection models
- **opencv**: Image processing for detection

**B. Feature-Based Search**

**Approach**:
1. **Feature Extraction & Matching**
   - Extract features from uploaded image
   - Match to database feature fields
   - Filter listings by detected features
   - Provide feature-based recommendations

**Python Implementation**:
- **scikit-learn**: Feature matching algorithms
- **pandas**: Data processing
- **sqlalchemy**: Database queries

#### 5.3.4 Expected Outcomes
- **Visual Search Adoption**: 25-30% of users
- **Search Accuracy**: 80%+ match rate for similar boats
- **Feature Detection**: 90%+ accuracy for common features
- **User Engagement**: 35% increase in search sessions

---

### 5.4 Priority 4: Enhanced Boat Search Experience (Ongoing)

#### 5.4.1 Overview
Transform the chatbot into a comprehensive, intelligent boat search assistant that provides an exceptional user experience.

#### 5.4.2 Business Value
- **User Retention**: Increase return visits by 50%
- **Conversion Rate**: Improve listing views and inquiries by 40%
- **User Satisfaction**: Achieve 4.5+ star rating
- **Market Leadership**: Become the go-to platform for boat searches

#### 5.4.3 Technical Approach

##### A. Intelligent Query Understanding

**Multi-Intent Recognition (Python)**:
- Build Python service for complex query parsing
- Recognize multiple intents in one query
- Extract search criteria, filters, sort preferences
- Use NLP models (spaCy, transformers) for intent extraction
- Example: "Show me luxury yachts in Miami under $2M with a helipad"

**Python Approach**:
- **spaCy**: Named Entity Recognition and dependency parsing
- **transformers**: BERT-based models for intent classification
- **scikit-learn**: Multi-label classification
- **FastAPI**: REST API for query processing

**Contextual Understanding**:
- Remember previous queries in conversation
- Build on user preferences
- Suggest refinements
- Learn from user behavior
- Use Python ML models for context understanding

##### B. Advanced Filtering & Sorting

**Smart Filters**:
- Price range with sliders
- Length, year, condition filters
- Location with map integration
- Feature-based filtering
- Saved filter combinations

**Intelligent Sorting**:
- Relevance (default)
- Price (low to high, high to low)
- Length, year, location
- AI-recommended (based on user profile)

##### C. Rich Listing Display

**Enhanced Listing Cards**:
- High-quality images with gallery
- 360° virtual tours
- Video walkthroughs
- Interactive floor plans
- Key specifications highlighted
- Quick comparison features

**Listing Details Page**:
- Comprehensive information
- Image galleries
- Virtual tours
- Contact forms
- Share functionality
- Save to favorites

##### D. Personalization Engine

**User Profiles**:
- Track search history
- Save favorite listings
- Store search preferences
- Notification preferences

**Recommendation System (Python)**:
- Build Python recommendation engine
- Analyze user behavior and build profiles
- Use collaborative filtering and content-based filtering
- Find similar users using ML algorithms
- Generate personalized recommendations

**Python Implementation**:
- **scikit-learn**: Collaborative filtering algorithms
- **surprise**: Recommendation system library
- **pandas**: User behavior analysis
- **numpy**: Similarity calculations
- **FastAPI**: Recommendation API endpoints

**Recommendation Algorithms**:
- **Collaborative Filtering**: Find users with similar preferences
- **Content-Based Filtering**: Match listings to user preferences
- **Hybrid Approach**: Combine both methods
- **Deep Learning**: Use neural networks for complex patterns

##### E. Conversational Search Interface

**Natural Language Processing**:
- Handle complex, multi-part queries
- Understand follow-up questions
- Provide clarifying questions
- Suggest alternatives

**Conversation Flow**:
- Welcome message with suggestions
- Progressive refinement
- Context-aware responses
- Smooth transitions between topics

##### F. Performance Optimizations

**Caching Strategy**:
- Cache popular searches
- Pre-generate common queries
- Optimize database queries
- CDN for images and assets

**Load Time Optimization**:
- Lazy load listings
- Progressive image loading
- Code splitting
- Service worker for offline support

#### 5.4.4 Implementation Timeline

**Month 1-2**: Core enhancements
- Multi-intent recognition
- Advanced filtering
- Rich listing display

**Month 3-4**: Personalization
- User profiles
- Recommendation engine
- Saved searches

**Month 5-6**: Polish & Optimization
- Performance optimization
- UI/UX refinements
- Testing and bug fixes

#### 5.4.5 Expected Outcomes
- **Search Accuracy**: 95%+ relevant results
- **User Satisfaction**: 4.5+ stars
- **Conversion Rate**: 40% increase
- **Return Visits**: 50% increase
- **Average Session Time**: 3+ minutes

---

### 5.5 Python Deployment Architecture

#### 5.5.1 Architecture Overview

**Microservices Approach**:
- Separate Python services for different AI capabilities
- Each service independently scalable
- REST API communication between services
- WordPress plugin calls Python services via REST API

**Service Structure**:
```
┌─────────────────────────────────────────────────┐
│         WordPress Plugin (PHP)                  │
│  - User Interface                               │
│  - Request Routing                              │
└─────────────────┬───────────────────────────────┘
                  │ REST API Calls
                  ▼
┌─────────────────────────────────────────────────┐
│      Python AI Services (FastAPI)              │
│                                                  │
│  ┌──────────────────────────────────────────┐  │
│  │ Intent Classification Service            │  │
│  │ - BERT/Transformer models                │  │
│  │ - Intent classification                  │  │
│  └──────────────────────────────────────────┘  │
│                                                  │
│  ┌──────────────────────────────────────────┐  │
│  │ Speech Recognition Service               │  │
│  │ - Whisper model                          │  │
│  │ - Audio transcription                   │  │
│  └──────────────────────────────────────────┘  │
│                                                  │
│  ┌──────────────────────────────────────────┐  │
│  │ Image Recognition Service                │  │
│  │ - CLIP/Vision models                     │  │
│  │ - Image analysis                        │  │
│  └──────────────────────────────────────────┘  │
│                                                  │
│  ┌──────────────────────────────────────────┐  │
│  │ Recommendation Service                   │  │
│  │ - ML recommendation algorithms           │  │
│  │ - User profiling                        │  │
│  └──────────────────────────────────────────┘  │
└─────────────────────────────────────────────────┘
```

#### 5.5.2 Technology Stack

**Core Python Stack**:
- **FastAPI**: High-performance API framework
- **Uvicorn/Gunicorn**: ASGI/WSGI servers
- **Docker**: Containerization
- **Redis**: Caching and task queues
- **PostgreSQL/MySQL**: Model metadata and logs

**ML/AI Stack**:
- **PyTorch/TensorFlow**: Deep learning frameworks
- **Hugging Face Transformers**: Pre-trained models
- **scikit-learn**: Traditional ML algorithms
- **spaCy**: NLP processing
- **OpenCV**: Computer vision

**Deployment Stack**:
- **Docker Compose**: Local development
- **Kubernetes**: Production orchestration (optional)
- **AWS/GCP/Azure**: Cloud deployment
- **Nginx**: Reverse proxy and load balancing

#### 5.5.3 Deployment Options

**Option 1: Self-Hosted Server**
- Deploy Python services on dedicated server
- Full control over models and data
- Lower long-term costs
- Requires infrastructure management

**Option 2: Cloud Platform (AWS/GCP/Azure)**
- **AWS**: EC2, ECS, Lambda, SageMaker
- **GCP**: Compute Engine, Cloud Run, Vertex AI
- **Azure**: Virtual Machines, Container Instances, ML Services
- Scalable and managed infrastructure

**Option 3: Hybrid Approach**
- Critical models on cloud (high availability)
- Non-critical models self-hosted (cost savings)
- Load balancing between services

#### 5.5.4 Integration with WordPress

**API Communication**:
- WordPress plugin makes REST API calls to Python services
- JSON request/response format
- Authentication via API keys
- Error handling and fallbacks

**Caching Strategy**:
- Cache model predictions in WordPress (Redis/Transients)
- Reduce API calls for repeated queries
- Cache expiration based on query type

**Performance Optimization**:
- Async API calls where possible
- Batch processing for multiple requests
- Connection pooling
- Request queuing for high load

---

### 5.6 Implementation Roadmap Summary

| Priority | Feature | Timeline | Status |
|----------|---------|----------|--------|
| 1 | Advanced Intent Identification | | Planned |
| 2 | Audio Recognition & Voice |  | Planned |
| 3 | Image Recognition & Visual Search |  | Planned |
| 4 | Enhanced Search Experience | Ongoing | Planned |

---

### 5.7 Success Metrics

#### Key Performance Indicators (KPIs)

1. **Intent Classification Accuracy**: Target 95%+
2. **Voice Input Adoption**: Target 30%+ of mobile users
3. **Image Search Usage**: Target 25%+ of users
4. **User Satisfaction Score**: Target 4.5+ stars
5. **Search Result Relevance**: Target 90%+ satisfaction
6. **Response Time**: Target <2 seconds average
7. **Conversion Rate**: Target 40% increase
8. **User Retention**: Target 50% increase in return visits

---

## 6. Scraper Documentation

> **Note**: This section is reserved for scraper documentation. The scraper documentation will be added here when available.

### 6.1 Scraper Overview
*[To be added]*

### 6.2 Scraper API Integration
*[To be added]*

### 6.3 Sync Endpoints Usage
*[To be added]*

### 6.4 Error Handling
*[To be added]*

### 6.5 Best Practices
*[To be added]*

---

## Appendix

### A. File Structure
```
boat-chatbot/
├── assets/
│   ├── admin.css
│   ├── admin.js
│   ├── chatbot-full.css
│   ├── chatbot-full.js
│   ├── frontend.css
│   ├── frontend.js
│   ├── landing.css
│   ├── landing.js
│   ├── images/
│   └── video/
├── includes/
│   ├── class-admin-settings.php
│   ├── class-chatbot-handler.php
│   ├── class-database-manager.php
│   ├── class-frontend-shortcode.php
│   ├── class-groq-embeddings-manager.php
│   ├── class-landing-page.php
│   ├── class-pinecone-manager.php
│   ├── class-redis-cache-manager.php
│   ├── class-vector-sync-manager.php
│   └── class-wp-cli-sync-command.php
├── boat-chatbot.php
└── COMPREHENSIVE_DOCUMENTATION.md
```

### B. Database Tables

#### wp_boat_chatbot_logs
- Stores conversation logs
- Created on plugin activation

#### wp_boat_chatbot_vector_sync
- Tracks vector sync status
- Created on plugin activation

### C. Constants
- `BOAT_CHATBOT_VERSION`: Plugin version
- `BOAT_CHATBOT_PLUGIN_URL`: Plugin URL
- `BOAT_CHATBOT_PLUGIN_PATH`: Plugin path

### D. WordPress Hooks

#### Actions
- `boat_chatbot_async_log`: Async logging hook
- `boat_chatbot_daily_sync`: Daily sync cron job

#### Filters
- `query_vars`: Adds custom query vars for landing page

### E. Dependencies
- **PHP**: 7.4+
- **WordPress**: 5.0+
- **PHP Extensions**: 
  - mysqli (for database)
  - curl (for API calls)
  - redis (optional, for caching)
  - parallel (optional, for parallel processing)

---

---

## 7. Recent Updates & Enhancements

### 7.1 Price Extraction Enhancement (Latest)
**Date**: 2024

**Feature**: Enhanced price recognition for budget/price/cost queries without direction words

**Details**:
- When users specify price with "budget", "price", or "cost" without direction words (e.g., "budget is 200k"), the system automatically treats it as "under" (max_price)
- Examples that now work:
  - "budget is 200k" → interpreted as "budget is under 200k"
  - "price is 500" → interpreted as "price is under 500"
  - "cost is 1000" → interpreted as "cost is under 1000"
  - "my budget is 200k" → interpreted as "my budget is under 200k"

**Implementation**: `includes/class-database-manager.php` (Pattern 6.5, lines 815-823)

### 7.2 Help Modal Feature (Latest)
**Date**: 2024

**Feature**: Customizable help modal with admin-configurable content

**Details**:
- Help button opens a modal dialog with usage instructions
- Content is fully editable from admin settings (UI Settings → Help Modal Description)
- Supports HTML formatting with `boat-help-section` class
- Responsive design with smooth animations
- Closes via close button, overlay click, or Escape key

**Implementation**:
- Admin Setting: `includes/class-admin-settings.php` (lines 797-840)
- Modal HTML: `includes/class-landing-page.php` (lines 659-703)
- JavaScript: `assets/landing.js` (lines 1240-1274)
- CSS: `assets/landing.css` (lines 1674-1851)

### 7.3 Translation Button Feature (Latest)
**Date**: 2024

**Feature**: Translation button shows "coming soon" message

**Details**:
- Translation button displays temporary "This feature coming soon" message
- Auto-dismisses after 2 seconds
- Smooth fade-in/fade-out animation

**Implementation**: `assets/landing.js` (lines 1276-1294)

### 7.4 Mobile Keyboard Dismissal (Latest)
**Date**: 2024

**Feature**: Input field automatically loses focus after message is sent

**Details**:
- Input field calls `blur()` after message is successfully sent
- Works for both send button click and Enter key press
- Dismisses mobile virtual keyboard automatically
- Improves user experience on mobile devices

**Implementation**: `assets/landing.js` (lines 287-290, 299-300, 310-312, 321-322, 354, 358, 579-580, 657-658)

### 7.5 Responsive Button Improvements (Latest)
**Date**: 2024

**Feature**: Enhanced responsive button layout and behavior

**Details**:
- Buttons maintain size on mobile (no shrinking)
- Send button aligned to right on mobile
- Improved flexbox layout for better button organization
- Action buttons container prevents shrinking below content size

**Implementation**:
- JavaScript: `assets/landing.js` (lines 97-221)
- CSS: `assets/landing.css` (lines 659-681, 696-706)

### 7.6 Code Cleanup (Latest)
**Date**: 2024

**Removed Unnecessary Code**:
- Removed unused variables: `isSending`, `landingChatHistory`, `currentConversationSection`
- Removed dead functions: `createNewConversationSection()`, `displayLandingListings()`, `showLandingTypingIndicator()`
- Simplified code in `loadMoreLandingListings()`
- Reduced file size by ~200 lines

**Files Affected**: `assets/landing.js`

---

**Document Version**: 1.1.0  
**Last Updated**: 2024  
**Maintained By**: Development Team

