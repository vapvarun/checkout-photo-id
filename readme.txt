=== Wbcom Checkout Photo ID Upload ===
Contributors: wbcomdesigns
Tags: woocommerce, checkout, photo id, verification, identity
Requires at least: 5.0
Tested up to: 6.3
Requires PHP: 7.2
Stable tag: 1.1.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Require customers to upload a Photo ID during WooCommerce checkout, securely store it, and manage the uploaded files.

== Description ==

**Wbcom Checkout Photo ID Upload** is a WooCommerce extension that adds a Photo ID upload field to the checkout process. This is useful for businesses that need to verify customer identity for regulatory compliance, age verification, or fraud prevention.

### Key Features

* **Secure File Storage**: All uploaded IDs are stored in a protected directory with proper security measures.
* **Configurable Settings**: Control which products or categories require ID verification.
* **Admin Interface**: Easily manage and download uploaded IDs from the order management screen.
* **Validation**: Client and server-side validation ensures only valid files are uploaded.
* **Privacy-Focused**: Files are stored securely and accessible only to authorized administrators.
* **File Retention**: Optionally set up automatic file deletion after a specified period.
* **Custom Hooks**: Extensive filter and action hooks for developers to extend functionality.
* **Email Request System**: Request missing IDs from customers via email.

### Use Cases

* Age-restricted products (alcohol, tobacco, etc.)
* High-value purchases requiring identity verification
* Regulatory compliance for certain products or services
* Fraud prevention for high-risk orders

### Developer-Friendly

The plugin includes numerous action and filter hooks for customization:

**Action Hooks:**

* `wbcom_photoid_activated` - Triggered when the plugin is activated
* `wbcom_photoid_before_field` - Before rendering the upload field
* `wbcom_photoid_after_field` - After rendering the upload field
* `wbcom_photoid_after_validation` - After validating the uploaded file
* `wbcom_photoid_before_save` - Before saving the uploaded file
* `wbcom_photoid_after_save` - After successfully saving the uploaded file
* `wbcom_photoid_save_failed` - When file saving fails
* `wbcom_photoid_before_download` - Before admin downloads an ID file
* `wbcom_photoid_after_download` - After admin downloads an ID file
* `wbcom_photoid_admin_actions` - For adding custom admin actions
* `wbcom_photoid_metabox_actions` - For adding custom metabox actions

**Filter Hooks:**

* `wbcom_photoid_bypass_categories` - Categories that don't require ID upload
* `wbcom_photoid_bypass_products` - Products that don't require ID upload
* `wbcom_photoid_force_bypass` - Force bypass ID requirement
* `wbcom_photoid_is_required` - Whether ID upload is required
* `wbcom_photoid_field_title` - Title for the upload field
* `wbcom_photoid_field_description` - Description for the upload field
* `wbcom_photoid_help_text` - Help text shown to customers
* `wbcom_photoid_pre_validation` - Pre-validate the uploaded file
* `wbcom_photoid_missing_error` - Error message for missing file
* `wbcom_photoid_filetype_error` - Error message for invalid file type
* `wbcom_photoid_filesize_error` - Error message for file size exceeding limit
* `wbcom_photoid_allowed_mime_types` - Allowed file MIME types
* `wbcom_photoid_allowed_extensions` - Allowed file extensions
* `wbcom_photoid_max_file_size` - Maximum file size in MB
* `wbcom_photoid_filename` - Customize the saved filename
* `wbcom_photoid_upload_path` - Custom upload directory path
* `wbcom_photoid_directory_name` - Name of the secure directory
* `wbcom_photoid_htaccess_content` - Content of the .htaccess file
* `wbcom_photoid_settings` - Admin settings array

== Installation ==

1. Upload the `wbcom-checkout-photo-id` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Settings > Checkout > Photo ID Upload to configure the plugin

== Configuration ==

After installation, you can configure the plugin under WooCommerce > Settings > Checkout > Photo ID Upload. The following options are available:

* **Enable Photo ID Upload** - Turn the feature on or off
* **Field Title** - The heading shown above the upload field
* **Field Description** - The label for the upload field
* **Help Text** - Additional information for customers
* **Maximum File Size** - Limit file size (in MB)
* **Exempt Categories** - Product categories that don't require ID
* **File Retention Period** - Days to keep files (0 = keep indefinitely)
* **Log Access** - Track when admins access ID files
* **Secure Directory Name** - Name of the protected upload folder

== Frequently Asked Questions ==

= Is the uploaded ID secure? =

Yes, the plugin creates a protected directory outside the web root with proper .htaccess rules to deny direct access. Only authorized admins can download the files through the secure endpoint.

= Can I exempt certain products from requiring ID? =

Yes, you can exempt entire product categories through the plugin settings or use the `wbcom_photoid_bypass_products` filter to exempt specific products.

= How can I customize the error messages? =

Use the filter hooks like `wbcom_photoid_missing_error`, `wbcom_photoid_filetype_error`, and `wbcom_photoid_filesize_error` to customize the validation error messages.

= Can admins be notified when an ID is uploaded? =

Yes, you can use the `wbcom_photoid_after_save` action hook to send an email notification or perform other actions when an ID is successfully uploaded.

== Changelog ==

= 1.1.0 =
* Added file preview functionality
* Added exempt categories feature
* Added admin settings page
* Added file retention options
* Added access logging
* Added bulk action to request IDs
* Improved security measures
* Added extensive filter and action hooks
* Added detailed admin interface

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.1.0 =
This update adds significant new features and improvements. Settings migration is automatic.