/**
 * Wbcom Photo ID Upload Frontend Script - Enhanced AJAX Version
 *
 * Handles client-side validation and AJAX upload of photo IDs.
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
            this.initializeValidation();
            this.bindEvents();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.$body = $('body');
            this.$uploadField = $('#photo_id');
            this.$previewArea = $('.wbcom-photoid-preview');
            this.$checkoutForm = $('form.woocommerce-checkout');
            this.$uploadButton = $('.wbcom-photoid-upload-btn');
            this.$progressBar = $('.wbcom-photoid-progress');
            this.$progressBarInner = $('.wbcom-photoid-progress-bar');
            this.$statusMessage = $('.wbcom-photoid-status');
            this.$fileInfo = $('.wbcom-photoid-file-info');
            this.$uploadSection = $('.wbcom-photoid-section');
        },

        /**
         * Initialize client-side validation
         */
        initializeValidation: function() {
            this.maxFileSize = wbcom_photoid.max_file_size * 1024 * 1024; // Convert to bytes
            this.allowedTypes = wbcom_photoid.allowed_file_types;
            this.uploadInProgress = false;
            this.fileUploaded = false;
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Handle file selection
            this.$uploadField.on('change', this.handleFileSelect.bind(this));
            
            // Handle upload button click
            this.$uploadButton.on('click', this.handleUpload.bind(this));
            
            // Add validation before checkout submission
            this.$checkoutForm.on('checkout_place_order', this.validateBeforeSubmit.bind(this));
            
            // Prevent default form submission when pressing Enter in the ID upload section
            this.$uploadSection.on('keypress', function(e) {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    return false;
                }
            });
        },

        /**
         * Handle file selection
         * 
         * @param {Event} e Change event
         */
        handleFileSelect: function(e) {
            // Clear any existing previews and errors
            this.clearUI();
            
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
            
            // Show file info
            this.$fileInfo.html('<strong>' + file.name + '</strong> (' + this.formatFileSize(file.size) + ')').show();
            
            // Enable upload button
            this.$uploadButton.prop('disabled', false);
            
            // Show file preview if it's an image
            if (file.type.match('image.*')) {
                this.createPreview(file);
            }
        },

        /**
         * Format file size in a human-readable way
         * 
         * @param {number} bytes File size in bytes
         * @return {string} Formatted file size
         */
        formatFileSize: function(bytes) {
            if (bytes < 1024) {
                return bytes + ' B';
            } else if (bytes < (1024 * 1024)) {
                return (bytes / 1024).toFixed(1) + ' KB';
            } else {
                return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
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
                $('.wbcom-photoid-remove-preview').on('click', this.handleRemoveFile.bind(this));
            }.bind(this);
            
            reader.readAsDataURL(file);
        },

        /**
         * Handle file removal
         */
        handleRemoveFile: function() {
            this.$uploadField.val('');
            this.clearUI();
            this.fileUploaded = false;
            
            // Add hidden input to indicate file was removed
            if ($('#wbcom_photoid_removed').length === 0) {
                this.$uploadSection.append('<input type="hidden" id="wbcom_photoid_removed" name="wbcom_photoid_removed" value="1">');
            }
        },

        /**
         * Clear UI elements
         */
        clearUI: function() {
            this.$previewArea.empty();
            this.$fileInfo.empty().hide();
            this.$progressBar.hide();
            this.$progressBarInner.width('0%');
            this.$statusMessage.empty().removeClass('wbcom-photoid-status-success wbcom-photoid-status-error').hide();
            $('.wbcom-photoid-error').remove();
        },

        /**
         * Handle file upload via AJAX
         */
        handleUpload: function(e) {
            e.preventDefault();
            
            var file = this.$uploadField[0].files[0];
            
            if (!file) {
                this.showError(wbcom_photoid.error_messages.missing_file);
                return;
            }
            
            // Prevent multiple uploads
            if (this.uploadInProgress) {
                return;
            }
            
            this.uploadInProgress = true;
            
            // Show progress bar
            this.$progressBar.show();
            this.$progressBarInner.width('0%');
            
            // Disable upload button
            this.$uploadButton.prop('disabled', true).text(wbcom_photoid.uploading_text);
            
            // Create FormData object
            var formData = new FormData();
            formData.append('action', 'wbcom_photoid_upload');
            formData.append('photo_id', file);
            formData.append('nonce', wbcom_photoid.nonce);
            
            // Add checkout fields to capture order data
            if (this.$checkoutForm.length) {
                formData.append('billing_first_name', $('#billing_first_name').val());
                formData.append('billing_last_name', $('#billing_last_name').val());
                formData.append('billing_email', $('#billing_email').val());
            }
            
            // Send AJAX request
            $.ajax({
                url: wbcom_photoid.ajax_url,
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                cache: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    
                    // Add progress event listener
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var percentComplete = (e.loaded / e.total) * 100;
                            this.$progressBarInner.width(percentComplete + '%');
                        }
                    }.bind(this), false);
                    
                    return xhr;
                }.bind(this),
                success: function(response) {
                    this.uploadInProgress = false;
                    
                    if (response.success) {
                        // Update UI for success
                        this.$statusMessage.html('<span class="dashicons dashicons-yes-alt"></span> ' + response.data.message).addClass('wbcom-photoid-status-success').show();
                        this.$progressBarInner.width('100%');
                        
                        // Store upload ID in hidden field
                        if ($('#wbcom_photoid_upload_id').length === 0) {
                            this.$uploadSection.append('<input type="hidden" id="wbcom_photoid_upload_id" name="wbcom_photoid_upload_id" value="' + response.data.upload_id + '">');
                        } else {
                            $('#wbcom_photoid_upload_id').val(response.data.upload_id);
                        }
                        
                        // Mark as uploaded
                        this.fileUploaded = true;
                        
                        // Change button text
                        this.$uploadButton.text(wbcom_photoid.change_photo_text);
                        
                        // Enable button again to allow changing the file
                        this.$uploadButton.prop('disabled', false);
                        
                        // Remove any "removed" flag
                        $('#wbcom_photoid_removed').remove();
                    } else {
                        // Show error
                        this.showError(response.data.message);
                        this.$progressBar.hide();
                        this.$uploadButton.prop('disabled', false).text(wbcom_photoid.upload_text);
                    }
                }.bind(this),
                error: function() {
                    this.uploadInProgress = false;
                    this.showError(wbcom_photoid.error_messages.upload_failed);
                    this.$progressBar.hide();
                    this.$uploadButton.prop('disabled', false).text(wbcom_photoid.upload_text);
                }.bind(this)
            });
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
            
            // If file was removed, require a new upload
            if ($('#wbcom_photoid_removed').length > 0 && !this.fileUploaded) {
                this.showError(wbcom_photoid.error_messages.missing_file);
                return false;
            }
            
            // Check if a file was uploaded
            if (!this.fileUploaded && $photoIdField[0].files.length === 0) {
                this.showError(wbcom_photoid.error_messages.missing_file);
                return false;
            }
            
            // If there's a file selected but not uploaded yet, show an error
            if (!this.fileUploaded && $photoIdField[0].files.length > 0) {
                this.showError(wbcom_photoid.error_messages.not_uploaded);
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
            
            // Also update status message
            this.$statusMessage.html('<span class="dashicons dashicons-warning"></span> ' + message).addClass('wbcom-photoid-status-error').show();
            
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