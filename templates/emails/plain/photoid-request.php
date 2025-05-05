<?php
/**
 * Photo ID Request email (Plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/photoid-request.php.
 *
 * @package Wbcom_Checkout_Photo_ID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo "= " . esc_html( $email_heading ) . " =\n\n";

echo sprintf( esc_html__( 'Hello %s,', 'wbcom-photoid' ), esc_html( $order->get_billing_first_name() ) ) . "\n\n";

echo sprintf( esc_html__( 'We need you to upload a photo ID for your recent order #%s.', 'wbcom-photoid' ), esc_html( $order->get_order_number() ) ) . "\n\n";

echo esc_html__( 'This is required to verify your identity and complete the processing of your order.', 'wbcom-photoid' ) . "\n\n";

if ( ! empty( $custom_message ) ) {
	echo esc_html__( 'Additional note from our team:', 'wbcom-photoid' ) . "\n";
	echo esc_html( $custom_message ) . "\n\n";
}

echo esc_html__( 'Please upload your ID by visiting this link:', 'wbcom-photoid' ) . "\n";
echo esc_url( $upload_url ) . "\n\n";

echo esc_html__( 'If the link above doesn\'t work, you can upload your ID by logging into your account and viewing the order details.', 'wbcom-photoid' ) . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html__( 'Order details', 'wbcom-photoid' ) . "\n\n";

echo wc_get_email_order_items( $order, array(
	'show_sku'      => false,
	'show_image'    => false,
	'image_size'    => array( 32, 32 ),
	'plain_text'    => true,
	'sent_to_admin' => false,
) );

echo "\n";

$totals = $order->get_order_item_totals();
if ( $totals ) {
	foreach ( $totals as $total ) {
		echo esc_html( $total['label'] ) . "\t " . wp_kses_post( $total['value'] ) . "\n";
	}
}

echo "\n" . esc_html__( 'Thank you for your cooperation.', 'wbcom-photoid' ) . "\n\n";

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );