<?php
/**
 * Photo ID Request email (HTML)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/photoid-request.php.
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

<p><?php printf( esc_html__( 'Hello %s,', 'wbcom-photoid' ), esc_html( $order->get_billing_first_name() ) ); ?></p>

<p><?php printf( esc_html__( 'We need you to upload a photo ID for your recent order #%s.', 'wbcom-photoid' ), esc_html( $order->get_order_number() ) ); ?></p>

<p><?php esc_html_e( 'This is required to verify your identity and complete the processing of your order.', 'wbcom-photoid' ); ?></p>

<?php if ( ! empty( $custom_message ) ) : ?>
<p>
	<strong><?php esc_html_e( 'Additional note from our team:', 'wbcom-photoid' ); ?></strong><br>
	<?php echo esc_html( $custom_message ); ?>
</p>
<?php endif; ?>

<p>
	<a class="button" href="<?php echo esc_url( $upload_url ); ?>"><?php esc_html_e( 'Upload Photo ID', 'wbcom-photoid' ); ?></a>
</p>

<p><?php esc_html_e( 'If the button above doesn\'t work, you can upload your ID by logging into your account and viewing the order details.', 'wbcom-photoid' ); ?></p>

<h2><?php esc_html_e( 'Order details', 'wbcom-photoid' ); ?></h2>

<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; margin-bottom: 1em; border: 1px solid #e5e5e5;">
	<thead>
		<tr>
			<th class="td" scope="col" style="text-align:left;"><?php esc_html_e( 'Product', 'wbcom-photoid' ); ?></th>
			<th class="td" scope="col" style="text-align:left;"><?php esc_html_e( 'Quantity', 'wbcom-photoid' ); ?></th>
			<th class="td" scope="col" style="text-align:left;"><?php esc_html_e( 'Price', 'wbcom-photoid' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		echo wc_get_email_order_items( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$order,
			array(
				'show_sku'      => false,
				'show_image'    => false,
				'image_size'    => array( 32, 32 ),
				'plain_text'    => false,
				'sent_to_admin' => false,
			)
		);
		?>
	</tbody>
	<tfoot>
		<?php
		$totals = $order->get_order_item_totals();

		if ( $totals ) {
			$i = 0;
			foreach ( $totals as $total ) {
				$i++;
				?>
				<tr>
					<th class="td" scope="row" colspan="2" style="text-align:left;"><?php echo esc_html( $total['label'] ); ?></th>
					<td class="td" style="text-align:left;"><?php echo wp_kses_post( $total['value'] ); ?></td>
				</tr>
				<?php
			}
		}
		?>
	</tfoot>
</table>

<p><?php esc_html_e( 'Thank you for your cooperation.', 'wbcom-photoid' ); ?></p>

<?php
/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );