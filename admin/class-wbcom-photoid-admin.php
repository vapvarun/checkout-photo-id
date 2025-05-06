<?php
/**
 * Admin Settings Class
 *
 * @package Wbcom_Checkout_Photo_ID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

/**
 * Wbcom_PhotoID_Admin class.
 */
class Wbcom_PhotoID_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Add WooCommerce settings tab.
		add_filter( 'woocommerce_get_sections_checkout', array( $this, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_checkout', array( $this, 'add_settings' ), 10, 2 );
		
		// Add meta box to order screen - works with both traditional and HPOS.
		add_action( 'add_meta_boxes', array( $this, 'add_order_meta_box' ), 30 ); // Higher priority to place after notes
		
		// Add ID status column to orders list.
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'populate_order_column' ), 10, 2 );
		
		// Add bulk actions.
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_bulk_actions' ), 10, 3 );
		
		// Add admin notices.
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		
		// Register AJAX handler for ID requests.
		add_action( 'wp_ajax_wbcom_photoid_request', array( $this, 'handle_ajax_request' ) );
		
		// Add scripts for admin pages.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        
        // Add image preview endpoint.
        add_action( 'admin_post_wbcom_preview_photo_id', array( $this, 'secure_preview_photo_id' ) );
	}
	
	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Hook suffix for the current admin page.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Load on both traditional order edit page and HPOS order edit page
		$is_order_page = ('post.php' === $hook && get_post_type() === 'shop_order') || 
		                 (strpos($hook, 'wc-orders') !== false);
		
		if (!$is_order_page) {
			return;
		}
		
		wp_enqueue_style( 'wbcom-photoid-admin-style', WBCOM_PHOTOID_PLUGIN_URL . 'admin/assets/css/wbcom-photoid.css', array(), WBCOM_PHOTOID_VERSION );
		wp_enqueue_script( 'wbcom-photoid-admin-script', WBCOM_PHOTOID_PLUGIN_URL . 'admin/assets/js/wbcom-photoid-admin.js', array( 'jquery' ), WBCOM_PHOTOID_VERSION, true );
		
		wp_localize_script( 'wbcom-photoid-admin-script', 'wbcom_photoid_admin', array(
			'nonce'        => wp_create_nonce( 'wbcom_photoid_request' ),
			'sending_text' => __( 'Sending...', 'wbcom-photoid' ),
			'sent_text'    => __( 'Request Sent', 'wbcom-photoid' ),
			'request_text' => __( 'Send Request', 'wbcom-photoid' ),
			'error_text'   => __( 'Error sending request', 'wbcom-photoid' ),
		) );
	}

	/**
	 * Add new section to WooCommerce > Settings > Checkout.
	 *
	 * @param array $sections Checkout sections.
	 * @return array
	 */
	public function add_section( $sections ) {
		$sections['wbcom_photoid'] = __( 'Photo ID Upload', 'wbcom-photoid' );
		return $sections;
	}

	/**
	 * Add settings to WooCommerce > Settings > Checkout > Photo ID Upload.
	 *
	 * @param array  $settings        Array of settings.
	 * @param string $current_section Current section being displayed.
	 * @return array
	 */
	public function add_settings( $settings, $current_section ) {
		if ( 'wbcom_photoid' !== $current_section ) {
			return $settings;
		}

		$custom_settings = array(
			array(
				'title' => __( 'Photo ID Upload Settings', 'wbcom-photoid' ),
				'type'  => 'title',
				'desc'  => __( 'Configure settings for the Photo ID upload feature at checkout.', 'wbcom-photoid' ),
				'id'    => 'wbcom_photoid_options',
			),
			
			array(
				'title'    => __( 'Enable Photo ID Upload', 'wbcom-photoid' ),
				'desc'     => __( 'Enable Photo ID upload at checkout', 'wbcom-photoid' ),
				'id'       => 'wbcom_photoid_enable',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			
			array(
				'title'    => __( 'Field Title', 'wbcom-photoid' ),
				'desc'     => __( 'The title displayed above the upload field', 'wbcom-photoid' ),
				'id'       => 'wbcom_photoid_field_title',
				'default'  => __( 'Upload Photo ID', 'wbcom-photoid' ),
				'type'     => 'text',
			),
			
			array(
				'title'    => __( 'Field Description', 'wbcom-photoid' ),
				'desc'     => __( 'The description displayed below the field title', 'wbcom-photoid' ),
				'id'       => 'wbcom_photoid_field_description',
				'default'  => __( 'Upload your ID (JPG/PNG, max 2MB)', 'wbcom-photoid' ),
				'type'     => 'text',
			),
			
			array(
				'title'    => __( 'Help Text', 'wbcom-photoid' ),
				'desc'     => __( 'Additional information displayed to the customer', 'wbcom-photoid' ),
				'id'       => 'wbcom_photoid_help_text',
				'default'  => __( 'We require a valid photo ID to verify your identity for this purchase.', 'wbcom-photoid' ),
				'type'     => 'textarea',
			),
			
			array(
				'title'    => __( 'Maximum File Size (MB)', 'wbcom-photoid' ),
				'desc'     => __( 'Maximum file size allowed for upload (in MB)', 'wbcom-photoid' ),
				'id'       => 'wbcom_photoid_max_size',
				'default'  => '2',
				'type'     => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'max'  => '10',
					'step' => '1',
				),
			),
			
			array(
				'title'    => __( 'Exempt Categories', 'wbcom-photoid' ),
				'desc'     => __( 'Select product categories that do NOT require Photo ID upload', 'wbcom-photoid' ),
				'id'       => 'wbcom_photoid_exempt_categories',
				'default'  => array(),
				'type'     => 'multiselect',
				'options'  => $this->get_product_categories(),
				'class'    => 'wc-enhanced-select',
			),
			
			array(
				'title'    => __( 'File Retention Period (days)', 'wbcom-photoid' ),
				'desc'     => __( 'Number of days to retain uploaded files (0 = keep indefinitely)', 'wbcom-photoid' ),
				'id'       => 'wbcom_photoid_retention_days',
				'default'  => '90',
				'type'     => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			),
			
			array(
				'title'    => __( 'Email Settings', 'wbcom-photoid' ),
				'type'     => 'title',
				'desc'     => __( 'Configure email settings for ID requests.', 'wbcom-photoid' ),
				'id'       => 'wbcom_photoid_email_options',
			),
			
			array(
				'title'    => __( 'Admin Notifications', 'wbcom-photoid' ),
				'desc'     => __( 'Send email notification to admin when customer uploads ID', 'wbcom-photoid' ),
				'id'       => 'wbcom_photoid_admin_notification',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			
			array(
				'title'    => __( 'Request Email Subject', 'wbcom-photoid' ),
				'desc'     => __( 'Email subject for ID request emails', 'wbcom-photoid' ),
				'id'       => 'wbcom_photoid_request_subject',
				'default'  => __( 'Action Required: Please upload your ID for order #{order_number}', 'wbcom-photoid' ),
				'type'     => 'text',
			),
			
			array(
				'title'    => __( 'Request Email Heading', 'wbcom-photoid' ),
				'desc'     => __( 'Email heading for ID request emails', 'wbcom-photoid' ),
				'id'       => 'wbcom_photoid_request_heading',
				'default'  => __( 'Please upload your ID', 'wbcom-photoid' ),
				'type'     => 'text',
			),
			
			array(
				'type' => 'sectionend',
				'id'   => 'wbcom_photoid_email_options',
			),
			
			array(
				'title'    => __( 'Security Settings', 'wbcom-photoid' ),
				'type'     => 'title',
				'desc'     => __( 'Configure security settings for the Photo ID upload feature.', 'wbcom-photoid' ),
				'id'       => 'wbcom_photoid_security_options',
			),
			
			array(
				'title'    => __( 'Log Access', 'wbcom-photoid' ),
				'desc'     => __( 'Log admin access to ID files in order notes', 'wbcom-photoid' ),
				'id'       => 'wbcom_photoid_log_access',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			
			array(
				'title'    => __( 'Secure Directory Name', 'wbcom-photoid' ),
				'desc'     => __( 'Name of the secure directory to store files (changing this will not move existing files)', 'wbcom-photoid' ),
				'id'       => 'wbcom_photoid_directory_name',
				'default'  => 'customer-id',
				'type'     => 'text',
			),
			
			array(
				'type' => 'sectionend',
				'id'   => 'wbcom_photoid_options',
			),
		);

		// Hook to allow other plugins to modify settings.
		return apply_filters( 'wbcom_photoid_settings', $custom_settings );
	}

	/**
	 * Get all product categories.
	 *
	 * @return array
	 */
	private function get_product_categories() {
		$categories = array();
		$terms = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		) );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$categories[ $term->term_id ] = $term->name;
			}
		}

		return $categories;
	}

	/**
	 * Add meta box to order screen - compatible with both traditional and HPOS.
	 */
	public function add_order_meta_box() {
        // Determine screen ID based on whether HPOS is enabled
        $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') && 
                  wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
                  ? wc_get_page_screen_id('shop-order')
                  : 'shop_order';
        
		add_meta_box(
			'wbcom_photoid_metabox',
			__( 'Photo ID', 'wbcom-photoid' ),
			array( $this, 'render_order_meta_box' ),
			$screen,
			'normal',  // Place in normal position
			'low'      // Low priority to ensure it appears after order notes
		);
	}

	/**
	 * Render order meta box content.
	 *
	 * @param WP_Post|WC_Order $post_or_order_object Post object or WC_Order object.
	 */
	public function render_order_meta_box( $post_or_order_object ) {
        // Get the order object depending on whether we're using HPOS or not
        $order = ($post_or_order_object instanceof WP_Post) 
               ? wc_get_order($post_or_order_object->ID) 
               : $post_or_order_object;
               
		if ( ! $order ) {
			return;
		}

		$filename = $order->get_meta( 'wbcom_photo_id_filename' );
		$upload_date = $order->get_meta( 'wbcom_photo_id_upload_date' );
		$file_path = $order->get_meta( 'wbcom_photo_id_path' );
		$mime_type = $order->get_meta( 'wbcom_photo_id_mime' );
		$original_filename = $order->get_meta( 'wbcom_photo_id_original_filename' );
		$file_size = $order->get_meta( 'wbcom_photo_id_filesize' );
		
		if ( $filename ) {
			$url = admin_url( 'admin-post.php?action=wbcom_download_photo_id&order_id=' . $order->get_id() . '&_wpnonce=' . wp_create_nonce( 'download_photo_id_' . $order->get_id() ) );
			
			echo '<p class="wbcom-photoid-status wbcom-photoid-status-uploaded">';
			echo '<span class="dashicons dashicons-yes-alt"></span> ';
			echo esc_html__( 'ID uploaded', 'wbcom-photoid' );
			echo '</p>';
			
			// Add image preview if it's an image file
			if ( file_exists( $file_path ) && in_array( $mime_type, array( 'image/jpeg', 'image/jpg', 'image/png' ) ) ) {
				// Generate preview URL
				$preview_url = add_query_arg( array(
					'action'   => 'wbcom_preview_photo_id',
					'order_id' => $order->get_id(),
					'_wpnonce' => wp_create_nonce( 'preview_photo_id_' . $order->get_id() ),
					'ts'       => time(), // Cache buster
				), admin_url( 'admin-post.php' ) );
				
				echo '<div class="wbcom-photoid-admin-preview" style="margin:10px 0 15px;padding:10px;background:#f8f8f8;border:1px solid #ddd;text-align:center;">';
				echo '<img src="' . esc_url( $preview_url ) . '" alt="ID Preview" style="max-width:100%;height:auto;border-radius:3px;" />';
				echo '</div>';
			}
			
			// Display file information
			echo '<p>';
			if ( $original_filename ) {
				echo '<strong>' . esc_html__( 'Original filename:', 'wbcom-photoid' ) . '</strong> ' . esc_html( $original_filename ) . '<br>';
			}
			echo '<strong>' . esc_html__( 'Stored as:', 'wbcom-photoid' ) . '</strong> ' . esc_html( $filename ) . '<br>';
			
			if ( $upload_date ) {
				echo '<strong>' . esc_html__( 'Uploaded:', 'wbcom-photoid' ) . '</strong> ' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $upload_date ) ) ) . '<br>';
			}
			
			if ( $file_size ) {
				echo '<strong>' . esc_html__( 'Size:', 'wbcom-photoid' ) . '</strong> ' . esc_html( size_format( $file_size ) ) . '<br>';
			}
			echo '</p>';
			
			// Download button
			echo '<p>';
			echo '<a href="' . esc_url( $url ) . '" class="button button-primary">';
			echo '<span class="dashicons dashicons-download"></span> ';
			echo esc_html__( 'Download', 'wbcom-photoid' );
			echo '</a>';
			echo '</p>';
			
			// Allow plugins to add more actions.
			do_action( 'wbcom_photoid_metabox_actions', $order );
		} else {
			// Check if there was an upload error.
			$error = $order->get_meta( 'wbcom_photo_id_upload_error' );
			if ( $error ) {
				echo '<p class="wbcom-photoid-status wbcom-photoid-status-error">';
				echo '<span class="dashicons dashicons-warning"></span> ';
				echo esc_html__( 'Upload error', 'wbcom-photoid' );
				echo '</p>';
				echo '<p class="error">' . esc_html( $error ) . '</p>';
			} else {
				echo '<p class="wbcom-photoid-status wbcom-photoid-status-missing">';
				echo '<span class="dashicons dashicons-no-alt"></span> ';
				echo esc_html__( 'No ID uploaded', 'wbcom-photoid' );
				echo '</p>';
				
				// Show link to request ID via email.
				echo '<p>';
				echo '<a href="#" class="button wbcom-photoid-request-button" data-order="' . esc_attr( $order->get_id() ) . '">';
				echo '<span class="dashicons dashicons-email"></span> ';
				echo esc_html__( 'Request ID', 'wbcom-photoid' );
				echo '</a>';
				echo '</p>';
				
				// Request ID form (hidden initially).
				echo '<div class="wbcom-photoid-request-form" style="display:none;">';
				echo '<p>';
				echo '<label for="wbcom-photoid-message">' . esc_html__( 'Custom message:', 'wbcom-photoid' ) . '</label>';
				echo '<textarea id="wbcom-photoid-message" class="widefat" rows="3"></textarea>';
				echo '</p>';
				echo '<p>';
				echo '<button type="button" class="button button-primary wbcom-photoid-send-request" data-order="' . esc_attr( $order->get_id() ) . '">';
				echo esc_html__( 'Send Request', 'wbcom-photoid' );
				echo '</button>';
				echo '</p>';
				echo '</div>';
			}
		}
	}

	/**
	 * Add column to orders list.
	 *
	 * @param array $columns Columns array.
	 * @return array
	 */
	public function add_order_column( $columns ) {
		$new_columns = array();
		
		foreach ( $columns as $key => $column ) {
			$new_columns[ $key ] = $column;
			
			// Add column after order status.
			if ( 'order_status' === $key ) {
				$new_columns['photo_id_status'] = __( 'Photo ID', 'wbcom-photoid' );
			}
		}
		
		return $new_columns;
	}

	/**
	 * Populate custom column in orders list.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function populate_order_column( $column, $post_id ) {
		if ( 'photo_id_status' !== $column ) {
			return;
		}
		
		$order = wc_get_order( $post_id );
		if ( ! $order ) {
			return;
		}
		
		$filename = $order->get_meta( 'wbcom_photo_id_filename' );
		
		if ( $filename ) {
			echo '<mark class="order-status status-completed tips" data-tip="' . esc_attr__( 'ID uploaded', 'wbcom-photoid' ) . '">';
			echo '<span class="dashicons dashicons-yes-alt"></span>';
			echo '</mark>';
		} else {
			// Check if there was an upload error.
			$error = $order->get_meta( 'wbcom_photo_id_upload_error' );
			if ( $error ) {
				echo '<mark class="order-status status-failed tips" data-tip="' . esc_attr__( 'Upload error', 'wbcom-photoid' ) . '">';
				echo '<span class="dashicons dashicons-warning"></span>';
				echo '</mark>';
			} else {
				// Check if order products require ID.
				$requires_id = false;
				
				// Get exempt categories.
				$exempt_categories = get_option( 'wbcom_photoid_exempt_categories', array() );
				
				foreach ( $order->get_items() as $item ) {
					$product_id = $item->get_product_id();
					$needs_id = true;
					
					// Check product categories.
					$terms = get_the_terms( $product_id, 'product_cat' );
					if ( ! empty( $terms ) ) {
						foreach ( $terms as $term ) {
							if ( in_array( $term->term_id, $exempt_categories, true ) ) {
								$needs_id = false;
								break;
							}
						}
					}
					
					if ( $needs_id ) {
						$requires_id = true;
						break;
					}
				}
				
				if ( $requires_id ) {
					echo '<mark class="order-status status-on-hold tips" data-tip="' . esc_attr__( 'ID required but not uploaded', 'wbcom-photoid' ) . '">';
					echo '<span class="dashicons dashicons-no-alt"></span>';
					echo '</mark>';
				} else {
					echo '<mark class="order-status tips" data-tip="' . esc_attr__( 'No ID required', 'wbcom-photoid' ) . '">';
					echo '<span class="dashicons dashicons-minus"></span>';
					echo '</mark>';
				}
			}
		}
	}

	/**
	 * Register bulk actions.
	 *
	 * @param array $actions Bulk actions.
	 * @return array
	 */
	public function register_bulk_actions( $actions ) {
		$actions['wbcom_photoid_request'] = __( 'Request Photo ID', 'wbcom-photoid' );
		return $actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $action      Action name.
	 * @param array  $post_ids    Selected post IDs.
	 * @return string
	 */
	public function handle_bulk_actions( $redirect_to, $action, $post_ids ) {
		if ( 'wbcom_photoid_request' !== $action ) {
			return $redirect_to;
		}

		$processed_count = 0;

		foreach ( $post_ids as $post_id ) {
			$order = wc_get_order( $post_id );
			
			if ( ! $order ) {
				continue;
			}
			
			// Check if ID already uploaded.
			$filename = $order->get_meta( 'wbcom_photo_id_filename' );
			if ( $filename ) {
				continue;
			}
			
			// Send email request.
			$this->send_id_request_email( $order );
			
			$processed_count++;
		}

		if ( $processed_count > 0 ) {
			$redirect_to = add_query_arg(
				array(
					'wbcom_photoid_requested' => $processed_count,
				),
				$redirect_to
			);
		}

		return $redirect_to;
	}

	/**
	 * Handle AJAX request for sending ID request.
	 */
	public function handle_ajax_request() {
		// Instantiate email class to handle the request
		$email_handler = new Wbcom_PhotoID_Email();
		$email_handler->handle_request_ajax();
	}

	/**
	 * Send email requesting Photo ID.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $custom_message Optional custom message.
	 * @return bool
	 */
	private function send_id_request_email( $order, $custom_message = '' ) {
		// Use the email class to send the request
		$email_handler = new Wbcom_PhotoID_Email();
		$result = $email_handler->send_request_email( $order, $custom_message );
		
		// Log that request was sent.
		if ( $result ) {
			$order->add_order_note(
				__( 'Photo ID request email sent to customer.', 'wbcom-photoid' )
			);
			
			// Mark as requested in order meta.
			$order->update_meta_data( 'wbcom_photo_id_requested', current_time( 'mysql' ) );
			$order->update_meta_data( 'wbcom_photo_id_requested_by', get_current_user_id() );
			$order->save();
		}
		
		return $result;
	}

	/**
	 * Display admin notices.
	 */
	public function admin_notices() {
		global $pagenow;
		
		if ( 'edit.php' !== $pagenow || ! isset( $_GET['post_type'] ) || 'shop_order' !== $_GET['post_type'] ) {
			return;
		}
		
		if ( ! empty( $_GET['wbcom_photoid_requested'] ) ) {
			$count = intval( $_GET['wbcom_photoid_requested'] );
			
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p>';
			
			if ( 1 === $count ) {
				echo esc_html__( 'Photo ID request email sent to 1 customer.', 'wbcom-photoid' );
			} else {
				// translators: %d: number of customers.
				echo sprintf( esc_html__( 'Photo ID request emails sent to %d customers.', 'wbcom-photoid' ), $count );
			}
			
			echo '</p>';
			echo '</div>';
		}
	}
    
    /**
     * Secure preview endpoint for admins only.
     */
    public function secure_preview_photo_id() {
        // Verify nonce.
        if ( ! isset( $_GET['_wpnonce'] ) || ! isset( $_GET['order_id'] ) ) {
            wp_die( esc_html__( 'Invalid request', 'wbcom-photoid' ) );
        }
        
        $order_id = absint( $_GET['order_id'] );
        
        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'preview_photo_id_' . $order_id ) ) {
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
        $mime = $order->get_meta( 'wbcom_photo_id_mime' );
        
        if ( ! $path || ! file_exists( $path ) ) {
            wp_die( esc_html__( 'File not found', 'wbcom-photoid' ) );
        }
        
        // Set proper content type for images
        if ( ! empty( $mime ) ) {
            header( 'Content-Type: ' . $mime );
        } else {
            // Default to jpeg if mime type is unknown
            header( 'Content-Type: image/jpeg' );
        }
        
        // Prevent caching
        header( 'Cache-Control: private, no-cache, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        
        // Log this access if logging is enabled
        if ( 'yes' === get_option( 'wbcom_photoid_log_access', 'yes' ) ) {
            $user = get_user_by( 'id', get_current_user_id() );
            $username = $user ? $user->display_name : __( 'Unknown user', 'wbcom-photoid' );
            
            $note = sprintf(
                /* translators: %1$s: user name, %2$s: date/time */
                __( 'Photo ID previewed by %1$s on %2$s', 'wbcom-photoid' ),
                $username,
                date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
            );
            
            $order->add_order_note( $note );
        }
        
        // Output the image
        readfile( $path );
        exit;
    }
}

// Initialize admin class.
return new Wbcom_PhotoID_Admin();