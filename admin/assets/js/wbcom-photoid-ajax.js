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
            this.checkForExistingPreview();
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
         * Check for existing preview (for page reloads)
         */
        checkForExistingPreview: function() {
            // If there's a stored file ID in sessionStorage, restore the visual feedback
            if (sessionStorage.getItem('photo_id_selected') === 'yes') {
                var uploadId = sessionStorage.getItem('photo_id_upload_id');
                if (uploadId) {
                    this.fileUploaded = true;
                    this.$uploadButton.text(wbcom_photoid.change_photo_text || 'Change Photo');
                    this.$statusMessage.html('<span class="dashicons dashicons-yes-alt"></span> ' + 
                        (wbcom_photoid.success_text || 'File uploaded successfully')).addClass('wbcom-photoid-status-success').show();
                }
            }
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

            // Handle browser back button
            window.addEventListener('pageshow', function(event) {
                // If the page is loaded from cache (back button)
                if (event.persisted) {
                    this.checkForExistingPreview();
                }
            }.bind(this));
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
            
            // Show file info with filename and size
            this.$fileInfo.html('<strong>' + file.name + '</strong> (' + this.formatFileSize(file.size) + ')').show();
            
            // Enable upload button
            this.$uploadButton.prop('disabled', false);
            
            // Store in session
            sessionStorage.setItem('photo_id_selected', 'yes');
            
            // Remove any "removed" flag
            $('#wbcom_photoid_removed').remove();
            
            // Show file preview if it's an image
            if (file.type.match('image.*')) {
                this.createPreview(file);
            }

            // Add visual feedback WITHOUT duplicating the filename
            var $feedback = $('<div class="photo-id-feedback"></div>');
            $feedback.html('File selected <span class="dashicons dashicons-yes"></span>');
            
            // Remove existing feedback if any
            $('.photo-id-feedback').remove();
            
            // Add new feedback after the file info
            this.$fileInfo.after($feedback);
            
            // Remove any error messages
            $('.woocommerce-error').each(function() {
                if ($(this).text().indexOf('Photo ID') !== -1) {
                    $(this).remove();
                }
            });
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
                $preview.append('<span class="wbcom-photoid-remove-preview" title="Remove">×</span>');
                
                this.$previewArea.html($preview);
                
                // Add remove button functionality
                $('.wbcom-photoid-remove-preview').on('click', this.handleRemoveFile.bind(this));
                
                // Improve accessibility
                $('.wbcom-photoid-remove-preview').attr('role', 'button').attr('aria-label', 'Remove selected file');
            }.bind(this);
            
            reader.readAsDataURL(file);
        },

        /**
         * Handle file removal
         */
        handleRemoveFile: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            this.$uploadField.val('');
            this.clearUI();
            this.fileUploaded = false;
            
            // Reset upload button text
            this.$uploadButton.text(wbcom_photoid.upload_text || 'Upload ID');
            
            // Clear session storage
            sessionStorage.removeItem('photo_id_selected');
            sessionStorage.removeItem('photo_id_upload_id');
            
            // Add hidden input to indicate file was removed
            if ($('#wbcom_photoid_removed').length === 0) {
                this.$uploadSection.append('<input type="hidden" id="wbcom_photoid_removed" name="wbcom_photoid_removed" value="1">');
            }
            
            // Remove feedback message
            $('.photo-id-feedback').remove();
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
            $('.photo-id-feedback').remove();
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
            this.$uploadButton.prop('disabled', true).text(wbcom_photoid.uploading_text || 'Uploading...');
            
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
                        
                        // Save upload ID in session storage
                        sessionStorage.setItem('photo_id_upload_id', response.data.upload_id);
                        
                        // Mark as uploaded
                        this.fileUploaded = true;
                        
                        // Change button text
                        this.$uploadButton.text(wbcom_photoid.change_photo_text || 'Change Photo');
                        
                        // Enable button again to allow changing the file
                        this.$uploadButton.prop('disabled', false);
                        
                        // Remove any "removed" flag
                        $('#wbcom_photoid_removed').remove();
                        
                        // Update feedback message without repeating filename
                        if ($('.photo-id-feedback').length) {
                            $('.photo-id-feedback').html('File uploaded successfully <span class="dashicons dashicons-yes"></span>');
                        } else {
                            var $feedback = $('<div class="photo-id-feedback"></div>');
                            $feedback.html('File uploaded successfully <span class="dashicons dashicons-yes"></span>');
                            this.$fileInfo.after($feedback);
                        }
                    } else {
                        // Show error
                        this.showError(response.data.message);
                        this.$progressBar.hide();
                        this.$uploadButton.prop('disabled', false).text(wbcom_photoid.upload_text || 'Upload ID');
                    }
                }.bind(this),
                error: function() {
                    this.uploadInProgress = false;
                    this.showError(wbcom_photoid.error_messages.upload_failed);
                    this.$progressBar.hide();
                    this.$uploadButton.prop('disabled', false).text(wbcom_photoid.upload_text || 'Upload ID');
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
                this.scrollToUploadSection();
                return false;
            }
            
            // Check session storage first (in case file was already selected)
            if (sessionStorage.getItem('photo_id_selected') === 'yes' && sessionStorage.getItem('photo_id_upload_id')) {
                return true;
            }
            
            // Check if a file was uploaded
            if (!this.fileUploaded && $photoIdField[0].files.length === 0) {
                this.showError(wbcom_photoid.error_messages.missing_file);
                this.scrollToUploadSection();
                return false;
            }
            
            // If there's a file selected but not uploaded yet, show an error
            if (!this.fileUploaded && $photoIdField[0].files.length > 0) {
                this.showError(wbcom_photoid.error_messages.not_uploaded);
                this.scrollToUploadSection();
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
            
            this.scrollToUploadSection();
        },
        
        /**
         * Scroll to upload section
         */
        scrollToUploadSection: function() {
            $('html, body').animate({
                scrollTop: this.$uploadSection.offset().top - 100
            }, 500);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WbcomPhotoID.init();
    });

})(jQuery);