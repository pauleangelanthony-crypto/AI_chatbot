jQuery(document).ready(function($) {
    'use strict';
    
    const chatbot = {
        conversationHistory: [],
        loadedListings: [],
        currentQuery: '',
        totalListings: 0,
        isLoadingMore: false,
        scrollTimeout: null,
        listingsPerPage: 5, // Default items per page for Load More
        initialItemsCount: 5, // Track initial page size (could be user-specified or 5)
        isRecording: false,
        recognition: null, // Web Speech API recognition object
        currentAudio: null, // For TTS playback
        
        messagesContainer: $('#boat-chatbot-messages-container'),
        inputField: $('#boat-chatbot-input-full'),
        sendButton: $('#boat-chatbot-send-full'),
        voiceButton: $('#boat-chatbot-voice-full'),
        newChatButton: $('#boat-new-chat'),
        
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
            this.checkInitialMessage();
        },
        
        bindEvents: function() {
            const self = this;
            
            // Send message on Enter (but allow Shift+Enter for new line)
            this.inputField.on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });
            
            // Send button click
            this.sendButton.on('click', function() {
                self.sendMessage();
            });
            
            // Voice button - Speech-to-Text
            this.voiceButton.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.toggleVoiceRecording();
            });
            
            // New chat button
            this.newChatButton.on('click', function() {
                self.newChat();
            });
            
            // Lazy load more listings when scrolling (throttled)
            this.messagesContainer.on('scroll', function() {
                self.handleScroll();
                clearTimeout(self.scrollTimeout);
                self.scrollTimeout = setTimeout(function() {
                    self.handleScroll();
                }, 100);
            });
        },
        
        checkInitialMessage: function() {
            // Check for initial message and response from landing page
            if (typeof(Storage) !== "undefined") {
                const initialResponse = sessionStorage.getItem('boat_chatbot_initial_response');
                const initialMessage = sessionStorage.getItem('boat_chatbot_initial_message');
                
                if (initialResponse) {
                    // If response is already stored, display it immediately
                    try {
                        const responseData = JSON.parse(initialResponse);
                        sessionStorage.removeItem('boat_chatbot_initial_response');
                        
                        if (initialMessage) {
                            // Display user message
                            this.addMessage(initialMessage, 'user');
                            sessionStorage.removeItem('boat_chatbot_initial_message');
                        }
                        
                        // Display assistant response
                        if (responseData.response) {
                            this.addMessage(responseData.response, 'assistant');
                        }
                        
                        // Display listings if available
                        if (responseData.listings && responseData.listings.length > 0) {
                            this.displayListings(responseData.listings, false);
                        }
                        
                        // Update conversation history
                        if (initialMessage && responseData.response) {
                            this.conversationHistory.push(
                                { role: 'user', content: initialMessage },
                                { role: 'assistant', content: responseData.response }
                            );
                        }
                        
                        return; // Don't show welcome message
                    } catch (e) {
                        console.error('Error parsing stored response:', e);
                        sessionStorage.removeItem('boat_chatbot_initial_response');
                    }
                } else if (initialMessage) {
                    // If only message is stored (no response yet), send it
                    sessionStorage.removeItem('boat_chatbot_initial_message');
                    this.inputField.val(initialMessage);
                    setTimeout(() => {
                        this.sendMessage();
                    }, 500);
                    return; // Don't show welcome message
                }
            }
            
            // Add welcome message if no initial message or response
            this.addWelcomeMessage();
        },
        
        newChat: function() {
            this.messagesContainer.find('.boat-chatbot-message').remove();
            this.inputField.val('');
            this.inputField.css('height', 'auto');
            this.conversationHistory = [];
            this.loadedListings = [];
            this.currentQuery = '';
            this.totalListings = 0;
            this.initialItemsCount = 5; // Reset to default
            
            // Stop any ongoing recording
            if (this.isRecording) {
                this.stopRecording();
            }
            
            // Stop any playing audio
            if (this.currentAudio) {
                this.currentAudio.pause();
                this.currentAudio = null;
            }
            
            // Show welcome state again
            const welcomeState = $('#boat-chatbot-welcome-state');
            if (welcomeState.length) {
                welcomeState.removeClass('hidden');
            }
            
            // Add welcome message
            this.addWelcomeMessage();
        },
        
        toggleVoiceRecording: function() {
            if (this.isRecording) {
                this.stopRecording();
            } else {
                this.startRecording();
            }
        },
        
        startRecording: function() {
            const self = this;
            
            // Use Web Speech API (browser built-in, free, no API key needed)
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                alert('Your browser does not support speech recognition. Please use Chrome, Edge, or Safari.');
                return;
            }
            
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            self.recognition = new SpeechRecognition();
            
            self.recognition.continuous = false;
            self.recognition.interimResults = false;
            self.recognition.lang = 'en-US';
            
            // Update button appearance
            self.voiceButton.addClass('recording');
            self.isRecording = true;
            
            self.recognition.onstart = function() {
                console.log('Speech recognition started');
            };
            
            self.recognition.onresult = function(event) {
                const transcript = event.results[0][0].transcript;
                
                // Fill input field with transcribed text
                self.inputField.val(transcript);
                // Auto-resize textarea
                self.inputField.css('height', 'auto');
                self.inputField.css('height', self.inputField[0].scrollHeight + 'px');
                
                self.voiceButton.removeClass('recording processing');
                self.isRecording = false;
            };
            
            self.recognition.onerror = function(event) {
                console.error('Speech recognition error:', event.error);
                self.voiceButton.removeClass('recording processing');
                self.isRecording = false;
                
                let errorMsg = 'Speech recognition error. Please try again.';
                if (event.error === 'no-speech') {
                    errorMsg = 'No speech detected. Please try again.';
                } else if (event.error === 'not-allowed') {
                    errorMsg = 'Microphone permission denied. Please allow microphone access.';
                }
                alert(errorMsg);
            };
            
            self.recognition.onend = function() {
                self.voiceButton.removeClass('recording processing');
                self.isRecording = false;
            };
            
            self.recognition.start();
        },
        
        stopRecording: function() {
            if (this.recognition && this.isRecording) {
                this.recognition.stop();
                this.isRecording = false;
                
                // Update button appearance
                this.voiceButton.removeClass('recording processing');
            }
        },
        
        playTTS: function(text) {
            const self = this;
            
            // Stop any currently playing audio
            if (this.currentAudio) {
                this.currentAudio.pause();
                this.currentAudio = null;
            }
            
            const restUrl = window.boatChatbot ? window.boatChatbot.restUrl : '';
            const restNonce = window.boatChatbot ? window.boatChatbot.restNonce : '';
            
            if (!restUrl) {
                console.error('REST URL not found');
                return;
            }
            
            $.ajax({
                url: restUrl + 'text-to-speech',
                type: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    if (restNonce) {
                        xhr.setRequestHeader('X-WP-Nonce', restNonce);
                    }
                },
                data: JSON.stringify({
                    text: text,
                    nonce: restNonce
                }),
                success: function(response) {
                    if (response.success && response.audio) {
                        // Convert base64 to blob
                        const audioData = atob(response.audio);
                        const arrayBuffer = new ArrayBuffer(audioData.length);
                        const uint8Array = new Uint8Array(arrayBuffer);
                        for (let i = 0; i < audioData.length; i++) {
                            uint8Array[i] = audioData.charCodeAt(i);
                        }
                        const blob = new Blob([arrayBuffer], { type: 'audio/mpeg' });
                        const audioUrl = URL.createObjectURL(blob);
                        
                        // Play audio
                        self.currentAudio = new Audio(audioUrl);
                        self.currentAudio.play();
                        
                        // Clean up URL when done
                        self.currentAudio.onended = function() {
                            URL.revokeObjectURL(audioUrl);
                            self.currentAudio = null;
                        };
                    }
                },
                error: function(xhr) {
                    console.error('TTS error:', xhr);
                    // Fail silently - TTS is optional
                }
            });
        },
        
        addWelcomeMessage: function() {
            const welcomeMsg = 'Hello! I\'m your boat assistant. I can help you find boat listings or answer general boating questions. How can I help you today?';
            this.addMessage(welcomeMsg, 'assistant');
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
            const message = this.inputField.val().trim();
            if (!message) return;
            
            // Hide welcome state
            const welcomeState = $('#boat-chatbot-welcome-state');
            if (welcomeState.length) {
                welcomeState.addClass('hidden');
            }
            
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
            
            this.inputField.val('');
            this.inputField.css('height', 'auto');
            
            // Show typing indicator
            const typingId = this.showTypingIndicator();
            
            // Store current query for lazy loading
            this.currentQuery = message;
            this.loadedListings = [];
            this.totalListings = 0;
            this.initialItemsCount = 5; // Reset to default
            
            // Send to REST API endpoint
            const startTime = performance.now();
            
            // Check if boatChatbot object exists (from wp_localize_script)
            if (typeof window.boatChatbot === 'undefined') {
                console.error('boatChatbot object not found. Make sure frontend.js is loaded.');
                this.hideTypingIndicator(typingId);
                this.addMessage('Sorry, I\'m having connection issues. Please refresh the page.', 'assistant');
                return;
            }
            
            $.ajax({
                url: window.boatChatbot.restUrl + 'send-message',
                type: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    if (window.boatChatbot.restNonce) {
                        xhr.setRequestHeader('X-WP-Nonce', window.boatChatbot.restNonce);
                    }
                },
                data: JSON.stringify({
                    message: message,
                    conversation_history: this.conversationHistory,
                    nonce: window.boatChatbot.restNonce || window.boatChatbot.nonce
                }),
                success: (response) => {
                    const responseTime = performance.now() - startTime;
                    this.hideTypingIndicator(typingId);
                    
                    if (response.success) {
                        const data = response.data;
                        
                        // Display AI response
                        this.addMessage(data.response, 'assistant');
                        
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
                        } else {
                            // Play TTS if enabled (only for text responses, not listings)
                            setTimeout(() => {
                                this.playTTS(data.response);
                            }, 500); // Small delay to ensure message is displayed
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
                        this.addMessage('Sorry, I encountered an error. Please try again.', 'assistant');
                    }
                    
                    // Re-enable input
                    this.inputField.prop('disabled', false);
                    this.sendButton.prop('disabled', false);
                    this.inputField.focus();
                },
                error: (xhr) => {
                    this.hideTypingIndicator(typingId);
                    let errorMsg = 'Sorry, I\'m having connection issues. Please try again.';
                    
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    
                    this.addMessage(errorMsg, 'assistant');
                    
                    // Re-enable input
                    this.inputField.prop('disabled', false);
                    this.sendButton.prop('disabled', false);
                    this.inputField.focus();
                }
            });
        },
        
        addMessage: function(content, role, isHtml = false) {
            // Hide welcome state when first message is added
            const welcomeState = $('#boat-chatbot-welcome-state');
            if (welcomeState.length && !welcomeState.hasClass('hidden')) {
                welcomeState.addClass('hidden');
            }
            
            const messageDiv = $('<div>').addClass('boat-chatbot-message').addClass(role);
            
            const avatar = $('<div>').addClass('boat-chatbot-message-avatar');
            if (role === 'user') {
                avatar.text('U');
            } else {
                avatar.text('AI');
            }
            
            const contentDiv = $('<div>').addClass('boat-chatbot-message-content');
            
            if (isHtml) {
                contentDiv.html(content);
            } else {
                const formattedContent = this.formatMessage(content);
                contentDiv.html(formattedContent);
            }
            
            messageDiv.append(avatar);
            messageDiv.append(contentDiv);
            
            this.messagesContainer.append(messageDiv);
            // Use setTimeout to ensure DOM is updated before scrolling
            setTimeout(() => {
                this.scrollToBottom();
            }, 50);
            
            return messageDiv;
        },
        
        formatMessage: function(content) {
            // Basic formatting - convert line breaks to paragraphs
            const paragraphs = content.split('\n\n').filter(p => p.trim());
            if (paragraphs.length === 0) {
                return '<p>' + this.escapeHtml(content) + '</p>';
            }
            
            return paragraphs.map(p => {
                // Convert single line breaks to <br>
                const formatted = this.escapeHtml(p.trim()).replace(/\n/g, '<br>');
                return '<p>' + formatted + '</p>';
            }).join('');
        },
        
        escapeHtml: function(text) {
            const div = $('<div>');
            div.text(text);
            return div.html();
        },
        
        showTypingIndicator: function() {
            const typingId = 'typing-' + Date.now();
            const messageDiv = $('<div>').addClass('boat-chatbot-message assistant boat-chatbot-typing').attr('id', typingId);
            
            const avatar = $('<div>').addClass('boat-chatbot-message-avatar').text('AI');
            const contentDiv = $('<div>').addClass('boat-chatbot-message-content');
            contentDiv.html('<div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>');
            
            messageDiv.append(avatar);
            messageDiv.append(contentDiv);
            
            this.messagesContainer.append(messageDiv);
            // Scroll to bottom after showing typing indicator
            setTimeout(() => {
                this.scrollToBottom();
            }, 50);
            
            return typingId;
        },
        
        hideTypingIndicator: function(typingId) {
            $('#' + typingId).remove();
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
                
                const detailPageUrl = "/yacht-details/";
                let vesselSEOTitleAndAlterText = listing.DisplayLengthFeet + '-ft-' + listing.Manufacturer + '-' + listing.Year + '-' + listing.Model + '-' + listing.VesselName + ' ' + listing.City + ' ' + listing.State + ' ' + listing.Country + '  yacht for sale';
                
                let string = vesselSEOTitleAndAlterText;
                let string_lower_status = 'true';
                let replace_with = '-';
                let detail_page_url = "";
                
                // Remove brackets and their content
                string = string.replace(/[\\[\\]]/g, '');
                string = string.replace(/\[.*?\]/g, '');
                string = string.replace(/&(amp;)?#?[a-z0-9]+;/gi, '-');
                
                // Convert HTML entities
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
                this.addMessage(listingsHtml, 'assistant', true);
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
            const self = this;
            $('.boat-chatbot-load-more-btn').off('click').on('click', function(e) {
                const offset = parseInt($(e.target).data('offset'));
                self.loadMoreListings(offset);
            });
        },
        
        loadMoreListings: function(offset) {
            // Prevent multiple simultaneous requests
            if (this.isLoadingMore) return;
            
            this.isLoadingMore = true;
            const $loadMoreBtn = $('.boat-chatbot-load-more-btn');
            $loadMoreBtn.prop('disabled', true).text('Loading...');
            
            const self = this;
            
            $.ajax({
                url: window.boatChatbot.restUrl + 'load-listings',
                type: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    if (window.boatChatbot.restNonce) {
                        xhr.setRequestHeader('X-WP-Nonce', window.boatChatbot.restNonce);
                    }
                },
                data: JSON.stringify({
                    query: self.currentQuery,
                    offset: offset,
                    limit: 5, // Always load 5 items per page
                    nonce: window.boatChatbot.restNonce || window.boatChatbot.nonce
                }),
                success: function(response) {
                    self.isLoadingMore = false;
                    $loadMoreBtn.remove();
                    
                    if (response.success && response.data && response.data.listings) {
                        const newListings = response.data.listings;
                        self.loadedListings = self.loadedListings.concat(newListings);
                        self.totalListings = response.data.total || self.loadedListings.length;
                        
                        // Display new listings
                        self.displayListings(newListings, true);
                        
                        // Update pagination info
                        self.updatePaginationInfo();
                        
                        // If there are more listings, show "Load More" option again
                        if (response.data.total > self.loadedListings.length) {
                            self.addLoadMoreButton(response.data.total, self.loadedListings.length);
                        } else {
                            // Update pagination info even when no more listings
                            self.updatePaginationInfo();
                        }
                        // Scroll to bottom after loading more
                        setTimeout(() => {
                            self.scrollToBottom();
                        }, 100);
                    } else {
                        $loadMoreBtn.remove();
                        // Update pagination info even when no more listings
                        self.updatePaginationInfo();
                        // Scroll to bottom
                        setTimeout(() => {
                            self.scrollToBottom();
                        }, 100);
                    }
                },
                error: function() {
                    self.isLoadingMore = false;
                    $loadMoreBtn.prop('disabled', false).text('Load More');
                    alert('Failed to load more listings. Please try again.');
                }
            });
        },
        
        handleScroll: function() {
            // Check if user scrolled near bottom (within 200px)
            const container = this.messagesContainer[0];
            if (!container) return;
            
            const scrollTop = container.scrollTop;
            const scrollHeight = container.scrollHeight;
            const clientHeight = container.clientHeight;
            
            // If scrolled near bottom and there are more listings, auto-load
            if (scrollHeight - scrollTop - clientHeight < 200) {
                // Auto-load more if available (optional feature)
                // This can be enabled if you want automatic loading on scroll
            }
        },
        
        scrollToBottom: function(smooth = false) {
            const container = this.messagesContainer[0];
            if (container) {
                // Get the last message element
                const lastMessage = $(container).find('.boat-chatbot-message').last();
                
                if (lastMessage.length > 0 && lastMessage[0]) {
                    // For full page, scroll to show message at top of content area
                    // Account for padding-top (20px) of messages container
                    const topPadding = 20;
                    const messageElement = lastMessage[0];
                    
                    // Use requestAnimationFrame to ensure DOM is updated
                    requestAnimationFrame(() => {
                        // Get message's offset from top of container
                        const messageOffsetTop = messageElement.offsetTop;
                        
                        // Calculate scroll position: message should be at topPadding from top of visible area
                        const targetScrollTop = messageOffsetTop - topPadding;
                        
                        if (smooth) {
                            container.scrollTo({
                                top: Math.max(0, targetScrollTop),
                                behavior: 'smooth'
                            });
                        } else {
                            container.scrollTop = Math.max(0, targetScrollTop);
                        }
                        
                        // Double-check after DOM settles
                        setTimeout(() => {
                            const updatedOffsetTop = messageElement.offsetTop;
                            const finalScrollTop = updatedOffsetTop - topPadding;
                            if (smooth) {
                                container.scrollTo({
                                    top: Math.max(0, finalScrollTop),
                                    behavior: 'smooth'
                                });
                            } else {
                                container.scrollTop = Math.max(0, finalScrollTop);
                            }
                        }, 100);
                    });
                } else {
                    // Fallback: scroll to top if no messages
                    if (smooth) {
                        container.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    } else {
                        container.scrollTop = 0;
                    }
                }
            }
        }
    };
    
    // Auto-resize textarea
    chatbot.inputField.on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
    
    // Initialize chatbot
    chatbot.init();
    
    // Focus input on load
    chatbot.inputField.focus();
});
