<?php
/**
 * Plugin Name: E-Transactions
 * Plugin URI:        https://github.com/19h47/woocommerce-e-transactions
 * Description: E-Transactions gateway payment plugins for WooCommerce
 * Version: 0.9.9.9.1
 * Author: 19h47
 * Author URI: https://19h47.fr
 * Text Domain: woocommerce-e-transactions
 *
 * @package WordPress
 * @since 0.9.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die();
}

function wooCommerceActiveETwp() {
		// Makes sure the plugin is defined before trying to use it
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return false;
	}
		return true;
}
// Ensure WooCommerce is active


if ( defined( 'WC_ETRANSACTIONS_PLUGIN' ) ) {
		_e( 'Previous plugin already installed. deactivate the previous one first.', WC_ETRANSACTIONS_PLUGIN );
		die( __( 'Previous plugin already installed. deactivate the previous one first.', WC_ETRANSACTIONS_PLUGIN ) );
}
	defined( 'WC_ETRANSACTIONS_PLUGIN' ) or define( 'WC_ETRANSACTIONS_PLUGIN', 'woocommerce-e-transactions' );
	defined( 'WC_ETRANSACTIONS_VERSION' ) or define( 'WC_ETRANSACTIONS_VERSION', '0.9.9.9.1' );
	defined( 'WC_ETRANSACTIONS_KEY_PATH' ) or define( 'WC_ETRANSACTIONS_KEY_PATH', ABSPATH . '/kek.php' );

function wc_etransactions_installation() {
	global $wpdb;
	$installed_ver = get_option( "WC_ETRANSACTIONS_PLUGIN.'_version'" );

	include_once ABSPATH . 'wp-admin/includes/plugin.php';
	if ( ! wooCommerceActiveETwp() ) {
		_e( 'WooCommerce must be activated', WC_ETRANSACTIONS_PLUGIN );
		die();
	}
	if ( $installed_ver != WC_ETRANSACTIONS_VERSION ) {
		$tableName = $wpdb->prefix . 'wc_etransactions_payment';
		$sql       = "CREATE TABLE $tableName (
			 id int not null auto_increment,
			 order_id bigint not null,
			 type enum('capture', 'first_payment', 'second_payment', 'third_payment', 'fourth_payment') not null,
			 data varchar(2048) not null,
			 KEY order_id (order_id),
			 PRIMARY KEY  (id))";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );

		update_option( WC_ETRANSACTIONS_PLUGIN . '_version', WC_ETRANSACTIONS_VERSION );
	}

}
function wc_etransactions_initialization() {
	if ( ! wooCommerceActiveETwp() ) {
		return ( 'Woocommerce not Active' );
	}
	$class = 'WC_Etransactions_Abstract_Gateway';

	if ( ! class_exists( $class ) ) {
		require_once dirname( __FILE__ ) . '/class/class-wc-etransactions-config.php';
		require_once dirname( __FILE__ ) . '/class/class-wc-etransactions-iso4217currency.php';
		require_once dirname( __FILE__ ) . '/class/class-wc-etransactions.php';
		require_once dirname( __FILE__ ) . '/class/class-wc-etransactions-abstract-gateway.php';
		require_once dirname( __FILE__ ) . '/class/class-wc-etransactions-standard-gateway.php';
		require_once dirname( __FILE__ ) . '/class/class-wc-etransactions-twotime-gateway.php';
		require_once dirname( __FILE__ ) . '/class/class-wc-etransactions-threetime-gateway.php';
		require_once dirname( __FILE__ ) . '/class/class-wc-etransactions-fourtime-gateway.php';
		require_once dirname( __FILE__ ) . '/class/class-wc-etransactions-encrypt.php';
	}

	load_plugin_textdomain( WC_ETRANSACTIONS_PLUGIN, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );

	$crypto = new ETransactionsEncrypt();
	if ( ! file_exists( WC_ETRANSACTIONS_KEY_PATH ) ) {
		$crypto->generateKey();
	}

	if ( get_site_option( WC_ETRANSACTIONS_PLUGIN . '_version' ) !== WC_ETRANSACTIONS_VERSION ) {
		wc_etransactions_installation();
	}
}

function wc_etransactions_register( array $methods ) {
	$methods[] = 'WC_EStdGw';
	$methods[] = 'WC_E2Gw';
	$methods[] = 'WC_E3Gw';
	$methods[] = 'WC_E4Gw';
	return $methods;
}

register_activation_hook( __FILE__, 'wc_etransactions_installation' );
add_action( 'plugins_loaded', 'wc_etransactions_initialization' );
add_filter( 'woocommerce_payment_gateways', 'wc_etransactions_register' );

function wc_etransactions_show_details( WC_Order $order ) {
	$method = get_post_meta( $order->get_id(), '_payment_method', true );
	switch ( $method ) {
		case 'etransactions_std':
			$method = new WC_EStdGw();
			$method->showDetails( $order );
			break;
		case 'etransactions_2x':
			$method = new WC_E2Gw();
			$method->showDetails( $order );
			break;
		case 'etransactions_3x':
			$method = new WC_E3Gw();
			$method->showDetails( $order );
			break;
		case 'etransactions_4x':
			$method = new WC_E4Gw();
			$method->showDetails( $order );
			break;
	}
}

add_action( 'woocommerce_admin_order_data_after_billing_address', 'wc_etransactions_show_details' );

function hmac_admin_notice() {

	if ( wooCommerceActiveETwp() ) {
		$temp        = new WC_EStdGw();
		$plugin_data = get_plugin_data( __FILE__ );
		$plugin_name = $plugin_data['Name'];
		if ( ! $temp->checkCrypto() ) {
			echo "<div class='notice notice-error  is-dismissible'>
			  <p><strong>/!\ Attention ! plugin " . $plugin_name . ' : </strong>' . __( 'HMAC key cannot be decrypted please re-enter or reinitialise it.', WC_ETRANSACTIONS_PLUGIN ) . '</p>
			 </div>';
		}
	} else {
		echo "<div class='notice notice-error  is-dismissible'>
			  <p><strong>/!\ Attention ! plugin E-Transactions : </strong>" . __( 'Woocommerce is not active!', WC_ETRANSACTIONS_PLUGIN ) . '</p>
			 </div>';

	}
}
add_action( 'admin_notices', 'hmac_admin_notice' );
