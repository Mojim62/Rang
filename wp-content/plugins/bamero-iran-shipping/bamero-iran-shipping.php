<?php
/**
 * Plugin Name: Bamero Iran Shipping
 * Description: Iranian shipping methods for WooCommerce - Post and Tipax
 * Version: 1.0.0
 * Author: Bamero
 * License: MIT
 * Text Domain: bamero-iran-shipping
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

define('BAMERO_IRAN_SHIPPING_VERSION', '1.0.0');

// Declare HPOS compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', 'bamero_iran_shipping_init', 20);

function bamero_iran_shipping_init() {
    if (!class_exists('WC_Shipping_Method')) {
        return;
    }

    /**
     * Post Shipping Method
     */
    class Bamero_Post_Shipping extends WC_Shipping_Method {
        public function __construct($instance_id = 0) {
            $this->id = 'post';
            $this->instance_id = absint($instance_id);
            $this->method_title = ' ';
            $this->method_description = '    ';
            $this->supports = array('shipping-zones', 'instance-settings');
            $this->enabled = 'yes';
            $this->title = ' ';

            $this->init();
        }

        public function init() {
            $this->init_settings();
            $this->title = $this->get_option('title', ' ');
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }

        public function calculate_shipping($package = array()) {
            $rate = array(
                'id' => $this->id . ':' . $this->instance_id,
                'label' => $this->title,
                'cost' => $this->get_option('cost', 0),
                'calc_tax' => 'per_item',
            );

            if ($this->get_option('free_shipping') === 'yes') {
                $rate['cost'] = 0;
                $rate['label'] .= ' ()';
            }

            $this->add_rate($rate);
        }
    }

    /**
     * Tipax Shipping Method
     */
    class Bamero_Tipax_Shipping extends WC_Shipping_Method {
        public function __construct($instance_id = 0) {
            $this->id = 'tipax';
            $this->instance_id = absint($instance_id);
            $this->method_title = '';
            $this->method_description = '    ';
            $this->supports = array('shipping-zones', 'instance-settings');
            $this->enabled = 'yes';
            $this->title = '';

            $this->init();
        }

        public function init() {
            $this->init_settings();
            $this->title = $this->get_option('title', '');
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }

        public function calculate_shipping($package = array()) {
            $rate = array(
                'id' => $this->id . ':' . $this->instance_id,
                'label' => $this->title,
                'cost' => $this->get_option('cost', 0),
                'calc_tax' => 'per_item',
            );

            if ($this->get_option('free_shipping') === 'yes') {
                $rate['cost'] = 0;
                $rate['label'] .= ' ()';
            }

            $this->add_rate($rate);
        }
    }

    /**
     * Register shipping methods
     */
    function bamero_register_iran_shipping_methods($methods) {
        $methods['post'] = 'Bamero_Post_Shipping';
        $methods['tipax'] = 'Bamero_Tipax_Shipping';
        return $methods;
    }
    add_filter('woocommerce_shipping_methods', 'bamero_register_iran_shipping_methods');

    /**
     * Set up shipping zones for Iran
     */
    function bamero_setup_iran_shipping_zones() {
        if (!class_exists('WC_Shipping_Zones')) {
            return;
        }

        $zones = WC_Shipping_Zones::get_zones();
        $iran_zone_exists = false;

        foreach ($zones as $zone) {
            if (isset($zone['zone_name']) && $zone['zone_name'] === '') {
                $iran_zone_exists = true;
                break;
            }
        }

        if (!$iran_zone_exists) {
            $zone = new WC_Shipping_Zone();
            $zone->set_zone_name('');
            $zone->set_zone_order(1);
            $zone->add_location('IR', 'country');
            $zone->save();

            // Add Post and Tipax to Iran zone
            $zone_id = $zone->get_id();
            $post_method = new Bamero_Post_Shipping();
            $post_method->set_cost(0);
            $post_method->set_free_shipping('no');
            $post_method->save();

            $tipax_method = new Bamero_Tipax_Shipping();
            $tipax_method->set_cost(0);
            $tipax_method->set_free_shipping('no');
            $tipax_method->save();
        }
    }
    add_action('woocommerce_init', 'bamero_setup_iran_shipping_zones');

    /**
     * Configure default shipping settings
     */
    function bamero_configure_shipping_settings() {
        // Enable shipping calculator on cart page
        update_option('woocommerce_enable_shipping_calc', 'yes');

        // Hide shipping costs until address is entered
        update_option('woocommerce_shipping_cost_requires_address', 'yes');

        // Shipping destination
        update_option('woocommerce_shipping_method_count', 2);
    }
    add_action('admin_init', 'bamero_configure_shipping_settings');
}
