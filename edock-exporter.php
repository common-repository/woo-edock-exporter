<?php
/*
Plugin Name: WooCommerce - eDock Exporter
Plugin URI: http://www.edock.it
Description: Export WooCommerce Products to eDock 
Version: 1.4.0
Author: Alessandro Alessio
Author URI: http://www.a2area.it
License: GPL2
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'WOO_ED_DIRNAME', basename( dirname( __FILE__ ) ) );
define( 'WOO_ED_RELPATH', basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ) );
define( 'WOO_ED_PATH', rtrim( plugin_dir_path( __FILE__ ), '/' ) );
define( 'WOO_ED_URL', plugins_url() . '/' . WOO_ED_DIRNAME . '/' );
define( 'WOO_ED_PREFIX', 'woo_ed' );
define( 'WOO_ED_UPLOAD_DIR', WP_CONTENT_DIR . '/uploads/wcedock-export' );
define( 'WOO_ED_DEBUG', false );

// Languages
function woo_edock_i18n() {
	load_plugin_textdomain( 'woo_ed', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'woo_edock_i18n' );

// Create Uploads Directory
if ( !file_exists( WOO_ED_UPLOAD_DIR ) ) {
	mkdir( WOO_ED_UPLOAD_DIR, 755, true );
}

// Require common functions
include_once( WOO_ED_PATH . '/common/Carbon/Carbon.php' );
include_once( WOO_ED_PATH . '/common/functions.php' );
use CarbonWCE\Carbon;

// Define Data Attribute for Woocommerce
$data_attribute = wce_define_attribute_table();

// Standard Tax
$wcedock_tax = '0.'.intval( wce_get_woocommerce_default_tax() );

if( is_admin() ) {

	/**
	* Create Menu Extension
	*/
	if ( !function_exists('wcedock_register_menu') ) {
		function wcedock_register_menu() {
			// Menu
			add_menu_page(
				'Export eDock',
				'Export eDock',
				'manage_options',
				'wcedock-export',
				'wcedock_view_main',
				WOO_ED_URL . '/assets/images/icon.png',
				100
			);
		}
		add_action( 'admin_menu', 'wcedock_register_menu' );
	}

	/**
	* View - Main
	*/
	if ( !function_exists('wcedock_view_main') ){
		function wcedock_view_main() {
			global $WC_Tax;

			// Action
			$mode = '';
			if ( isset($_GET['mode']) ) {
				$mode = sanitize_text_field($_GET['mode']);
			}
			if ( isset($mode) /*&& wp_verify_nonce($_GET['nonce'], 'edock-exporter')*/ ){
				switch ($mode) {
					case 'savedata':
						$wce_option_status = sanitize_text_field($_GET['wce_option_status']);
						$wce_option_email = sanitize_email($_GET['wce_option_email']);
						$wce_option_export_description = sanitize_text_field($_GET['wce_option_export_description']);
						$wce_option_force_qty = ( isset($_GET['wce_option_force_qty']) ) ? 'on' : 'off';

						update_option( 'wce_option_status', $wce_option_status );
						update_option( 'wce_option_email', $wce_option_email );
						update_option( 'wce_option_export_description', $wce_option_export_description );
						update_option( 'wce_option_force_qty', $wce_option_force_qty );
						update_option( 'wce_option_ts_latest_update', time() );

						if ( $wce_option_status=='on' ):
							wce_output_csv();
							wce_cron_activation();
						else:
							wce_cron_deactivation();
						endif;
					break;

					case 'export':
						echo 'Ops...<br>';
					break;
				}
			}

			/* ============= *
			*	 MAIN VIEW   *
			*  ============= */

			echo '<div class="wrap">';
				echo '<h2>Export to eDock</h2>';
				echo '<form name="form_wcedock_run" method="GET">';
					settings_fields( 'woocommerce-edock-exporter-settings-group' );
					do_settings_sections( 'woocommerce-edock-exporter-settings-group' );
					echo '<input type="hidden" name="mode" value="savedata">';
					echo '<input type="hidden" name="action" value="savedata">';
					echo '<input type="hidden" name="page" value="wcedock-export">';
					echo '<input type="hidden" name="nonce" value="'.wp_create_nonce('edock-exporter').'">';

					echo '<table class="form-table">';
						echo '<tbody>';
							echo '<tr>';
								echo '<th>';
									
									$status = get_option('wce_option_status');
									echo '<label for="wce_option_status">' . __('Status', 'wcedock-export').': </label>';
								
								echo '</th>';
								echo '<td>';
						
									$status_checked_on = '';
									if ( $status=='on' ) $status_checked_on = ' checked'; 
									echo '&nbsp;<input type="radio" name="wce_option_status" value="on" '.$status_checked_on.'> '.__('On', 'wcedock-export').' ';

									$status_checked_off = '';
									if ( $status=='off' ) $status_checked_off = ' checked';
									echo '<input type="radio" name="wce_option_status" value="off" '.$status_checked_off.'> '.__('Off', 'wcedock-export').' ';

								echo '</td>';
							echo '</tr>';

							echo '<tr>';
								echo '<th><label for="wce_option_email">' . __('Notify Email', 'wcedock-export').': </label></th>';
								echo '<td><input type="text" name="wce_option_email" value="'.get_option('wce_option_email').'"></td>';
							echo '</tr>';

							echo '<tr>';
								echo '<th><label for="wce_option_export_description">' . __('Export Description', 'wcedock-export').': </label></th>';
								echo '<td>';
									echo '<select name="wce_option_export_description">';
										echo '<option value="excerpt" '.( get_option('wce_option_export_description')=='excerpt' ? 'selected="selected"' : '' ).'>Excerpt</option>';
										echo '<option value="content" '.( get_option('wce_option_export_description')=='content' ? 'selected="selected"' : '' ).'>Content</option>';
									echo '</select>';
							echo '</tr>';

							$wce_option_force_qty_status = ( get_option('wce_option_force_qty')=='on' ) ? 'checked' : '';
							echo '<tr>';
								echo '<th><label for="wce_option_force_qty">'.__('Force default quantity', 'wcedock-export').'</label></th>';
								echo '<td><input type="checkbox" name="wce_option_force_qty" value="1" '.$wce_option_force_qty_status.'></td>';
							echo '</tr>';

							if ( $status=='on' ){
								echo '<tr>';
									echo '<th>';
										echo __('URL File', 'wcedock-export').':';
									echo '</th>';
									echo '<td>';
										echo '<input type="text" readonly value="' . content_url() . '/uploads/wcedock-export/export.csv" size="50" class="regular-text">';
										echo '<a href="' . content_url() . '/uploads/wcedock-export/export.csv" class="button" target="_blank">'.__('Open file', 'wcedock-export').'</a>';
									echo '</td>';
								echo '</tr>';

								echo '<tr>';
									echo '<th>';
										echo __('Latest update', 'wcedock-export').':';
									echo '</th>';
									echo '<td>' . date('d/m/Y h:i:s', time()) . '</td>';
								echo '</tr>';
							}

							echo '<tr>';
								echo '<th>Brands Available:</th>';
								if (  defined('YITH_WCBR') ){
									echo '<td><strong style="color: #2cc134;">Yes</strong> <small style="font-style:italic">from YITH WooCommerce Brands Add-On</small></td>';
									update_option( 'wce_option_brands', true );
								} else {
									echo '<td><strong style="color: #c92a2a;">No</strong></td>';
									update_option( 'wce_option_brands', false );
								}
							echo '</tr>';
							
							echo '<tr>';
								echo '<th>WPML Available:</th>';
								if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
									echo '<td><strong style="color: #2cc134;">Yes</strong></td>';
								} else {
									echo '<td><strong style="color: #c92a2a;">No</strong></td>';
								}
							echo '</tr>';

							echo '<tr>';
								echo '<th>&nbsp;</th>';
								echo '<td>'; submit_button(); echo '</td>';
							echo '</tr>';

						echo '</tbody>';
					echo '</table>';
				echo '</form>';
			echo '</div>';

		}
	}

} // if is_admin

// Setting Cron Event
add_action('wce_cron_event','wce_cron_exec');

// Activation & Deactivation Plugin
register_activation_hook  (__FILE__,'wce_activation');
register_deactivation_hook(__FILE__,'wce_deactivation');

?>