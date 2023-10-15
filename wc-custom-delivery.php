<?php
/**
 * WooCommerce Custom Delivery plugin.
 *
 * @package           kagg/wc-custom-delivery
 * @author            Ivan Ovsyannikov, KAGG Design
 * @license           GPL-2.0-or-later
 * @wordpress-plugin
 *
 * Plugin Name:       WooCommerce — Доставка в другие регионы
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

if (!defined('ABSPATH')) exit;
define('PLUGIN_CUSTOM_DELIVERY_ID', 'custom_delivery');

add_action('woocommerce_shipping_init', 'wc_custom_delivery_init');

function wc_custom_delivery_init() {
	if (!class_exists('WC_Shipping_Method')) return;

	class WC_Custom_Delivery extends WC_Shipping_Method {
		function __construct() {
			$this->id = PLUGIN_CUSTOM_DELIVERY_ID;
			$this->method_title = 'Доставка в другие регионы';
			$this->init();
		}

		function init() {
			$this->init_form_fields();
			$this->init_settings();
			$this->title = $this->get_option('title');
			$this->availability = $this->get_option('availability');
			$this->countries = $this->get_option('countries');
			$this->description = $this->get_option('description');
			add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
		}

		function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Enable/Disable', 'woocommerce'),
					'type' => 'checkbox',
					'label' => 'Включить доставку в другие регионы',
					'default' => 'no'
				),
				'title' => array(
					'title' => __('Method Title', 'woocommerce'),
					'type'=> 'text',
					'default' => 'Доставка в другие регионы'
				),
				'availability' => array(
					'title' => __('Method availability', 'woocommerce'),
					'type' => 'select',
					'default' => 'all',
					'class' => 'availability',
					'options' => array(
						'all' => __('All allowed countries', 'woocommerce'),
						'specific' => __('Specific Countries', 'woocommerce')
					)
				),
				'countries' => array(
					'title' => __('Specific Countries', 'woocommerce'),
					'type' => 'multiselect',
					'class' => 'chosen_select',
					'css' => 'width: 450px;',
					'default' => '',
					'options' => WC()->countries->get_shipping_countries(),
					'custom_attributes' => array(
						'data-placeholder' => __('Select some countries', 'woocommerce')
					)
				),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
					'class' => 'wp-editor-area wp-editor-textarea wc-description-hidden'
				)
			);
		}

		function admin_options() {
			echo '<h3>' . $this->title . '</h3>';
			echo '<p>Подключите «Доставку в другие регионы», чтобы добавить произвольный способ доставки. Необходимо заполнить описание ниже, оно будет выведено как пояснение к способу доставки. Например, можно указать какой транспортной компанией будет доставлен товар.</p>';
			?>
			<table class="form-table">
				<?php $this->generate_settings_html(); ?>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="woocommerce_custom-delivery_description">Описание</label>
					</th>
					<td class="forminp">
						<fieldset>
							<?php
							wp_editor($this->description, 'customdeliverydescription', array(
								'textarea_name' => 'woocommerce_' . $this->id . '_description',
								'textarea_rows' => 12
							));
							?>
							<p class="description">Описание будет выведено в форме оформления заказа</p>
						</fieldset>
					</td>
				</tr>
			</table>
			<script type="text/javascript">
			(function($) {
				$('.wc-description-hidden').closest('tr').hide();
			})(jQuery);
			</script>
			<?php
		}

		function is_available($package) {
			if ($this->enabled == "no") return false;
			if ($this->availability == 'specific') {
				$ship_to_countries = $this->countries;
			} else {
				$ship_to_countries = array_keys(WC()->countries->get_shipping_countries());
			}
			if (is_array($ship_to_countries)) {
				if (!in_array($package['destination']['country'] , $ship_to_countries)) return false;
			}
			return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', true);
		}

		function calculate_shipping( $package = [] ) {
			$args = array(
				'id' => $this->id,
				'label' => $this->title,
				'cost' => 0,
				'taxes' => ''
			);
			$this->add_rate( $args );
		}

		public function wc_custom_delivery_description() {
			return $this->description;
		}
	}

	add_filter('woocommerce_shipping_methods', 'woocommerce_add_custom_delivery');
	function woocommerce_add_custom_delivery($methods) {
		$methods[] = 'WC_Custom_Delivery';
		return $methods;
	}

}

add_filter('woocommerce_cart_shipping_method_full_label', 'wc_remove_free_label', 10, 2);
function wc_remove_free_label($full_label, $method) {
	if (class_exists('WC_Custom_Delivery')) {
		if ($method->id == PLUGIN_CUSTOM_DELIVERY_ID) {
			$full_label = str_replace(' (' . __('Free!', 'woocommerce') . ')', '', $full_label);
		}
	}
	return $full_label;
}

add_action('woocommerce_review_order_before_payment', 'wc_custom_delivery_insert_description');
function wc_custom_delivery_insert_description() {
	global $woocommerce;
	if (class_exists('WC_Custom_Delivery')) {
		$custom_delivery = new WC_Custom_Delivery();
		$description = $custom_delivery->settings['description'];
		$packages = WC()->shipping->get_packages();
		$chosen_method = '';
		foreach ($packages as $i => $package) {
			$chosen_method = isset(WC()->session->chosen_shipping_methods[$i]) ? WC()->session->chosen_shipping_methods[$i] : '';
		}
		if ($chosen_method == PLUGIN_CUSTOM_DELIVERY_ID) {
			?>
			<div class="checkout-custom-delivery">
				<?php echo apply_filters('the_content', $description); ?>
			</div>
			<?php
		}
	}
}

add_filter('woocommerce_package_rates', 'hide_shipping_when_free_is_available', 10, 2);
function hide_shipping_when_free_is_available($rates, $package) {
  	if (isset($rates['free_shipping'])) {
  		unset($rates['flat_rate']);
  		$free_shipping = $rates['free_shipping'];
  		$rates = array();
  		$rates['free_shipping'] = $free_shipping;
	}
	return $rates;
}
