jQuery(document).ready(function($) {
    'use strict';
    
    // Responsive Video Switching
    function switchVideoByScreenSize() {
        const video = document.getElementById('boat-main-video');
        if (!video) return;
        
        const desktopUrl = video.getAttribute('data-video-desktop');
        const mobileUrl = video.getAttribute('data-video-mobile');
        
        if (!desktopUrl) return;
        
        const isMobile = window.innerWidth <= 768;
        // Detect iOS devices
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || 
                      (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        const currentSource = video.querySelector('.boat-video-source');
        
        if (isMobile && mobileUrl) {
            // On mobile/iOS, mute video to allow autoplay (required by mobile browsers)
            // But only if user hasn't manually toggled mute
            if (!video.muted) {
                // Check if user has toggled mute (function may not exist yet on first call)
                if (!video._userHasToggledMute || typeof video._userHasToggledMute !== 'function' || !video._userHasToggledMute()) {
                    video.muted = true;
                }
            }
            
            // For iOS, ensure playsInline
            if (isIOS) {
                video.playsInline = true;
                video.setAttribute('playsinline', 'true');
                video.setAttribute('webkit-playsinline', 'true');
            }
            
            // Switch to mobile video
            if (currentSource && currentSource.src !== mobileUrl) {
                const wasPlaying = !video.paused;
                const currentTime = video.currentTime;
                
                currentSource.src = mobileUrl;
                video.load();
                
                // Wait for video to load, then play
                video.addEventListener('loadeddata', function playAfterLoad() {
                    video.removeEventListener('loadeddata', playAfterLoad);
                    const playPromise = video.play();
                    if (playPromise !== undefined) {
                        playPromise.catch(function(error) {
                            console.log('Mobile video play prevented:', error);
                        });
                    }
                }, { once: true });
                
                if (wasPlaying) {
                    video.currentTime = currentTime;
                }
            } else {
                // Ensure video is playing on mobile (video source already correct)
                if (video.paused) {
                    // Check if video is ready to play
                    if (video.readyState >= 2) {
                        // Video is ready, play immediately
                        const playPromise = video.play();
                        if (playPromise !== undefined) {
                            playPromise.catch(function(error) {
                                console.log('Mobile video autoplay prevented:', error);
                            });
                        }
                    } else {
                        // Video still loading, wait for it
                        video.addEventListener('loadeddata', function playAfterLoad() {
                            video.removeEventListener('loadeddata', playAfterLoad);
                            const playPromise = video.play();
                            if (playPromise !== undefined) {
                                playPromise.catch(function(error) {
                                    console.log('Mobile video autoplay prevented:', error);
                                });
                            }
                        }, { once: true });
                    }
                }
            }
        } else {
            // Desktop: unmute if it was muted for mobile, but only if user hasn't manually toggled
            // AND user hasn't started chatting yet
            if (video.muted && !video.hasAttribute('data-keep-muted')) {
                // Check if user has toggled mute (function may not exist yet on first call)
                if (!video._chatIsActive && (!video._userHasToggledMute || typeof video._userHasToggledMute !== 'function' || !video._userHasToggledMute())) {
                    video.muted = false;
                }
            }
            
            // Switch to desktop video
            if (currentSource && currentSource.src !== desktopUrl) {
                const wasPlaying = !video.paused;
                const currentTime = video.currentTime;
                
                currentSource.src = desktopUrl;
                video.load();
                
                if (wasPlaying) {
                    const playPromise = video.play();
                    if (playPromise !== undefined) {
                        playPromise.catch(function(error) {
                            // Auto-play was prevented
                        });
                    }
                }
                video.currentTime = currentTime;
            }
        }
    }
    
    // Video element
    const video = document.getElementById('boat-main-video');
    
    if (video) {
        const isMobile = window.innerWidth <= 768;
        // Detect iOS devices (including iPad on iOS 13+)
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || 
                      (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        let previousTime = 0; // Track previous video time to detect loop
        let hasReachedNearEnd = false; // Track if video has reached near the end
        let hasAttemptedUnmute = false; // Track if we've tried to unmute on mobile
        
        // Store chatIsActive state on video element so it's accessible from anywhere
        video._chatIsActive = false;
        
        // On mobile/iOS, ensure playsinline and muted attributes are set for autoplay
        // We'll try to unmute after video starts playing
        if (isMobile || isIOS) {
            video.setAttribute('playsinline', 'true');
            video.setAttribute('webkit-playsinline', 'true');
            video.muted = true;
            // Ensure video can play inline on iOS
            video.playsInline = true;
        }
        
        // Mute video after each loop completes
        // Since video has loop attribute, we need to detect loop by tracking time reset
        video.addEventListener('timeupdate', function() {
            const currentTime = video.currentTime;
            const duration = video.duration;
            
            if (duration <= 0) return; // Duration not loaded yet
            
            // Check if video has reached near the end (within 0.5 seconds)
            if (currentTime >= duration - 0.5) {
                hasReachedNearEnd = true;
            }
            
            // Detect loop: if we were near the end and now we're back near the beginning
            if (hasReachedNearEnd && currentTime < 0.5 && previousTime > duration - 0.5) {
                // Always mute video after loop completes (regardless of user toggle)
                video.muted = true;
                hasAttemptedUnmute = false; // Reset so video can be unmuted again on next interaction
                // Update mute button state if it exists
                const muteBtn = document.getElementById('boat-video-mute-btn');
                if (muteBtn) {
                    muteBtn.classList.add('muted');
                    muteBtn.classList.remove('unmuted');
                    muteBtn.setAttribute('aria-label', 'Unmute Video');
                    muteBtn.setAttribute('title', 'Unmute Video');
                }
                // Reset flag for next loop detection
                hasReachedNearEnd = false;
            }
            
            previousTime = currentTime;
        });
        
        // Also listen for ended event as fallback (in case loop doesn't work as expected)
        video.addEventListener('ended', function() {
            // Always mute video after loop completes (regardless of user toggle)
            video.muted = true;
            hasAttemptedUnmute = false; // Reset so video can be unmuted again on next interaction
            // Update mute button state if it exists
            const muteBtn = document.getElementById('boat-video-mute-btn');
            if (muteBtn) {
                muteBtn.classList.add('muted');
                muteBtn.classList.remove('unmuted');
                muteBtn.setAttribute('aria-label', 'Unmute Video');
                muteBtn.setAttribute('title', 'Unmute Video');
            }
        });
        
        // Ensure video plays on mobile/iOS - add event listeners for mobile compatibility
        video.addEventListener('loadedmetadata', function() {
            // On mobile/iOS, ensure video is muted for autoplay (only if user hasn't toggled)
            if ((isMobile || isIOS) && !video.muted) {
                // Check if user has toggled mute (function may not exist yet on first call)
                if (!video._userHasToggledMute || typeof video._userHasToggledMute !== 'function' || !video._userHasToggledMute()) {
                    video.muted = true;
                }
            }
            
            // For iOS, ensure playsInline is set
            if (isIOS) {
                video.playsInline = true;
            }
            
            const playPromise = video.play();
            if (playPromise !== undefined) {
                playPromise.then(function() {
                    // Video started playing - try to unmute on mobile after a short delay
                    // BUT only if user hasn't started chatting yet
                    if ((isMobile || isIOS) && !hasAttemptedUnmute && !video._chatIsActive && !video._userHasToggledMute()) {
                        setTimeout(function() {
                            // Try to unmute the video
                            video.muted = false;
                            hasAttemptedUnmute = true;
                            
                            // Update mute button if it exists
                            const muteBtn = document.getElementById('boat-video-mute-btn');
                            if (muteBtn) {
                                muteBtn.classList.add('unmuted');
                                muteBtn.classList.remove('muted');
                                muteBtn.setAttribute('aria-label', 'Mute Video');
                                muteBtn.setAttribute('title', 'Mute Video');
                            }
                        }, 500); // Small delay to ensure video is playing smoothly
                    }
                }).catch(function(error) {
                    // Auto-play was prevented, try again on user interaction
                    console.log('Video autoplay prevented, will play on user interaction:', error);
                });
            }
        });
        
        // Start video playing
        const playVideo = function() {
            // Ensure muted and playsInline for iOS
            if (isIOS) {
                video.playsInline = true;
                if (!video._userHasToggledMute || typeof video._userHasToggledMute !== 'function' || !video._userHasToggledMute()) {
                    video.muted = true;
                }
            }
            
            const playPromise = video.play();
            if (playPromise !== undefined) {
                playPromise.then(function() {
                    // Video started playing - try to unmute on mobile after a short delay
                    // BUT only if user hasn't started chatting yet
                    if ((isMobile || isIOS) && !hasAttemptedUnmute && !video._chatIsActive && (!video._userHasToggledMute || !video._userHasToggledMute())) {
                        setTimeout(function() {
                            // Try to unmute the video
                            video.muted = false;
                            hasAttemptedUnmute = true;
                            
                            // Update mute button if it exists
                            const muteBtn = document.getElementById('boat-video-mute-btn');
                            if (muteBtn) {
                                muteBtn.classList.add('unmuted');
                                muteBtn.classList.remove('muted');
                                muteBtn.setAttribute('aria-label', 'Mute Video');
                                muteBtn.setAttribute('title', 'Mute Video');
                            }
                        }, 500); // Small delay to ensure video is playing smoothly
                    }
                }).catch(function(error) {
                    // Auto-play was prevented - try on user interaction
                    console.log('Video autoplay prevented, will play on user interaction:', error);
                    
                    // Add one-time click handler to start video on user interaction
                    const startVideoOnInteraction = function() {
                        // Don't re-mute on user interaction - we want to UNMUTE
                        if (isIOS) {
                            video.playsInline = true;
                        }
                        
                        // Try to play and unmute immediately after user interaction
                        const playPromise = video.play();
                        if (playPromise !== undefined) {
                            playPromise.then(function() {
                                // User interaction allows us to unmute immediately
                                video.muted = false;
                                hasAttemptedUnmute = true;
                                const muteBtn = document.getElementById('boat-video-mute-btn');
                                if (muteBtn) {
                                    muteBtn.classList.add('unmuted');
                                    muteBtn.classList.remove('muted');
                                    muteBtn.setAttribute('aria-label', 'Mute Video');
                                    muteBtn.setAttribute('title', 'Mute Video');
                                }
                            }).catch(function(err) {
                                console.log('Video play failed:', err);
                            });
                        }
                        
                        document.removeEventListener('click', startVideoOnInteraction);
                        document.removeEventListener('touchstart', startVideoOnInteraction);
                    };
                    
                    document.addEventListener('click', startVideoOnInteraction, { once: true });
                    document.addEventListener('touchstart', startVideoOnInteraction, { once: true });
                });
            }
        };
        
        // Try to play immediately
        playVideo();
        
        // Add global user interaction handler to unmute video on mobile
        // This ensures that any tap/click will enable sound
        if (isMobile || isIOS) {
            const enableSoundOnInteraction = function(e) {
                // Only unmute if video is currently muted, not already attempted, and user hasn't manually toggled
                if (video.muted && !hasAttemptedUnmute && (!video._userHasToggledMute || !video._userHasToggledMute())) {
                    video.muted = false;
                    hasAttemptedUnmute = true;
                    
                    // Update mute button state
                    const muteBtn = document.getElementById('boat-video-mute-btn');
                    if (muteBtn) {
                        muteBtn.classList.add('unmuted');
                        muteBtn.classList.remove('muted');
                        muteBtn.setAttribute('aria-label', 'Mute Video');
                        muteBtn.setAttribute('title', 'Mute Video');
                    }
                    
                    console.log('Video unmuted after user interaction');
                }
                
                // Remove listeners after first interaction
                document.removeEventListener('click', enableSoundOnInteraction);
                document.removeEventListener('touchstart', enableSoundOnInteraction);
            };
            
            // Add listeners for first user interaction
            document.addEventListener('click', enableSoundOnInteraction, { once: true, passive: true });
            document.addEventListener('touchstart', enableSoundOnInteraction, { once: true, passive: true });
        }
        
        // Also try on canplay event for better iOS compatibility
        video.addEventListener('canplay', function() {
            if (video.paused) {
                const playPromise = video.play();
                if (playPromise !== undefined) {
                    playPromise.then(function() {
                        // Try to unmute after video starts
                        // BUT only if user hasn't started chatting yet
                        if ((isMobile || isIOS) && !hasAttemptedUnmute && !video._chatIsActive && (!video._userHasToggledMute || !video._userHasToggledMute())) {
                            setTimeout(function() {
                                video.muted = false;
                                hasAttemptedUnmute = true;
                                const muteBtn = document.getElementById('boat-video-mute-btn');
                                if (muteBtn) {
                                    muteBtn.classList.add('unmuted');
                                    muteBtn.classList.remove('muted');
                                    muteBtn.setAttribute('aria-label', 'Mute Video');
                                    muteBtn.setAttribute('title', 'Mute Video');
                                }
                            }, 500);
                        }
                    }).catch(function(err) {
                        console.log('Video play on canplay failed:', err);
                    });
                }
            }
        }, { once: true });
        
        // Additional mobile/iOS-specific handling
        // Try to play video when page becomes visible (helps with mobile browsers)
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && video.paused) {
                // Ensure muted on mobile/iOS (check dynamically, but respect user preference)
                const currentlyMobile = window.innerWidth <= 768;
                const currentlyIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || 
                                     (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
                
                if ((currentlyMobile || currentlyIOS) && !video.muted) {
                    // Check if user has toggled mute (function may not exist yet on first call)
                    if (!video._userHasToggledMute || typeof video._userHasToggledMute !== 'function' || !video._userHasToggledMute()) {
                        video.muted = true;
                    }
                }
                
                // For iOS, ensure playsInline
                if (currentlyIOS) {
                    video.playsInline = true;
                }
                
                video.play().then(function() {
                    // Try to unmute after video starts
                    // BUT only if user hasn't started chatting yet
                    if ((currentlyMobile || currentlyIOS) && !hasAttemptedUnmute && !video._chatIsActive && (!video._userHasToggledMute || !video._userHasToggledMute())) {
                        setTimeout(function() {
                            video.muted = false;
                            hasAttemptedUnmute = true;
                            const muteBtn = document.getElementById('boat-video-mute-btn');
                            if (muteBtn) {
                                muteBtn.classList.add('unmuted');
                                muteBtn.classList.remove('muted');
                                muteBtn.setAttribute('aria-label', 'Mute Video');
                                muteBtn.setAttribute('title', 'Mute Video');
                            }
                        }, 500);
                    }
                }).catch(function(error) {
                    // Ignore autoplay errors
                    console.log('Video play on visibility change failed:', error);
                });
            }
        });
        
        // Store function to mute video (for use when user sends message)
        video._muteVideo = function() {
            if (!video._userHasToggledMute || typeof video._userHasToggledMute !== 'function' || !video._userHasToggledMute()) {
                video.muted = true;
                hasAttemptedUnmute = false; // Reset so video can be unmuted again on next interaction
                video._chatIsActive = true; // Mark that user is chatting, so video should not auto-unmute
                // Update mute button state if it exists
                const muteBtn = document.getElementById('boat-video-mute-btn');
                if (muteBtn) {
                    muteBtn.classList.add('muted');
                    muteBtn.classList.remove('unmuted');
                    muteBtn.setAttribute('aria-label', 'Unmute Video');
                    muteBtn.setAttribute('title', 'Unmute Video');
                }
            }
        };
    }
    
    // Mute button functionality
    if (video) {
        const muteBtn = document.getElementById('boat-video-mute-btn');
        let userHasToggledMute = false; // Track if user manually toggled mute
        
        // Function to update mute button visual state
        function updateMuteButtonState() {
            if (!muteBtn) return;
            
            if (video.muted) {
                muteBtn.classList.add('muted');
                muteBtn.classList.remove('unmuted');
                muteBtn.setAttribute('aria-label', 'Unmute Video');
                muteBtn.setAttribute('title', 'Unmute Video');
            } else {
                muteBtn.classList.add('unmuted');
                muteBtn.classList.remove('muted');
                muteBtn.setAttribute('aria-label', 'Mute Video');
                muteBtn.setAttribute('title', 'Mute Video');
            }
        }
        
        // Initialize button state
        updateMuteButtonState();
        
        // Listen for mute state changes (from code or user)
        video.addEventListener('volumechange', updateMuteButtonState);
        
        // Mute button click handler
        if (muteBtn) {
            muteBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Toggle mute state
                video.muted = !video.muted;
                userHasToggledMute = true; // Mark that user has manually toggled
                
                // Update button state
                updateMuteButtonState();
            });
        }
        
        // Store user toggle state on video element for access in other functions
        video._userHasToggledMute = function() {
            return userHasToggledMute;
        };
        video._setUserToggledMute = function(value) {
            userHasToggledMute = value;
        };
    }
    
    // Switch video on load and resize
    switchVideoByScreenSize();
    
    // On mobile, ensure video plays after switching (with a small delay to ensure video is ready)
    if (video && window.innerWidth <= 768) {
        setTimeout(function() {
            if (video.paused && video.readyState >= 2) {
                // Only force mute if user hasn't manually toggled it
                if (!video._userHasToggledMute || typeof video._userHasToggledMute !== 'function' || !video._userHasToggledMute()) {
                    video.muted = true; // Ensure muted for autoplay
                    // Update button state if it exists
                    const muteBtn = document.getElementById('boat-video-mute-btn');
                    if (muteBtn) {
                        muteBtn.classList.add('muted');
                        muteBtn.classList.remove('unmuted');
                    }
                }
                video.play().catch(function(error) {
                    console.log('Mobile video autoplay prevented after switch:', error);
                });
            }
        }, 200);
    }
    
    let resizeTimer;
    let lastWidth = window.innerWidth;
    let lastHeight = window.innerHeight;
    
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            switchVideoByScreenSize();
            
            // Update the last conversation section min-height on resize
            updateLastConversationMinHeight();
            
            const currentWidth = window.innerWidth;
            const currentHeight = window.innerHeight;
            
            // Only reorganize layout if width changed significantly (orientation change)
            // Don't reorganize on height-only changes (keyboard on Android/iOS)
            const widthChanged = Math.abs(currentWidth - lastWidth) > 50;
            const heightChanged = Math.abs(currentHeight - lastHeight) > 100;
            
            // On Android, only reorganize if it's clearly an orientation change
            // (both dimensions changed significantly)
            if (isAndroid) {
                if (widthChanged && heightChanged) {
                    handleActionButtonsPosition(true); // Force run on orientation change
                    lastWidth = currentWidth;
                    lastHeight = currentHeight;
                }
            } else {
                // On other devices, reorganize if width changed
                if (currentWidth !== lastWidth) {
                    handleActionButtonsPosition();
                    lastWidth = currentWidth;
                }
            }
            
            lastHeight = currentHeight;
        }, 250);
    });
    
    // Detect Android device
    const isAndroid = /Android/i.test(navigator.userAgent);
    let layoutInitialized = false;
    
    // Function to handle action buttons positioning based on screen size
    function handleActionButtonsPosition(forceRun = false) {
        const container = $('.boat-chatbot-container');
        const inputWrapper = $('.boat-chatbot-input-wrapper');
        const actionButtons = $('.boat-chatbot-action-buttons');
        
        if (container.length === 0 || inputWrapper.length === 0 || actionButtons.length === 0) {
            return;
        }
        
        // On Android, only run once on initial load to prevent keyboard issues
        // After that, only run if explicitly forced (orientation change)
        if (isAndroid && layoutInitialized && !forceRun) {
            return;
        }
        
        // Don't reorganize layout if input is currently focused (prevents Android keyboard issue)
        const messageInput = $('#message-input');
        const chatbotInput = $('#boat-chatbot-message');
        if ((messageInput.length && messageInput.is(':focus')) || (chatbotInput.length && chatbotInput.is(':focus'))) {
            return;
        }
        
        const isDesktop = window.innerWidth >= 769;
        
        // Check if action buttons are currently inside input wrapper
        const isInside = inputWrapper.find('.boat-chatbot-action-buttons').length > 0;
        
        // Check if buttons row wrappers exist (old or new format)
        let buttonsRow = inputWrapper.find('.boat-chatbot-buttons-row');
        let firstRow = inputWrapper.find('.boat-chatbot-buttons-row-first');
        let secondRow = inputWrapper.find('.boat-chatbot-buttons-row-second');
        
        if (isDesktop) {
            // Desktop: Remove all button row wrappers if they exist and restore original structure
            // But keep the input container structure (input + send button inside)
            if (buttonsRow.length > 0) {
                // Unwrap buttons from the old row container
                const buttons = buttonsRow.children();
                buttonsRow.replaceWith(buttons);
            }
            if (firstRow.length > 0) {
                // Unwrap buttons from the first row container, but preserve input container
                const buttons = firstRow.children();
                // Check if input container is inside firstRow
                const inputContainerInRow = firstRow.find('.boat-chatbot-input-container').first();
                if (inputContainerInRow.length) {
                    // Keep input container structure, just unwrap other buttons
                    const otherButtons = firstRow.children().not('.boat-chatbot-input-container');
                    otherButtons.detach();
                    firstRow.replaceWith(inputContainerInRow);
                    // Re-add other buttons to input wrapper
                    inputWrapper.prepend(otherButtons);
                } else {
                    firstRow.replaceWith(buttons);
                }
            }
            if (secondRow.length > 0) {
                // Unwrap buttons from the second row container
                const buttons = secondRow.children();
                secondRow.replaceWith(buttons);
            }
            
            // Ensure input container exists and send button is inside it
            const inputContainer = inputWrapper.find('.boat-chatbot-input-container').first();
            const sendBtn = inputWrapper.find('.boat-chatbot-send-btn').first();
            const input = inputWrapper.find('.boat-chatbot-input, #message-input').first();
            
            // If send button is outside input container, move it inside
            if (sendBtn.length && inputContainer.length) {
                const sendInContainer = inputContainer.find('.boat-chatbot-send-btn').first();
                if (!sendInContainer.length) {
                    // Send button is outside container, move it inside
                    sendBtn.detach();
                    inputContainer.append(sendBtn);
                }
            }
            
            // If input is outside container, wrap it with send button
            if (input.length && !inputContainer.length) {
                // Create input container
                const newContainer = $('<div class="boat-chatbot-input-container"></div>');
                input.detach();
                sendBtn.detach();
                newContainer.append(input);
                if (sendBtn.length) {
                    newContainer.append(sendBtn);
                }
                inputWrapper.append(newContainer);
            }
            
            // Move action buttons outside input wrapper (as sibling)
            if (isInside) {
                // Detach action buttons from inside
                const actionButtonsElement = inputWrapper.find('.boat-chatbot-action-buttons').first();
                if (actionButtonsElement.length > 0) {
                    // Move to outside as sibling of input wrapper
                    actionButtonsElement.detach();
                    inputWrapper.after(actionButtonsElement);
                }
            }
        } else {
            // Mobile: Create two rows
            // First row: [Voice] [Image] [Input] [Send]
            // Second row: [Action Buttons]
            
            // First, ensure action buttons are inside input wrapper
            if (!isInside) {
                // Find action buttons that are siblings of input wrapper
                const actionButtonsElement = container.children('.boat-chatbot-action-buttons').first();
                if (actionButtonsElement.length > 0) {
                    // Append to input wrapper
                    actionButtonsElement.detach();
                    inputWrapper.append(actionButtonsElement);
                }
            }
            
            // Get all buttons and input container
            const voiceBtn = inputWrapper.find('.boat-chatbot-voice-btn').first();
            const imageBtn = inputWrapper.find('.boat-chatbot-image-btn').first();
            const actionButtonsElement = inputWrapper.find('.boat-chatbot-action-buttons').first();
            const inputContainer = inputWrapper.find('.boat-chatbot-input-container').first();
            
            // Check if we need to create or update the row wrappers
            if (voiceBtn.length && imageBtn.length && actionButtonsElement.length && inputContainer.length) {
                // Check for existing row wrappers
                let firstRow = inputWrapper.find('.boat-chatbot-buttons-row-first');
                let secondRow = inputWrapper.find('.boat-chatbot-buttons-row-second');
                
                if (firstRow.length === 0) {
                    // Create first row wrapper for: voice, image, input, send
                    firstRow = $('<div class="boat-chatbot-buttons-row-first"></div>');
                    
                    // Insert first row at the beginning of input wrapper
                    inputWrapper.prepend(firstRow);
                    
                    // Move voice, image, and input container into first row
                    voiceBtn.detach();
                    imageBtn.detach();
                    inputContainer.detach();
                    
                    firstRow.append(voiceBtn);
                    firstRow.append(imageBtn);
                    firstRow.append(inputContainer);
                } else {
                    // First row exists, ensure correct order
                    const voice = firstRow.find('.boat-chatbot-voice-btn').first();
                    const image = firstRow.find('.boat-chatbot-image-btn').first();
                    const inputContainerInRow = firstRow.find('.boat-chatbot-input-container').first();
                    
                    // Move any buttons that are outside into first row
                    if (voiceBtn.length && !voice.length) {
                        voiceBtn.detach();
                        firstRow.append(voiceBtn);
                    }
                    if (imageBtn.length && !image.length) {
                        imageBtn.detach();
                        firstRow.append(imageBtn);
                    }
                    if (inputContainer.length && !inputContainerInRow.length) {
                        inputContainer.detach();
                        firstRow.append(inputContainer);
                    }
                    
                    // Ensure correct order: voice, image, input container
                    if (voice.length && image.length && inputContainerInRow.length) {
                        const voiceEl = voice.detach();
                        const imageEl = image.detach();
                        const inputContainerEl = inputContainerInRow.detach();
                        
                        firstRow.append(voiceEl);
                        firstRow.append(imageEl);
                        firstRow.append(inputContainerEl);
                    }
                }
                
                // Create or update second row for action buttons only (no send button)
                if (secondRow.length === 0) {
                    // Create second row wrapper for action buttons
                    secondRow = $('<div class="boat-chatbot-buttons-row-second"></div>');
                    
                    // Insert second row after first row
                    firstRow.after(secondRow);
                    
                    // Move action buttons into second row
                    actionButtonsElement.detach();
                    secondRow.append(actionButtonsElement);
                } else {
                    // Second row exists, ensure action buttons are inside it
                    const actionsInRow = secondRow.find('.boat-chatbot-action-buttons').first();
                    
                    if (actionButtonsElement.length && !actionsInRow.length) {
                        actionButtonsElement.detach();
                        secondRow.append(actionButtonsElement);
                    }
                }
            }
        }
        
        // Mark layout as initialized (especially important for Android)
        layoutInitialized = true;
    }
    
    // Handle action buttons positioning on page load
    // Use setTimeout to ensure DOM is fully ready
    setTimeout(function() {
        handleActionButtonsPosition();
    }, 100);
    
    // Also handle on DOMContentLoaded for immediate execution
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(handleActionButtonsPosition, 50);
        });
    }
    
    
    // Get elements using new IDs
    const messageInput = $('#message-input');
    const sendBtn = $('#send-btn');
    const newChatBtn = $('#new-chat-btn');
    
    // Also keep old IDs for backward compatibility
    const chatbotInput = $('#boat-chatbot-message');
    const sendButton = $('#boat-chatbot-send');
    
    // Prevent input from losing focus on Android when keyboard appears
    let inputFocusTimestamp = 0;
    
    if (messageInput.length) {
        messageInput.on('focus', function() {
            $(this).data('user-focused', true);
            inputFocusTimestamp = Date.now();
        });
        messageInput.on('blur', function(e) {
            // Prevent blur if it happens within 300ms of focus (Android keyboard issue)
            const timeSinceFocus = Date.now() - inputFocusTimestamp;
            if (isAndroid && timeSinceFocus < 300) {
                e.preventDefault();
                // Refocus the input
                setTimeout(function() {
                    if (!messageInput.data('intentional-blur')) {
                        messageInput.focus();
                    }
                    messageInput.data('intentional-blur', false);
                }, 10);
                return false;
            }
            
            // Small delay to allow intentional blur (like after sending message)
            setTimeout(function() {
                messageInput.data('user-focused', false);
            }, 100);
        });
    }
    
    if (chatbotInput.length) {
        chatbotInput.on('focus', function() {
            $(this).data('user-focused', true);
            inputFocusTimestamp = Date.now();
        });
        chatbotInput.on('blur', function(e) {
            // Prevent blur if it happens within 300ms of focus (Android keyboard issue)
            const timeSinceFocus = Date.now() - inputFocusTimestamp;
            if (isAndroid && timeSinceFocus < 300) {
                e.preventDefault();
                // Refocus the input
                setTimeout(function() {
                    if (!chatbotInput.data('intentional-blur')) {
                        chatbotInput.focus();
                    }
                    chatbotInput.data('intentional-blur', false);
                }, 10);
                return false;
            }
            
            // Small delay to allow intentional blur (like after sending message)
            setTimeout(function() {
                chatbotInput.data('user-focused', false);
            }, 100);
        });
    }
    
    // Store pagination state for landing page listings
    // Backend behavior:
    // - If specific count requested (e.g., "show me 10 boats"): show only that many, no pagination
    // - If no count specified: show 5 items per page with pagination
    // - load-listings: Always uses limit=5 (hardcoded in handle_load_listings, line 581)
    let landingListingsState = {
        totalListings: 0,
        loadedListings: [],
        currentQuery: '',
        isLoadingMore: false,
        initialItemsCount: 0, // Will be set dynamically from first response
        itemsPerPage: 5, // Backend load-listings endpoint always returns 5 items
        enablePagination: true // Whether pagination is enabled (false if specific count requested)
    };
    
    // Landing page chatbot functionality
    const landingChatbot = {
        conversationHistory: [],
        loadedListings: [],
        currentQuery: '',
        totalListings: 0,
        isLoadingMore: false,
        initialItemsCount: 5,
        listingsPerPage: 5,
        isStreaming: false,
        currentStreamingMessage: null,
        currentStreamingContent: null,
        isRecording: false,
        recognition: null, // Web Speech API recognition object
        currentAudio: null, // For TTS playback
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            const self = this;
            
            // Send button click
    if (sendBtn.length) {
        sendBtn.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            self.sendMessage();
            // Explicitly blur input to dismiss mobile keyboard
            if (messageInput.length) {
                messageInput.data('intentional-blur', true);
                messageInput.blur();
            }
            if (chatbotInput.length) {
                chatbotInput.data('intentional-blur', true);
                chatbotInput.blur();
            }
        });
    }
    
            // Enter key press
    if (messageInput.length) {
        messageInput.on('keypress', function(e) {
                    if (e.which === 13 || e.keyCode === 13) {
                e.preventDefault();
                        self.sendMessage();
                        // Explicitly blur input to dismiss mobile keyboard
                        messageInput.data('intentional-blur', true);
                        messageInput.blur();
                    }
        });
            }
        
            // Also handle old IDs
            if (sendButton.length) {
                sendButton.on('click', function(e) {
                e.preventDefault();
                    self.sendMessage();
                    // Explicitly blur input to dismiss mobile keyboard
                    if (messageInput.length) {
                        messageInput.data('intentional-blur', true);
                        messageInput.blur();
                    }
                    if (chatbotInput.length) {
                        chatbotInput.data('intentional-blur', true);
                        chatbotInput.blur();
                    }
                });
            }
            
            if (chatbotInput.length) {
                chatbotInput.on('keypress', function(e) {
                    if (e.which === 13 || e.keyCode === 13) {
                        e.preventDefault();
                        self.sendMessage();
                        // Explicitly blur input to dismiss mobile keyboard
                        chatbotInput.data('intentional-blur', true);
                        chatbotInput.blur();
                    }
        });
    }
    
            // New chat button
    if (newChatBtn.length) {
        newChatBtn.on('click', function(e) {
            e.preventDefault();
                    self.newChat();
                });
            }
            
            // Voice button - Speech-to-Text
            const voiceBtn = $('#boat-chatbot-voice');
            if (voiceBtn.length) {
                voiceBtn.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    // self.toggleVoiceRecording();
                });
            }
        },
        
        sendMessage: function() {
            if (this.isStreaming) return;
            
            // Get message from either input field
            let message = '';
            if (messageInput.length) {
                message = messageInput.val().trim();
            } else if (chatbotInput.length) {
                message = chatbotInput.val().trim();
            }
            
            if (!message) return;
            
            
            // Hide video section and show chat container
            this.showChatContainer();
            
            // Add user message to chat
            this.addMessage(message, 'user');
            
            // Add to conversation history
            this.conversationHistory.push({
                role: 'user',
                content: message
            });
            
            // Clear input and remove focus
            if (messageInput.length) {
                messageInput.val('');
                messageInput.data('intentional-blur', true);
                messageInput.blur();
            }
            if (chatbotInput.length) {
                chatbotInput.val('');
                chatbotInput.data('intentional-blur', true);
                chatbotInput.blur();
            }
            
            // Store current query for listings
            this.currentQuery = message;
            this.loadedListings = [];
            this.totalListings = 0;
            
            // Show typing indicator
            this.showTypingIndicator();
            
            // Send message with streaming
            this.sendMessageWithStreaming(message);
        },
        
        sendMessageWithStreaming: function(message) {
            const self = this;
            this.isStreaming = true;
            
            const restUrl = window.boatChatbot ? window.boatChatbot.restUrl : '';
            const restNonce = window.boatChatbot ? window.boatChatbot.restNonce : '';
            
            if (!restUrl) {
                console.error('REST URL not found');
                this.hideTypingIndicator();
                this.isStreaming = false;
                return;
            }
            
            // Create message element for streaming content
            const messageId = 'msg-' + Date.now();
            const messageHtml = `
                <div class="boat-chatbot-message boat-chatbot-message-assistant" id="${messageId}">
                    <div class="boat-chatbot-message-avatar">AI</div>
                    <div class="boat-chatbot-message-content">
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                    </div>
                </div>
            `;
            // Hide typing indicator and add streaming message with thinking indicator
            this.hideTypingIndicator();
            $('#boat-landing-messages-container').append(messageHtml);
            const messageElement = $('#' + messageId);
            const contentElement = messageElement.find('.boat-chatbot-message-content');
            
            // Store references for fallback
            this.currentStreamingMessage = messageElement;
            this.currentStreamingContent = contentElement;
            
            let accumulatedContent = '';
            let hasReceivedContent = false;
            
            // Use fetch API for streaming with POST support
            fetch(restUrl + 'send-message-stream', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': restNonce || ''
                },
                body: JSON.stringify({
                    message: message,
                    conversation_history: this.conversationHistory,
                    nonce: restNonce
                })
            })
            .then(response => {
                console.log('Stream response received, status:', response.status);
                
                if (!response.ok) {
                    // Try to get error message
                    return response.text().then(text => {
                        console.error('Response error:', text);
                        throw new Error('Network response was not ok: ' + response.status);
                    });
                }
                
                // Check if response body exists
                if (!response.body) {
                    throw new Error('Response body is null');
                }
                
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                
                function readChunk() {
                    return reader.read().then(({ done, value }) => {
                        if (done) {
                            // Process any remaining buffer content
                            if (buffer.trim()) {
                                const trimmedLine = buffer.trim();
                                if (trimmedLine.startsWith('data: ')) {
                                    try {
                                        const jsonStr = trimmedLine.substring(6);
                                        const data = JSON.parse(jsonStr);
                                        if (data.type === 'content' && data.data) {
                                            if (!hasReceivedContent) {
                                                contentElement.text(data.data);
                                                hasReceivedContent = true;
                                            } else {
                                                const currentText = contentElement.text();
                                                contentElement.text(currentText + data.data);
                                            }
                                            accumulatedContent += data.data || '';
                                        }
                                    } catch (e) {
                                        console.error('Error parsing final buffer:', e);
                                    }
                                }
                            }
                            
                            // If stream ends without 'done' event, finalize with accumulated content
                            if (accumulatedContent && hasReceivedContent) {
                                self.handleStreamComplete({ data: { response: accumulatedContent } }, accumulatedContent, messageElement);
                            } else if (!hasReceivedContent) {
                                // No content received, show error
                                console.error('Stream ended without content');
                                if (messageElement && messageElement.length && contentElement) {
                                    contentElement.html('<span style="color: #d63638;">Error: No response received from server.</span>');
                                }
                                self.isStreaming = false;
                                self.hideTypingIndicator();
                            }
                            return;
                        }
                        
                        // Decode the chunk
                        const chunk = decoder.decode(value, { stream: true });
                        buffer += chunk;
                        
                        // Process complete SSE messages (lines ending with \n\n)
                        const lines = buffer.split('\n\n');
                        buffer = lines.pop() || ''; // Keep incomplete line in buffer
                        
                        for (const line of lines) {
                            const trimmedLine = line.trim();
                            if (!trimmedLine) continue; // Skip empty lines
                            
                            // Handle SSE format: "data: {...}"
                            if (trimmedLine.startsWith('data: ')) {
                                try {
                                    const jsonStr = trimmedLine.substring(6); // Remove "data: " prefix
                                    const data = JSON.parse(jsonStr);
                                    
                                    // Handle content chunks
                                    if (data.type === 'content' && data.data) {
                                        // Handle content chunks
                                        if (!hasReceivedContent) {
                                            // First chunk - replace thinking indicator with actual content
                                            contentElement.text(data.data);
                                            hasReceivedContent = true;
                                        } else {
                                            // Subsequent chunks - append text
                                            const currentText = contentElement.text();
                                            contentElement.text(currentText + data.data);
                                        }
                                        
                                        accumulatedContent += data.data || '';
                                        
                                        // Auto-scroll as content streams
                                        self.scrollToBottom();
                                    }
                                    
                                    // Handle error
                                    if (data.type === 'error') {
                                        console.error('Stream error:', data.data);
                                        if (messageElement && messageElement.length && contentElement) {
                                            contentElement.html('<span style="color: #d63638;">Error: ' + (data.data || 'Unknown error') + '</span>');
                                        }
                                        self.isStreaming = false;
                                        self.hideTypingIndicator();
                                        return;
                                    }
                                    
                                    // Handle completion
                                    if (data.type === 'done') {
                                        self.handleStreamComplete(data, accumulatedContent, messageElement);
                                        return;
                                    }
                                    
                                    // Handle close
                                    if (data.type === 'close') {
                                        self.isStreaming = false;
                                        return;
                                    }
                                    
                                    // Handle intent (for debugging)
                                    if (data.type === 'intent') {
                                        console.log('Intent detected:', data.data);
                                    }
                                } catch (e) {
                                    console.error('Error parsing SSE data:', e);
                                    console.error('Line that failed:', trimmedLine);
                                    console.error('Full buffer:', buffer);
                                }
                            } else if (trimmedLine.startsWith(':')) {
                                // SSE comment line, ignore
                                continue;
                            } else {
                                // Unexpected format, log for debugging
                                console.warn('Unexpected SSE line format:', trimmedLine);
                            }
                        }
                        
                        // Continue reading
                        return readChunk();
                    });
                }
                
                return readChunk();
            })
            .catch(error => {
                console.error('Streaming error:', error);
                
                // Fallback to regular AJAX if streaming fails
                self.fallbackToRegularAjax(message);
            });
        },
        
        fallbackToRegularAjax: function(message) {
            const self = this;
            const restUrl = window.boatChatbot ? window.boatChatbot.restUrl : '';
            const restNonce = window.boatChatbot ? window.boatChatbot.restNonce : '';
            
            const messageElement = this.currentStreamingMessage;
            const contentElement = this.currentStreamingContent;
            
            // If message element exists with thinking indicator, keep it (don't remove)
            // The content will be updated below
            
            // Use regular AJAX as fallback
            $.ajax({
                url: restUrl + 'send-message',
                type: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    if (restNonce) {
                        xhr.setRequestHeader('X-WP-Nonce', restNonce);
                    }
                },
                data: JSON.stringify({
                    message: message,
                    conversation_history: self.conversationHistory,
                    nonce: restNonce
                }),
                success: function(response) {
                    self.hideTypingIndicator();
                    self.isStreaming = false;
                    
                    let hasListings = false;
                    
                    if (response.success && response.data) {
                        const data = response.data;
                        
                        // Add AI message
                        if (messageElement && messageElement.length && contentElement) {
                            // Update existing message (replace thinking indicator or existing content)
                            contentElement.html(self.formatMessage(data.response));
                        } else {
                            // Add new message
                            self.addMessage(data.response, 'assistant');
                        }
                        
                        // Add to conversation history
                        self.conversationHistory.push({
                            role: 'assistant',
                            content: data.response
                        });
                        
                        // Display listings if available
                        if (data.listings && data.listings.length > 0) {
                            hasListings = true;
                            self.loadedListings = data.listings;
                            self.totalListings = data.total_listings || data.listings.length;
                            self.initialItemsCount = data.listings.length;
                            self.displayListings(data.listings, false);
                            
                            if (data.has_more && data.total_listings > data.listings.length) {
                                self.addLoadMoreButton(data.total_listings, data.listings.length);
                            }
                        } else {
                            // // Play TTS if enabled (only for text responses, not listings)
                            // setTimeout(function() {
                            //     self.playTTS(data.response);
                            // }, 500); // Small delay to ensure message is displayed
                        }
                        
                        // Log performance metrics if available
                        if (data.performance_log) {
                            const perfMetrics = {
                                total: data.response_time ? (data.response_time * 1000).toFixed(2) + 'ms' : 'N/A',
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
                        // Show error message
                        if (messageElement && messageElement.length && contentElement) {
                            contentElement.html('Sorry, I encountered an error. Please try again.');
                        } else {
                            self.addMessage('Sorry, I encountered an error. Please try again.', 'assistant');
                        }
                    }
                    
                    // Clear streaming references
                    self.currentStreamingMessage = null;
                    self.currentStreamingContent = null;
                    
                    // Ensure input loses focus after successful send
                    if (messageInput.length) {
                        messageInput.data('intentional-blur', true);
                        messageInput.blur();
                    }
                    if (chatbotInput.length) {
                        chatbotInput.data('intentional-blur', true);
                        chatbotInput.blur();
                    }
                    
                    // If there are listings, scroll to the start of the response message
                    // Otherwise scroll to bottom
                    if (hasListings && messageElement) {
                        self.scrollToMessage(messageElement);
                    } else {
                        self.scrollToBottom();
                    }
                },
                error: function(xhr) {
                    self.hideTypingIndicator();
                    self.isStreaming = false;
                    
                    let errorMsg = 'Sorry, I\'m having connection issues. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    
                    // Show error message
                    if (messageElement && messageElement.length && contentElement) {
                        contentElement.html(errorMsg);
                    } else {
                        self.addMessage(errorMsg, 'assistant');
                    }
                    
                    // Clear streaming references
                    self.currentStreamingMessage = null;
                    self.currentStreamingContent = null;
                    
                    self.scrollToBottom();
                }
            });
        },
        
        
        handleStreamComplete: function(data, accumulatedContent, messageElement) {
            const self = this;
            this.isStreaming = false;
            
            let finalResponse = '';
            let hasListings = false;
            
            if (data.data) {
                const responseData = data.data;
                const contentElement = messageElement ? messageElement.find('.boat-chatbot-message-content') : this.currentStreamingContent;
                
                // Format the final accumulated content
                if (responseData.response) {
                    // Use the response from the done event (complete response)
                    finalResponse = responseData.response;
                    contentElement.html(this.formatMessage(finalResponse));
                    
                    // Add to conversation history
                    this.conversationHistory.push({
                        role: 'assistant',
                        content: finalResponse
                    });
                } else if (accumulatedContent) {
                    // Fallback: format accumulated content if no response in done event
                    finalResponse = accumulatedContent;
                    contentElement.html(this.formatMessage(finalResponse));
                    
                    // Add to conversation history
                    this.conversationHistory.push({
                        role: 'assistant',
                        content: finalResponse
                    });
                }
                
                // Display listings if available
                if (responseData.listings && responseData.listings.length > 0) {
                    hasListings = true;
                    this.loadedListings = responseData.listings;
                    this.totalListings = responseData.total_listings || responseData.listings.length;
                    this.initialItemsCount = responseData.listings.length;
                    this.displayListings(responseData.listings, false);
                    
                    if (responseData.has_more && responseData.total_listings > responseData.listings.length) {
                        this.addLoadMoreButton(responseData.total_listings, responseData.listings.length);
                    }
                }
                
                // Log performance metrics if available
                if (responseData.performance_log) {
                    const perfMetrics = {
                        total: responseData.response_time ? (responseData.response_time * 1000).toFixed(2) + 'ms' : 'N/A',
                        intent: responseData.performance_log.intent_time ? (responseData.performance_log.intent_time * 1000).toFixed(2) + 'ms' : 'N/A',
                        db: responseData.performance_log.db_time ? (responseData.performance_log.db_time * 1000).toFixed(2) + 'ms' : 'N/A',
                        ai: responseData.performance_log.ai_time ? (responseData.performance_log.ai_time * 1000).toFixed(2) + 'ms' : 'N/A',
                        cached: responseData.cached || false
                    };
                    
                    // Add sparse vector information if available
                    if (responseData.performance_log.sparse_vector_generated !== undefined) {
                        perfMetrics.sparse_vector_used = responseData.performance_log.sparse_vector_generated;
                    }
                    if (responseData.performance_log.sparse_vector_method) {
                        perfMetrics.sparse_method = responseData.performance_log.sparse_vector_method;
                    }
                    if (responseData.performance_log.hybrid_search_used !== undefined) {
                        perfMetrics.hybrid_search = responseData.performance_log.hybrid_search_used;
                    }
                    
                    // Add embedding and vector search time if available
                    if (responseData.performance_log.embedding_time) {
                        perfMetrics.embedding = (responseData.performance_log.embedding_time * 1000).toFixed(2) + 'ms';
                    }
                    if (responseData.performance_log.vector_search_time) {
                        perfMetrics.vector_search = (responseData.performance_log.vector_search_time * 1000).toFixed(2) + 'ms';
                    }
                    if (responseData.performance_log.keyword_search_time) {
                        perfMetrics.keyword_search = (responseData.performance_log.keyword_search_time * 1000).toFixed(2) + 'ms';
                    }
                    
                    console.log('Performance Metrics:', perfMetrics);
                    console.log('Full Performance Log:', responseData.performance_log);
                }
                
                // // Play TTS if enabled (only for text responses, not listings)
                // if (finalResponse && !responseData.listings) {
                //     // Check if TTS is enabled via a simple check (you can make this more sophisticated)
                //     // For now, we'll always try TTS - it will fail silently if not configured
                //     setTimeout(function() {
                //         self.playTTS(finalResponse);
                //     }, 500); // Small delay to ensure message is displayed
                // }
            }
            
            // Clear streaming references
            this.currentStreamingMessage = null;
            this.currentStreamingContent = null;
            
            // Ensure input loses focus after successful send
            if (messageInput.length) {
                messageInput.data('intentional-blur', true);
                messageInput.blur();
            }
            if (chatbotInput.length) {
                chatbotInput.data('intentional-blur', true);
                chatbotInput.blur();
            }
            
            // If there are listings, scroll to the start of the response message
            // Otherwise scroll to bottom
            if (hasListings && messageElement) {
                this.scrollToMessage(messageElement);
            } else {
                this.scrollToBottom();
            }
        },
        
        displayListings: function(listings, append = false) {
            if (!listings || listings.length === 0) return;
            
            let listingsHtml = '';
            
            if (!append) {
                listingsHtml = '<div class="boat-chatbot-listings-container">';
                listingsHtml += '<div class="boat-chatbot-listings-header">Available Listings:</div>';
            }
            
            listings.forEach((listing) => {
                const title = this.escapeHtml(listing.VesselName || 'Untitled');
                const type = this.escapeHtml(listing.Type_ || 'N/A');
                const length = listing.DisplayLengthFeet || 'N/A';
                const price = listing.PriceUSD ? '$' + parseFloat(listing.PriceUSD).toLocaleString() : 'N/A';
                const location = this.escapeHtml(listing.State || 'N/A');
                const Manufacturer = this.escapeHtml(listing.Manufacturer || 'N/A');
                const Year = this.escapeHtml(listing.Year || 'N/A');
                // Generate detail page URL
                const detailPageUrl = "/yacht-details/";
                let vesselSEOTitleAndAlterText = listing.DisplayLengthFeet + '-ft-' + listing.Manufacturer + '-' + listing.Year + '-' + listing.Model + '-' + listing.VesselName + ' ' + listing.City + ' ' + listing.State + ' ' + listing.Country + '  yacht for sale';
                
                let string = vesselSEOTitleAndAlterText;
                let string_lower_status = 'true';
                let replace_with = '-';
                
                string = string.replace(/[\[\]]/g, '');
                string = string.replace(/\[.*?\]/g, '');
                string = string.replace(/&(amp;)?#?[a-z0-9]+;/gi, replace_with);
                string = string.replace(/&([a-z])(acute|uml|circ|grave|ring|cedil|slash|tilde|caron|lig|quot|rsquo);/gi, '$1');
                string = string.replace(/[^a-z0-9]/gi, replace_with);
                string = string.replace(/[-]+/g, replace_with);
                
                let seoTitleForVesselDetail = string;
                if (string_lower_status === 'true') {
                    seoTitleForVesselDetail = string.toLowerCase();
                }
                seoTitleForVesselDetail = seoTitleForVesselDetail.trim().replace(new RegExp('^' + replace_with + '+|' + replace_with + '+$', 'g'), '');
                
                const detail_page_url = detailPageUrl + '' + seoTitleForVesselDetail + '_' + listing.ID;
                
                // Get image URL
                let imageUrl = '';
                if (listing.Thumbnail) {
                    imageUrl = listing.Thumbnail;
                } else if (listing.ImageURL || listing.PhotoURL || listing.ThumbnailURL) {
                    imageUrl = listing.ImageURL || listing.PhotoURL || listing.ThumbnailURL;
                }
                
                listingsHtml += '<div class="boat-chatbot-listing-item">';
                listingsHtml += '<div class="boat-chatbot-listing-content-wrapper">';
                
                // Image preview
                if (imageUrl) {
                    listingsHtml += '<div class="boat-chatbot-listing-image">';
                    listingsHtml += '<a href="' + detail_page_url + '" target="_blank" rel="noopener">';
                    listingsHtml += '<img src="' + this.escapeHtml(imageUrl) + '" alt="' + title + '" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'block\';">';
                    listingsHtml += '<div class="boat-chatbot-listing-image-placeholder" style="display: none;">';
                    listingsHtml += '<svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">';
                    listingsHtml += '<path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>';
                    listingsHtml += '</svg>';
                    listingsHtml += '</div>';
                    listingsHtml += '</a>';
                    listingsHtml += '</div>';
                }
                
                // Listing details
                listingsHtml += '<div class="boat-chatbot-listing-info">';
                listingsHtml += '<div class="boat-chatbot-listing-title">';
                listingsHtml += '<a href="' + detail_page_url + '" target="_blank" rel="noopener">' + Year+ " " + Manufacturer+ " " + length+ " " + "in  fit" +  '</a>';
                listingsHtml += '</div>';
                listingsHtml += '<div class="boat-chatbot-listing-details">';
                listingsHtml += type + ' | ' + length + '\' | ' + price + ' | ' + location;
                listingsHtml += '</div>';
                listingsHtml += '</div>'; // .boat-chatbot-listing-info
                listingsHtml += '</div>'; // .boat-chatbot-listing-content-wrapper
                listingsHtml += '</div>'; // .boat-chatbot-listing-item
            });
            
            if (!append) {
                listingsHtml += '</div>';
                this.addMessage(listingsHtml, 'assistant', true);
            } else {
                // When appending (load more), just add the listings without auto-scrolling
                // User can manually scroll if they want to see more
                $('.boat-chatbot-listings-container').append(listingsHtml);
            }
        },
        
        addLoadMoreButton: function(total, current) {
            const self = this;
            const remaining = total - current;
            
            // Remove any existing load more buttons first to prevent duplicates
            $('.boat-chatbot-load-more').remove();
            
            // Find the last listings container to append the button to
            const $listingsContainer = $('.boat-chatbot-listings-container').last();
            if ($listingsContainer.length === 0) {
                return; // No listings container found, don't add button
            }
            
            const loadMoreHtml = `
                <div class="boat-chatbot-load-more">
                    <button class="boat-chatbot-load-more-btn" data-offset="${current}">
                        Load ${remaining} more listing${remaining > 1 ? 's' : ''}
                    </button>
                </div>
            `;
            
            $listingsContainer.append(loadMoreHtml);
            
            $('.boat-chatbot-load-more-btn').off('click').on('click', function(e) {
                e.preventDefault();
                const offset = parseInt($(this).data('offset'));
                self.loadMoreListings(offset);
            });
        },
        
        loadMoreListings: function(offset) {
            if (this.isLoadingMore) return;
            
            const self = this;
            this.isLoadingMore = true;
            const $btn = $('.boat-chatbot-load-more-btn');
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Loading...');
            
            const restUrl = window.boatChatbot ? window.boatChatbot.restUrl : '';
            const restNonce = window.boatChatbot ? window.boatChatbot.restNonce : '';
            
            $.ajax({
                url: restUrl + 'load-listings',
                type: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    if (restNonce) {
                        xhr.setRequestHeader('X-WP-Nonce', restNonce);
                    }
                },
                data: JSON.stringify({
                    query: this.currentQuery,
                    offset: offset,
                    limit: 5,
                    nonce: restNonce
                }),
                success: function(response) {
                    self.isLoadingMore = false;
                    if (response.success && response.data.listings.length > 0) {
                        self.displayListings(response.data.listings, true);
                        self.loadedListings = self.loadedListings.concat(response.data.listings);
                        self.totalListings = response.data.total || self.loadedListings.length;
                        
                        $('.boat-chatbot-load-more').remove();
                        
                        if (response.data.total > self.loadedListings.length) {
                            self.addLoadMoreButton(response.data.total, self.loadedListings.length);
                        }
                        
                        setTimeout(() => {
                            self.scrollToBottom();
                        }, 100);
                    } else {
                        $('.boat-chatbot-load-more').remove();
                    }
                },
                error: function() {
                    self.isLoadingMore = false;
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },
        
        addMessage: function(message, role, isHtml = false) {
            const messageClass = role === 'user' ? 'boat-chatbot-message-user' : 'boat-chatbot-message-assistant';
            let formattedMessage = message;
            
            if (role === 'assistant' && !isHtml) {
                formattedMessage = this.formatMessage(message);
            } else if (!isHtml) {
                formattedMessage = this.escapeHtml(message);
            }
            
            const messageHtml = `
                <div class="boat-chatbot-message ${messageClass}">
                    <div class="boat-chatbot-message-avatar">${role === 'user' ? 'U' : 'AI'}</div>
                    <div class="boat-chatbot-message-content">${formattedMessage}</div>
                </div>
            `;
            
            $('#boat-landing-messages-container').append(messageHtml);
            
            // Don't auto-scroll for listings HTML - will be handled by calling function
            // Only auto-scroll for regular text messages
            if (!isHtml) {
                setTimeout(() => {
                    this.scrollToBottom();
                }, 50);
            }
        },
        
        formatMessage: function(text) {
            if (!text) return '';
            
            let formatted = this.escapeHtml(text);
            
            // Format bold
            formatted = formatted.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            
            // Format italic
            formatted = formatted.replace(/\*([^*\n]+?)\*/g, '<em>$1</em>');
            
            // Format links
            formatted = formatted.replace(/(https?:\/\/[^\s<>"']+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
            
            // Convert line breaks
            formatted = formatted.replace(/\n\n+/g, '</p><p>');
            formatted = formatted.replace(/\n/g, '<br>');
            formatted = '<p>' + formatted + '</p>';
            
            return formatted;
        },
        
        showTypingIndicator: function() {
            const typingHtml = `
                <div class="boat-chatbot-message boat-chatbot-message-assistant boat-chatbot-typing">
                    <div class="boat-chatbot-message-avatar">AI</div>
                    <div class="boat-chatbot-message-content">
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                    </div>
                </div>
            `;
            $('#boat-landing-messages-container').append(typingHtml);
            this.scrollToBottom();
        },
        
        hideTypingIndicator: function() {
            $('.boat-chatbot-typing').remove();
        },
        
        scrollToMessage: function(messageElement) {
            const container = $('#boat-landing-messages-container');
            if (container.length > 0 && messageElement && messageElement.length > 0) {
                const containerElement = container[0];
                const messageEl = messageElement[0];
                const isMobile = window.innerWidth <= 768;
                // Detect iOS devices (including iPad on iOS 13+)
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || 
                              (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
                
                // iOS Safari needs a longer delay for smooth scrolling
                const delay = isIOS ? 200 : 100;
                setTimeout(() => {
                    // Use requestAnimationFrame for smoother scrolling
                    requestAnimationFrame(() => {
                        // Scroll to the message element
                        const messageOffset = messageEl.offsetTop;
                        
                        // For iOS, use scrollTo for better compatibility
                        if (isIOS && containerElement.scrollTo) {
                            containerElement.scrollTo({
                                top: messageOffset,
                                behavior: 'smooth'
                            });
                        } else {
                            containerElement.scrollTop = messageOffset;
                        }
                    });
                }, delay);
            }
        },
        
        scrollToBottom: function() {
            const container = $('#boat-landing-messages-container');
            if (container.length > 0) {
                const containerElement = container[0];
                const isMobile = window.innerWidth <= 768;
                // Detect iOS devices (including iPad on iOS 13+)
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || 
                              (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
                const wasScrolling = containerElement.dataset.userScrolling === 'true';
                
                // Check if user is near the bottom (within 100px)
                const scrollTop = containerElement.scrollTop;
                const scrollHeight = containerElement.scrollHeight;
                const clientHeight = containerElement.clientHeight;
                const isNearBottom = (scrollHeight - scrollTop - clientHeight) < 100;
                
                // Only auto-scroll if:
                // 1. User isn't actively scrolling, OR
                // 2. User is already near the bottom (they want to see new content)
                if (!wasScrolling || isNearBottom || !isMobile) {
                    // iOS Safari needs a longer delay for smooth scrolling
                    const delay = isIOS ? 200 : 100;
                    setTimeout(() => {
                        // Use requestAnimationFrame for smoother scrolling
                        requestAnimationFrame(() => {
                            // Double-check user isn't scrolling now
                            if (!containerElement.dataset.userScrolling || 
                                containerElement.dataset.userScrolling !== 'true') {
                                // For iOS, use scrollTo for better compatibility
                                if (isIOS && containerElement.scrollTo) {
                                    containerElement.scrollTo({
                                        top: containerElement.scrollHeight,
                                        behavior: 'smooth'
                                    });
                                } else {
                                    containerElement.scrollTop = containerElement.scrollHeight;
                                }
                            }
                        });
                    }, delay);
                }
            }
        },
        
        showChatContainer: function() {
            // Hide video section
            $('#boat-landing-video-section').hide();
            
            // Mute video when user sends message (only if user hasn't manually toggled)
            const video = document.getElementById('boat-main-video');
            if (video && video._muteVideo && typeof video._muteVideo === 'function') {
                video._muteVideo();
            }
            
            // Show chat container
            $('#boat-landing-chat-container').show().addClass('active');
            
            // Add class to landing page for styling (scoped to prevent conflicts)
            $('.boat-landing-page').addClass('chat-active');
            
            // Scroll to top of messages
            setTimeout(() => {
                this.scrollToBottom();
            }, 100);
        },
        
        hideChatContainer: function() {
            // Show video section
            $('#boat-landing-video-section').show();
            
            // Hide chat container
            $('#boat-landing-chat-container').hide().removeClass('active');
            
            // Remove class from landing page (scoped to prevent conflicts)
            $('.boat-landing-page').removeClass('chat-active');
        },
        
        newChat: function() {
            // Clear conversation
            this.conversationHistory = [];
            this.loadedListings = [];
            this.currentQuery = '';
            this.totalListings = 0;
            
            // Clear messages
            $('#boat-landing-messages-container').empty();
            
            // Hide chat and show video
            this.hideChatContainer();
            
            // Clear input
            if (messageInput.length) messageInput.val('');
            if (chatbotInput.length) chatbotInput.val('');
            
            // Stop any ongoing recording
            if (this.isRecording) {
                this.stopRecording();
            }
            
            // Stop any playing audio
            if (this.currentAudio) {
                this.currentAudio.pause();
                this.currentAudio = null;
            }
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
            const voiceBtn = $('#boat-chatbot-voice');
            voiceBtn.addClass('recording');
            self.isRecording = true;
            
            self.recognition.onstart = function() {
                console.log('Speech recognition started');
            };
            
            self.recognition.onresult = function(event) {
                const transcript = event.results[0][0].transcript;
                
                // Fill input field with transcribed text
                if (messageInput.length) {
                    messageInput.val(transcript);
                } else if (chatbotInput.length) {
                    chatbotInput.val(transcript);
                }
                
                voiceBtn.removeClass('recording processing');
                self.isRecording = false;
            };
            
            self.recognition.onerror = function(event) {
                console.error('Speech recognition error:', event.error);
                voiceBtn.removeClass('recording processing');
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
                voiceBtn.removeClass('recording processing');
                self.isRecording = false;
            };
            
            self.recognition.start();
        },
        
        stopRecording: function() {
            if (this.recognition && this.isRecording) {
                this.recognition.stop();
                this.isRecording = false;
                
                // Update button appearance
                const voiceBtn = $('#boat-chatbot-voice');
                voiceBtn.removeClass('recording processing');
            }
        },
        
        sendAudioToSTT: function(audioBlob) {
            // Use Web Speech API (browser built-in, free, no API key needed)
            const self = this;
            const voiceBtn = $('#boat-chatbot-voice');
            
            // Check if Web Speech API is available
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                voiceBtn.removeClass('processing');
                alert('Your browser does not support speech recognition. Please use Chrome, Edge, or Safari.');
                return;
            }
            
            // This method is now handled by startRecording with Web Speech API
            // Keeping this for backward compatibility but it won't be called
            voiceBtn.removeClass('processing');
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
        
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
    
    // Initialize landing chatbot
    landingChatbot.init();
    
    // Track user scrolling on mobile to prevent auto-scroll interference
    // Wait for DOM to be ready
    setTimeout(function() {
        const messagesContainer = $('#boat-landing-messages-container');
        if (messagesContainer.length > 0) {
            const containerElement = messagesContainer[0];
            let scrollTimeout;
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || 
                          (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            
            // Mark when user starts scrolling
            const handleScrollStart = function() {
                containerElement.dataset.userScrolling = 'true';
                clearTimeout(scrollTimeout);
                
                // iOS needs longer timeout due to momentum scrolling
                const timeoutDelay = isIOS ? 500 : 300;
                
                // Reset flag after user stops scrolling
                scrollTimeout = setTimeout(function() {
                    containerElement.dataset.userScrolling = 'false';
                }, timeoutDelay);
            };
            
            // Mark when user touches the container (mobile)
            const handleTouchStart = function(e) {
                containerElement.dataset.userScrolling = 'true';
                clearTimeout(scrollTimeout);
                
                // For iOS, prevent default only if not scrolling
                if (isIOS) {
                    // Allow touch events to propagate for scrolling
                    return true;
                }
            };
            
            // Listen for touch events (mobile) - iOS needs special handling
            containerElement.addEventListener('touchstart', handleTouchStart, { passive: true });
            containerElement.addEventListener('touchmove', handleScrollStart, { passive: true });
            
            // Listen for scroll events (all devices)
            containerElement.addEventListener('scroll', handleScrollStart, { passive: true });
            
            // Listen for wheel events (desktop)
            containerElement.addEventListener('wheel', handleScrollStart, { passive: true });
            
            // iOS Safari specific: handle momentum scrolling end
            if (isIOS) {
                let momentumTimeout;
                containerElement.addEventListener('scroll', function() {
                    clearTimeout(momentumTimeout);
                    momentumTimeout = setTimeout(function() {
                        // Momentum scrolling has ended
                        containerElement.dataset.userScrolling = 'false';
                    }, 150);
                }, { passive: true });
            }
            
            // Initialize as not scrolling
            containerElement.dataset.userScrolling = 'false';
            
            // iOS Safari: Force enable scrolling
            if (isIOS) {
                // Ensure container is scrollable
                containerElement.style.webkitOverflowScrolling = 'touch';
                containerElement.style.overflowY = 'auto';
                
                // Fix for iOS Safari fixed positioning issues
                const chatContainer = $('#boat-landing-chat-container');
                if (chatContainer.length > 0) {
                    chatContainer.on('touchstart touchmove touchend', function(e) {
                        // Allow touch events to propagate
                        return true;
                    });
                }
            }
        }
    }, 500);
    
    // Function to calculate conversation section min-height
    function calculateConversationMinHeight() {
        const headerHeight = 40; // .boat-topbar height
        const totalHeight = window.innerHeight;
        // Remove footer height from calculation - only subtract header height
        return totalHeight - headerHeight;
    }
    
    // Function to get boat-chatbot-section height
    function getBoatChatbotSectionHeight() {
        const chatbotSection = $('.boat-chatbot-section');
        if (chatbotSection.length > 0) {
            return chatbotSection.outerHeight(true) || 0;
        }
        return 0;
    }
    
    // Function to update the last conversation section's min-height
    // Only increases height if content overflows the screen
    function updateLastConversationMinHeight() {
        const lastConversation = $('.boat-conversation-section').last();
        if (lastConversation.length === 0) return;
        
        // Check if content overflows the screen
        const documentHeight = document.documentElement.scrollHeight;
        const viewportHeight = window.innerHeight;
        const contentOverflows = documentHeight > viewportHeight;
        
            // Remove min-height and padding from all conversation sections first
            $('.boat-conversation-section').css({
                'min-height': 'auto',
                'padding-top': '0',
                'padding-bottom': '0'
            });
            
        // Only set min-height if content actually overflows
        if (contentOverflows) {
            // Get header height and chatbot section height
            const headerHeight = 40; // .boat-topbar height
            const chatbotSectionHeight = getBoatChatbotSectionHeight();
            
            // Set min-height and padding-bottom only for the last conversation
            const minHeight = calculateConversationMinHeight();
            lastConversation.css({
                'min-height': minHeight + 'px',
                'padding-bottom': chatbotSectionHeight + 'px'
            });
        }
        // If content fits on screen, leave min-height as 'auto' (no forced height)
    }
    
    
    // Load more listings for landing page
    // Follows the pagination pattern from PAGINATION_IMPLEMENTATION.md
    function loadMoreLandingListings(offset) {
        // Prevent multiple simultaneous requests
        if (landingListingsState.isLoadingMore) return;
        
        landingListingsState.isLoadingMore = true;
        const $loadMoreBtn = $('.boat-chatbot-load-more-btn');
        $loadMoreBtn.prop('disabled', true).text('Loading...');
        
        if (typeof window.boatChatbot === 'undefined') {
            console.error('boatChatbot object not found');
            $loadMoreBtn.prop('disabled', false).text('More View');
            landingListingsState.isLoadingMore = false;
            return;
        }
        
        // Use the same query string for pagination (as per documentation)
        const requestData = {
            query: landingListingsState.currentQuery,
            offset: offset,
            limit: landingListingsState.itemsPerPage, // Backend ignores this and always uses 5
            nonce: window.boatChatbot.restNonce || window.boatChatbot.nonce
        };
        
        
        $.ajax({
            url: window.boatChatbot.restUrl + 'load-listings',
            type: 'POST',
            contentType: 'application/json',
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
                    // Update total from response (response.data.total from load-listings endpoint)
                    landingListingsState.totalListings = response.data.total || landingListingsState.loadedListings.length;
                    
                    // If pagination is enabled and there are more listings, add the "More View" button again
                    if (landingListingsState.enablePagination && response.data.total > landingListingsState.loadedListings.length) {
                        const remaining = landingListingsState.totalListings - landingListingsState.loadedListings.length;
                        
                        // Remove any existing load more buttons first to prevent duplicates
                        $('.boat-chatbot-load-more').remove();
                        
                        // Find the target container
                        const messagesContainer = $('#conversation-history').length ? $('#conversation-history') : $('#boat-landing-messages-container');
                        const $existingContainer = messagesContainer.find('.boat-chatbot-listings-container').last();
                        if ($existingContainer.length > 0) {
                            const loadMoreHtml = '<div class="boat-chatbot-load-more">' +
                                '<button class="boat-chatbot-load-more-btn" data-offset="' + landingListingsState.loadedListings.length + '">' +
                                'More View (' + remaining + ' more)' +
                                '</button>' +
                                '</div>';
                            $existingContainer.append($(loadMoreHtml));
                            
                            // Bind click event for the new button
                            $('.boat-chatbot-load-more-btn').off('click').on('click', function(e) {
                                e.preventDefault();
                                const btnOffset = parseInt($(this).data('offset'));
                                loadMoreLandingListings(btnOffset);
                            });
                        }
                    }
                    
                    // Scroll to show new listings
                    setTimeout(function() {
                        const messagesContainer = $('#conversation-history').length ? $('#conversation-history') : $('#boat-landing-messages-container');
                        if (messagesContainer.length > 0) {
                            const container = messagesContainer[0];
                            if (container) {
                                container.scrollTop = container.scrollHeight;
                            }
                        }
                    }, 100);
                } else {
                    // No more listings or error
                    if (response.success && response.data && (!response.data.listings || response.data.listings.length === 0)) {
                        // No more listings to load
                    }
                }
            },
            error: function(xhr, status, error) {
                // Error loading more listings - handled silently
                landingListingsState.isLoadingMore = false;
                $loadMoreBtn.prop('disabled', false).text('More View');
            }
        });
    }
    
    // Help Button Click Handler
    $(document).on('click', '.boat-action-help', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $modal = $('#boat-help-modal');
        if ($modal.length) {
            $modal.fadeIn(300);
            // Prevent landing page scroll when modal is open (scoped to prevent conflicts)
            $('.boat-landing-page').css('overflow', 'hidden');
        }
    });
    
    // Close Help Modal
    $(document).on('click', '.boat-help-modal-close, .boat-help-modal-overlay', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $modal = $('#boat-help-modal');
        if ($modal.length) {
            $modal.fadeOut(300);
            // Restore landing page scroll (scoped to prevent conflicts)
            $('.boat-landing-page').css('overflow', '');
        }
    });
    
    // Close modal on Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            const $modal = $('#boat-help-modal');
            if ($modal.is(':visible')) {
                $modal.fadeOut(300);
                $('.boat-landing-page').css('overflow', '');
            }
        }
    });
    
    // Translation Button Click Handler - Show "Coming Soon" Message
    $(document).on('click', '.boat-action-translate', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Remove any existing coming soon message
        $('.boat-coming-soon-message').remove();
        
        // Create and show coming soon message
        const $message = $('<div class="boat-coming-soon-message">This feature coming soon</div>');
        $('body').append($message);
        
        // Auto-hide after 2 seconds
        setTimeout(function() {
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        }, 2000);
    });
    
    // Settings Button Click Handler - Show "Coming Soon" Message
    $(document).on('click', '.boat-action-settings', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Remove any existing coming soon message
        $('.boat-coming-soon-message').remove();
        
        // Create and show coming soon message
        const $message = $('<div class="boat-coming-soon-message">This feature coming soon</div>');
        $('body').append($message);
        
        // Auto-hide after 2 seconds
        setTimeout(function() {
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        }, 2000);
    });
    
    
});

