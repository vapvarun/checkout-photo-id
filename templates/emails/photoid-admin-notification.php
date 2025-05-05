<?php
/**
 * Photo ID Admin Notification email (HTML)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/photoid-admin-notification.php.
 *
 * @package Wbcom_Checkout_Photo_ID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php esc_html_e( 'A customer has uploaded a photo ID for an order.', 'wbcom-photoid' ); ?></p>

<p><?php printf( esc_html__( 'Order: #%s', 'wbcom-photoid' ), esc_html( $order->get_order_number() ) ); ?></p>

<p><?php printf( esc_html__( 'Customer: %s', 'wbcom-photoid' ), esc_html( $order->get_formatted_billing_full_name() ) ); ?></p>

<p><?php printf( esc_html__( 'Email: %s', 'wbcom-photoid' ), esc_html( $order->get_billing_email() ) ); ?></p>

<p><?php esc_html_e( 'You can view this ID by going to the order details screen in the admin dashboard.', 'wbcom-photoid' ); ?></p>

<p>
	<a class="button" href="<?php echo esc_url( admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) ); ?>"><?php esc_html_e( 'View Order', 'wbcom-photoid' ); ?></a>
</p>

<?php
/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );