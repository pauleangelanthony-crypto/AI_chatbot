# Decision Tree: Message Handling to Response Flow

This document illustrates the complete decision tree for processing user messages and generating responses in the Boat Chatbot plugin.

## Main Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    User Message Received                     │
│              (REST API or AJAX Request)                      │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│              Security Check (Nonce Verification)             │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│              Check Cache (message hash)                      │
│                                                               │
│  Cache Key: 'message_' + MD5(user_message)                   │
└───────────────────────┬─────────────────────────────────────┘
                        │
            ┌───────────┴───────────┐
            │                       │
        CACHED?                 NOT CACHED
            │                       │
            ▼                       ▼
    ┌───────────────┐     ┌──────────────────────┐
    │ Return Cached │     │ Continue Processing  │
    │   Response    │     │                      │
    └───────┬───────┘     └──────────┬───────────┘
            │                        │
            │                        ▼
            │            ┌──────────────────────────┐
            │            │  Classify Intent          │
            │            │  (check intent cache)    │
            │            └───────────┬──────────────┘
            │                        │
            │            ┌───────────┴───────────┐
            │            │                       │
            │        CACHED?                 NOT CACHED
            │            │                       │
            │            ▼                       ▼
            │    ┌──────────────┐      ┌──────────────────┐
            │    │ Return       │      │ Analyze Message  │
            │    │ Cached Intent│      │                 │
            │    └──────┬───────┘      └────────┬───────┘
            │            │                       │
            │            └───────────┬───────────┘
            │                        │
            │                        ▼
            │            ┌──────────────────────────┐
            │            │  Intent Classification    │
            │            │                          │
            │            │  Step 1: Check Keywords:  │
            │            │  - list, show, find      │
            │            │  - price, cost, buy      │
            │            │  - location, type         │
            │            │                          │
            │            │  Step 2: Check Patterns: │
            │            │  - Price patterns         │
            │            │  - Currency symbols       │
            │            │                          │
            │            │  Step 3: Hybrid Detection:│
            │            │  - Semantic indicators    │
            │            │  - Complex queries       │
            │            │  - Natural language       │
            └────────────┴───────────┬──────────────┘
                                     │
                    ┌────────────────┼────────────────┐
                    │                │                │
                    ▼                ▼                ▼
        ┌──────────────────────┐  ┌──────────────┐  ┌──────────────────────────┐
        │  database_query       │  │   hybrid     │  │  general_knowledge       │
        │  (Intent = SQL)       │  │  (Intent =   │  │  (Intent = General)      │
        │                       │  │  Hybrid)     │  │                          │
        │  - Structured query   │  │              │  │  - No DB Query            │
        │  - Exact matches      │  │  - Semantic  │  │  - General AI question    │
        │  - SQL filters        │  │    search    │  │                          │
        └──────────┬───────────┘  └──────┬───────┘  └──────────┬───────────────┘
                   │                      │                     │
                   │                      │                     ▼
                   │                      │         ┌──────────────────────────┐
                   │                      │         │  Get AI Response         │
                   │                      │         │  (No DB Query)            │
                   │                      │         │                          │
                   │                      │         │  - Build prompt         │
                   │                      │         │  - Call Grok API         │
                   │                      │         │  - Filter blocked sites   │
                   │                      │         └──────────┬───────────────────┘
                   │                      │             │
                   │                      │             │
                   ▼                      ▼             │
    ┌─────────────────────────────────────┘             │
    │                                                  │
    │  ┌──────────────────────────────────────┐       │
    │  │  handle_database_query_optimized()   │       │
    │  └──────────────┬───────────────────────┘       │
    │                 │                                 │
    │                 ▼                                 │
    │  ┌──────────────────────────────────────┐            │
    │  │  Query SQL Database                  │            │
    │  │  - Extract search terms            │            │
    │  │  - Build WHERE clause              │            │
    │  │  - Execute query (limit 15)        │            │
    │  │  - Get total count                 │            │
    │  └──────────────┬───────────────────────┘            │
    │                 │                                 │
    └─────────────────┴─────────────────────────────────┘
                     │
                     ▼
    ┌──────────────────────────────────────┐
    │  handle_hybrid_query_optimized()     │
    │                                      │
    │  Step 1: Vector Database Search     │
    │  - Convert query to embedding        │
    │  - Semantic similarity search        │
    │  - Get top N similar listings        │
    │                                      │
    │  Step 2: SQL Filter Application      │
    │  - Extract structured filters        │
    │  - Apply price/length/location       │
    │  - Filter vector results             │
    │                                      │
    │  Step 3: Merge & Rank Results       │
    │  - Combine vector + SQL results       │
    │  - Apply relevance scoring           │
    └──────────────┬───────────────────────┘
                   │
                   ▼
    ┌──────────────────────────────────────┐
    │  Results Found?                      │
    └──────────────┬───────────────────────┘
                   │
     ┌─────────────┴─────────────┐
     │                           │
   YES                         NO
     │                           │
     │                           ▼
     │            ┌──────────────────────────┐
     │            │  Try Broader Search       │
     │            │  (fallback to SQL only)   │
     │            └───────────┬───────────────┘
     │                        │
     │            ┌───────────┴───────────┐
     │            │                       │
     │        FOUND?                 NOT FOUND
     │            │                       │
     │            ▼                       ▼
     │    ┌──────────────┐      ┌──────────────────┐
     │    │ Process       │      │ Return Error     │
     │    │ Results       │      │ Message         │
     │    └───────┬───────┘      └──────────────────┘
     │            │
     └────────────┘
                  │
                  ▼
    ┌──────────────────────────────────────┐
    │  process_results_for_ai()           │
    │  (Deduplication, Scoring, Sorting)   │
    └──────────────┬───────────────────────┘
                   │
                   ▼
    ┌──────────────────────────────────────┐
    │  format_listings_for_ai()            │
    │  - Apply format template             │
    │  - Replace placeholders              │
    │  - Limit to token budget (~450)      │
    └──────────────┬───────────────────────┘
                   │
                   ▼
    ┌──────────────────────────────────────┐
    │  get_ai_response_optimized()         │
    │                                      │
    │  - Build prompt with:                │
    │    * Tone of voice                  │
    │    * Blocked websites restriction   │
    │    * Formatted listings             │
    │    * User message                   │
    │                                      │
    │  - Call Grok API                    │
    │  - Handle errors                    │
    │  - Filter blocked websites          │
    └──────────────┬───────────────────────┘
                   │
                   ▼
    ┌──────────────────────────────────────┐
    │  Cache Response                      │
    │  - Store in transient cache          │
    │  - Include response, intent, listings│
    └──────────────┬───────────────────────┘
                   │
                   ▼
    ┌──────────────────────────────────────┐
    │  Log Interaction                     │
    │  - Save to wp_boat_chatbot_logs      │
    │  - Include performance metrics        │
    └──────────────┬───────────────────────┘
                   │
                   ▼
    ┌──────────────────────────────────────┐
    │  Return Response                     │
    │                                      │
    │  {                                   │
    │    success: true,                    │
    │    data: {                           │
    │      response: AI response,          │
    │      intent: classified intent,       │
    │      listings: processed listings,   │
    │      total_listings: count,         │
    │      response_time: seconds,         │
    │      performance_log: {...},          │
    │      cached: false                   │
    │    }                                  │
    │  }                                   │
    └──────────────────────────────────────┘
```

## Detailed Decision Points

### 1. Cache Check
- **Key**: `'message_' + MD5(user_message)`
- **If Cached**: Return immediately with cached response
- **If Not Cached**: Continue to intent classification

### 2. Intent Classification
- **Cache Key**: `'intent_' + MD5(message)`
- **Methods**:
  - Keyword matching (list, show, find, price, etc.)
  - Price pattern matching (regex)
  - Location/type keyword detection
  - Hybrid detection (semantic indicators)
- **Outcomes**:
  - `database_query`: Structured SQL query with exact matches
  - `hybrid`: Semantic search (vector DB) + SQL filters
  - `general_knowledge`: General AI question (no database)

#### Hybrid Intent Detection Logic

A query should be classified as `hybrid` when it meets **ALL** of the following criteria:

1. **Has Database Query Indicators** (must have at least one):
   - Contains keywords: list, show, find, search, price, cost, buy, sale, listing
   - Contains location keywords: where, location, area, region, city, place
   - Contains boat type keywords: boat, yacht, vessel, craft, ship, sailboat, powerboat
   - Contains price patterns: "under $X", "how much", currency symbols

2. **Has Semantic/Complex Query Indicators** (must have at least one):
   - **Natural language descriptions**: Contains descriptive phrases like "luxury", "comfortable", "fast", "spacious", "well-maintained", "family-friendly"
   - **Subjective criteria**: Words like "best", "good", "nice", "beautiful", "modern", "classic", "popular"
   - **Feature-based queries**: Mentions specific features like "with galley", "sleeps 6", "has generator", "air conditioning"
   - **Use case queries**: "for fishing", "for cruising", "for racing", "for charter"
   - **Complex multi-criteria**: Combines multiple subjective criteria (e.g., "luxury yacht under $500k in Miami")
   - **Question format**: Natural questions like "What boats are good for beginners?" or "Which yachts have the best amenities?"
   - **Comparative queries**: "better", "compared to", "similar to", "like"
   - **Longer queries**: Typically 10+ words indicating complex intent

3. **Has Structured Filters** (optional but common):
   - Price ranges, length specifications, location, year ranges
   - These will be applied as SQL filters on vector DB results

**Examples of Hybrid Queries:**
- "Show me luxury yachts under $2 million in Florida with modern amenities"
- "Find comfortable sailboats for long-distance cruising"
- "What are the best family-friendly boats with good safety features?"
- "I'm looking for a well-maintained fishing boat that's good for beginners"
- "Show me spacious catamarans similar to Lagoon models"

**Examples of Non-Hybrid Queries:**
- "Show boats under $100k" → `database_query` (structured, no semantic)
- "What is a sailboat?" → `general_knowledge` (no database query)
- "List yachts in Miami" → `database_query` (structured filters only)
- "How do I maintain a boat?" → `general_knowledge` (general question)

### 3. Database Query Path (SQL Only)
- **Query Database**: Extract search terms, build WHERE clause, execute
- **Results Check**:
  - **If Results Found**: Process and score
  - **If No Results**: Try broader search (no filters)
    - **If Broader Search Finds Results**: Process those
    - **If Still No Results**: Return error message

### 3a. Hybrid Query Path (Vector DB + SQL)
- **Step 1: Vector Database Search**
  - Convert user query to embedding vector using embedding API
  - Perform semantic similarity search in vector database
  - Retrieve top N most semantically similar listings (e.g., top 50)
  - Results ranked by cosine similarity / distance score
  
- **Step 2: SQL Filter Application**
  - Extract structured filters from query (price, length, location, year, etc.)
  - Apply SQL WHERE clause filters to vector DB results
  - Filter out results that don't match structured criteria
  - This combines semantic relevance with exact filter matching
  
- **Step 3: Merge & Rank Results**
  - Combine vector similarity scores with SQL filter matches
  - Apply relevance scoring algorithm
  - Deduplicate results
  - Sort by combined relevance score
  
- **Results Check**:
  - **If Results Found**: Process and format for AI
  - **If No Results**: Fallback to SQL-only search
    - **If SQL Search Finds Results**: Process those
    - **If Still No Results**: Return error message

**Hybrid Approach Benefits:**
- **Semantic Understanding**: Finds boats based on meaning, not just keywords
- **Natural Language**: Handles complex, descriptive queries
- **Precision**: SQL filters ensure exact criteria are met
- **Best of Both**: Combines semantic relevance with structured filtering

### 4. Results Processing
- **Deduplication**: Remove duplicates by ID or composite key
- **Relevance Scoring**: Calculate score based on:
  - Field matches (vessel name, type, manufacturer, etc.)
  - Exact phrase matches
  - Price range compatibility
  - Length proximity
  - Year matching
- **Sorting**: Sort by relevance score (highest first)

### 5. AI Response Generation
- **Build Prompt**: Include tone, restrictions, listings, user message
- **Call Grok API**: Send request with proper headers
- **Error Handling**: Handle API errors gracefully
- **Filter Response**: Remove blocked website references

### 6. Response Caching & Logging
- **Cache**: Store response for future identical queries
- **Log**: Save interaction to database with performance metrics
- **Return**: Send formatted response to client

## Performance Optimizations

1. **Caching**: Both message responses and intent classifications are cached
2. **Parallel Processing**: Database queries and AI calls can run in parallel where possible
3. **Token Limiting**: Results are formatted to stay within token budget
4. **Result Processing**: Deduplication and scoring happen before AI formatting to optimize relevance

## Error Handling

- **Database Connection Errors**: Return user-friendly error message
- **API Errors**: Return fallback message, log error details
- **Empty Results**: Try broader search, then return helpful message
- **Security Failures**: Return 403 error with appropriate message

## Key Methods Reference

- `handle_rest_message()`: Main entry point for REST API
- `classify_intent()`: Determines query type (database_query, hybrid, or general_knowledge)
- `handle_database_query_optimized()`: Handles SQL-only database queries
- `handle_hybrid_query_optimized()`: Handles hybrid queries (vector DB + SQL filters)
- `handle_general_query_optimized()`: Handles general questions (no database)
- `process_results_for_ai()`: Deduplication, scoring, sorting
- `format_listings_for_ai()`: Formats results for AI context
- `get_ai_response_optimized()`: Calls Grok API and processes response

## Hybrid Intent Implementation Notes

### Vector Database Requirements
- **Embedding Generation**: Need embedding API (OpenAI, Grok, or similar) to convert queries to vectors
- **Vector Storage**: Vector database (Pinecone, Weaviate, Qdrant, pgvector, etc.) storing boat listing embeddings
- **Embedding Fields**: Typically embed: VesselName, Description, Summary, Type, Manufacturer, Model
- **Similarity Metric**: Cosine similarity or Euclidean distance for ranking

### Hybrid Query Flow
1. **Query → Embedding**: Convert user query to embedding vector
2. **Vector Search**: Find semantically similar listings (top 50-100)
3. **Extract Filters**: Parse structured filters (price, length, location, year)
4. **Apply SQL Filters**: Filter vector results by structured criteria
5. **Merge & Score**: Combine similarity scores with filter matches
6. **Process**: Deduplicate, score, sort results
7. **Format**: Prepare for AI context
8. **AI Response**: Send to Grok with formatted results

### Performance Considerations
- **Vector Search Speed**: Vector DB queries are fast but embedding generation adds latency
- **Caching**: Cache embeddings for common queries
- **Parallel Processing**: Can run vector search and filter extraction in parallel
- **Result Limiting**: Limit vector results before SQL filtering to improve performance

