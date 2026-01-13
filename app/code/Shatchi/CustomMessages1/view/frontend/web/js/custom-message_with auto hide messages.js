define(['jquery', 'Magento_Customer/js/customer-data'], function ($, customerData) {
    'use strict';
    
    // Debug flag - set to true to see console logs
    const debug = true;
    
    const messageQueue = [];
    const svgIcons = {
        success: `<svg class="popup-icon" fill="#28a745" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM6.97 11.03a.75.75 0 0 0 1.06 0L13 6.06l-1.06-1.06-4.47 4.47-2.47-2.47L4.94 8l2.03 2.97z"/></svg>`,
        error: `<svg class="popup-icon" fill="#dc3545" viewBox="0 0 16 16"><path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0zM4.646 4.646l2.828 2.828-2.828 2.828 1.414 1.414 2.828-2.828 2.828 2.828 1.414-1.414-2.828-2.828 2.828-2.828-1.414-1.414-2.828 2.828L6.06 3.232 4.646 4.646z"/></svg>`,
        warning: `<svg class="popup-icon" fill="#ffc107" viewBox="0 0 16 16"><path d="M8.982 1.566a1.5 1.5 0 0 0-2.964 0L.165 13.233A1.5 1.5 0 0 0 1.5 15h13a1.5 1.5 0 0 0 1.335-2.233L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1-2.002 0 1 1 0 0 1 2.002 0z"/></svg>`,
        notice: `<svg class="popup-icon" fill="#17a2b8" viewBox="0 0 16 16"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-12.412a.5.5 0 0 1-.854-.353v-.875a.5.5 0 0 1 1 0v.875a.5.5 0 0 1-.146.353zM8 5a.5.5 0 0 1 .496.438l.39 4.692a.5.5 0 0 1-.992.092L7.5 5.5a.5.5 0 0 1 .5-.5z"/></svg>`
    };

    // Initialize the popup system
    $(document).ready(function() {
        if (debug) console.log('Popup notification system initialized');
        
        // Create container if it doesn't exist
        if ($('#popup-stack').length === 0) {
            $('body').append('<div class="custom-popup-stack" id="popup-stack"></div>');
            if (debug) console.log('Created popup stack container');
        }

        // Listen for AJAX events
        $(document).on('ajaxComplete', handleAjaxResponse);
        
        // Also listen to customer data updates (minicart updates)
        customerData.get('messages').subscribe(function(messages) {
            if (messages && messages.messages) {
                messages.messages.forEach(function(msg) {
                    addMessage({
                        type: msg.type || 'notice',
                        text: msg.text
                    });
                });
                // Clear messages after displaying
                customerData.set('messages', {});
            }
        });
    });

    // Handle AJAX responses
    function handleAjaxResponse(event, xhr, settings) {
        if (debug) console.log('AJAX complete:', settings.url);
        
        // Skip if not a cart add request
        if (!settings.url.includes('checkout/cart/add')) {
            if (debug) console.log('Skipping non-cart AJAX request');
            return;
        }

        try {
            const response = xhr.responseJSON || JSON.parse(xhr.responseText);
            if (debug) console.log('AJAX response:', response);

            // Handle standard Magento messages
            if (response && response.messages && response.messages.messages) {
                response.messages.messages.forEach(function(msg) {
                    addMessage(msg);
                });
            }
            // Handle direct error responses
            else if (response && response.error) {
                addMessage({
                    type: 'error',
                    text: response.error
                });
            }
            // Handle success responses without messages
            else if (response && response.success) {
                addMessage({
                    type: 'success',
                    text: response.success_message || 'Product was added to your shopping cart.'
                });
            }
        } catch (e) {
            if (debug) console.error('Error processing AJAX response:', e);
        }
    }

    // Add message to queue and display
    function addMessage(msg) {
        if (!msg || !msg.text) {
            if (debug) console.warn('Invalid message format:', msg);
            return;
        }

        if (debug) console.log('Adding message:', msg);
        
        // Add timestamp for tracking
        msg.timestamp = Date.now();
        messageQueue.push(msg);
        
        // Process the queue
        processQueue();
    }

    // Process and display messages
    function processQueue() {
        const $stack = $('#popup-stack');
        if ($stack.length === 0) {
            if (debug) console.error('Popup stack container not found!');
            return;
        }

        // Clear existing popups
        $stack.empty();
        
        // Display up to 5 most recent messages
        const maxVisible = 5;
        const visibleMessages = messageQueue.slice(-maxVisible);
        
        visibleMessages.forEach((msg, index) => {
            const stackIndex = visibleMessages.length - 1 - index;
            const $popup = createPopup(msg, stackIndex);
            $stack.append($popup);
            
            // Animate only the newest message
            if (index === visibleMessages.length - 1) {
                $popup.addClass('slideIn');
                setTimeout(() => $popup.removeClass('slideIn'), 400);
                
                // Auto-remove after delay
                setTimeout(() => {
                    const msgIndex = messageQueue.findIndex(m => m.timestamp === msg.timestamp);
                    if (msgIndex !== -1) {
                        messageQueue.splice(msgIndex, 1);
                        processQueue();
                    }
                }, 5000);
            }
        });
    }

    // Create popup DOM element
    function createPopup(msg, stackIndex) {
        return $('<div>', {
            class: `custom-popup ${msg.type} ${stackIndex === 0 ? '' : 'stacked'}`,
            css: {
                top: `${30 + stackIndex * 20}px`,
                right: `${30 + stackIndex * 10}px`,
                transform: `rotate(${stackIndex * 2}deg)`,
                zIndex: 9999 - stackIndex,
                opacity: 1 - (stackIndex * 0.1)
            }
        }).append(
            $(svgIcons[msg.type] || svgIcons.notice),
            $('<div>', { class: 'message-text', html: msg.text }),
            $('<button>', {
                class: 'close-popup',
                html: '×',
                click: function() {
                    const msgIndex = messageQueue.findIndex(m => m.timestamp === msg.timestamp);
                    if (msgIndex !== -1) {
                        messageQueue.splice(msgIndex, 1);
                        processQueue();
                    }
                }
            })
        );
    }
});