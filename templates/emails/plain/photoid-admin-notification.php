<?php
/**
 * Photo ID Admin Notification email (Plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/photoid-admin-notification.php.
 *
 * @package Wbcom_Checkout_Photo_ID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo "= " . esc_html( $email_heading ) . " =\n\n";

echo esc_html__( 'A customer has uploaded a photo ID for an order.', 'wbcom-photoid' ) . "\n\n";

echo sprintf( esc_html__( 'Order: #%s', 'wbcom-photoid' ), esc_html( $order->get_order_number() ) ) . "\n";

echo sprintf( esc_html__( 'Customer: %s', 'wbcom-photoid' ), esc_html( $order->get_formatted_billing_full_name() ) ) . "\n";

echo sprintf( esc_html__( 'Email: %s', 'wbcom-photoid' ), esc_html( $order->get_billing_email() ) ) . "\n\n";

echo esc_html__( 'You can view this ID by going to the order details screen in the admin dashboard.', 'wbcom-photoid' ) . "\n";

echo esc_url( admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) ) . "\n\n";

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );