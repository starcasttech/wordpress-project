<?php
/**
 * WP Ultimate CSV Importer plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\FCSV;

if ( ! defined( 'ABSPATH' ) )
exit; // Exit if accessed directly

/**
 * Class SendPassword
 * @package Smackcoders\FCSV
 */
class SendPassword {

	protected static $instance = null,$plugin;

	public static function getInstance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
			self::$instance->doHooks();
		}
		return self::$instance;
	}

	/**
	 * SendPassword constructor.
	 */
	public function __construct() {
		$plugin = Plugin::getInstance();
	}

	/**
	 * SendPassword hooks.
	 */
	public function doHooks(){
		add_action('wp_ajax_settings_options', array($this,'settingsOptions'));
		add_action('wp_ajax_get_options', array($this,'showOptions'));
		add_action('wp_ajax_get_setting', array($this,'showsetting'));
	}

	/**
	 * Function for save settings options
	 *
	 */
	public function settingsOptions() {
		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');
		if(is_user_logged_in() && current_user_can('create_users')){
		$ucisettings = get_option('sm_uci_pro_settings');
		$option = sanitize_text_field($_POST['option']);
		$value = sanitize_text_field($_POST['value']);
		foreach ($ucisettings as $key => $val) {
			$settings[$key] = $val;
		}
		$settings[$option] = $value;
		update_option('sm_uci_pro_settings', $settings);
		$result['success'] = true;
		$result['option'] = $value === 'true' ? true : false; 
		echo wp_json_encode($result);
		wp_die();
	}
	else {
		$result['success'] = false;
		echo wp_json_encode($result);
		wp_die();
	}
	}

	public function showsetting() {

		// Nonce verification (CSRF protection)
		if ( empty( $_POST['securekey'] ) || 
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['securekey'] ) ), 'smack-ultimate-csv-importer' ) ) {
			wp_die( __( 'Security check failed.', 'wp-ultimate-csv-importer' ) );
		}

		// Capability check (authorization)
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'wp-ultimate-csv-importer' ) );
		}

		// Fetch settings safely
		$result['setting'] = get_option( 'openAI_settings' );

		echo wp_json_encode($result);
		wp_die();

	}


	/**
	 * Function for show settings options
	 *
	 */
	public function showOptions() {
		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');

		$prefixValue = isset($_POST['prefixValue']) ? sanitize_text_field($_POST['prefixValue']) : '';

		if ($prefixValue === 'delete') {
			delete_option('openAI_settings');
			$result['success'] = true;
			echo wp_json_encode($result);
			wp_die();
		}

		$json = isset($_POST['data']) ? wp_unslash($_POST['data']) : '';
		$data = json_decode($json, true);

		if (is_array($data)) {
			$settings = [
				'ai' => isset($data['ai']) ? sanitize_text_field($data['ai']) : 'chatgpt',
				'apikey' => isset($data['apikey']) ? sanitize_text_field($data['apikey']) : '',
				'model' => isset($data['model']) ? sanitize_text_field($data['model']) : '',
				'enabled' => isset($data['enabled']) ? (bool)$data['enabled'] : false,
			];
			
			update_option('openAI_settings', json_encode($settings));
		}

		$ucisettings = get_option('sm_uci_pro_settings');
		foreach ($ucisettings as $key => $val) {
			$settings[$key] = json_decode($val);
		}
		$result['options'] = $settings;
		echo wp_json_encode($result);
		wp_die();
	}
}