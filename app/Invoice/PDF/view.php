<?php 
defined( 'ABSPATH' ) || exit;
$nashenski_wrap = apply_filters( 'nashenski/invoice/pdf/default_template/product_name_word_wrap_length', 80, $this );
?>
<!DOCTYPE html>
<html>
<head>
	<title><?php echo esc_html( $this->document->title ) ?></title>
<style type="text/css">
	h1 { font-size:20px; }
	.va-top { vertical-align:top; }
	.m-0 { margin: 0px; }
	.p-0 { padding: 0px; }
	.fz-12 { font-size: 12px; }
	.pt-5 { padding-top:5px; }
	.mt-10 { margin-top:10px; }
	.text-center { text-align:center !important; }
	.text-right { text-align:right !important; }
	.w-100 { width: 100%; }
	.w-90 { width:90%; }
	.w-85 { width:85%; }
	.w-80 { width:80%; }
	.w-75 { width:75%; }
	.w-70 { width:70%; }
	.w-65 { width:65%; }
	.w-60 { width:60%; }
	.w-55 { width:55%; }
	.w-50 { width:50%; }
	.w-45 { width:45%; }
	.w-40 { width:40%; }
	.w-35 { width:35%; }
	.w-30 { width:30%; }
	.w-25 { width:25%; }
	.w-20 { width:20%; }
	.w-15 { width:15%; }
	.w-10 { width:10%; }
	.logo .site-logo { margin-bottom:6px; }
	.site-details p { margin:0; padding:0; font-size:11px; line-height:14px; color:#5D5D5D; }
	.site-details .site-details__name { font-size:13px; font-weight:bold; color:#000; }
	.qr { margin-top:8px; }
	.qr img { width:110px; height:110px; }
	.logo span { margin-left:8px; top:19px; position: absolute; font-weight: bold; font-size:25px; }
	.gray-color { color:#5D5D5D; }
	.text-bold { font-weight: bold; }
	.border { border:1px solid black; }
	.includes_tax { display: none!important; }
	table tr,th,td { border: 1px solid #d2d2d2; border-collapse:collapse; padding:7px 8px; }
	table tr th { background: #F4F4F4; font-size:13px; }
	table tr td { font-size:10px; }
	table { border-collapse:collapse; }
	.float-left { float:left; }
	footer { font-size: 12px; position: fixed; bottom: -40px; left: 0px; right: 0px; height: 50px; }
	footer p { display: inline-block; }
	.page-number:before { content: counter(page); }
	.page-count { float:right }
	/* Long invoices: the product table header repeats on each page and a row is not split. */
	thead { display: table-header-group; }
	tr { page-break-inside: avoid; }

	<?php echo \Nashenski\Invoice\PDF\Style::css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated hex colours and a fixed font list. ?>

	<?php do_action( 'nashenski/invoice/pdf/default_template/additional_css', $this ) ?>
</style>
</head>
<body>
	<div class="add-detail">
		<div class="w-45 float-left logo">
			<?php $this->document->render_pdf_logo(); ?>
		</div>
		
		<div class="w-55 float-left text-right">
			<h1 class="m-0 p-0"><?php echo esc_html( $this->document->title ) ?></h1>

			<?php if ( !empty( $this->document->get_head_items() ) ): ?>
				<?php foreach ( $this->document->get_head_items() as $nashenski_head_item ): if ( '' === trim( (string) $nashenski_head_item['value'] ) ) { continue; } ?>
					<p class="m-0 pt-0 fz-12 text-bold w-100">
						<?php printf('%s: <span class="gray-color">%s</span>', esc_html( $nashenski_head_item['label'] ), esc_html( $nashenski_head_item['value'] ) ) ?>
					</p>
				<?php endforeach ?>
			<?php endif ?>

			<?php $this->document->render_pdf_qr(); ?>
		</div>

		<div style="clear: both;"></div>
	</div>

	<?php // dompdf draws a fixed footer only from the page it is declared on, so it stays on page 1; after the clear, which would also clear its floated page count. ?>
	<footer>
		<?php echo esc_html( get_bloginfo( 'name' ) ) ?>

		<span class="page-count text-right">
			<?php /* translators: 1: page number, 2: number of pages */ printf( esc_html__('Page %1$s of %2$s', 'nashenski-commerce'), '<span class="page-number"></span>', '%PC%' ) ?>
		</span>
	</footer>

	<div class="w-100 mt-10">
		<table class="w-100 mt-10">
			<tr>
				<th class="w-50"><?php esc_html_e( 'Billing from', 'nashenski-commerce' ) ?></th>
				<th class="w-50"><?php esc_html_e( 'Billing to', 'nashenski-commerce' ) ?></th>
			</tr>

			<tr class="va-top">
				<td>
					<div class="box-text"><?php echo wp_kses_post( implode( '<br>', $this->document->get_from_items() ) ); ?></div>
				</td>

				<td>
					<div class="box-text"><?php echo wp_kses_post( implode( '<br>', $this->document->get_to_items() ) ); ?></div>
				</td>
			</tr>
		</table>
	</div>

	<table class="w-100 mt-10">
		<?php $nashenski_headers = $this->document->get_cart_headers(); ?>

		<thead>
		<tr>
			<?php foreach ( $nashenski_headers as $nashenski_item ): ?>
				<th class="<?php echo esc_attr( $nashenski_item['class'] ) ?>"><?php echo wp_kses_post( $nashenski_item['label'] ) ?></th>
			<?php endforeach ?>
		</tr>
		</thead>

		<tbody>
		<?php foreach ( $this->document->order->get_items() as $nashenski_item ): ?>
			<tr>
				<?php foreach ( $nashenski_item as $nashenski_key => $nashenski_col ): ?>
					<td <?php echo ( $nashenski_key !== 'name' ) ? 'align="center"' : '' ?>><?php echo wp_kses_post( wordwrap( wp_strip_all_tags( $nashenski_col ), $nashenski_wrap, "<br>" ) ) ?></td>
				<?php endforeach ?>
			</tr>
		<?php endforeach ?>
		</tbody>
	</table>

	<div class="w-100">
		<div class="fz-12">
			<div align="right">
				<?php 
				foreach ( $this->document->order->get_total_items() as $nashenski_item ) {
					?>
					<p style="line-height: 8px">
						<?php echo wp_kses_post( '<strong>' . $nashenski_item['label'] . '</strong> ' . wp_strip_all_tags( $nashenski_item['value'] ) ); ?>
					</p>
					<?php 
				}
				?>
			</div>
		</div> 
	</div>

	<?php do_action( 'nashenski/invoice/pdf/default_template/after_table', $this->document->order->woo_order, $this ); ?>

	<div class="w-100 mt-10">
		<table class="w-100 mt-10">
			<tr>
				<?php foreach ( $this->document->get_additional_items_labels() as $nashenski_label ): ?>
					<th class="w-50"><?php echo wp_kses_post( $nashenski_label ) ?></th>
				<?php endforeach ?>
			</tr>

			<tr class="text-center">
				<?php foreach ( $this->document->get_additional_items() as $nashenski_value ): ?>
					<td><?php echo wp_kses_post( $nashenski_value ) ?></td>
				<?php endforeach ?>
			</tr>
		</table>
	</div>

	<?php 
	if ( $this->document->footer_text ) {
		echo wp_kses_post( wpautop( '<span class="gray-color fz-12">' . $this->document->footer_text . '</span>' ) );
	}
	?>
</body>
</html>