<?php
/**
 * WooCommerce Custom Delivery plugin.
 *
 * @package           kagg/wc-custom-delivery
 * @author            Ivan Ovsyannikov, KAGG Design
 * @license           GPL-2.0-or-later
 * @wordpress-plugin
 *
 * Plugin Name:       WooCommerce Custom Delivery
 * Plugin URI:        https://kagg.eu/en/
 * Description:       Adds delivery method «В другие регионы» (textual description).
 * Version:           1.0
 * Requires at least: 4.4
 * Requires PHP:      7.4
 * Author:            Ivan Ovsyannikov, KAGG Design
 * Author URI:        https://kagg.eu/en/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kagg-wc-ribbon
 * Domain Path:       /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PLUGIN_CUSTOM_DELIVERY_ID = 'custom_delivery';

function wc_custom_delivery_init() {
	if ( ! class_exists( 'WC_Shipping_Method' ) ) {
		return;
	}

	class WC_Custom_Delivery extends WC_Shipping_Method {

		/**
		 * @var mixed
		 */
		private $description;

		public function __construct() {
			parent::__construct();

			$this->id           = PLUGIN_CUSTOM_DELIVERY_ID;
			$this->method_title = 'Доставка в другие регионы';
			$this->init();
		}

		/**
		 * Init class.
		 *
		 * @return void
		 */
		private function init(): void {
			$this->init_form_fields();
			$this->init_settings();

			$this->title        = $this->get_option( 'title' );
			$this->availability = $this->get_option( 'availability' );
			$this->countries    = $this->get_option( 'countries' );
			$this->description  = $this->get_option( 'description' );

			add_action( 'woocommerce_update_options_shipping_' . $this->id, [ $this, 'process_admin_options' ] );
		}

		/**
		 * Init form fields.
		 *
		 * @return void
		 * @noinspection ReturnTypeCanBeDeclaredInspection
		 */
		public function init_form_fields() {
			$this->form_fields = [
				'enabled'      => [
					'title'   => __( 'Enable/Disable', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => 'Включить доставку в другие регионы',
					'default' => 'no',
				],
				'title'        => [
					'title'   => __( 'Method Title', 'woocommerce' ),
					'type'    => 'text',
					'default' => 'Доставка в другие регионы',
				],
				'availability' => [
					'title'   => __( 'Method availability', 'woocommerce' ),
					'type'    => 'select',
					'default' => 'all',
					'class'   => 'availability',
					'options' => [
						'all'      => __( 'All allowed countries', 'woocommerce' ),
						'specific' => __( 'Specific Countries', 'woocommerce' ),
					],
				],
				'countries'    => [
					'title'             => __( 'Specific Countries', 'woocommerce' ),
					'type'              => 'multiselect',
					'class'             => 'chosen_select',
					'css'               => 'width: 450px;',
					'default'           => '',
					'options'           => WC()->countries->get_shipping_countries(),
					'custom_attributes' => [
						'data-placeholder' => __( 'Select some countries', 'woocommerce' ),
					],
				],
				'description'  => [
					'title' => __( 'Description', 'woocommerce' ),
					'type'  => 'textarea',
					'class' => 'wp-editor-area wp-editor-textarea wc-description-hidden',
				],
			];
		}

		/**
		 * Admin options.
		 *
		 * @return void
		 * @noinspection ReturnTypeCanBeDeclaredInspection
		 * @noinspection UnusedFunctionResultInspection
		 */
		public function admin_options() {
			echo '<h3>' . $this->title . '</h3>';
			echo '<p>Подключите «Доставку в другие регионы», чтобы добавить произвольный способ доставки. Необходимо заполнить описание ниже, оно будет выведено как пояснение к способу доставки. Например, можно указать какой транспортной компанией будет доставлен товар.</p>';
			?>
			<table class="form-table">
				<?php $this->generate_settings_html(); ?>
				<tr>
					<th scope="row" class="titledesc">
						<label for="woocommerce_custom-delivery_description">Описание</label>
					</th>
					<td class="forminp">
						<fieldset>
							<?php
							wp_editor( $this->description, 'customdeliverydescription', [
								'textarea_name' => 'woocommerce_' . $this->id . '_description',
								'textarea_rows' => 12,
							] );
							?>
							<p class="description">Описание будет выведено в форме оформления заказа</p>
						</fieldset>
					</td>
				</tr>
			</table>
			<script type="text/javascript">
				( function( $ ) {
					$( '.wc-description-hidden' ).closest( 'tr' ).hide();
				} )( jQuery );
			</script>
			<?php
		}

		/**
		 * Is package available.
		 *
		 * @param $package
		 *
		 * @return bool|mixed|null
		 */
		public function is_available( $package ) {
			if ( 'no' === $this->enabled ) {
				return false;
			}

			if ( 'specific' === $this->availability ) {
				$ship_to_countries = $this->countries;
			} else {
				$ship_to_countries = array_keys( WC()->countries->get_shipping_countries() );
			}

			if (
				is_array( $ship_to_countries ) &&
				! in_array( $package['destination']['country'], $ship_to_countries, true )
			) {
				return false;
			}

			return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', true );
		}

		/**
		 * Calculate shipping.
		 *
		 * @param array $package Package.
		 *
		 * @return void
		 * @noinspection ReturnTypeCanBeDeclaredInspection
		 */
		public function calculate_shipping( $package = [] ) {
			$args = [
				'id'    => $this->id,
				'label' => $this->title,
				'cost'  => 0,
				'taxes' => '',
			];

			$this->add_rate( $args );
		}
	}

	function woocommerce_add_custom_delivery( $methods ) {
		$methods[] = 'WC_Custom_Delivery';

		return $methods;
	}

	add_filter( 'woocommerce_shipping_methods', 'woocommerce_add_custom_delivery' );
}

add_action( 'woocommerce_shipping_init', 'wc_custom_delivery_init' );

function wc_remove_free_label( $full_label, $method ) {
	if ( ! class_exists( 'WC_Custom_Delivery' ) || $method->id !== PLUGIN_CUSTOM_DELIVERY_ID ) {
		return $full_label;
	}

	return str_replace( ' (' . __( 'Free!', 'woocommerce' ) . ')', '', $full_label );
}

add_filter( 'woocommerce_cart_shipping_method_full_label', 'wc_remove_free_label', 10, 2 );

/**
 * Insert description.
 *
 * @return void
 */
function wc_custom_delivery_insert_description() {
	if ( ! class_exists( 'WC_Custom_Delivery' ) ) {
		return;
	}

	$custom_delivery = new WC_Custom_Delivery();
	$description     = $custom_delivery->settings['description'];
	$packages        = WC()->shipping()->get_packages();
	$chosen_method   = '';

	foreach ( $packages as $i => $package ) {
		$chosen_method = WC()->session->chosen_shipping_methods[ $i ] ?? '';
	}

	if ( PLUGIN_CUSTOM_DELIVERY_ID !== $chosen_method ) {
		return;
	}

	?>
	<div class="checkout-custom-delivery">
		<?php echo apply_filters( 'the_content', $description ); ?>
	</div>
	<?php
}

add_action( 'woocommerce_review_order_before_payment', 'wc_custom_delivery_insert_description' );

function hide_shipping_when_free_is_available( $rates, $package ) {
	if ( isset( $rates['free_shipping'] ) ) {
		unset( $rates['flat_rate'] );
		$free_shipping          = $rates['free_shipping'];
		$rates                  = [];
		$rates['free_shipping'] = $free_shipping;
	}

	return $rates;
}

add_filter( 'woocommerce_package_rates', 'hide_shipping_when_free_is_available', 10, 2 );
