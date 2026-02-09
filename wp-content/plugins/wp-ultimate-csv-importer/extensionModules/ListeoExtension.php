<?php
/******************************************************************************************
 * Copyright (C) Smackcoders. - All Rights Reserved under Smackcoders Proprietary License
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * You can contact Smackcoders at email address info@smackcoders.com.
 *******************************************************************************************/

namespace Smackcoders\FCSV;

if ( ! defined( 'ABSPATH' ) )
    exit; // Exit if accessed directly

class ListeoExtension extends ExtensionHandler {
	private static $instance = null;

    public static function getInstance() {

		if (ListeoExtension::$instance == null) {
			ListeoExtension::$instance = new ListeoExtension;
		}
		return ListeoExtension::$instance;
    }

	/**
	 * Provides Listeo User Profile fields for CSV mapping
	 * @param string $data - selected import type
	 * @return array - mapping fields
	 */
	public function processExtension( $data ) {

		$listeo_fields = [
    'Avatar'            => 'listeo_core_avatar_id',
    'Verified User'     => 'listeo_verified_user',
    'Phone'             => 'phone',
    'Twitter'           => 'twitter',
    'Facebook'          => 'facebook',
    'LinkedIn'          => 'linkedin',
    'Instagram'         => 'instagram',
    'YouTube'           => 'youtube',
    'Skype'             => 'skype',
    'WhatsApp'          => 'whatsapp',
    'Stripe User ID'    => 'stripe_user_id',
];


		$mapping_array = $this->convert_static_fields_to_array($listeo_fields);

		$response = [];
		$response['listeo_fields'] = $mapping_array;

		return $response;
	}

	/**
	 * Listeo extension supported import types
	 * @param string $import_type
	 * @return boolean
	 */
	public function extensionSupportedImportType( $import_type ) {

		$plugin_active = is_plugin_active( 'listeo-core/listeo-core.php' );

		if ( ! $plugin_active ) {
			return false;
		}

		if ( $import_type == 'nav_menu_item' ) {
			return false;
		}

		$import_type = $this->import_name_as( $import_type );

		$supported = array(
			'Users' // Listeo only adds user fields
		);

		return in_array( $import_type, $supported, true );
	}
}
