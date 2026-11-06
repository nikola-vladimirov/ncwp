<?php
/**
 * Quick order.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Front_End;

use Nashenski\Abandoned_Carts\Tracker;

defined( 'ABSPATH' ) || exit;

/**
 * Quick order: a "Quick order" button on the product page and/or the cart opens a short form. The
 * form is loaded by a request, so cached pages still show a fresh nonce. The submission becomes a
 * WooCommerce order with the "Quick order" status. That status reserves no stock and sends no payment
 * link; the owner confirms it by changing the status to "Pending payment" and sending the customer
 * invoice, which carries the payment link.
 *
 * The owner picks a ready-made field template (or builds a custom one), how the form appears
 * (dialog, inline under the button, side panel) and the phone format the form expects. The phone is
 * validated on the server and shaped by a small mask in the browser: Bulgarian numbers only, local
 * or international.
 */
class Quick_Order {
	/** Order status key without the "wc-" prefix (WooCommerce adds it). */
	const STATUS = 'nash-quick';

	const NONCE = 'nashenski_quick_order';

	/** Submissions per IP address in the window below. */
	const RATE_LIMIT  = 5;
	const RATE_WINDOW = 600;

	/** Order meta: when the shopper gave consent to be contacted. */
	const META_CONSENT = '_nashenski_quick_order_consent';

	/** Placement ids; the translated labels come from placements(), which must not run before init. */
	const PLACEMENT_IDS = array( 'product', 'cart', 'both' );

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register_status' ) );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'add_status' ) );
		// Quick orders wait for the owner, so they count in the Orders menu bubble with the processing ones.
		add_filter( 'woocommerce_menu_order_count', array( __CLASS__, 'menu_count' ) );

		if ( ! self::enabled() ) {
			return;
		}

		$placement = nashenski_get_choice( 'quick_order', 'placement', array_flip( self::PLACEMENT_IDS ) );

		if ( in_array( $placement, array( 'product', 'both' ), true ) ) {
			add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'button' ) );
		}

		if ( in_array( $placement, array( 'cart', 'both' ), true ) ) {
			add_action( 'woocommerce_after_cart_totals', array( __CLASS__, 'button' ) );
		}

		add_action( 'wp_ajax_nashenski_quick_order_form', array( __CLASS__, 'form_ajax' ) );
		add_action( 'wp_ajax_nopriv_nashenski_quick_order_form', array( __CLASS__, 'form_ajax' ) );
		add_action( 'wp_ajax_nashenski_quick_order_submit', array( __CLASS__, 'submit_ajax' ) );
		add_action( 'wp_ajax_nopriv_nashenski_quick_order_submit', array( __CLASS__, 'submit_ajax' ) );
	}

	/**
	 * Enabled.
	 *
	 * @return string Result.
	 */
	public static function enabled() {
		return 'yes' === nashenski_get_option( 'apis', 'enable_quick_order' );
	}

	/**
	 * Placements.
	 *
	 * @return array Result.
	 */
	public static function placements() {
		return array(
			'product' => __( 'Product page', 'nashenski-commerce' ),
			'cart'    => __( 'Cart', 'nashenski-commerce' ),
			'both'    => __( 'Product page and cart', 'nashenski-commerce' ),
		);
	}

	/**
	 * How the form appears when the button is pressed.
	 */
	public static function appearances() {
		return array(
			'modal'  => __( 'Dialog window (a pop-up in the middle)', 'nashenski-commerce' ),
			'inline' => __( 'Inline, under the button', 'nashenski-commerce' ),
			'side'   => __( 'Sliding panel from the right', 'nashenski-commerce' ),
		);
	}

	/**
	 * Ready-made field templates. "Custom" leaves the field list to the per-field toggles.
	 */
	public static function templates() {
		$all = array_keys( self::optional_fields() );

		return array(
			'minimal'  => array(
				'label'  => __( 'Minimal (name, phone, email)', 'nashenski-commerce' ),
				'fields' => array(),
			),
			'callback' => array(
				'label'  => __( 'Callback request', 'nashenski-commerce' ),
				'fields' => array( 'preferred_time', 'message' ),
			),
			'standard' => array(
				'label'  => __( 'Standard', 'nashenski-commerce' ),
				'fields' => array( 'town', 'address', 'quantity' ),
			),
			'full'     => array(
				'label'  => __( 'Full order (every field)', 'nashenski-commerce' ),
				'fields' => $all,
			),
			'custom'   => array(
				'label'  => __( 'Custom (choose the fields below)', 'nashenski-commerce' ),
				'fields' => null,
			),
		);
	}

	/**
	 * The phone formats the form can ask for. Both end in the same canonical value.
	 */
	public static function phone_formats() {
		return array(
			'local'         => __( 'National (0888 123 456)', 'nashenski-commerce' ),
			'international' => __( 'International (+359 88 123 4567)', 'nashenski-commerce' ),
		);
	}

	/**
	 * Phone format.
	 *
	 * @return mixed Result.
	 */
	public static function phone_format() {
		return nashenski_get_choice( 'quick_order', 'phone_format', self::phone_formats() );
	}

	/**
	 * Dial codes for the countries the form can be set to. A country not in the table is still
	 * accepted, it is only checked by length.
	 */
	public static function dial_codes() {
		return array(
			'AL' => '355',
			'AT' => '43',
			'AU' => '61',
			'BE' => '32',
			'BG' => '359',
			'BR' => '55',
			'CA' => '1',
			'CH' => '41',
			'CY' => '357',
			'CZ' => '420',
			'DE' => '49',
			'DK' => '45',
			'EE' => '372',
			'ES' => '34',
			'FI' => '358',
			'FR' => '33',
			'GB' => '44',
			'GR' => '30',
			'HR' => '385',
			'HU' => '36',
			'IE' => '353',
			'IL' => '972',
			'IN' => '91',
			'IS' => '354',
			'IT' => '39',
			'JP' => '81',
			'LT' => '370',
			'LU' => '352',
			'LV' => '371',
			'MT' => '356',
			'MX' => '52',
			'NL' => '31',
			'NO' => '47',
			'NZ' => '64',
			'PL' => '48',
			'PT' => '351',
			'RO' => '40',
			'RS' => '381',
			'SE' => '46',
			'SI' => '386',
			'SK' => '421',
			'TR' => '90',
			'US' => '1',
			'ZA' => '27',
		);
	}

	/**
	 * The country the phone numbers are in: the saved setting, else the shop country, else Bulgaria.
	 */
	public static function phone_country() {
		$saved = nashenski_get_option( 'quick_order', 'phone_country' );
		$codes = self::dial_codes();

		if ( $saved && isset( $codes[ $saved ] ) ) {
			return $saved;
		}

		if ( function_exists( 'wc_get_base_location' ) ) {
			$base = (string) wc_get_base_location()['country'];
			if ( $base && isset( $codes[ $base ] ) ) {
				return $base;
			}
		}

		return 'BG';
	}

	/**
	 * The dial code of the chosen country, without the plus, or '' when it is not known.
	 */
	public static function phone_dial() {
		$codes = self::dial_codes();
		$iso   = self::phone_country();

		return isset( $codes[ $iso ] ) ? $codes[ $iso ] : '';
	}

	/**
	 * Optional form fields the owner can turn on. Name and phone and email are always there.
	 */
	public static function optional_fields() {
		return array(
			'address'        => __( 'Address', 'nashenski-commerce' ),
			'town'           => __( 'Town', 'nashenski-commerce' ),
			'quantity'       => __( 'Quantity', 'nashenski-commerce' ),
			'product_notes'  => __( 'Product notes', 'nashenski-commerce' ),
			'preferred_time' => __( 'Preferred delivery time', 'nashenski-commerce' ),
			'message'        => __( 'Message', 'nashenski-commerce' ),
		);
	}

	/**
	 * The optional fields the current template asks for, keyed like optional_fields().
	 */
	public static function active_fields() {
		$templates = self::templates();
		$template  = nashenski_get_choice( 'quick_order', 'template', $templates );
		$all       = self::optional_fields();
		$keys      = $templates[ $template ]['fields'];

		if ( null === $keys ) {
			$keys = array_keys(
				array_filter(
					$all,
					function ( $key ) {
						return 'yes' === nashenski_get_option( 'quick_order', 'field_' . $key );
					},
					ARRAY_FILTER_USE_KEY
				)
			);
		}

		$fields = array();

		foreach ( $keys as $key ) {
			if ( isset( $all[ $key ] ) ) {
				$fields[ $key ] = $all[ $key ];
			}
		}

		return $fields;
	}

	/**
	 * Register status.
	 */
	public static function register_status() {
		register_post_status(
			'wc-' . self::STATUS,
			array(
				'label'                     => _x( 'Quick order', 'Order status', 'nashenski-commerce' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of quick orders */
				'label_count'               => _n_noop( 'Quick order <span class="count">(%s)</span>', 'Quick orders <span class="count">(%s)</span>', 'nashenski-commerce' ),
			)
		);
	}

	/**
	 * Add status.
	 *
	 * @param mixed $statuses Statuses.
	 * @return mixed Result.
	 */
	public static function add_status( $statuses ) {
		$statuses[ 'wc-' . self::STATUS ] = _x( 'Quick order', 'Order status', 'nashenski-commerce' );

		return $statuses;
	}

	/**
	 * Menu count.
	 *
	 * @param mixed $count Count.
	 * @return mixed Result.
	 */
	public static function menu_count( $count ) {
		return (int) $count + wc_orders_count( self::STATUS );
	}

	/**
	 * The button, and the script that opens the form dialog. Shown to every visitor; logged-in
	 * shoppers get the form prefilled from their account.
	 */
	public static function button() {
		$context = is_cart() ? 'cart' : 'product';
		$product = 'product' === $context ? wc_get_product( get_the_ID() ) : null;

		// A variable product needs its variation picked, which the form does not do.
		if ( $product && ! $product->is_type( 'simple' ) ) {
			return;
		}

		// Space it away from the add-to-cart button and quantity above it, so it does not look stuck.
		static $styled = false;
		if ( ! $styled ) {
			$styled = true;
			echo '<style>.nashenski-quick-order-open{display:inline-block;margin-top:12px}</style>';
		}

		printf(
			'<button type="button" class="button nashenski-quick-order-open" data-context="%1$s" data-product-id="%2$d">%3$s</button>',
			esc_attr( $context ),
			absint( $product ? $product->get_id() : 0 ),
			esc_html__( 'Quick order', 'nashenski-commerce' )
		);

		self::enqueue_script();
	}

	/**
	 * Enqueue script.
	 */
	private static function enqueue_script() {
		if ( wp_script_is( 'nashenski-quick-order', 'enqueued' ) ) {
			return;
		}

		wp_register_script( 'nashenski-quick-order', false, array(), \Nashenski\Plugin::VERSION, true );
		wp_enqueue_script( 'nashenski-quick-order' );
		wp_add_inline_script( 'nashenski-quick-order', self::script() );
	}

	/**
	 * Plain script, no build step: a form fetched on each click, shown as chosen, with a light
	 * phone mask that follows the configured Bulgarian format.
	 */
	private static function script() {
		$config = wp_json_encode(
			array(
				'ajax'        => admin_url( 'admin-ajax.php' ),
				'appearance'  => nashenski_get_choice( 'quick_order', 'appearance', self::appearances() ),
				'phoneFormat' => self::phone_format(),
				'phoneDial'   => self::phone_dial(),
				'errorText'   => __( 'Something went wrong. Please try again.', 'nashenski-commerce' ),
				'closeText'   => __( 'Close', 'nashenski-commerce' ),
			)
		);

		return '(function(){var cfg=' . $config . ';
var open=null,box=null;
function css(){
  if(document.getElementById("nashenski-quick-order-css")){return;}
  var s=document.createElement("style");
  s.id="nashenski-quick-order-css";
  s.textContent=".nashenski-quick-order{border:0;border-radius:8px;padding:24px;width:min(440px,92vw);max-height:90vh;overflow:auto;box-shadow:0 10px 40px rgba(0,0,0,.25);background:#fff}.nashenski-quick-order::backdrop{background:rgba(0,0,0,.45)}.nashenski-quick-order label{display:block}.nashenski-quick-order input:not([type=checkbox]),.nashenski-quick-order textarea{width:100%;box-sizing:border-box;margin-top:4px}.nashenski-quick-order h2{margin:0 0 8px}.nashenski-quick-order-actions{display:flex;gap:12px;align-items:center;justify-content:flex-start;margin-top:12px}.nashenski-quick-order--inline{position:static;box-shadow:none;border:1px solid rgba(0,0,0,.12);width:auto;margin:8px 0}.nashenski-quick-order--side{position:fixed;top:0;right:0;height:100%;width:min(440px,92vw);max-height:none;border-radius:0;z-index:100000;overflow:auto}.nashenski-quick-order__backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999}";
  document.head.appendChild(s);
}
function close(){
  if(!box){return;}
  if(typeof box.close==="function"&&box.tagName==="DIALOG"){box.close();}
  if(box._backdrop){box._backdrop.remove();}
  box.remove();box=null;
}
function show(html,trigger){
  close();css();
  var a=cfg.appearance;
  if(a==="modal"){
    box=document.createElement("dialog");box.className="nashenski-quick-order";
    box.addEventListener("click",function(e){if(e.target===box){close();}});
    document.body.appendChild(box);box.innerHTML=html;box.showModal();
  } else if(a==="side"){
    box=document.createElement("div");box.className="nashenski-quick-order nashenski-quick-order--side";
    box._backdrop=document.createElement("div");box._backdrop.className="nashenski-quick-order__backdrop";
    box._backdrop.addEventListener("click",close);
    document.body.appendChild(box._backdrop);document.body.appendChild(box);box.innerHTML=html;
  } else {
    box=document.createElement("div");box.className="nashenski-quick-order nashenski-quick-order--inline";
    box.innerHTML=html;
    if(trigger&&trigger.parentNode){trigger.parentNode.insertBefore(box,trigger.nextSibling);}
    else{document.body.appendChild(box);}
  }
  var actions=box.querySelector(".nashenski-quick-order-actions");
  if(actions){actions.insertAdjacentHTML("beforeend","<button type=\'button\' class=\'button nashenski-quick-order-close\'>"+cfg.closeText+"</button>");}
  else{box.insertAdjacentHTML("beforeend","<p class=\'nashenski-quick-order-actions\'><button type=\'button\' class=\'button nashenski-quick-order-close\'>"+cfg.closeText+"</button></p>");}
  var phone=box.querySelector("[data-nashenski-phone]");
  if(phone){mask(phone);phone.addEventListener("input",function(){mask(phone);});}
}
function mask(input){
  var d=input.value.replace(/[^0-9]/g,""),out="",dial=cfg.phoneDial||"";
  if(cfg.phoneFormat==="international"){
    if(dial&&d.indexOf(dial)!==0){d=(d.charAt(0)==="0")?dial+d.slice(1):dial+d;}
    var nat=d.slice(dial.length,20),parts=nat.match(/\d{1,4}/g)||[];
    out="+"+dial+(parts.length?" "+parts.join(" "):"");
  } else {
    if(d.charAt(0)!=="0"){d="0"+d;}
    var p=d.slice(0,14).match(/\d{1,4}/g)||[];
    out=p.join(" ");
  }
  if(input.value!==out){input.value=out;}
}
document.addEventListener("click",function(e){
  var btn=e.target.closest(".nashenski-quick-order-open");
  if(btn){
    e.preventDefault();open=btn;
    var qty=document.querySelector("form.cart input.qty");
    var body=new URLSearchParams({action:"nashenski_quick_order_form",context:btn.getAttribute("data-context")||"product",product_id:btn.getAttribute("data-product-id")||"0",quantity:qty?qty.value:"1"});
    fetch(cfg.ajax,{method:"POST",credentials:"same-origin",body:body}).then(function(r){return r.text();}).then(function(html){show(html,btn);}).catch(function(){show("<p>"+cfg.errorText+"</p>",btn);});
    return;
  }
  if(e.target.closest(".nashenski-quick-order-close")){close();}
});
document.addEventListener("submit",function(e){
  var form=e.target;
  if(!form.closest||!form.closest(".nashenski-quick-order")){return;}
  e.preventDefault();
  var button=form.querySelector("button[type=submit]");
  if(button){button.disabled=true;}
  fetch(cfg.ajax,{method:"POST",credentials:"same-origin",body:new URLSearchParams(new FormData(form))}).then(function(r){return r.json();}).then(function(res){
    var msg=res&&res.data&&res.data.message?res.data.message:cfg.errorText;
    if(res&&res.success){if(box){box.innerHTML="<p>"+msg+"</p><p><button type=\'button\' class=\'button nashenski-quick-order-close\'>"+cfg.closeText+"</button></p>";}}
    else{if(button){button.disabled=false;}var note=form.querySelector(".nashenski-quick-order-error");if(note){note.textContent=msg;}}
  }).catch(function(){if(button){button.disabled=false;}});
});
})();';
	}

	/**
	 * The form, as an HTML fragment for the dialog.
	 */
	public static function form_ajax() {
		if ( ! self::enabled() ) {
			wp_die( '', '', array( 'response' => 404 ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- read-only: the fragment changes nothing and carries the nonce itself.
		$context    = isset( $_POST['context'] ) && 'cart' === $_POST['context'] ? 'cart' : 'product';
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$quantity   = isset( $_POST['quantity'] ) ? max( 1, absint( $_POST['quantity'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// The fragment is escaped in its template; wp_kses_post would strip the form tags.
		echo wp_kses( self::form_html( $context, $product_id, $quantity ), nashenski_allowed_html() );
		wp_die();
	}

	/**
	 * Form html.
	 *
	 * @param mixed $context Context.
	 * @param mixed $product_id Product id.
	 * @param mixed $quantity Quantity.
	 * @return mixed Result.
	 */
	private static function form_html( $context, $product_id, $quantity ) {
		$user   = wp_get_current_user();
		$fields = self::active_fields();
		$format = self::phone_format();

		$privacy      = get_privacy_policy_url();
		$privacy_link = $privacy ? sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url( $privacy ), esc_html__( 'privacy policy', 'nashenski-commerce' ) ) : esc_html__( 'privacy policy', 'nashenski-commerce' );

		ob_start();
		?>
		<form class="nashenski-quick-order-form" method="post">
			<h2><?php esc_html_e( 'Quick order', 'nashenski-commerce' ); ?></h2>
			<p><?php esc_html_e( 'Leave your details and we will contact you to confirm the order.', 'nashenski-commerce' ); ?></p>
			<?php // Honeypot: people do not see it, bots fill it in. ?>
			<p class="nashenski-quick-order-hp" aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden;">
				<label><?php esc_html_e( 'Website', 'nashenski-commerce' ); ?> <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
			</p>
			<p><label><?php esc_html_e( 'Name', 'nashenski-commerce' ); ?> <span aria-hidden="true">*</span><br>
				<input type="text" name="name" required autocomplete="name" value="<?php echo esc_attr( trim( $user->first_name . ' ' . $user->last_name ) ); ?>"></label></p>
			<p><label><?php esc_html_e( 'Phone', 'nashenski-commerce' ); ?> <span aria-hidden="true">*</span><br>
				<input type="tel" name="phone" required autocomplete="tel" inputmode="tel" data-nashenski-phone placeholder="<?php echo esc_attr( self::phone_example() ); ?>"></label>
				<span class="description">
				<?php
					/* translators: %s: example phone number */
					echo esc_html( sprintf( __( 'Phone number, for example %s.', 'nashenski-commerce' ), self::phone_example() ) );
				?>
				</span></p>
			<p><label><?php esc_html_e( 'Email', 'nashenski-commerce' ); ?> <span aria-hidden="true">*</span><br>
				<input type="email" name="email" required autocomplete="email" value="<?php echo esc_attr( $user->user_email ); ?>"></label></p>

			<?php if ( isset( $fields['address'] ) ) : ?>
				<p><label><?php echo esc_html( $fields['address'] ); ?><br><input type="text" name="address" autocomplete="street-address"></label></p>
			<?php endif; ?>
			<?php if ( isset( $fields['town'] ) ) : ?>
				<p><label><?php echo esc_html( $fields['town'] ); ?><br><input type="text" name="town" autocomplete="address-level2"></label></p>
			<?php endif; ?>
			<?php if ( isset( $fields['quantity'] ) ) : ?>
				<p><label><?php echo esc_html( $fields['quantity'] ); ?><br><input type="number" name="quantity" min="1" step="1" value="<?php echo esc_attr( $quantity ); ?>"></label></p>
			<?php else : ?>
				<input type="hidden" name="quantity" value="<?php echo esc_attr( $quantity ); ?>">
			<?php endif; ?>
			<?php if ( isset( $fields['product_notes'] ) ) : ?>
				<p><label><?php echo esc_html( $fields['product_notes'] ); ?><br><textarea name="product_notes" rows="2"></textarea></label></p>
			<?php endif; ?>
			<?php if ( isset( $fields['preferred_time'] ) ) : ?>
				<p><label><?php echo esc_html( $fields['preferred_time'] ); ?><br><input type="text" name="preferred_time"></label></p>
			<?php endif; ?>
			<?php if ( isset( $fields['message'] ) ) : ?>
				<p><label><?php echo esc_html( $fields['message'] ); ?><br><textarea name="message" rows="3"></textarea></label></p>
			<?php endif; ?>

			<p><label><input type="checkbox" name="consent" value="yes" required>
				<?php
				/* translators: %s: link to the privacy policy */
				echo wp_kses_post( sprintf( __( 'I agree that the store may contact me about this order. I have read the %s.', 'nashenski-commerce' ), $privacy_link ) );
				?>
				<span aria-hidden="true">*</span></label></p>

			<input type="hidden" name="action" value="nashenski_quick_order_submit">
			<input type="hidden" name="context" value="<?php echo esc_attr( $context ); ?>">
			<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
			<?php wp_nonce_field( self::NONCE, '_nonce' ); ?>
			<p class="nashenski-quick-order-error" role="alert"></p>
			<p class="nashenski-quick-order-actions"><button type="submit" class="button alt"><?php esc_html_e( 'Send', 'nashenski-commerce' ); ?></button></p>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Checks the submission and creates the order.
	 */
	public static function submit_ajax() {
		if ( ! self::enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Quick orders are turned off.', 'nashenski-commerce' ) ) );
		}

		$post = wp_unslash( $_POST );

		if ( ! wp_verify_nonce( isset( $post['_nonce'] ) ? $post['_nonce'] : '', self::NONCE ) ) {
			wp_send_json_error( array( 'message' => __( 'The form has expired. Reload the page and try again.', 'nashenski-commerce' ) ) );
		}

		// A filled-in honeypot means a bot. It gets the same answer as a person, and no order.
		if ( ! empty( $post['website'] ) ) {
			wp_send_json_success( array( 'message' => self::thanks_text() ) );
		}

		if ( self::too_many_requests() ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Try again in a few minutes.', 'nashenski-commerce' ) ) );
		}

		self::count_request();

		$name  = sanitize_text_field( isset( $post['name'] ) ? $post['name'] : '' );
		$email = sanitize_email( isset( $post['email'] ) ? $post['email'] : '' );
		$phone = self::normalize_phone( isset( $post['phone'] ) ? $post['phone'] : '' );

		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Enter your name.', 'nashenski-commerce' ) ) );
		}

		if ( '' === $phone ) {
			wp_send_json_error( array( 'message' => self::phone_error() ) );
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Enter a valid email address.', 'nashenski-commerce' ) ) );
		}

		if ( ! isset( $post['consent'] ) || 'yes' !== $post['consent'] ) {
			wp_send_json_error( array( 'message' => __( 'Tick the box to agree to be contacted.', 'nashenski-commerce' ) ) );
		}

		$context    = isset( $post['context'] ) && 'cart' === $post['context'] ? 'cart' : 'product';
		$product_id = isset( $post['product_id'] ) ? absint( $post['product_id'] ) : 0;
		$quantity   = isset( $post['quantity'] ) ? max( 1, absint( $post['quantity'] ) ) : 1;

		$items = array();

		if ( 'cart' === $context ) {
			if ( ! WC()->cart || WC()->cart->is_empty() ) {
				wp_send_json_error( array( 'message' => __( 'Your cart is empty.', 'nashenski-commerce' ) ) );
			}

			foreach ( WC()->cart->get_cart() as $item ) {
				$items[] = array( $item['data'], (int) $item['quantity'] );
			}
		} else {
			$product = $product_id ? wc_get_product( $product_id ) : false;

			if ( ! $product || ! $product->is_type( 'simple' ) || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				wp_send_json_error( array( 'message' => __( 'This product cannot be ordered now.', 'nashenski-commerce' ) ) );
			}

			$items[] = array( $product, $quantity );
		}

		$order = self::create_order( $items, $post, compact( 'name', 'phone', 'email' ) );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'The order could not be saved. Please try again.', 'nashenski-commerce' ) ) );
		}

		// The cart became the order: empty it, so it is not ordered twice or tracked again as abandoned.
		if ( 'cart' === $context ) {
			Tracker::mark_recovered();
			WC()->cart->empty_cart();
		}

		self::notify_owner( $order );

		if ( 'yes' === nashenski_get_option( 'quick_order', 'confirm_to_shopper' ) ) {
			self::notify_shopper( $order );
		}

		wp_send_json_success( array( 'message' => self::thanks_text() ) );
	}

	/**
	 * A phone number, however it was typed, as +<dial><national>. The country comes from the setting,
	 * and a number typed with an explicit +<dial> is taken as it was typed. Returns '' when invalid.
	 *
	 * @param mixed $raw Raw.
	 */
	public static function normalize_phone( $raw ) {
		$raw    = trim( (string) $raw );
		$digits = preg_replace( '/[^0-9]/', '', $raw );

		if ( '' === $digits ) {
			return '';
		}

		// An explicit +<dial> is trusted as written, whichever country it belongs to.
		if ( 0 === strpos( $raw, '+' ) ) {
			if ( strlen( $digits ) < 7 || strlen( $digits ) > 15 ) {
				return '';
			}

			return '+' . $digits;
		}

		// "00" is the international prefix people often type instead of the plus.
		if ( 0 === strpos( $digits, '00' ) ) {
			$digits = substr( $digits, 2 );
		}

		$dial = self::phone_dial();

		if ( '' !== $dial && 0 === strpos( $digits, $dial ) ) {
			$national = substr( $digits, strlen( $dial ) );
		} elseif ( '0' === $digits[0] ) {
			$national = substr( $digits, 1 );
		} else {
			$national = $digits;
		}

		// The national significant number: 5 to 12 digits covers every country in the table.
		if ( ! preg_match( '/^[1-9][0-9]{4,11}$/', $national ) ) {
			return '';
		}

		if ( '' === $dial ) {
			return '+' . $national;
		}

		return '+' . $dial . $national;
	}

	/**
	 * The phone example shown as placeholder and named in the error message, for the chosen country.
	 */
	public static function phone_example() {
		$dial = self::phone_dial();

		if ( 'international' === self::phone_format() ) {
			return $dial ? '+' . $dial . ' 88 123 4567' : '+CC 88 123 4567';
		}

		return '0888 123 456';
	}

	/**
	 * Phone error.
	 *
	 * @return mixed Result.
	 */
	private static function phone_error() {
		return sprintf(
			/* translators: %s: example phone number */
			__( 'Enter a valid phone number, for example %s.', 'nashenski-commerce' ),
			self::phone_example()
		);
	}

	/**
	 * Thanks text.
	 *
	 * @return mixed Result.
	 */
	private static function thanks_text() {
		return esc_html__( 'Thank you. We have your details and will contact you to confirm the order.', 'nashenski-commerce' );
	}

	/**
	 * Create order.
	 *
	 * @param mixed $items Items.
	 * @param mixed $post Post.
	 * @param mixed $contact Contact.
	 * @return bool|mixed Result.
	 */
	private static function create_order( $items, $post, $contact ) {
		$order = wc_create_order(
			array(
				'status'      => self::STATUS,
				'customer_id' => get_current_user_id(),
			)
		);

		if ( is_wp_error( $order ) || ! $order ) {
			return false;
		}

		$country = WC()->countries ? WC()->countries->get_base_country() : 'BG';
		$order->set_billing_first_name( $contact['name'] );
		$order->set_billing_phone( $contact['phone'] );
		$order->set_billing_email( $contact['email'] );
		$order->set_billing_country( $country );

		if ( ! empty( $post['address'] ) ) {
			$order->set_billing_address_1( sanitize_text_field( $post['address'] ) );
		}

		if ( ! empty( $post['town'] ) ) {
			$order->set_billing_city( sanitize_text_field( $post['town'] ) );
		}

		foreach ( $items as $item ) {
			$order->add_product( $item[0], $item[1] );
		}

		$order->calculate_totals();

		// Owner-chosen extras go to the order as the customer note, one line each.
		$fields = self::active_fields();
		$notes  = array();

		foreach ( array(
			'product_notes'  => __( 'Product notes', 'nashenski-commerce' ),
			'preferred_time' => __( 'Preferred delivery time', 'nashenski-commerce' ),
			'message'        => __( 'Message', 'nashenski-commerce' ),
		) as $key => $label ) {
			if ( isset( $fields[ $key ] ) && ! empty( $post[ $key ] ) ) {
				$notes[] = $label . ': ' . sanitize_textarea_field( $post[ $key ] );
			}
		}

		if ( $notes ) {
			$order->set_customer_note( implode( "\n", $notes ) );
		}

		$order->update_meta_data( self::META_CONSENT, current_time( 'mysql' ) );
		$order->add_order_note( __( 'Quick order from the shop. No stock is reserved and no payment link is sent until the status is changed to Pending payment.', 'nashenski-commerce' ) );
		$order->save();

		return $order;
	}

	/**
	 * The owner gets WooCommerce's own "New order" email, like for any order (its recipients and on/off
	 * setting apply). It is marked sent, so confirming the quick order later does not send it again.
	 *
	 * @param mixed $order Order.
	 */
	private static function notify_owner( $order ) {
		$emails = WC()->mailer() ? WC()->mailer()->get_emails() : array();

		if ( isset( $emails['WC_Email_New_Order'] ) ) {
			$emails['WC_Email_New_Order']->trigger( $order->get_id(), $order );

			return;
		}

		$settings = get_option( 'woocommerce_new_order_settings', array() );
		$to       = ! empty( $settings['recipient'] ) ? $settings['recipient'] : get_option( 'admin_email' );

		wp_mail(
			$to,
			/* translators: %s: order number */
			sprintf( __( 'New quick order #%s', 'nashenski-commerce' ), $order->get_order_number() ),
			sprintf(
				/* translators: 1: shopper name, 2: phone, 3: email, 4: link to the order */
				__( "A shopper asked for a quick order.\n\nName: %1\$s\nPhone: %2\$s\nEmail: %3\$s\n\nOpen the order: %4\$s", 'nashenski-commerce' ),
				$order->get_billing_first_name(),
				$order->get_billing_phone(),
				$order->get_billing_email(),
				$order->get_edit_order_url()
			)
		);
	}

	/**
	 * Notify shopper.
	 *
	 * @param mixed $order Order.
	 */
	private static function notify_shopper( $order ) {
		wp_mail(
			$order->get_billing_email(),
			/* translators: 1: site name, 2: order number */
			sprintf( __( '%1$s: we received your quick order #%2$s', 'nashenski-commerce' ), get_bloginfo( 'name' ), $order->get_order_number() ),
			sprintf(
				/* translators: %s: shopper name */
				__( "Hello %s,\n\nThank you. We have your quick order and will contact you to confirm it.\n\nNo payment is needed now.", 'nashenski-commerce' ),
				trim( preg_replace( '~(?:https?://|www\.)\S*~i', '', $order->get_billing_first_name() ) )
			)
		);
	}

	/**
	 * Client ip.
	 *
	 * @return mixed Result.
	 */
	private static function client_ip() {
		// Not WC_Geolocation: it trusts X-Forwarded-For, which a bot can spoof to dodge the limit.
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );

		// Shops behind a proxy can return the real address here.
		return (string) \Nashenski\Hooks::filter( 'nashenski_quick_order_client_ip', 'nashenski/quick_order/client_ip', $ip );
	}

	/**
	 * Too many requests.
	 *
	 * @return mixed Result.
	 */
	private static function too_many_requests() {
		$count = (int) get_transient( self::rate_key() );

		return $count >= self::RATE_LIMIT;
	}

	/**
	 * Count request.
	 */
	private static function count_request() {
		// Not atomic: two requests at the same moment can both pass. Enough to slow a bot down.
		set_transient( self::rate_key(), (int) get_transient( self::rate_key() ) + 1, self::RATE_WINDOW );
	}

	/**
	 * Rate key.
	 *
	 * @return string Result.
	 */
	private static function rate_key() {
		return 'nashenski_qo_' . md5( self::client_ip() );
	}
}
