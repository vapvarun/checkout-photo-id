/**
 * Wbcom Photo ID Upload Frontend Script
 *
 * Handles client-side validation and preview of uploaded photo IDs.
 */
(function($) {
    'use strict';

    // Main Photo ID handler
    var WbcomPhotoID = {
        /**
         * Initialize the script
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.initializeValidation();
            
            // Add session storage tracking for file selection
            this.$uploadField.on('change', function() {
                if (this.files.length > 0) {
                    sessionStorage.setItem('photo_id_selected', 'yes');
                    
                    // Add visual feedback
                    var $feedback = $('<div class="photo-id-feedback" style="margin-top:10px;padding:8px;background:#f0f9e6;border-left:3px solid #46b450;"></div>');
                    $feedback.html('File selected: <strong>' + this.files[0].name + '</strong>');
                    
                    // Remove existing feedback if any
                    $('.photo-id-feedback').remove();
                    
                    // Add new feedback after the preview area
                    this.$previewArea.after($feedback);
                    
                    // Remove any error messages
                    $('.woocommerce-error').each(function() {
                        if ($(this).text().indexOf('Photo ID') !== -1) {
                            $(this).remove();
                        }
                    });
                }
            }.bind(this));
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.$body = $('body');
            this.$uploadField = $('#photo_id');
            this.$previewArea = $('.wbcom-photoid-preview');
            this.$checkoutForm = $('form.woocommerce-checkout');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Handle file selection
            this.$uploadField.on('change', this.handleFileSelect.bind(this));
            
            // Add validation before checkout submission
            this.$checkoutForm.on('checkout_place_order', this.validateBeforeSubmit.bind(this));
        },

        /**
         * Initialize client-side validation
         */
        initializeValidation: function() {
            this.maxFileSize = wbcom_photoid.max_file_size * 1024 * 1024; // Convert to bytes
            this.allowedTypes = wbcom_photoid.allowed_file_types;
        },

        /**
         * Handle file selection
         * 
         * @param {Event} e Change event
         */
        handleFileSelect: function(e) {
            // Clear any existing previews
            this.$previewArea.empty();
            
            var file = e.target.files[0];
            
            // Exit if no file selected
            if (!file) {
                return;
            }
            
            // Validate file size
            if (file.size > this.maxFileSize) {
                this.showError(wbcom_photoid.error_messages.file_size);
                this.$uploadField.val('');
                return;
            }
            
            // Validate file type
            var fileExtension = file.name.split('.').pop().toLowerCase();
            if (this.allowedTypes.indexOf(fileExtension) === -1) {
                this.showError(wbcom_photoid.error_messages.file_type);
                this.$uploadField.val('');
                return;
            }
            
            // Show file preview if it's an image
            if (file.type.match('image.*')) {
                this.createPreview(file);
            } else {
                // Show file name for non-image files
                this.$previewArea.html('<span class="file-name">' + file.name + '</span>');
            }
        },

        /**
         * Create image preview
         * 
         * @param {File} file Selected file
         */
        createPreview: function(file) {
            var reader = new FileReader();
            
            reader.onload = function(e) {
                var $preview = $('<div class="wbcom-photoid-image-preview"></div>');
                $preview.append('<img src="' + e.target.result + '" alt="ID Preview" />');
                $preview.append('<span class="wbcom-photoid-remove-preview">×</span>');
                
                this.$previewArea.append($preview);
                
                // Add remove button functionality
                $('.wbcom-photoid-remove-preview').on('click', function() {
                    this.$uploadField.val('');
                    this.$previewArea.empty();
                    // Clear session storage when file is removed
                    sessionStorage.removeItem('photo_id_selected');
                }.bind(this));
            }.bind(this);
            
            reader.readAsDataURL(file);
        },

        /**
         * Validate before form submission
         * 
         * @param {Event} e Submit event
         * @return {boolean} Whether to proceed with submission
         */
        validateBeforeSubmit: function(e) {
            var $photoIdField = $('#photo_id');
            
            // Skip validation if the field is not required
            if ($photoIdField.length === 0 || $photoIdField.prop('required') === false) {
                return true;
            }
            
            // Check session storage first (in case file was already selected)
            if (sessionStorage.getItem('photo_id_selected') === 'yes') {
                return true;
            }
            
            // Check if a file is selected now
            if ($photoIdField[0].files.length === 0) {
                this.showError(wbcom_photoid.error_messages.missing_file || 'Please select a photo ID file.');
                return false;
            }
            
            return true;
        },

        /**
         * Show error message
         * 
         * @param {string} message Error message
         */
        showError: function(message) {
            // Remove any existing error messages
            $('.wbcom-photoid-error').remove();
            
            // Create error message element
            var $error = $('<div class="wbcom-photoid-error woocommerce-error"></div>');
            $error.text(message);
            
            // Add error message before the field
            this.$uploadField.before($error);
            
            // Scroll to error message
            $('html, body').animate({
                scrollTop: $error.offset().top - 100
            }, 500);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WbcomPhotoID.init();
    });

})(jQuery);