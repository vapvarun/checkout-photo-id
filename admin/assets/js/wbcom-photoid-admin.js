/**
 * Wbcom Photo ID Upload Admin JavaScript
 *
 * Handles admin-side functionality for Photo ID requests.
 */
(function($) {
    'use strict';

    // Main Admin Photo ID handler
    var WbcomPhotoIDAdmin = {
        /**
         * Initialize the script
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.$metaBox = $('#wbcom_photoid_metabox');
            this.$requestButton = $('.wbcom-photoid-request-button', this.$metaBox);
            this.$requestForm = $('.wbcom-photoid-request-form', this.$metaBox);
            this.$sendButton = $('.wbcom-photoid-send-request', this.$metaBox);
            this.$messageField = $('#wbcom-photoid-message', this.$metaBox);
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Toggle request form
            this.$requestButton.on('click', this.toggleRequestForm.bind(this));
            
            // Send request
            this.$sendButton.on('click', this.sendRequest.bind(this));
        },

        /**
         * Toggle the request form visibility
         * 
         * @param {Event} e Click event
         */
        toggleRequestForm: function(e) {
            e.preventDefault();
            this.$requestForm.slideToggle();
        },

        /**
         * Send ID request via AJAX
         * 
         * @param {Event} e Click event
         */
        sendRequest: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var orderId = $button.data('order');
            var message = this.$messageField.val();
            
            // Disable button and show loading state
            $button.prop('disabled', true).text(wbcom_photoid_admin.sending_text);
            
            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wbcom_photoid_request',
                    order_id: orderId,
                    message: message,
                    nonce: wbcom_photoid_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        this.showMessage(response.data.message, 'success');
                        
                        // Hide form and update status
                        this.$requestForm.slideUp();
                        this.$requestButton.text(wbcom_photoid_admin.sent_text);
                    } else {
                        // Show error message
                        this.showMessage(response.data.message, 'error');
                    }
                }.bind(this),
                error: function() {
                    // Show generic error message
                    this.showMessage(wbcom_photoid_admin.error_text, 'error');
                }.bind(this),
                complete: function() {
                    // Reset button state
                    $button.prop('disabled', false).text(wbcom_photoid_admin.request_text);
                }
            });
        },

        /**
         * Show status message
         * 
         * @param {string} message Message text
         * @param {string} type Message type (success or error)
         */
        showMessage: function(message, type) {
            // Remove any existing messages
            $('.wbcom-photoid-message', this.$metaBox).remove();
            
            // Create message element
            var $message = $('<div class="wbcom-photoid-message wbcom-photoid-message-' + type + '"></div>');
            $message.text(message);
            
            // Add message before the form
            this.$requestForm.before($message);
            
            // Auto-hide message after 5 seconds
            setTimeout(function() {
                $message.fadeOut(function() {
                    $message.remove();
                });
            }, 5000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WbcomPhotoIDAdmin.init();
    });

})(jQuery);