<?php
namespace Acowebs\WCPA;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin autoloader.
 *
 * Plugin autoloader handler class is responsible for loading the different
 * classes needed to run the plugin.
 *
 * @since 5.0.0
 */
class Autoloader {

	/**
	 * Classes map.
	 *
	 * Maps Plugin classes to file names.
	 *
	 * @since 5.0.0
	 * @access private
	 * @static
	 *
	 * @var array Classes used by *.
	 */
	private static $classes_map;

	/**
	 * Classes aliases.
	 *
	 * Maps * classes to aliases.
	 *
	 * @since 5.0.0
	 * @access private
	 * @static
	 *
	 * @var array Classes aliases.
	 */
	private static $classes_aliases;

	/**
	 * Default path for autoloader.
	 *
	 * @var string
	 */
	private static $default_path;

	/**
	 * Default namespace for autoloader.
	 *
	 * @var string
	 */
	private static $default_namespace;

	/**
	 * Run autoloader.
	 *
	 * Register a function as `__autoload()` implementation.
	 *
	 * @param string $default_path
	 * @param string $default_namespace
	 *
	 * @since
	 * @access public
	 * @static
	 */
	public static function run( $default_path = '', $default_namespace = '' ) {
		if ( '' === $default_path ) {
			$default_path = WCPA_PATH;
		}

		if ( '' === $default_namespace ) {
			$default_namespace = __NAMESPACE__;
		}

		self::$default_path = $default_path;
		self::$default_namespace = $default_namespace;

		spl_autoload_register( [ __CLASS__, 'autoload' ] );
	}

	/**
	 * Get classes aliases.
	 *
	 * Retrieve the classes aliases names.
	 *
	 * @since 1.6.0
	 * @access public
	 * @static
	 *
	 * @return array Classes aliases.
	 */
	public static function get_classes_aliases() {
		if ( ! self::$classes_aliases ) {
			self::init_classes_aliases();
		}

		return self::$classes_aliases;
	}

	public static function get_classes_map() {
		if ( ! self::$classes_map ) {
			self::init_classes_map();
		}

		return self::$classes_map;
	}

	private static function init_classes_map() {
		self::$classes_map = [
			'Api' => 'includes/api.php',
			'Main' => 'includes/main.php',
			'Front' => 'includes/front.php',
			'Config' => 'includes/config.php',
			'Settings' => 'includes/backend/settings.php',
			'Form' => 'includes/form.php',
			'Admin' => 'includes/backend/admin.php',
			'Options' => 'includes/options.php',
			'BackendApi' => 'includes/backend/api.php',
			'ML' => 'includes/compatibility/ml.php',
            'FormSettings'=>'includes/form-settings.php',
            'Cron'=>'includes/cron.php',
            'Render'=>'includes/render/render.php',
            'Product'=>'includes/render/product.php',
            'Product_Meta'=>'includes/backend/product-meta.php',
            'Updater'=>'includes/backend/updater.php',
            'Designs'=>'includes/backend/designs.php',
            'Process'=>'includes/process/process.php',
            'ReadForm'=>'includes/process/read-form.php',
            'CLogic'=>'includes/process/conditional-logic.php',
            'FormValidation'=>'includes/process/form-validation.php',
            'Cart'=>'includes/process/cart.php',
            'Price'=>'includes/process/price.php',
            'Weight'=>'includes/process/weight.php',
            'Customer'=>'includes/customer.php',
            'File'=>'includes/process/file.php',
            'Migration'=>'includes/migration.php',
            'Themes'=>'includes/backend/themes.php',
            'Currency'=>'includes/compatibility/currency.php',
            'Discounts'=>'includes/compatibility/discounts.php',
            'Order'=>'includes/order/order.php',
            'MetaDisplay'=>'includes/meta-display.php',
            'OrderMetaLineItem'=>'includes/order/meta-line-item.php',
            'WPMLCurrency'=>'includes/compatibility/wpml_currency.php',
            'Comp'=>'includes/compatibility/comp.php',
            'Tus'=>'includes/tus.php',
            'S3'=>'includes/s3.php',
		];


	}



	private static function init_classes_aliases() {
		self::$classes_aliases = [
//			'System_Info\Main' => [
//				'replacement' => 'Modules\System_Info\Module',
//				'version' => '2.9.0',
//			],
//			'System_Info\Classes\Abstracts\Base_Reporter' => [
//				'replacement' => 'Modules\System_Info\Reporters\Base',
//				'version' => '2.9.0',
//			]
		];
	}

	/**
	 * Load class.
	 *
	 * For a given class name, require the class file.
	 *
	 * @since 1.6.0
	 * @access private
	 * @static
	 *
	 * @param string $relative_class_name Class name.
	 */
	private static function load_class( $relative_class_name ) {
		$classes_map = self::get_classes_map();

		if ( isset( $classes_map[ $relative_class_name ] ) ) {
			$filename = self::$default_path . '/' . $classes_map[ $relative_class_name ];
		} else {
			$filename = strtolower(
				preg_replace(
					[ '/([a-z])([A-Z])/', '/_/', '/\\\/' ],
					[ '$1-$2', '-', DIRECTORY_SEPARATOR ],
					$relative_class_name
				)
			);

			$filename = self::$default_path . $filename . '.php';
		}

		if ( is_readable( $filename ) ) {
			require $filename;
		}
	}

	/**
	 * Autoload.
	 *
	 * For a given class, check if it exist and load it.
	 *
	 * @since 1.6.0
	 * @access private
	 * @static
	 *
	 * @param string $class Class name.
	 */
	private static function autoload( $class ) {
		if ( 0 !== strpos( $class, self::$default_namespace . '\\' ) ) {
			return;
		}

		$relative_class_name = str_replace( self::$default_namespace.'\\', '', $class );

		$classes_aliases = self::get_classes_aliases();

		$has_class_alias = isset( $classes_aliases[ $relative_class_name ] );

		$final_class_name = self::$default_namespace . '\\' . $relative_class_name;

		if ( ! class_exists( $final_class_name ) ) {
			self::load_class( $relative_class_name );
		}

		if ( $has_class_alias ) {
			class_alias( $final_class_name, $class );

		}
	}
}
