# Chatbot Test Messages

This document provides comprehensive test message examples to verify all chatbot functionality.

## Database Query Tests (Intent: database_query)

### Boat Type Searches
```
Show me sailboats
Find powerboats
I'm looking for a yacht
Do you have any catamarans?
Show me fishing boats
I want to see pontoon boats
Find me a speedboat
Show cruisers
```

### Location-Based Searches
```
Show me boats in Miami
Find listings in Seattle
I'm looking for boats in San Francisco
Show me boats in Fort Lauderdale
Find boats in Boston
Do you have boats in New York?
Show me listings in Los Angeles
Find boats in Chicago
```

### Price-Based Searches
```
Show me boats under $100,000
Find boats less than $50,000
I'm looking for boats under $200k
Show me boats under $50000
Find boats max $150,000
Show listings under $75,000
```

### Length-Based Searches
```
Show me 30 foot boats
Find boats that are 40 feet
I'm looking for a 25 foot boat
Show me 50ft boats
Find boats that are 35 feet long
Show listings for 20 foot boats
```

### Combined Searches
```
Show me sailboats in Miami under $100,000
Find 30 foot powerboats in Seattle
I'm looking for yachts in San Francisco under $500,000
Show me fishing boats in Florida under $50,000
Find 40 foot catamarans in Miami
Show me boats in Boston that are 25 feet and under $75,000
Find sailboats under $150k in San Diego
```

## Hybrid Query Tests (Intent: hybrid_query)

Hybrid queries combine semantic/vector search (natural language understanding) with structured SQL filters. These test the vector database + SQL filtering capabilities.

### Luxury & Premium Queries
```
Show me luxury yachts under $2 million in Florida
Find premium sailboats with modern amenities
I'm looking for high-end boats in Miami
Show me luxury catamarans under $1.5 million
Find upscale powerboats with all the features
What are the most luxurious boats you have?
Show me premium yachts in Fort Lauderdale
Find boats with luxury finishes and amenities
```

### Comfort & Spaciousness Queries
```
Find comfortable sailboats for long-distance cruising
Show me spacious catamarans that sleep 6 or more
I want a boat with plenty of room for my family
What boats have comfortable cabins?
Show me roomy yachts under $500k
Find boats with good headroom and living space
I'm looking for a comfortable boat for extended trips
Show me boats with spacious interiors
```

### Feature-Based Queries
```
Find boats with a full galley and generator
Show me sailboats that have air conditioning
I want a boat with GPS, autopilot, and radar
Find boats with a tender and water toys included
Show me yachts with stabilizers and bow thrusters
What boats have a flybridge and multiple cabins?
Find boats with modern electronics and navigation
Show me boats that include fishing equipment
```

### Use Case Queries
```
Find boats good for beginners
Show me boats suitable for fishing
What boats are best for long-distance cruising?
Find boats perfect for day trips
Show me boats ideal for charter business
What boats work well for racing?
Find boats good for family outings
Show me boats suitable for liveaboard
```

### Condition & Maintenance Queries
```
Show me well-maintained boats in excellent condition
Find boats that have been recently refitted
I want a boat in pristine condition
Show me boats with good maintenance records
Find boats that are turnkey ready
What boats are in like-new condition?
Show me boats that have been well cared for
Find boats with recent upgrades and improvements
```

### Subjective Criteria Queries
```
What are the best sailboats under $200k?
Show me the nicest yachts in Miami
Find beautiful boats that catch the eye
What boats have the best reviews?
Show me popular boats that sell quickly
Find boats with the best value for money
What are the most reliable boat brands?
Show me boats with excellent craftsmanship
```

### Complex Multi-Criteria Queries
```
Show me luxury yachts under $2 million in Florida with modern amenities and stabilizers
Find comfortable sailboats for long-distance cruising that are 40-50 feet and under $500k
I'm looking for a well-maintained fishing boat in Miami that's good for beginners
Show me spacious catamarans similar to Lagoon models under $800k
Find boats with a full galley, generator, and air conditioning under $300k
What are the best family-friendly boats in San Francisco under $400k?
Show me luxury powerboats with all modern features in Fort Lauderdale
Find boats perfect for charter that are 50+ feet and well-equipped
```

### Question Format Hybrid Queries
```
What boats are good for beginners under $100k?
Which yachts have the best amenities in Miami?
What are the most comfortable boats for long trips?
Which boats are best for fishing in Florida?
What sailboats are similar to Beneteau models?
Which boats have the most modern electronics?
What are the best value boats under $200k?
Which yachts are most suitable for charter?
```

### Comparative Queries
```
Show me boats similar to Lagoon catamarans
Find boats like Beneteau sailboats but cheaper
What boats are comparable to Azimut yachts?
Show me boats better than typical fishing boats
Find boats similar to what I saw but in Miami
What boats are like luxury yachts but more affordable?
Show me boats comparable to the one I'm interested in
Find boats similar to popular models but newer
```

### Style & Design Queries
```
Show me modern boats with contemporary styling
Find classic boats with traditional design
I want boats with sleek, modern aesthetics
Show me boats with elegant interior design
Find boats with minimalist, clean designs
What boats have the most stylish exteriors?
Show me boats with custom design features
Find boats with unique and distinctive styling
```

### Performance Queries
```
Show me fast boats that can cruise at high speeds
Find boats with good fuel efficiency
What boats have the best range for long trips?
Show me boats with powerful engines
Find boats that handle well in rough seas
What boats are known for excellent performance?
Show me boats with advanced hull designs
Find boats optimized for speed and efficiency
```

### Age & Year Queries (Combined with Semantic)
```
Show me modern boats from recent years under $500k
Find classic boats from the 80s that are well-maintained
I want newer boats with latest technology
Show me boats from the last 5 years with modern features
Find vintage boats that have been restored
What boats from recent years have the best features?
Show me boats built after 2015 with modern amenities
Find boats from the 90s that are in excellent condition
```

### Location + Semantic Queries
```
Show me luxury boats in the Florida Keys
Find comfortable boats for cruising in the Caribbean
What boats are good for Pacific Northwest waters?
Show me boats suitable for Mediterranean cruising
Find boats perfect for East Coast sailing
What boats work well in San Francisco Bay?
Show me boats ideal for tropical waters
Find boats suitable for cold weather cruising
```

### Price Range + Semantic Queries
```
Show me affordable luxury boats under $300k
Find budget-friendly boats that are still well-equipped
What are the best value boats between $200k and $400k?
Show me expensive boats worth the investment
Find mid-range boats with premium features
What boats offer the most for under $150k?
Show me boats in the $500k-$1M range with all features
Find boats that are reasonably priced but well-maintained
```

### Length + Semantic Queries
```
Show me spacious 40-foot boats with good amenities
Find comfortable boats around 50 feet for long trips
What are the best boats in the 30-40 foot range?
Show me boats 60+ feet with luxury features
Find boats around 35 feet that are well-equipped
What boats in the 45-55 foot range are most comfortable?
Show me boats 25-30 feet that are good for beginners
Find boats 50+ feet suitable for charter
```

### Manufacturer + Semantic Queries
**NOTE: These queries now use Pinecone metadata filters for MUCH faster performance!**

#### Single Manufacturer (Tests Pinecone Filter)
```
Show me Everglades boats
Find Sea Ray yachts
What Bertram boats are available?
Show me Princess yachts
Find me a Hatteras
Display all Azimut boats
What Ferretti yachts do you have?
Show Sunseeker inventory
Find Beneteau sailboats
```

#### Manufacturer + Price (Tests Combined Filters)
```
Show me Everglades boats under $500k
Find Sea Ray yachts between $200k and $800k
What Bertram boats are available under $1M?
Show me Princess yachts over $2M
Find affordable Beneteau sailboats under $100k
Display Azimut boats in the $500k-$1M range
```

#### Manufacturer + Location (Tests Combined Filters)
```
Show me Everglades boats in Florida
Find Sea Ray yachts in Miami
What Bertram boats are available in Fort Lauderdale?
Show me Princess yachts on the East Coast
Find Hatteras boats in North Carolina
Display Sunseeker yachts in California
```

#### Manufacturer + Length (Tests Combined Filters)
```
Show me Everglades boats under 35 feet
Find Sea Ray yachts 40-50 feet
What Bertram boats over 60 feet are available?
Show me Princess yachts around 55 feet
Find compact Beneteau sailboats under 30 feet
```

#### Manufacturer + Multiple Filters (Tests Complex Filtering)
```
Show me Everglades center console under 35 feet in Florida under $300k
Find Sea Ray yachts 45-55 feet in Miami between $500k-$1M
What Bertram sportfish over 50 feet in Fort Lauderdale under $800k?
Show me Princess yachts 60+ feet on the East Coast over $2M
Find Beneteau sailboats 35-45 feet in good condition under $200k
```

#### Manufacturer + Semantic Qualities (Tests Hybrid Search)
```
Show me Beneteau sailboats that are well-maintained
Find Azimut yachts with modern features
What Lagoon catamarans are in excellent condition?
Show me Hatteras boats that are turnkey ready
Find Sunseeker yachts with luxury amenities
What Beneteau models are best for cruising?
Show me Ferretti boats with all the latest features
Find Everglades center consoles with fishing equipment
What Sea Ray yachts have entertainment systems?
```

#### Case Sensitivity Tests (Should All Work)
```
Show me everglades boats (lowercase)
Find EVERGLADES boats (uppercase)
What EvErGlAdEs boats are available? (mixed case)
Show me sea ray yachts (lowercase multi-word)
Find SEA RAY yachts (uppercase multi-word)
```

#### Multi-Word Manufacturers
```
Show me Sea Ray yachts
Find Boston Whaler boats
What Hans Christian sailboats are available?
Show me Palmer Johnson yachts
Find C&C sailboats (special characters)
Display O'Day boats (apostrophe)
```

### Natural Language Variations
```
What boats do you have available?
Can you show me some listings?
I want to buy a boat
What's for sale?
Show me your inventory
What boats are available in Miami?
Do you have any listings?
I'm shopping for a boat
What can you find for me?
```

### Price Variations
```
What boats cost under $100k?
Show me affordable boats
Find cheap boats
What's the cheapest boat you have?
Show me expensive yachts
Find boats around $200,000
What boats are under $50k?
```

### Location Variations
```
Boats in Florida
Show me West Coast listings
What do you have in California?
Find boats near Miami
Show me East Coast boats
What's available in the Pacific Northwest?
```

## General Knowledge Tests (Intent: general_knowledge)

### Boating Questions
```
What is a catamaran?
How do sailboats work?
What's the difference between a yacht and a boat?
Tell me about powerboats
What should I know about boat maintenance?
How do I choose the right boat?
What are the best boat types for fishing?
Tell me about boat safety
```

### General Questions
```
Hello
How are you?
What can you help me with?
Tell me about boating
What do you know about boats?
Help me understand boats
Can you give me boating advice?
What's your favorite type of boat?
```

## Edge Cases & Error Handling

### Empty/Invalid Inputs
```
(empty message)
?
...
12345
@@@@@
```

### Very Long Messages
```
Show me sailboats in Miami under $100,000 that are 30 feet long and in good condition with GPS and autopilot and radar and fish finder and...
```

### Mixed Queries
```
Show me boats and also tell me about sailboats
Find listings and explain what a catamaran is
```

### Ambiguous Queries
```
Show me something
Find it
What about boats?
Tell me more
```

### Special Characters
```
Show me boats under $100,000!
Find boats in Miami, FL.
What boats are available? (I need one soon)
Show me: sailboats, powerboats, and yachts
```

## Performance Testing Messages

### Cache Testing (Send Same Message Twice)
```
Show me sailboats in Miami
Show me sailboats in Miami  (should be faster - cached)
```

### Complex Queries
```
Find 40 foot sailboats in San Francisco under $300,000 that are in excellent condition
Show me luxury yachts in Miami and Fort Lauderdale under $1,000,000
Find fishing boats in Florida that are 25-30 feet and under $75,000
```

## Intent Classification Tests

### Should Trigger Database Query
```
list
show
find
search
price
cost
buy
sale
listing
display
view
browse
available
inventory
purchase
where
location
boat
yacht
vessel
```

### Should Trigger General Knowledge
```
what is
how does
tell me about
explain
describe
help me understand
what are
```

### Should Trigger Hybrid Query
```
luxury
comfortable
spacious
well-maintained
modern amenities
best
good for
suitable for
similar to
with [feature]
has [feature]
perfect for
ideal for
excellent
beautiful
premium
high-end
turnkey
well-equipped
family-friendly
beginner-friendly
```

## Real-World User Scenarios

### Scenario 1: First-time Buyer
```
User: I'm new to boating. What should I look for?
Bot: (General knowledge response)

User: Show me some beginner-friendly boats
Bot: (Database query - should show listings)

User: What about boats under $50,000?
Bot: (Database query with price filter)
```

### Scenario 2: Specific Search
```
User: I'm looking for a 35 foot sailboat in Miami
Bot: (Database query with type, length, location)

User: Do you have any under $200,000?
Bot: (Refined search with price)

User: Show me more options
Bot: (Lazy load more listings)
```

### Scenario 3: Information Seeking
```
User: What's the difference between a sailboat and a catamaran?
Bot: (General knowledge response)

User: Show me examples of both
Bot: (Database query for both types)
```

### Scenario 4: Price Comparison
```
User: Show me boats under $100k
Bot: (Listings under $100k)

User: What about under $50k?
Bot: (Listings under $50k)

User: Show me the most expensive ones
Bot: (Should handle this - might need to show high-end listings)
```

### Scenario 5: Hybrid Query - Semantic Search
```
User: Show me luxury yachts under $2 million in Florida
Bot: (Hybrid query - vector search for "luxury" + SQL filters for price/location)

User: Find comfortable boats for long trips
Bot: (Hybrid query - semantic search for "comfortable" and "long trips")

User: What boats are good for beginners under $100k?
Bot: (Hybrid query - semantic search for "good for beginners" + price filter)
```

### Scenario 6: Feature-Based Hybrid Query
```
User: Find boats with a full galley and generator under $300k
Bot: (Hybrid query - semantic search for features + price filter)

User: Show me boats with modern electronics and navigation
Bot: (Hybrid query - semantic search for "modern electronics")

User: What boats have stabilizers and are well-maintained?
Bot: (Hybrid query - semantic search for features and condition)
```

## Testing Checklist

### ✅ Intent Classification
- [ ] Database query keywords trigger correct intent
- [ ] General knowledge questions trigger correct intent
- [ ] Hybrid query indicators trigger correct intent (semantic + structured)
- [ ] Synonyms work correctly
- [ ] Fuzzy matching works

### ✅ Database Queries
- [ ] Boat type filtering works
- [ ] Location filtering works
- [ ] Price filtering works
- [ ] Length filtering works
- [ ] Combined filters work
- [ ] No results handled gracefully
- [ ] Results are formatted correctly

### ✅ Hybrid Queries (Vector Search + SQL Filters)
- [ ] Semantic search finds relevant boats based on meaning
- [ ] Natural language descriptions work (luxury, comfortable, spacious)
- [ ] Subjective criteria work (best, good, nice, beautiful)
- [ ] Feature-based queries work (with galley, has generator)
- [ ] Use case queries work (for fishing, for cruising)
- [ ] SQL filters are applied to vector results correctly
- [ ] Combined semantic + structured queries work
- [ ] Question format hybrid queries work
- [ ] Comparative queries work (similar to, like, better than)
- [ ] Vector search fallback to SQL works when needed
- [ ] Results are ranked by combined relevance score

### ✅ Performance
- [ ] First query response time < 1.5s
- [ ] Cached queries are faster
- [ ] Lazy loading works
- [ ] Token limit is respected

### ✅ AI Responses
- [ ] Responses are relevant
- [ ] Tone of voice is applied
- [ ] Blocked websites are not mentioned
- [ ] Listings are summarized correctly

### ✅ Error Handling
- [ ] Invalid inputs handled
- [ ] Empty messages handled
- [ ] Database errors handled
- [ ] API errors handled

### ✅ User Experience
- [ ] Typing indicator shows
- [ ] Messages display correctly
- [ ] Listings display properly
- [ ] Load more button works
- [ ] Scroll to bottom works

## Quick Test Sequence

Run these in order to quickly test all features:

1. `Hello` - Test welcome/general response
2. `Show me sailboats` - Test basic database query
3. `Find boats in Miami under $100,000` - Test combined filters
4. `What is a catamaran?` - Test general knowledge
5. `Show me sailboats` - Test caching (should be faster)
6. `Show me 30 foot boats` - Test length filter
7. `Tell me about boat maintenance` - Test general knowledge
8. `Find yachts in San Francisco` - Test location + type
9. `Show me luxury yachts under $2 million in Florida` - Test hybrid query (semantic + filters)
10. `Find comfortable sailboats for long-distance cruising` - Test semantic search
11. `What boats are good for beginners under $100k?` - Test question format hybrid query
12. `Show me well-maintained boats with modern amenities` - Test multiple semantic criteria

## Expected Behaviors

### Database Queries Should:
- Return relevant listings
- Show first 15 listings immediately
- Display "Load More" if more available
- Format listings according to admin settings
- Respect token limits
- Cache results for 5 minutes

### Hybrid Queries Should:
- Use vector/semantic search to find relevant boats based on meaning
- Apply SQL filters to vector search results
- Combine semantic relevance scores with filter matches
- Return results ranked by combined relevance
- Fallback to SQL-only search if vector search fails
- Handle natural language descriptions correctly
- Support complex multi-criteria queries

### General Knowledge Should:
- Provide helpful information
- Use configured tone of voice
- Not reference blocked websites
- Be concise and relevant

### Performance Should:
- Respond in < 1.5s for uncached queries
- Respond in < 100ms for cached queries
- Show performance metrics in console (dev mode)
- Handle errors gracefully

## Notes

- All test messages should be sent through the chatbot interface
- Check browser console for performance metrics
- Verify cache hits on repeated queries
- Test with different admin settings (format, token limit)
- Verify SQL injection prevention (try malicious inputs)
- Check that nonce security works correctly

