<?php
/**
 * Plugin Name: Wbcom Checkout Photo ID Upload
 * Plugin URI: https://wbcomdesigns.com/plugins/checkout-photo-id
 * Description: Requires customers to upload a Photo ID during WooCommerce checkout, stores it securely in a protected folder.
 * Version: 1.1.0
 * Author: Wbcom Designs
 * Author URI: https://wbcomdesigns.com
 * Text Domain: wbcom-photoid
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 5.0
 * WC tested up to: 8.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Wbcom_Checkout_Photo_ID
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Wbcom Checkout Photo ID Upload Class.
 *
 * @class Wbcom_Checkout_Photo_ID
 */
class Wbcom_Checkout_Photo_ID {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version = '1.1.0';

	/**
	 * The single instance of the class.
	 *
	 * @var Wbcom_Checkout_Photo_ID
	 */
	protected static $_instance = null;

	/**
	 * Main Wbcom_Checkout_Photo_ID Instance.
	 *
	 * Ensures only one instance of Wbcom_Checkout_Photo_ID is loaded or can be loaded.
	 *
	 * @static
	 * @return Wbcom_Checkout_Photo_ID - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->init_hooks();
		$this->includes();

		// Register activation hook.
		register_activation_hook( __FILE__, array( $this, 'activation_setup' ) );
		
		// Load plugin text domain.
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
	}

	/**
	 * Define plugin constants.
	 */
	private function define_constants() {
		define( 'WBCOM_PHOTOID_VERSION', $this->version );
		define( 'WBCOM_PHOTOID_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		define( 'WBCOM_PHOTOID_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		define( 'WBCOM_PHOTOID_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		// Admin classes.
		if ( is_admin() ) {
			require_once WBCOM_PHOTOID_PLUGIN_DIR . 'admin/class-wbcom-photoid-admin.php';
		}
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Add file upload field on checkout.
		add_action( 'woocommerce_before_order_notes', array( $this, 'add_photo_id_upload_field' ), 10, 1 );
		
		// Validate file upload.
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_photo_id_upload' ) );
		
		// Handle file upload and save to order meta.
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_photo_id_to_order_meta' ), 10, 2 );
		
		// Show download link in admin.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_photo_id_in_admin' ) );
		
		// Secure download endpoint for admins only.
		add_action( 'admin_post_wbcom_download_photo_id', array( $this, 'secure_download_photo_id' ) );
		
		// Add checkout styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		
		// Add settings link to plugins page.
		add_filter( 'plugin_action_links_' . WBCOM_PHOTOID_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'wbcom-photoid', false, dirname( WBCOM_PHOTOID_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Plugin activation setup.
	 */
	public function activation_setup() {
		// Create secure directory on activation.
		$this->get_photo_id_upload_dir();
		
		// Add custom capability to admin roles.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( 'manage_photo_id' );
		}
		
		// Trigger action for other plugins.
		do_action( 'wbcom_photoid_activated' );
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_scripts() {
		if ( is_checkout() ) {
			wp_enqueue_style( 'wbcom-photoid-style', WBCOM_PHOTOID_PLUGIN_URL . 'assets/css/wbcom-photoid.css', array(), WBCOM_PHOTOID_VERSION );
			wp_enqueue_script( 'wbcom-photoid-script', WBCOM_PHOTOID_PLUGIN_URL . 'assets/js/wbcom-photoid.js', array( 'jquery' ), WBCOM_PHOTOID_VERSION, true );
			
			// Localize script with data.
			wp_localize_script( 'wbcom-photoid-script', 'wbcom_photoid', array(
				'max_file_size'    => apply_filters( 'wbcom_photoid_max_file_size', 2 ), // In MB
				'allowed_file_types' => apply_filters( 'wbcom_photoid_allowed_extensions', array( 'jpg', 'jpeg', 'png' ) ),
				'error_messages'   => array(
					'file_size'     => __( 'File size exceeds the maximum limit.', 'wbcom-photoid' ),
					'file_type'     => __( 'Invalid file type. Please upload JPG or PNG files only.', 'wbcom-photoid' ),
				),
			) );
		}
	}

	/**
	 * Add file upload field on checkout.
	 *
	 * @param WC_Checkout $checkout Checkout object.
	 */
	public function add_photo_id_upload_field( $checkout ) {
		// Check if ID upload is required for the current cart.
		if ( ! $this->is_photo_id_required() ) {
			return;
		}
		
		// Allow filtering of field title and description.
		$title = apply_filters(
			'wbcom_photoid_field_title',
			__( 'Upload Photo ID', 'wbcom-photoid' )
		);
		
		$description = apply_filters(
			'wbcom_photoid_field_description',
			__( 'Upload your ID (JPG/PNG, max 2MB)', 'wbcom-photoid' )
		);
		
		// Hook before the photo ID field is displayed.
		do_action( 'wbcom_photoid_before_field', $checkout );
		
		// Output the field.
		echo '<div id="wbcom_photo_id_upload" class="wbcom-photoid-section">';
		echo '<h3>' . esc_html( $title ) . '</h3>';
		
		// Add optional description.
		$help_text = apply_filters(
			'wbcom_photoid_help_text',
			__( 'We require a valid photo ID to verify your identity for this purchase.', 'wbcom-photoid' )
		);
		
		if ( ! empty( $help_text ) ) {
			echo '<div class="wbcom-photoid-help">' . wp_kses_post( $help_text ) . '</div>';
		}
		
		echo '<p class="form-row form-row-wide">';
		echo '<label for="photo_id">' . esc_html( $description ) . '</label>';
		echo '<input type="file" name="photo_id" id="photo_id" accept="' . esc_attr( $this->get_allowed_mime_types_attribute() ) . '" />';
		echo '<span class="wbcom-photoid-preview"></span>';
		echo '</p>';
		echo '</div>';
		
		// Hook after the photo ID field is displayed.
		do_action( 'wbcom_photoid_after_field', $checkout );
	}

	/**
	 * Validate file upload.
	 */
	public function validate_photo_id_upload() {
		// Check if ID upload is required for the current cart.
		if ( ! $this->is_photo_id_required() ) {
			return;
		}

		$validation_error = apply_filters(
			'wbcom_photoid_pre_validation',
			false,
			$_FILES
		);

		// If a filter has already set an error, use that.
		if ( $validation_error ) {
			wc_add_notice( $validation_error, 'error' );
			return;
		}

		// Basic validation.
		if ( ! isset( $_FILES['photo_id'] ) || $_FILES['photo_id']['error'] !== UPLOAD_ERR_OK ) {
			wc_add_notice(
				apply_filters(
					'wbcom_photoid_missing_error',
					__( 'Please upload a valid Photo ID (JPG/PNG, max 2MB).', 'wbcom-photoid' )
				),
				'error'
			);
			return;
		}

		// File type validation.
		$file_type = wp_check_filetype( $_FILES['photo_id']['name'] );
		$allowed_types = $this->get_allowed_mime_types();
		
		if ( ! $file_type['type'] || ! in_array( $file_type['type'], $allowed_types, true ) ) {
			wc_add_notice(
				apply_filters(
					'wbcom_photoid_filetype_error',
					__( 'Invalid file type. Please upload a valid JPG or PNG file.', 'wbcom-photoid' )
				),
				'error'
			);
			return;
		}

		// File size validation.
		$max_size = apply_filters( 'wbcom_photoid_max_file_size', 2 ) * 1024 * 1024; // Convert MB to bytes.
		
		if ( $_FILES['photo_id']['size'] > $max_size ) {
			wc_add_notice(
				apply_filters(
					'wbcom_photoid_filesize_error',
					sprintf( __( 'File size exceeds the maximum limit of %d MB.', 'wbcom-photoid' ), $max_size / ( 1024 * 1024 ) )
				),
				'error'
			);
			return;
		}

		// Allow additional validation by other plugins.
		do_action( 'wbcom_photoid_after_validation', $_FILES );
	}

	/**
	 * Get the allowed mime types.
	 *
	 * @return array Array of allowed mime types.
	 */
	public function get_allowed_mime_types() {
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
	 * Get the allowed mime types as an attribute string.
	 *
	 * @return string Comma-separated list of file extensions.
	 */
	private function get_allowed_mime_types_attribute() {
		$extensions = apply_filters(
			'wbcom_photoid_allowed_extensions',
			array( '.jpg', '.jpeg', '.png' )
		);
		
		return implode( ',', $extensions );
	}

	/**
	 * Check if photo ID is required based on cart contents.
	 *
	 * @return bool True if photo ID is required, false otherwise.
	 */
	public function is_photo_id_required() {
		// Get categories that don't require ID upload.
		$bypass_categories = apply_filters( 'wbcom_photoid_bypass_categories', array() );
		
		// Get product IDs that don't require ID upload.
		$bypass_products = apply_filters( 'wbcom_photoid_bypass_products', array() );
		
		// Default to requiring ID.
		$requires_id = true;
		
		// Allow complete bypass via filter.
		if ( apply_filters( 'wbcom_photoid_force_bypass', false ) ) {
			return false;
		}

		// Check cart contents.
		foreach ( WC()->cart->get_cart_contents() as $cart_item ) {
			$product_id = $cart_item['product_id'];
			
			// Check if product is in bypass list.
			if ( in_array( $product_id, $bypass_products, true ) ) {
				$requires_id = false;
				break;
			}
			
			// Check product categories.
			$terms = get_the_terms( $product_id, 'product_cat' );
			if ( ! empty( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( in_array( $term->term_id, $bypass_categories, true ) ) {
						$requires_id = false;
						break 2;
					}
				}
			}
		}
		
		// Final filter to allow overriding the decision.
		return apply_filters( 'wbcom_photoid_is_required', $requires_id );
	}

	/**
	 * Create secure directory if not exists.
	 *
	 * @return string Path to the secure upload directory.
	 */
	public function get_photo_id_upload_dir() {
		$upload_dir = wp_upload_dir();
		
		// Allow changing the base directory name.
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
		
		// Create an empty index.php file for additional security.
		$index_file = trailingslashit( $path ) . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			file_put_contents( $index_file, '<?php // Silence is golden.' );
		}

		return apply_filters( 'wbcom_photoid_upload_path', $path );
	}

	/**
	 * Handle file upload and save to order meta.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @param array    $data  Posted data.
	 */
	public function save_photo_id_to_order_meta( $order, $data ) {
		if ( ! isset( $_FILES['photo_id'] ) || $_FILES['photo_id']['error'] !== UPLOAD_ERR_OK ) {
			return;
		}

		// Hook before saving file.
		do_action( 'wbcom_photoid_before_save', $order, $_FILES['photo_id'] );

		$upload_dir = $this->get_photo_id_upload_dir();
		
		// Generate a unique filename.
		$extension = pathinfo( $_FILES['photo_id']['name'], PATHINFO_EXTENSION );
		$filename = apply_filters(
			'wbcom_photoid_filename',
			'order-' . $order->get_id() . '-' . uniqid() . '.' . $extension,
			$order
		);
		
		// Ensure filename is safe.
		$filename = sanitize_file_name( $filename );
		
		$target_path = trailingslashit( $upload_dir ) . $filename;

		// Move the uploaded file.
		if ( move_uploaded_file( $_FILES['photo_id']['tmp_name'], $target_path ) ) {
			// Store file information in order meta.
			$order->update_meta_data( 'wbcom_photo_id_path', $target_path );
			$order->update_meta_data( 'wbcom_photo_id_filename', $filename );
			$order->update_meta_data( 'wbcom_photo_id_upload_date', current_time( 'mysql' ) );
			
			// Store file size.
			$order->update_meta_data( 'wbcom_photo_id_filesize', filesize( $target_path ) );
			
			// Store MIME type.
			$mime = wp_check_filetype( $target_path );
			$order->update_meta_data( 'wbcom_photo_id_mime', $mime['type'] );
			
			// Hook after successful save.
			do_action( 'wbcom_photoid_after_save', $order, $target_path );
		} else {
			// Log upload error.
			$order->update_meta_data( 'wbcom_photo_id_upload_error', 'Failed to move uploaded file.' );
			
			// Hook after failed save.
			do_action( 'wbcom_photoid_save_failed', $order, $_FILES['photo_id'] );
		}
	}

	/**
	 * Show download link in admin.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 */
	public function display_photo_id_in_admin( $order ) {
		// Check if user has permission to view ID.
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_photo_id' ) ) {
			return;
		}

		$filename = $order->get_meta( 'wbcom_photo_id_filename' );
		$upload_date = $order->get_meta( 'wbcom_photo_id_upload_date' );
		$file_size = $order->get_meta( 'wbcom_photo_id_filesize' );
		
		if ( $filename ) {
			$url = admin_url( 'admin-post.php?action=wbcom_download_photo_id&order_id=' . $order->get_id() . '&_wpnonce=' . wp_create_nonce( 'download_photo_id_' . $order->get_id() ) );
			
			echo '<div class="wbcom-photoid-admin">';
			echo '<h4>' . esc_html__( 'Photo ID', 'wbcom-photoid' ) . '</h4>';
			
			// Display file information.
			echo '<p>';
			echo '<strong>' . esc_html__( 'Filename:', 'wbcom-photoid' ) . '</strong> ' . esc_html( $filename ) . '<br>';
			
			if ( $upload_date ) {
				echo '<strong>' . esc_html__( 'Uploaded:', 'wbcom-photoid' ) . '</strong> ' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $upload_date ) ) ) . '<br>';
			}
			
			if ( $file_size ) {
				echo '<strong>' . esc_html__( 'Size:', 'wbcom-photoid' ) . '</strong> ' . esc_html( size_format( $file_size ) ) . '<br>';
			}
			
			echo '</p>';
			
			// Download button.
			echo '<p><a href="' . esc_url( $url ) . '" class="button">' . esc_html__( 'Download ID', 'wbcom-photoid' ) . '</a></p>';
			
			// Allow plugins to add more actions.
			do_action( 'wbcom_photoid_admin_actions', $order );
			
			echo '</div>';
		} else {
			// Check if there was an upload error.
			$error = $order->get_meta( 'wbcom_photo_id_upload_error' );
			if ( $error ) {
				echo '<div class="wbcom-photoid-admin wbcom-photoid-error">';
				echo '<h4>' . esc_html__( 'Photo ID', 'wbcom-photoid' ) . '</h4>';
				echo '<p class="error">' . esc_html__( 'Error:', 'wbcom-photoid' ) . ' ' . esc_html( $error ) . '</p>';
				echo '</div>';
			} else {
				// No ID uploaded.
				echo '<div class="wbcom-photoid-admin">';
				echo '<h4>' . esc_html__( 'Photo ID', 'wbcom-photoid' ) . '</h4>';
				echo '<p>' . esc_html__( 'No photo ID uploaded with this order.', 'wbcom-photoid' ) . '</p>';
				echo '</div>';
			}
		}
	}

	/**
	 * Secure download endpoint for admins only.
	 */
	public function secure_download_photo_id() {
		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! isset( $_GET['order_id'] ) ) {
			wp_die( esc_html__( 'Invalid request', 'wbcom-photoid' ) );
		}
		
		$order_id = absint( $_GET['order_id'] );
		
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'download_photo_id_' . $order_id ) ) {
			wp_die( esc_html__( 'Security check failed', 'wbcom-photoid' ) );
		}
		
		// Check permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_photo_id' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'wbcom-photoid' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Invalid order', 'wbcom-photoid' ) );
		}

		$path = $order->get_meta( 'wbcom_photo_id_path' );
		$filename = $order->get_meta( 'wbcom_photo_id_filename' );
		
		if ( ! $path || ! file_exists( $path ) ) {
			wp_die( esc_html__( 'File not found', 'wbcom-photoid' ) );
		}
		
		// Log this access.
		$this->log_file_access( $order_id, get_current_user_id() );
		
		// Hook before download.
		do_action( 'wbcom_photoid_before_download', $order, $path );

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Cache-Control: private, no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		
		readfile( $path );
		
		// Hook after download.
		do_action( 'wbcom_photoid_after_download', $order, $path );
		
		exit;
	}

	/**
	 * Log file access to order notes.
	 *
	 * @param int $order_id    Order ID.
	 * @param int $user_id     User ID.
	 */
	private function log_file_access( $order_id, $user_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		
		$user = get_user_by( 'id', $user_id );
		$username = $user ? $user->display_name : __( 'Unknown user', 'wbcom-photoid' );
		
		$note = sprintf(
			/* translators: %1$s: user name, %2$s: date/time */
			__( 'Photo ID downloaded by %1$s on %2$s', 'wbcom-photoid' ),
			$username,
			date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
		);
		
		$order->add_order_note( $note );
	}

	/**
	 * Add plugin action links.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wbcom_photoid' ) . '">' . __( 'Settings', 'wbcom-photoid' ) . '</a>',
		);
		
		return array_merge( $plugin_links, $links );
	}
}

/**
 * Main instance of Wbcom_Checkout_Photo_ID.
 *
 * Returns the main instance of WCPID to prevent the need to use globals.
 *
 * @return Wbcom_Checkout_Photo_ID
 */
function WCPID() {
	return Wbcom_Checkout_Photo_ID::instance();
}

// Global for backwards compatibility.
$GLOBALS['wbcom_checkout_photo_id'] = WCPID();
