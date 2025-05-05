<?php
/**
 * Privacy Class
 *
 * Handles GDPR compliance and privacy concerns.
 *
 * @package Wbcom_Checkout_Photo_ID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wbcom_PhotoID_Privacy class.
 */
class Wbcom_PhotoID_Privacy {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register with WP Privacy Policy features.
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		
		// Add privacy-related hooks.
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_erasers' ) );
		
		// File retention schedule.
		add_action( 'init', array( $this, 'register_cleanup_schedule' ) );
		add_action( 'wbcom_photoid_cleanup_files', array( $this, 'cleanup_old_files' ) );
	}

	/**
	 * Add privacy policy content for the privacy policy page.
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			'<h2>%s</h2><p>%s</p><p>%s</p><p>%s</p><p>%s</p>',
			__( 'Photo ID Upload', 'wbcom-photoid' ),
			__( 'When you place an order on our site that requires identity verification, you will be asked to upload a photo ID. This is necessary for us to verify your identity and comply with regulatory requirements.', 'wbcom-photoid' ),
			__( 'Your uploaded ID is stored securely in a protected directory on our server. Access to these files is strictly limited to authorized administrators.', 'wbcom-photoid' ),
			sprintf(
				/* translators: %d: number of days */
				__( 'We retain these files for a maximum of %d days, after which they are automatically deleted from our system. You may request earlier deletion by contacting us.', 'wbcom-photoid' ),
				absint( get_option( 'wbcom_photoid_retention_days', 90 ) )
			),
			__( 'When administrators access your ID file, this action is logged for security purposes.', 'wbcom-photoid' )
		);

		wp_add_privacy_policy_content( 'Wbcom Checkout Photo ID Upload', wp_kses_post( $content ) );
	}

	/**
	 * Register data exporters.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public function register_exporters( $exporters ) {
		$exporters['wbcom-photo-id'] = array(
			'exporter_friendly_name' => __( 'Photo ID Data', 'wbcom-photoid' ),
			'callback'               => array( $this, 'photo_id_data_exporter' ),
		);
		return $exporters;
	}

	/**
	 * Register data erasers.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public function register_erasers( $erasers ) {
		$erasers['wbcom-photo-id'] = array(
			'eraser_friendly_name' => __( 'Photo ID Data', 'wbcom-photoid' ),
			'callback'             => array( $this, 'photo_id_data_eraser' ),
		);
		return $erasers;
	}

	/**
	 * Export photo ID data for a user.
	 *
	 * @param string $email_address User email address.
	 * @param int    $page Page number.
	 * @return array
	 */
	public function photo_id_data_exporter( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$data = array();
		$orders = wc_get_orders( array(
			'customer_id' => $user->ID,
			'limit'       => -1,
		) );

		foreach ( $orders as $order ) {
			$photo_id_path = $order->get_meta( 'wbcom_photo_id_path' );
			$photo_id_filename = $order->get_meta( 'wbcom_photo_id_filename' );
			$upload_date = $order->get_meta( 'wbcom_photo_id_upload_date' );
			
			if ( $photo_id_filename ) {
				$data[] = array(
					'group_id'    => 'wbcom_photo_id',
					'group_label' => __( 'Photo ID Files', 'wbcom-photoid' ),
					'item_id'     => 'order-' . $order->get_id(),
					'data'        => array(
						array(
							'name'  => __( 'Order', 'wbcom-photoid' ),
							'value' => '#' . $order->get_order_number(),
						),
						array(
							'name'  => __( 'ID Filename', 'wbcom-photoid' ),
							'value' => $photo_id_filename,
						),
						array(
							'name'  => __( 'Upload Date', 'wbcom-photoid' ),
							'value' => $upload_date ? date_i18n( get_option( 'date_format' ), strtotime( $upload_date ) ) : '',
						),
					),
				);
			}
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erase photo ID data for a user.
	 *
	 * @param string $email_address User email address.
	 * @param int    $page Page number.
	 * @return array
	 */
	public function photo_id_data_eraser( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$items_removed = false;
		$items_retained = false;
		$messages = array();
		
		$orders = wc_get_orders( array(
			'customer_id' => $user->ID,
			'limit'       => -1,
		) );

		foreach ( $orders as $order ) {
			$photo_id_path = $order->get_meta( 'wbcom_photo_id_path' );
			
			if ( $photo_id_path && file_exists( $photo_id_path ) ) {
				// Check if order is completed and within retention period.
				$order_completed = $order->get_status() === 'completed';
				$order_date = $order->get_date_completed() ? $order->get_date_completed()->getTimestamp() : 0;
				$retention_days = absint( get_option( 'wbcom_photoid_retention_days', 90 ) );
				$retention_seconds = $retention_days * DAY_IN_SECONDS;
				$within_retention = $retention_days === 0 || ( time() - $order_date ) < $retention_seconds;
				
				// Determine if we can delete the file.
				if ( ! $order_completed || ! $within_retention ) {
					// Safe to delete.
					if ( @unlink( $photo_id_path ) ) {
						$order->delete_meta_data( 'wbcom_photo_id_path' );
						$order->delete_meta_data( 'wbcom_photo_id_filename' );
						$order->delete_meta_data( 'wbcom_photo_id_upload_date' );
						$order->delete_meta_data( 'wbcom_photo_id_filesize' );
						$order->delete_meta_data( 'wbcom_photo_id_mime' );
						$order->save();
						
						$items_removed = true;
					} else {
						$messages[] = sprintf(
							/* translators: %d: order ID */
							__( 'Failed to remove photo ID for order #%d.', 'wbcom-photoid' ),
							$order->get_id()
						);
						$items_retained = true;
					}
				} else {
					// Need to retain for now.
					$messages[] = sprintf(
						/* translators: %d: order ID */
						__( 'Photo ID for order #%d has been retained for compliance purposes.', 'wbcom-photoid' ),
						$order->get_id()
					);
					$items_retained = true;
				}
			}
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Register cleanup schedule.
	 */
	public function register_cleanup_schedule() {
		if ( ! wp_next_scheduled( 'wbcom_photoid_cleanup_files' ) ) {
			wp_schedule_event( time(), 'daily', 'wbcom_photoid_cleanup_files' );
		}
	}

	/**
	 * Clean up old files based on retention period.
	 */
	public function cleanup_old_files() {
		$retention_days = absint( get_option( 'wbcom_photoid_retention_days', 90 ) );
		
		// Skip if retention is set to keep indefinitely.
		if ( $retention_days === 0 ) {
			return;
		}
		
		$cutoff_time = time() - ( $retention_days * DAY_IN_SECONDS );
		
		// Get orders with photo IDs.
		$orders = wc_get_orders( array(
			'limit'  => -1,
			'meta_key' => 'wbcom_photo_id_upload_date',
		) );
		
		foreach ( $orders as $order ) {
			$upload_date = $order->get_meta( 'wbcom_photo_id_upload_date' );
			$photo_id_path = $order->get_meta( 'wbcom_photo_id_path' );
			
			if ( $upload_date && $photo_id_path && file_exists( $photo_id_path ) ) {
				$upload_time = strtotime( $upload_date );
				
				if ( $upload_time < $cutoff_time ) {
					// File is older than retention period, delete it.
					if ( @unlink( $photo_id_path ) ) {
						// Add note to order.
						$order->add_order_note(
							sprintf(
								/* translators: %d: number of days */
								__( 'Photo ID file deleted after %d days retention period.', 'wbcom-photoid' ),
								$retention_days
							)
						);
						
						// Remove meta data.
						$order->delete_meta_data( 'wbcom_photo_id_path' );
						$order->delete_meta_data( 'wbcom_photo_id_filename' );
						$order->delete_meta_data( 'wbcom_photo_id_upload_date' );
						$order->delete_meta_data( 'wbcom_photo_id_filesize' );
						$order->delete_meta_data( 'wbcom_photo_id_mime' );
						$order->save();
						
						// Log action.
						if ( function_exists( 'wc_get_logger' ) ) {
							$logger = wc_get_logger();
							$logger->info(
								sprintf(
									/* translators: %1$d: order ID, %2$s: file path */
									__( 'Deleted photo ID for order #%1$d: %2$s', 'wbcom-photoid' ),
									$order->get_id(),
									$photo_id_path
								),
								array( 'source' => 'wbcom-photoid' )
							);
						}
					}
				}
			}
		}
		
		// Hook for additional cleanup actions.
		do_action( 'wbcom_photoid_after_cleanup' );
	}
}

// Initialize privacy class.
return new Wbcom_PhotoID_Privacy();