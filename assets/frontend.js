jQuery(document).ready(function($) {
    // Don't initialize on landing page - landing.js handles it
    if ($('#boat-landing-video-section').length > 0 || 
        $('.boat-landing-page').length > 0 ||
        window.location.pathname.includes('virtual-yachtbroker')) {
        console.log('Landing page detected, skipping frontend.js initialization');
        return;
    }
    
    const chatbot = {
        conversationHistory: [],
        loadedListings: [],
        currentQuery: '',
        totalListings: 0,
        isLoadingMore: false,
        scrollTimeout: null,
        listingsPerPage: 5, // Default items per page for Load More
        initialItemsCount: 5, // Track initial page size (could be user-specified or 5)
        
        init: function() {
            // Initialize conversation history first
            if (!this.conversationHistory) {
                this.conversationHistory = [];
            }
            if (!this.loadedListings) {
                this.loadedListings = [];
            }
            if (!this.currentQuery) {
                this.currentQuery = '';
            }
            this.bindEvents();
            this.addWelcomeMessage();
        },
        
        bindEvents: function() {
            const self = this;
            $('#boat-chatbot-toggle').on('click', this.toggleChat.bind(this));
            $('#boat-chatbot-close').on('click', this.closeChat.bind(this));
            $('#boat-chatbot-send').on('click', this.sendMessage.bind(this));
            $('#boat-chatbot-message').on('keypress', function(e) {
                if (e.which === 13) {
                    self.sendMessage();
                }
            });
            
            // Lazy load more listings when scrolling (throttled)
            $('#boat-chatbot-messages').on('scroll', function() {
                // Check immediately first
                self.handleScroll();
                // Then throttle subsequent checks
                clearTimeout(self.scrollTimeout);
                self.scrollTimeout = setTimeout(function() {
                    self.handleScroll();
                }, 100); // Throttle to every 100ms
            });
        },
        
        toggleChat: function() {
            $('#boat-chatbot-widget').toggleClass('boat-chatbot-open');
            $('#boat-chatbot-toggle').toggleClass('boat-chatbot-open boat-chatbot-closed');
        },
        
        closeChat: function() {
            $('#boat-chatbot-widget').removeClass('boat-chatbot-open');
            $('#boat-chatbot-toggle').removeClass('boat-chatbot-open').addClass('boat-chatbot-closed');
        },
        
        addWelcomeMessage: function() {
            const welcomeMsg = 'Hello! I\'m your boat assistant. I can help you find boat listings or answer general boating questions. How can I help you today?';
            this.addMessage(welcomeMsg, 'bot');
            // Add welcome message to conversation history
            if (!this.conversationHistory) {
                this.conversationHistory = [];
            }
            this.conversationHistory.push({
                role: 'assistant',
                content: welcomeMsg
            });
        },
        
        sendMessage: function() {
            const message = $('#boat-chatbot-message').val().trim();
            if (!message) return;
            
            // Add user message to chat
            this.addMessage(message, 'user');
            
            // Add user message to conversation history
            if (!this.conversationHistory) {
                this.conversationHistory = [];
            }
            this.conversationHistory.push({
                role: 'user',
                content: message
            });
            
            $('#boat-chatbot-message').val('');
            
            // Show typing indicator
            this.showTypingIndicator();
            
            // Store current query for lazy loading
            this.currentQuery = message;
            this.loadedListings = [];
            this.totalListings = 0;
            this.initialItemsCount = 5; // Reset to default
            
            // Send to REST API endpoint
            const startTime = performance.now();
            
            $.ajax({
                url: boat_chatbot_ajax.rest_url + '/send-message',
                type: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    // Set WordPress REST API nonce in header (preferred method)
                    if (boat_chatbot_ajax.rest_nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', boat_chatbot_ajax.rest_nonce);
                    }
                },
                data: JSON.stringify({
                    message: message,
                    conversation_history: this.conversationHistory,
                    nonce: boat_chatbot_ajax.rest_nonce || boat_chatbot_ajax.nonce // Fallback in body
                }),
                success: (response) => {
                    const responseTime = performance.now() - startTime;
                    this.hideTypingIndicator();
                    
                    if (response.success) {
                        const data = response.data;
                        
                        // Display AI response
                        this.addMessage(data.response, 'bot');
                        
                        // Add bot response to conversation history
                        if (!this.conversationHistory) {
                            this.conversationHistory = [];
                        }
                        this.conversationHistory.push({
                            role: 'assistant',
                            content: data.response
                        });
                        
                        // If there are listings, show them with lazy loading option
                        if (data.listings && data.listings.length > 0) {
                            this.loadedListings = data.listings;
                            this.totalListings = data.total_listings || data.listings.length;
                            
                            // Track initial items count (could be user-specified or default 5)
                            this.initialItemsCount = data.listings.length;
                            
                            // Show initial listings
                            this.displayListings(data.listings, false);
                            
                            // If there are more listings, show "Load More" option
                            if (data.has_more && data.total_listings > data.listings.length) {
                                this.addLoadMoreButton(data.total_listings, data.listings.length);
                            } else {
                                // Update pagination info even if no more listings
                                this.updatePaginationInfo();
                            }
                        }
                        
                        // Log performance metrics if available
                        if (data.performance_log) {
                            const perfMetrics = {
                                total: (data.response_time * 1000).toFixed(2) + 'ms',
                                intent: data.performance_log.intent_time ? (data.performance_log.intent_time * 1000).toFixed(2) + 'ms' : 'N/A',
                                db: data.performance_log.db_time ? (data.performance_log.db_time * 1000).toFixed(2) + 'ms' : 'N/A',
                                ai: data.performance_log.ai_time ? (data.performance_log.ai_time * 1000).toFixed(2) + 'ms' : 'N/A',
                                cached: data.cached || false
                            };
                            
                            // Add sparse vector information if available
                            if (data.performance_log.sparse_vector_generated !== undefined) {
                                perfMetrics.sparse_vector_used = data.performance_log.sparse_vector_generated;
                            }
                            if (data.performance_log.sparse_vector_method) {
                                perfMetrics.sparse_method = data.performance_log.sparse_vector_method;
                            }
                            if (data.performance_log.hybrid_search_used !== undefined) {
                                perfMetrics.hybrid_search = data.performance_log.hybrid_search_used;
                            }
                            
                            // Add embedding and vector search time if available
                            if (data.performance_log.embedding_time) {
                                perfMetrics.embedding = (data.performance_log.embedding_time * 1000).toFixed(2) + 'ms';
                            }
                            if (data.performance_log.vector_search_time) {
                                perfMetrics.vector_search = (data.performance_log.vector_search_time * 1000).toFixed(2) + 'ms';
                            }
                            if (data.performance_log.keyword_search_time) {
                                perfMetrics.keyword_search = (data.performance_log.keyword_search_time * 1000).toFixed(2) + 'ms';
                            }
                            
                            console.log('Performance Metrics:', perfMetrics);
                            console.log('Full Performance Log:', data.performance_log);
                        }
                    } else {
                        this.addMessage('Sorry, I encountered an error. Please try again.', 'bot');
                    }
                },
                error: (xhr) => {
                    this.hideTypingIndicator();
                    let errorMsg = 'Sorry, I\'m having connection issues. Please try again.';
                    
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    
                    this.addMessage(errorMsg, 'bot');
                }
            });
        },
        
        displayListings: function(listings, append = false) {
            if (!listings || listings.length === 0) return;
            
            let listingsHtml = '';
            
            if (!append) {
                listingsHtml = '<div class="boat-chatbot-listings-container">';
                listingsHtml += '<div class="boat-chatbot-listings-header">Available Listings:</div>';
                // Add pagination info
                listingsHtml += this.getPaginationInfo();
            }
            
            listings.forEach((listing) => {
                const title = this.escapeHtml(listing.VesselName || 'Untitled');
                const type = this.escapeHtml(listing.Type_ || 'N/A');
                const length = listing.DisplayLengthFeet || 'N/A';
                const price = listing.PriceUSD ? '$' + parseFloat(listing.PriceUSD).toLocaleString() : 'N/A';
                const location = this.escapeHtml(listing.State || 'N/A');
                const url = listing.url || '#';

                const detailPageUrl = "/yacht-details/";
                let vesselSEOTitleAndAlterText = listing.DisplayLengthFeet + '-ft-' + listing.Manufacturer + '-' + listing.Year + '-' + listing.Model + '-' + listing.VesselName + ' ' + listing.City + ' ' + listing.State + ' ' + listing.Country + '  yacht for sale';

                let string = vesselSEOTitleAndAlterText;
                let string_lower_status = 'true';
                let replace_with = '-';
                let detail_page_url =""
                
                // Remove brackets and their content
                string = string.replace(/[\\[\\]]/g, '');
                string = string.replace(/\[.*?\]/g, '');
                string = string.replace(/&(amp;)?#?[a-z0-9]+;/gi, '-');
                
                // Convert HTML entities (simplified version - for full HTML entity handling, consider using a library)
                string = string.replace(/&([a-z])(acute|uml|circ|grave|ring|cedil|slash|tilde|caron|lig|quot|rsquo);/gi, '$1');
                
                // Remove non-alphanumeric characters and replace multiple hyphens
                string = string.replace(/[^a-z0-9]/gi, replace_with);
                string = string.replace(/[-]+/g, replace_with);
                
                // Trim and convert to lowercase if needed
                let seoTitleForVesselDetail = string;
                if (string_lower_status === 'true') {
                    seoTitleForVesselDetail = string.toLowerCase();
                }
                seoTitleForVesselDetail = seoTitleForVesselDetail.trim().replace(new RegExp('^' + replace_with + '+|' + replace_with + '+$', 'g'), '');
                
                detail_page_url = detailPageUrl + '' + seoTitleForVesselDetail + '_' + listing.ID;


                // const url = listing.length + "-ft-" + listing.Manufacturer + "-" + listing.Year + "-" + listing.;
                listingsHtml += `
                    <div class="boat-chatbot-listing-item">
                        <div class="boat-chatbot-listing-title">
                            <a href="${detail_page_url}" target="_blank" rel="noopener">${title}</a>
                        </div>
                        <div class="boat-chatbot-listing-details">
                            ${type} | ${length}' | ${price} | ${location}
                        </div>
                    </div>
                `;
            });
            
            if (!append) {
                listingsHtml += '</div>';
                this.addMessage(listingsHtml, 'bot', true);
            } else {
                $('.boat-chatbot-listings-container').append(listingsHtml);
                // Scroll to bottom after appending new listings
                setTimeout(() => {
                    this.scrollToBottom();
                }, 100);
            }
        },
        
        getPaginationInfo: function() {
            if (!this.totalListings || this.totalListings === 0 || this.loadedListings.length === 0) return '';
            
            // Calculate page number accounting for variable initial page size
            // First page has initialItemsCount items, subsequent pages have 5 items each
            let currentPage = 1;
            if (this.loadedListings.length > this.initialItemsCount) {
                // After first page, calculate based on 5 items per page
                const itemsAfterFirstPage = this.loadedListings.length - this.initialItemsCount;
                currentPage = 1 + Math.ceil(itemsAfterFirstPage / this.listingsPerPage);
            }
            
            // Calculate total pages: first page (initialItemsCount) + remaining pages (5 items each)
            const remainingItems = Math.max(0, this.totalListings - this.initialItemsCount);
            const totalPages = 1 + Math.ceil(remainingItems / this.listingsPerPage);
            
            const showingFrom = this.loadedListings.length > 0 ? 1 : 0;
            const showingTo = this.loadedListings.length;
            
            return `
                <div class="boat-chatbot-pagination-info">
                    Showing ${showingFrom}-${showingTo} of ${this.totalListings} listing${this.totalListings !== 1 ? 's' : ''} | Page ${currentPage} of ${totalPages}
                </div>
            `;
        },
        
        updatePaginationInfo: function() {
            const $paginationInfo = $('.boat-chatbot-pagination-info');
            if ($paginationInfo.length) {
                $paginationInfo.replaceWith(this.getPaginationInfo());
            } else {
                // If pagination info doesn't exist, add it after header
                $('.boat-chatbot-listings-header').after(this.getPaginationInfo());
            }
        },
        
        addLoadMoreButton: function(total, current) {
            const remaining = total - current;
            const loadMoreHtml = `
                <div class="boat-chatbot-load-more">
                    <button class="boat-chatbot-load-more-btn" data-offset="${current}">
                        Load ${remaining} more listing${remaining > 1 ? 's' : ''}
                    </button>
                </div>
            `;
            
            $('.boat-chatbot-listings-container').append(loadMoreHtml);
            
            // Update pagination info
            this.updatePaginationInfo();
            
            // Bind click event
            $('.boat-chatbot-load-more-btn').off('click').on('click', (e) => {
                const offset = parseInt($(e.target).data('offset'));
                this.loadMoreListings(offset);
            });
        },
        
        loadMoreListings: function(offset) {
            // Prevent multiple simultaneous requests
            if (this.isLoadingMore) return;
            
            const $btn = $('.boat-chatbot-load-more-btn');
            if (!$btn.length) return;
            
            this.isLoadingMore = true;
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Loading...');
            
            $.ajax({
                url: boat_chatbot_ajax.rest_url + '/load-listings',
                type: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    // Set WordPress REST API nonce in header (preferred method)
                    if (boat_chatbot_ajax.rest_nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', boat_chatbot_ajax.rest_nonce);
                    }
                },
                data: JSON.stringify({
                    query: this.currentQuery,
                    offset: offset,
                    limit: 5, // Always load 5 items per page
                    nonce: boat_chatbot_ajax.rest_nonce || boat_chatbot_ajax.nonce // Fallback in body
                }),
                success: (response) => {
                    this.isLoadingMore = false;
                    if (response.success && response.data.listings.length > 0) {
                        // Append new listings
                        this.displayListings(response.data.listings, true);
                        this.loadedListings = this.loadedListings.concat(response.data.listings);
                        this.totalListings = response.data.total || this.loadedListings.length;
                        
                        // Update pagination info
                        this.updatePaginationInfo();
                        
                        // Update or remove load more button
                        $('.boat-chatbot-load-more').remove();
                        
                        if (response.data.total > this.loadedListings.length) {
                            this.addLoadMoreButton(response.data.total, this.loadedListings.length);
                            // Scroll to bottom after loading more and adding button
                            setTimeout(() => {
                                this.scrollToBottom();
                                this.handleScroll();
                            }, 100);
                        } else {
                            // Scroll to bottom even when no more listings
                            setTimeout(() => {
                                this.scrollToBottom();
                            }, 100);
                        }
                    } else {
                        $('.boat-chatbot-load-more').remove();
                        // Update pagination info even when no more listings
                        this.updatePaginationInfo();
                        // Scroll to bottom
                        setTimeout(() => {
                            this.scrollToBottom();
                        }, 100);
                    }
                },
                error: () => {
                    this.isLoadingMore = false;
                    $btn.prop('disabled', false).text(originalText);
                    this.addMessage('Failed to load more listings. Please try again.', 'bot');
                }
            });
        },
        
        handleScroll: function() {
            // Prevent multiple simultaneous scroll-triggered loads
            if (this.isLoadingMore) return;
            
            const $messages = $('#boat-chatbot-messages');
            if (!$messages.length || !$messages[0]) return;
            
            // Check if there are more listings to load
            if (!this.totalListings || this.totalListings <= this.loadedListings.length) return;
            
            const scrollTop = $messages.scrollTop();
            const scrollHeight = $messages[0].scrollHeight;
            const clientHeight = $messages.height();
            
            // Safety check
            if (scrollHeight <= 0 || clientHeight <= 0) return;
            
            // Calculate distance from bottom
            const distanceFromBottom = scrollHeight - scrollTop - clientHeight;
            
            // Load more when near bottom (within 200px) or at the very bottom
            if (distanceFromBottom <= 200) {
                // Try to get offset from button first, otherwise calculate from loaded listings
                let offset = this.loadedListings.length;
                const $loadMoreBtn = $('.boat-chatbot-load-more-btn');
                if ($loadMoreBtn.length && !$loadMoreBtn.prop('disabled')) {
                    const btnOffset = parseInt($loadMoreBtn.data('offset'));
                    if (!isNaN(btnOffset)) {
                        offset = btnOffset;
                    }
                }
                
                // Only load if we have more to load
                if (offset < this.totalListings) {
                    this.loadMoreListings(offset);
                }
            }
        },
        
        addMessage: function(message, sender, isHtml = false) {
            const messageClass = sender === 'user' ? 'boat-chatbot-user-message' : 'boat-chatbot-bot-message';
            let formattedMessage = message;
            
            // Format bot messages for better readability
            if (sender === 'bot' && !isHtml) {
                formattedMessage = this.formatMessage(message);
            } else if (!isHtml) {
                formattedMessage = this.escapeHtml(message);
            }
            
            const messageHtml = `
                <div class="boat-chatbot-message ${messageClass}">
                    <div class="boat-chatbot-message-content">${formattedMessage}</div>
                </div>
            `;
            
            $('#boat-chatbot-messages').append(messageHtml);
            // Use setTimeout to ensure DOM is updated before scrolling
            setTimeout(() => {
                this.scrollToBottom();
            }, 50);
        },
        
        formatMessage: function(text) {
            if (!text) return '';
            
            // Escape HTML first to prevent XSS
            let formatted = this.escapeHtml(text);
            
            // Split into lines for processing
            let lines = formatted.split('\n');
            let result = [];
            let inList = false;
            let listItems = [];
            
            for (let i = 0; i < lines.length; i++) {
                let line = lines[i].trim();
                let isListItem = /^[\-\*]\s+/.test(line) || /^\d+\.\s+/.test(line);
                
                if (isListItem) {
                    // Remove list marker and add to list items
                    line = line.replace(/^[\-\*]\s+/, '').replace(/^\d+\.\s+/, '');
                    listItems.push(line);
                    inList = true;
                } else {
                    // If we were in a list, close it
                    if (inList && listItems.length > 0) {
                        result.push('<ul>' + listItems.map(item => '<li>' + item + '</li>').join('') + '</ul>');
                        listItems = [];
                        inList = false;
                    }
                    
                    // Handle empty lines (paragraph breaks)
                    if (line === '') {
                        if (result.length > 0 && !result[result.length - 1].endsWith('</p>')) {
                            result.push('</p><p>');
                        }
                    } else {
                        // Regular line - format inline markdown
                        line = this.formatInlineMarkdown(line);
                        
                        // Wrap in paragraph if needed
                        if (result.length === 0 || result[result.length - 1].endsWith('</p>') || result[result.length - 1].endsWith('</ul>')) {
                            result.push('<p>' + line);
                        } else {
                            result[result.length - 1] += '<br>' + line;
                        }
                    }
                }
            }
            
            // Close any remaining list
            if (inList && listItems.length > 0) {
                result.push('<ul>' + listItems.map(item => '<li>' + item + '</li>').join('') + '</ul>');
            }
            
            // Close any open paragraph
            if (result.length > 0 && result[result.length - 1].startsWith('<p>') && !result[result.length - 1].endsWith('</p>')) {
                result[result.length - 1] += '</p>';
            }
            
            formatted = result.join('');
            
            // Clean up empty paragraphs
            formatted = formatted.replace(/<p>\s*<\/p>/g, '');
            formatted = formatted.replace(/<p>(<ul>.*<\/ul>)<\/p>/g, '$1');
            
            // Ensure we have content
            if (!formatted.trim()) {
                formatted = '<p>' + this.escapeHtml(text) + '</p>';
            }
            
            return formatted;
        },
        
        formatInlineMarkdown: function(text) {
            // Format bold text (**text** or __text__) first
            text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            text = text.replace(/__([^_]+)__/g, '<strong>$1</strong>');
            
            // Format italic text (*text* or _text_) - match single asterisks/underscores
            // Process italic only on text that doesn't contain <strong> tags
            let parts = text.split(/(<strong>.*?<\/strong>)/g);
            for (let i = 0; i < parts.length; i++) {
                if (!parts[i].includes('<strong>')) {
                    // Format italic in non-bold parts
                    parts[i] = parts[i].replace(/\*([^*\n]+?)\*/g, '<em>$1</em>');
                    parts[i] = parts[i].replace(/_([^_\n]+?)_/g, '<em>$1</em>');
                }
            }
            text = parts.join('');
            
            // Format links (basic URL detection)
            text = text.replace(/(https?:\/\/[^\s<>"']+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
            
            return text;
        },
        
        showTypingIndicator: function() {
            const typingHtml = `
                <div class="boat-chatbot-message boat-chatbot-bot-message boat-chatbot-typing">
                    <div class="boat-chatbot-message-content">
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                    </div>
                </div>
            `;
            $('#boat-chatbot-messages').append(typingHtml);
            // Scroll to bottom after showing typing indicator
            setTimeout(() => {
                this.scrollToBottom();
            }, 50);
        },
        
        hideTypingIndicator: function() {
            $('.boat-chatbot-typing').remove();
        },
        
        scrollToBottom: function(smooth = false) {
            const messages = $('#boat-chatbot-messages');
            if (messages.length > 0 && messages[0]) {
                const container = messages[0];
                
                // Get the last message element
                const lastMessage = $(container).find('.boat-chatbot-message').last();
                
                if (lastMessage.length > 0 && lastMessage[0]) {
                    // Get header height dynamically
                    const header = $('#boat-chatbot-header');
                    const headerHeight = header.length > 0 ? header.outerHeight() || 60 : 60;
                    
                    const messageElement = lastMessage[0];
                    
                    // Use requestAnimationFrame to ensure DOM is updated
                    requestAnimationFrame(() => {
                        // Get message's offset from top of container
                        const messageOffsetTop = messageElement.offsetTop;
                        
                        // Calculate scroll position: message should be at headerHeight from top of visible area
                        const targetScrollTop = messageOffsetTop - headerHeight;
                        
                        if (smooth) {
                            messages[0].scrollTo({
                                top: Math.max(0, targetScrollTop),
                                behavior: 'smooth'
                            });
                        } else {
                            messages.scrollTop(Math.max(0, targetScrollTop));
                        }
                        
                        // Double-check after DOM settles
                        setTimeout(() => {
                            const updatedOffsetTop = messageElement.offsetTop;
                            const finalScrollTop = updatedOffsetTop - headerHeight;
                            if (smooth) {
                                messages[0].scrollTo({
                                    top: Math.max(0, finalScrollTop),
                                    behavior: 'smooth'
                                });
                            } else {
                                messages.scrollTop(Math.max(0, finalScrollTop));
                            }
                        }, 100);
                    });
                } else {
                    // Fallback: scroll to top if no messages
                    if (smooth) {
                        messages[0].scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    } else {
                        messages.scrollTop(0);
                    }
                }
            }
        },
        
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
    
    chatbot.init();
});
