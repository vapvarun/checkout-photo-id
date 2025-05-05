<?php
/**
 * Email Class
 *
 * Handles all email functionality for the Photo ID Upload plugin.
 *
 * @package Wbcom_Checkout_Photo_ID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wbcom_PhotoID_Email class.
 */
class Wbcom_PhotoID_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register custom email templates.
		add_filter( 'woocommerce_email_classes', array( $this, 'register_email_classes' ) );
		
		// Add endpoints for requesting ID via AJAX.
		add_action( 'wp_ajax_wbcom_photoid_request', array( $this, 'handle_request_ajax' ) );
		
		// Handle sending emails.
		add_action( 'wbcom_photoid_send_request_email', array( $this, 'send_request_email' ), 10, 2 );
		add_action( 'wbcom_photoid_after_save', array( $this, 'send_admin_notification' ), 10, 2 );
		
		// Email content customization.
		add_filter( 'wbcom_photoid_request_email_subject', array( $this, 'get_request_email_subject' ), 10, 2 );
		add_filter( 'wbcom_photoid_request_email_heading', array( $this, 'get_request_email_heading' ), 10, 2 );
		add_filter( 'wbcom_photoid_request_email_content', array( $this, 'get_request_email_content' ), 10, 3 );
	}

	/**
	 * Register email classes.
	 *
	 * @param array $email_classes WooCommerce email classes.
	 * @return array
	 */
	public function register_email_classes( $email_classes ) {
		// Check if WooCommerce email classes are available.
		if ( ! class_exists( 'WC_Email' ) ) {
			return $email_classes; // Return unchanged if WC_Email doesn't exist.
		}
		
		// Register custom email classes, but only if they exist (they're defined outside this class).
		if ( class_exists( 'Wbcom_PhotoID_Request_Email' ) ) {
			$email_classes['Wbcom_PhotoID_Request_Email'] = new Wbcom_PhotoID_Request_Email();
		}
		
		if ( class_exists( 'Wbcom_PhotoID_Admin_Notification' ) ) {
			$email_classes['Wbcom_PhotoID_Admin_Notification'] = new Wbcom_PhotoID_Admin_Notification();
		}
		
		return $email_classes;
	}

	/**
	 * Handle AJAX request for sending ID request email.
	 */
	public function handle_request_ajax() {
		// Check nonce and permissions.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wbcom_photoid_request' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wbcom-photoid' ) ) );
		}
		
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_photo_id' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wbcom-photoid' ) ) );
		}
		
		// Get order and custom message.
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$custom_message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'wbcom-photoid' ) ) );
		}
		
		// Send email.
		$this->send_request_email( $order, $custom_message );
		
		// Add note to the order.
		$order->add_order_note(
			__( 'Photo ID request email sent to customer.', 'wbcom-photoid' )
		);
		
		// Mark as requested in order meta.
		$order->update_meta_data( 'wbcom_photo_id_requested', current_time( 'mysql' ) );
		$order->update_meta_data( 'wbcom_photo_id_requested_by', get_current_user_id() );
		$order->save();
		
		wp_send_json_success( array( 'message' => __( 'Request email sent successfully.', 'wbcom-photoid' ) ) );
	}

	/**
	 * Send ID request email to customer.
	 *
	 * @param WC_Order $order         Order object.
	 * @param string   $custom_message Optional custom message.
	 * @return bool
	 */
	public function send_request_email( $order, $custom_message = '' ) {
		if ( ! $order || ! class_exists( 'WC_Email' ) || ! class_exists( 'Wbcom_PhotoID_Request_Email' ) ) {
			return false;
		}

		// Get WooCommerce mailer.
		$mailer = WC()->mailer();
		
		// Get the email class.
		$email = $mailer->emails['Wbcom_PhotoID_Request_Email'];
		
		// Set custom message if provided.
		if ( ! empty( $custom_message ) ) {
			$email->custom_message = $custom_message;
		}
		
		// Generate upload URL with secure token.
		$token = $this->generate_upload_token( $order );
		$upload_url = add_query_arg(
			array(
				'wbcom_photoid_upload' => 1,
				'order_id'             => $order->get_id(),
				'token'                => $token,
			),
			wc_get_endpoint_url( 'view-order', $order->get_id(), wc_get_page_permalink( 'myaccount' ) )
		);
		
		// Store token in order meta.
		$order->update_meta_data( 'wbcom_photo_id_upload_token', $token );
		$order->update_meta_data( 'wbcom_photo_id_upload_token_expiry', time() + ( 7 * DAY_IN_SECONDS ) );
		$order->save();
		
		// Set upload URL for email.
		$email->upload_url = $upload_url;
		
		// Send the email.
		$result = $email->trigger( $order->get_id() );
		
		return $result;
	}

	/**
	 * Send notification to admin when ID is uploaded.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $path  Path to uploaded file.
	 * @return bool
	 */
	public function send_admin_notification( $order, $path ) {
		// Check if admin notifications are enabled.
		if ( 'no' === get_option( 'wbcom_photoid_admin_notification', 'yes' ) || 
			! class_exists( 'WC_Email' ) || 
			! class_exists( 'Wbcom_PhotoID_Admin_Notification' ) ) {
			return false;
		}
		
		// Get WooCommerce mailer.
		$mailer = WC()->mailer();
		
		// Get the email class.
		$email = $mailer->emails['Wbcom_PhotoID_Admin_Notification'];
		
		// Send the email.
		$result = $email->trigger( $order->get_id() );
		
		return $result;
	}

	/**
	 * Get request email subject.
	 *
	 * @param string   $subject Default subject.
	 * @param WC_Order $order   Order object.
	 * @return string
	 */
	public function get_request_email_subject( $subject, $order ) {
		// Get custom subject from settings or use default.
		$custom_subject = get_option( 'wbcom_photoid_request_subject', '' );
		
		if ( ! empty( $custom_subject ) ) {
			$subject = $this->replace_variables( $custom_subject, $order );
		}
		
		return $subject;
	}

	/**
	 * Get request email heading.
	 *
	 * @param string   $heading Default heading.
	 * @param WC_Order $order   Order object.
	 * @return string
	 */
	public function get_request_email_heading( $heading, $order ) {
		// Get custom heading from settings or use default.
		$custom_heading = get_option( 'wbcom_photoid_request_heading', '' );
		
		if ( ! empty( $custom_heading ) ) {
			$heading = $this->replace_variables( $custom_heading, $order );
		}
		
		return $heading;
	}

	/**
	 * Get request email content.
	 *
	 * @param string   $content        Default content.
	 * @param WC_Order $order          Order object.
	 * @param string   $custom_message Custom message from admin.
	 * @return string
	 */
	public function get_request_email_content( $content, $order, $custom_message ) {
		// Get custom content from settings or use default.
		$custom_content = get_option( 'wbcom_photoid_request_content', '' );
		
		if ( ! empty( $custom_content ) ) {
			$content = $this->replace_variables( $custom_content, $order );
			
			// Add custom message if provided.
			if ( ! empty( $custom_message ) ) {
				$content .= "\n\n" . __( 'Additional note from our team:', 'wbcom-photoid' ) . "\n";
				$content .= $custom_message;
			}
		}
		
		return $content;
	}

	/**
	 * Replace variables in email text.
	 *
	 * @param string   $text  Text with variables.
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	private function replace_variables( $text, $order ) {
		$variables = array(
			'{site_title}'      => get_bloginfo( 'name' ),
			'{order_number}'    => $order->get_order_number(),
			'{customer_name}'   => $order->get_formatted_billing_full_name(),
			'{customer_email}'  => $order->get_billing_email(),
			'{order_date}'      => wc_format_datetime( $order->get_date_created() ),
			'{order_total}'     => $order->get_formatted_order_total(),
		);
		
		return str_replace( array_keys( $variables ), array_values( $variables ), $text );
	}

	/**
	 * Generate secure upload token.
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	private function generate_upload_token( $order ) {
		$key = wp_generate_password( 32, false );
		return wp_hash( $order->get_id() . '|' . $order->get_billing_email() . '|' . $key );
	}
}

// Define the email classes only if WooCommerce is active and the WC_Email class exists
if ( class_exists( 'WC_Email' ) ) {
	/**
	 * Request email class.
	 */
	class Wbcom_PhotoID_Request_Email extends WC_Email {
		/**
		 * Custom message from admin.
		 *
		 * @var string
		 */
		public $custom_message = '';
		
		/**
		 * Upload URL for customer.
		 *
		 * @var string
		 */
		public $upload_url = '';
		
		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id             = 'wbcom_photoid_request';
			$this->customer_email = true;
			$this->title          = __( 'Photo ID Request', 'wbcom-photoid' );
			$this->description    = __( 'This email is sent to customers when an admin requests a photo ID upload.', 'wbcom-photoid' );
			$this->template_html  = 'emails/photoid-request.php';
			$this->template_plain = 'emails/plain/photoid-request.php';
			$this->placeholders   = array(
				'{site_title}'      => $this->get_blogname(),
				'{order_number}'    => '',
				'{customer_name}'   => '',
			);
			
			// Call parent constructor.
			parent::__construct();
			
			// Default subject and heading.
			$this->subject = __( 'Action Required: Please upload your ID for order #{order_number}', 'wbcom-photoid' );
			$this->heading = __( 'Please upload your ID', 'wbcom-photoid' );
		}
		
		/**
		 * Get email subject.
		 *
		 * @return string
		 */
		public function get_subject() {
			return apply_filters( 'wbcom_photoid_request_email_subject', $this->format_string( $this->subject ), $this->object );
		}
		
		/**
		 * Get email heading.
		 *
		 * @return string
		 */
		public function get_heading() {
			return apply_filters( 'wbcom_photoid_request_email_heading', $this->format_string( $this->heading ), $this->object );
		}
		
		/**
		 * Trigger the sending of this email.
		 *
		 * @param int $order_id Order ID.
		 * @return bool
		 */
		public function trigger( $order_id ) {
			$this->setup_locale();
			
			if ( $order_id ) {
				$this->object = wc_get_order( $order_id );
				
				if ( ! $this->object ) {
					return false;
				}
				
				$this->recipient = $this->object->get_billing_email();
				
				$this->placeholders['{order_number}'] = $this->object->get_order_number();
				$this->placeholders['{customer_name}'] = $this->object->get_formatted_billing_full_name();
			}
			
			if ( $this->is_enabled() && $this->get_recipient() ) {
				return $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
			
			$this->restore_locale();
			
			return false;
		}
		
		/**
		 * Get content HTML.
		 *
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				array(
					'order'          => $this->object,
					'email_heading'  => $this->get_heading(),
					'custom_message' => $this->custom_message,
					'upload_url'     => $this->upload_url,
					'sent_to_admin'  => false,
					'plain_text'     => false,
					'email'          => $this,
				)
			);
		}
		
		/**
		 * Get content plain.
		 *
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				array(
					'order'          => $this->object,
					'email_heading'  => $this->get_heading(),
					'custom_message' => $this->custom_message,
					'upload_url'     => $this->upload_url,
					'sent_to_admin'  => false,
					'plain_text'     => true,
					'email'          => $this,
				)
			);
		}
	}

	/**
	 * Admin notification email class.
	 */
	class Wbcom_PhotoID_Admin_Notification extends WC_Email {
		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id             = 'wbcom_photoid_admin_notification';
			$this->title          = __( 'Photo ID Uploaded', 'wbcom-photoid' );
			$this->description    = __( 'This email is sent to admins when a customer uploads a photo ID.', 'wbcom-photoid' );
			$this->template_html  = 'emails/photoid-admin-notification.php';
			$this->template_plain = 'emails/plain/photoid-admin-notification.php';
			$this->placeholders   = array(
				'{site_title}'      => $this->get_blogname(),
				'{order_number}'    => '',
				'{customer_name}'   => '',
			);
			
			// Set default recipient to admin email.
			$this->recipient = get_option( 'admin_email' );
			
			// Call parent constructor.
			parent::__construct();
			
			// Default subject and heading.
			$this->subject = __( 'Photo ID Uploaded for Order #{order_number}', 'wbcom-photoid' );
			$this->heading = __( 'Photo ID Uploaded', 'wbcom-photoid' );
		}
		
		/**
		 * Trigger the sending of this email.
		 *
		 * @param int $order_id Order ID.
		 * @return bool
		 */
		public function trigger( $order_id ) {
			$this->setup_locale();
			
			if ( $order_id ) {
				$this->object = wc_get_order( $order_id );
				
				if ( ! $this->object ) {
					return false;
				}
				
				$this->placeholders['{order_number}'] = $this->object->get_order_number();
				$this->placeholders['{customer_name}'] = $this->object->get_formatted_billing_full_name();
			}
			
			if ( $this->is_enabled() && $this->get_recipient() ) {
				return $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
			
			$this->restore_locale();
			
			return false;
		}
		
		/**
		 * Get content HTML.
		 *
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				array(
					'order'         => $this->object,
					'email_heading' => $this->get_heading(),
					'sent_to_admin' => true,
					'plain_text'    => false,
					'email'         => $this,
				)
			);
		}
		
		/**
		 * Get content plain.
		 *
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				array(
					'order'         => $this->object,
					'email_heading' => $this->get_heading(),
					'sent_to_admin' => true,
					'plain_text'    => true,
					'email'         => $this,
				)
			);
		}
	}
}