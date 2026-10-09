<?php defined( 'ABSPATH' ) || exit; ?>
<p>
	<?php if ( !empty( $data[ 'number' ] ) && is_array( $data ) ): ?>
		<a target="_blank" href="<?php echo esc_url( $data['link'] ); ?>">
			<?php echo wp_kses_post( $data['courier'] . ': ' . $data['number'] ) ?>
		</a>
	<?php elseif( empty( $data[ 'number' ] ) && !empty( $data['items'] ) ): ?>
		<?php foreach ( $data['items'] as $nashenski_label ): ?>
			<a target="_blank" href="<?php echo esc_url( $nashenski_label['link'] ) ?>">
				<?php echo esc_html( $data['courier'] . ': ' . $nashenski_label['number'] ) ?>
			</a>
		<?php endforeach ?>
	<?php else: ?>
		<a 
			class="nashenski--generate-label" 
			href="#" 
			data-method="<?php echo esc_attr( $data['method'] ) ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'nashenski_admin_label' ) ) ?>" 
			data-orderid="<?php echo esc_attr( $order->get_id() ) ?>"
		>
			<?php echo esc_html( $data['courier'] . ': ' . __( 'Generate label', 'nashenski-commerce' ) ); ?>
		</a>
	<?php endif ?>
</p>