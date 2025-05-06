<?php
/**
 * AJAX Photo ID Upload Handler
 *
 * Adds AJAX support for photo ID uploads in the Wbcom Checkout Photo ID plugin.
 *
 * @package Wbcom_Checkout_Photo_ID
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Wbcom_PhotoID_AJAX class.
 */
class Wbcom_PhotoID_AJAX {

    /**
     * Temporary upload directory path.
     *
     * @var string
     */
    private $temp_dir;

    /**
     * Constructor.
     */
    public function __construct() {
        // Register AJAX handlers
        add_action( 'wp_ajax_wbcom_photoid_upload', array( $this, 'handle_upload' ) );
        add_action( 'wp_ajax_nopriv_wbcom_photoid_upload', array( $this, 'handle_upload' ) );
        
        // Modify checkout field to add AJAX upload UI
        add_filter( 'wbcom_photoid_after_field', array( $this, 'add_ajax_upload_ui' ), 10, 1 );
        
        // Add our custom JS and CSS
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );
        
        // Process uploaded file during checkout
        add_action( 'woocommerce_checkout_create_order', array( $this, 'process_uploaded_file' ), 20, 2 );
        
        // Set up temp directory
        $this->setup_temp_directory();
        
        // Clean up temporary files
        add_action( 'wbcom_photoid_cleanup_files', array( $this, 'cleanup_temp_files' ) );
    }

    /**
     * Enqueue scripts and styles.
     */
    public function enqueue_scripts() {
        if ( is_checkout() ) {
            wp_enqueue_style( 'wbcom-photoid-ajax-style', WBCOM_PHOTOID_PLUGIN_URL . 'admin/assets/css/wbcom-photoid-ajax.css', array(), WBCOM_PHOTOID_VERSION );
            wp_enqueue_script( 'wbcom-photoid-ajax-script', WBCOM_PHOTOID_PLUGIN_URL . 'admin/assets/js/wbcom-photoid-ajax.js', array( 'jquery' ), WBCOM_PHOTOID_VERSION, true );
            
            // Localize script with data
            wp_localize_script( 'wbcom-photoid-ajax-script', 'wbcom_photoid', array(
                'ajax_url'           => admin_url( 'admin-ajax.php' ),
                'nonce'              => wp_create_nonce( 'wbcom_photoid_upload' ),
                'max_file_size'      => apply_filters( 'wbcom_photoid_max_file_size', 2 ), // In MB
                'allowed_file_types' => apply_filters( 'wbcom_photoid_allowed_extensions', array( 'jpg', 'jpeg', 'png' ) ),
                'error_messages'     => array(
                    'file_size'      => __( 'File size exceeds the maximum limit.', 'wbcom-photoid' ),
                    'file_type'      => __( 'Invalid file type. Please upload JPG or PNG files only.', 'wbcom-photoid' ),
                    'missing_file'   => __( 'Please select a photo ID file.', 'wbcom-photoid' ),
                    'upload_failed'  => __( 'Upload failed. Please try again.', 'wbcom-photoid' ),
                    'not_uploaded'   => __( 'Please upload your selected file before proceeding.', 'wbcom-photoid' ),
                ),
                'uploading_text'     => __( 'Uploading...', 'wbcom-photoid' ),
                'upload_text'        => __( 'Upload ID', 'wbcom-photoid' ),
                'change_photo_text'  => __( 'Change Photo', 'wbcom-photoid' ),
                'remove_text'        => __( 'Remove', 'wbcom-photoid' ),
            ) );
        }
    }

    /**
     * Add AJAX upload UI elements after the file input field.
     *
     * @param WC_Checkout $checkout Checkout object.
     */
    public function add_ajax_upload_ui( $checkout ) {
        // File info display
        echo '<div class="wbcom-photoid-file-info"></div>';
        
        // Upload button
        echo '<button type="button" class="wbcom-photoid-upload-btn button alt">' . esc_html__( 'Upload ID', 'wbcom-photoid' ) . '</button>';
        
        // Progress bar
        echo '<div class="wbcom-photoid-progress" style="display:none;">';
        echo '<div class="wbcom-photoid-progress-bar"></div>';
        echo '</div>';
        
        // Status message
        echo '<div class="wbcom-photoid-status"></div>';
    }

    /**
     * Set up temporary upload directory.
     */
    private function setup_temp_directory() {
        $upload_dir = wp_upload_dir();
        $this->temp_dir = trailingslashit( $upload_dir['basedir'] ) . 'wbcom-photoid-temp';
        
        // Create directory if it doesn't exist
        if ( ! file_exists( $this->temp_dir ) ) {
            wp_mkdir_p( $this->temp_dir );
        }
        
        // Add .htaccess to protect directory
        $htaccess = trailingslashit( $this->temp_dir ) . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            $htaccess_content = "Order Deny,Allow\nDeny from all";
            file_put_contents( $htaccess, $htaccess_content );
        }
        
        // Add empty index.php
        $index_file = trailingslashit( $this->temp_dir ) . 'index.php';
        if ( ! file_exists( $index_file ) ) {
            file_put_contents( $index_file, '<?php // Silence is golden.' );
        }
    }

    /**
     * Handle AJAX file upload request.
     */
    public function handle_upload() {
        // Check nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wbcom_photoid_upload' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wbcom-photoid' ) ) );
        }
        
        // Check if file is uploaded
        if ( ! isset( $_FILES['photo_id'] ) || ! is_uploaded_file( $_FILES['photo_id']['tmp_name'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No file was uploaded.', 'wbcom-photoid' ) ) );
            return;
        }
        
        // Validate file type
        $file_type = wp_check_filetype( $_FILES['photo_id']['name'] );
        $allowed_types = $this->get_allowed_mime_types();
        
        if ( ! $file_type['type'] || ! in_array( $file_type['type'], $allowed_types, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid file type. Please upload a valid JPG or PNG file.', 'wbcom-photoid' ) ) );
            return;
        }
        
        // Validate file size
        $max_size = apply_filters( 'wbcom_photoid_max_file_size', 2 ) * 1024 * 1024; // Convert MB to bytes
        
        if ( $_FILES['photo_id']['size'] > $max_size ) {
            wp_send_json_error( array(
                'message' => sprintf(
                    __( 'File size exceeds the maximum limit of %d MB.', 'wbcom-photoid' ),
                    $max_size / ( 1024 * 1024 )
                )
            ) );
            return;
        }
        
        // Generate a unique filename
        $upload_id = md5( uniqid( rand(), true ) );
        $extension = pathinfo( $_FILES['photo_id']['name'], PATHINFO_EXTENSION );
        $filename = 'temp-' . $upload_id . '.' . $extension;
        $filepath = trailingslashit( $this->temp_dir ) . $filename;
        
        // Move uploaded file to temp directory
        if ( ! move_uploaded_file( $_FILES['photo_id']['tmp_name'], $filepath ) ) {
            wp_send_json_error( array( 'message' => __( 'Failed to save uploaded file.', 'wbcom-photoid' ) ) );
            return;
        }
        
        // Store upload data in session
        WC()->session->set( 'wbcom_photoid_temp_' . $upload_id, array(
            'filepath' => $filepath,
            'filename' => sanitize_file_name( $_FILES['photo_id']['name'] ),
            'filesize' => $_FILES['photo_id']['size'],
            'filetype' => $file_type['type'],
            'uploaded' => current_time( 'mysql' ),
        ) );
        
        // Return success
        wp_send_json_success( array(
            'message'   => __( 'File uploaded successfully.', 'wbcom-photoid' ),
            'upload_id' => $upload_id,
        ) );
    }

    /**
     * Get the allowed mime types.
     *
     * @return array Array of allowed mime types.
     */
    private function get_allowed_mime_types() {
        return apply_filters(
            'wbcom_photoid_allowed_mime_types',
            array(
                'image/jpeg',
                'image/jpg',
                'image/png',
            )
        );
    }

    /**
     * Process the uploaded file during checkout.
     *
     * @param WC_Order $order WooCommerce order object.
     * @param array    $data  Posted checkout data.
     */
    public function process_uploaded_file( $order, $data ) {
        // Check if an upload ID is provided
        if ( ! isset( $_POST['wbcom_photoid_upload_id'] ) ) {
            return;
        }
        
        $upload_id = sanitize_text_field( wp_unslash( $_POST['wbcom_photoid_upload_id'] ) );
        
        // Check if the uploaded file exists in session
        $upload_data = WC()->session->get( 'wbcom_photoid_temp_' . $upload_id );
        
        if ( ! $upload_data || ! file_exists( $upload_data['filepath'] ) ) {
            // Log error to order
            $order->update_meta_data( 'wbcom_photo_id_upload_error', 'Temporary file not found.' );
            return;
        }
        
        // Get secure upload directory
        $upload_dir = $this->get_secure_upload_dir();
        
        // Generate final filename
        $extension = pathinfo( $upload_data['filename'], PATHINFO_EXTENSION );
        $final_filename = apply_filters(
            'wbcom_photoid_filename',
            'order-' . $order->get_id() . '-' . uniqid() . '.' . $extension,
            $order
        );
        
        // Ensure filename is safe
        $final_filename = sanitize_file_name( $final_filename );
        $final_path = trailingslashit( $upload_dir ) . $final_filename;
        
        // Move the file from temp to secure directory
        if ( @rename( $upload_data['filepath'], $final_path ) || @copy( $upload_data['filepath'], $final_path ) ) {
            // Delete the temp file if copy was used
            if ( file_exists( $upload_data['filepath'] ) ) {
                @unlink( $upload_data['filepath'] );
            }
            
            // Store file information in order meta
            $order->update_meta_data( 'wbcom_photo_id_path', $final_path );
            $order->update_meta_data( 'wbcom_photo_id_filename', $final_filename );
            $order->update_meta_data( 'wbcom_photo_id_original_filename', $upload_data['filename'] );
            $order->update_meta_data( 'wbcom_photo_id_upload_date', current_time( 'mysql' ) );
            $order->update_meta_data( 'wbcom_photo_id_filesize', $upload_data['filesize'] );
            $order->update_meta_data( 'wbcom_photo_id_mime', $upload_data['filetype'] );
            
            // Clear session data
            WC()->session->__unset( 'wbcom_photoid_temp_' . $upload_id );
            
            // Hook after successful save
            do_action( 'wbcom_photoid_after_save', $order, $final_path );
        } else {
            // Log upload error
            $order->update_meta_data( 'wbcom_photo_id_upload_error', 'Failed to move uploaded file from temporary location.' );
            
            // Hook after failed save
            do_action( 'wbcom_photoid_save_failed', $order, array(
                'tmp_name' => $upload_data['filepath'],
                'name'     => $upload_data['filename'],
                'size'     => $upload_data['filesize'],
                'type'     => $upload_data['filetype'],
            ) );
        }
    }

    /**
     * Get secure upload directory.
     *
     * @return string Path to the secure upload directory.
     */
    private function get_secure_upload_dir() {
        $upload_dir = wp_upload_dir();
        
        // Allow changing the base directory name
        $dir_name = apply_filters( 'wbcom_photoid_directory_name', 'customer-id' );
        
        $path = trailingslashit( $upload_dir['basedir'] ) . $dir_name;
        
        if ( ! file_exists( $path ) ) {
            wp_mkdir_p( $path );
        }
        
        $htaccess = trailingslashit( $path ) . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            $htaccess_content = apply_filters(
                'wbcom_photoid_htaccess_content',
                "Order Deny,Allow\nDeny from all"
            );
            
            file_put_contents( $htaccess, $htaccess_content );
        }
        
        // Create an empty index.php file for additional security
        $index_file = trailingslashit( $path ) . 'index.php';
        if ( ! file_exists( $index_file ) ) {
            file_put_contents( $index_file, '<?php // Silence is golden.' );
        }
        
        return apply_filters( 'wbcom_photoid_upload_path', $path );
    }

    /**
     * Clean up temporary files older than 24 hours.
     */
    public function cleanup_temp_files() {
        $files = glob( trailingslashit( $this->temp_dir ) . 'temp-*.*' );
        $cutoff_time = time() - ( 24 * HOUR_IN_SECONDS );
        
        foreach ( $files as $file ) {
            if ( filemtime( $file ) < $cutoff_time ) {
                @unlink( $file );
            }
        }
    }
}

// Initialize AJAX handler
return new Wbcom_PhotoID_AJAX();