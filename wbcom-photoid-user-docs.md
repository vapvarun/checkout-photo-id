# Wbcom Checkout Photo ID Upload - User Documentation

## Introduction

**Wbcom Checkout Photo ID Upload** is a WooCommerce extension that adds a Photo ID upload field to your checkout process. This plugin is ideal for businesses that need to verify customer identity for:

- Age-restricted products (alcohol, tobacco, etc.)
- High-value purchases requiring identity verification
- Regulatory compliance for certain products or services
- Fraud prevention for high-risk orders

## Features

- **Secure File Storage**: Customer IDs are stored in a protected directory with proper security measures
- **Customizable Settings**: Control which products or categories require ID verification
- **Easy Management**: Manage and download uploaded IDs from the order management screen
- **Automatic Validation**: Client and server-side validation ensures only valid files are uploaded
- **Privacy-Focused**: Files are stored securely and accessible only to authorized administrators
- **Automatic Deletion**: Set up automatic file deletion after a specified retention period
- **Email Request System**: Request missing IDs from customers via email

## Installation

1. Upload the `wbcom-checkout-photo-id` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Settings > Checkout > Photo ID Upload to configure the plugin

## Configuration

After installation, configure the plugin under **WooCommerce > Settings > Checkout > Photo ID Upload**. The following options are available:

### Basic Settings

- **Enable Photo ID Upload**: Turn the feature on or off
- **Field Title**: The heading shown above the upload field
- **Field Description**: The label for the upload field
- **Help Text**: Additional information for customers
- **Maximum File Size**: Limit file size (in MB)
- **Exempt Categories**: Product categories that don't require ID

### Retention Settings

- **File Retention Period**: Days to keep files (0 = keep indefinitely)
- **Log Access**: Track when admins access ID files

### Email Settings

- **Admin Notifications**: Enable/disable notifications when IDs are uploaded
- **Request Email Subject**: Customize the subject line for ID request emails
- **Request Email Heading**: Customize the heading for ID request emails

### Security Settings

- **Secure Directory Name**: Name of the protected upload folder (change only if needed)

## How It Works

### For Customers

1. **During Checkout**: If a customer's cart contains products requiring ID verification, they will see a Photo ID upload field during checkout.

2. **File Upload**: Customers can upload a JPG or PNG image of their ID (maximum size configurable).

3. **Validation**: The system validates the file type and size before accepting the upload.

4. **Order Completion**: Once a valid ID is uploaded, the customer can complete their order normally.

5. **Email Requests**: If a customer doesn't upload an ID, admins can send an email request with a secure link for uploading.

### For Store Owners/Admins

1. **Order Management**: View and download uploaded IDs from the order details screen.

2. **Order List**: The orders list includes a column showing ID upload status for each order.

3. **Request IDs**: For orders without IDs, you can request them individually or in bulk:
   - From the order details screen with an optional custom message
   - Via bulk action on the orders list

4. **Access Control**: Only administrators can view or download the uploaded IDs.

5. **Access Logging**: All ID download activities are logged in order notes for security.

## Viewing and Managing IDs

### Checking ID Status

1. Go to **WooCommerce > Orders** to see all orders
2. The **Photo ID** column shows the status for each order:
   - Green checkmark: ID uploaded
   - Red X: ID required but not uploaded
   - Warning icon: Upload error
   - Dash: No ID required

### Viewing Uploaded IDs

1. Open an order by clicking on it in the orders list
2. In the order details screen, look for the **Photo ID** box
3. Here you can see the filename, upload date, and file size
4. Click **Download ID** to view the uploaded document

### Requesting Missing IDs

#### For Individual Orders:

1. Open the order missing an ID
2. In the Photo ID box, click **Request ID**
3. Add an optional custom message
4. Click **Send Request**
5. The customer will receive an email with a link to upload their ID

#### For Multiple Orders:

1. Go to **WooCommerce > Orders**
2. Select the orders requiring IDs
3. From the **Bulk Actions** dropdown, select **Request Photo ID**
4. Click **Apply**
5. All selected customers will receive email requests

## Security Information

### How Files Are Protected

- Files are stored in a secure directory outside the public web space
- Access is protected by .htaccess rules
- Files can only be accessed through the admin interface
- All downloads are tracked in order notes

### Data Privacy

- IDs are stored only for the specified retention period
- The plugin supports WordPress privacy exports and data erasure requests
- Customer IDs are automatically deleted after the retention period
- Only authorized administrators can access the files

## Troubleshooting

### Customer Reports Upload Errors

- Check the maximum file size setting
- Verify allowed file types (JPG, PNG only)
- Ensure the uploads directory is writable
- Check server file upload limits in PHP settings

### ID Not Appearing in Admin

- Verify the order contains products requiring ID
- Check that the upload was completed during checkout
- Confirm file was within size limits
- Check order notes for any upload errors

### Email Requests Not Working

- Verify WooCommerce emails are functioning properly
- Check spam/junk folders
- Ensure correct customer email addresses
- Test WooCommerce email settings

## Best Practices

1. **Set Appropriate Retention**: Balance regulatory requirements with privacy concerns
2. **Clear Instructions**: Customize help text to clearly explain requirements to customers
3. **Category Exemptions**: Use exempt categories for products not requiring verification
4. **Regular Monitoring**: Check orders requiring IDs and follow up promptly
5. **Secure Access**: Limit which administrators have access to view IDs

## Support

For support or questions about this plugin, please contact:

- Email: [support@wbcomdesigns.com](mailto:support@wbcomdesigns.com)
- Website: [https://wbcomdesigns.com](https://wbcomdesigns.com)

Please include your WordPress version, WooCommerce version, and detailed description of any issues.
