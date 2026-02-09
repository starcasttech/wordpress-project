<?php
if( ! defined( 'ABSPATH' ) ) {
    exit;
}
if ( ! class_exists( 'Wt_Gdpr_Promotion_banner' ) ) {
    class Wt_Gdpr_Promotion_banner {
        
        private $banner_id = 'wt-gdpr-promotion-banner';
        private static $banner_state_option_name = "wt_gdpr_promotion_banner_state";
        private $banner_state = 1;
        private static $show_banner = null;
        private static $ajax_action_name = "wt_gdpr_promotion_banner_state";
        private static $promotion_link = "https://www.webtoffee.com/product/gdpr-cookie-consent/?utm_source=free_plugin_import_export_suite&utm_medium=import_export_premium&utm_campaign=GDPR"; // Update the promotion link depending on the plugin
        private static $banner_version = '';
        public function __construct( ) {
            $this->banner_state = get_option( self::$banner_state_option_name ); // Get the banner's current state from database
            $this->banner_state = absint( false === $this->banner_state ? 1 : $this->banner_state );
            self::$banner_version = WT_IEW_VERSION; // Plugin version
            // Enqueue styles
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
            // Add banner
            add_action( 'admin_notices', array( $this, 'show_banner' ) );
            //Add banner scripts
			add_action( 'admin_print_footer_scripts', array( $this, 'add_banner_scripts' ) );
            // Ajax hook to save banner state
			add_action( 'wp_ajax_' . self::$ajax_action_name, array( $this, 'update_banner_state' ) ); 
        }
        /**
         * To add the banner styles
         *
         * @return void
         */
        public function enqueue_styles() {
            wp_enqueue_style( $this->banner_id, plugin_dir_url( __FILE__ ) . '../css/wt-gdpr-promotion-banner.css', array(), self::$banner_version, 'all' );
        }
        /**
         * To show the banner, depending on the banner state
         *
         * @return void
         */
        public function show_banner() {
            $current_screen = get_current_screen();
            if ( $this->is_show_banner() && $current_screen->base === 'dashboard' ) {
            ?>
            <div class="wt-gdpr-promotion-banner notice notice-info is-dismissible">
                <div class="wt-gdpr-promotion-banner-header">
                    <p><?php esc_html_e( 'Get Cookie Consent With Google-Certified CMP for WordPress', 'wt-import-export-for-woo' ); ?></p>
                </div>
                <div class="wt-gdpr-promotion-banner-body">
                    <p>
                        <?php 
                            echo sprintf(
                                esc_html__('Trusted by %1$s WordPress websites for cookie compliance with %2$s.', 'wt-import-export-for-woo'),
                                '<span>' . esc_html__( '1 Million+', 'wt-import-export-for-woo' ) . '</span>',
                                '<span>' . esc_html__( 'GPDF, CCPA', 'wt-import-export-for-woo' ) . '</span>',
                                '<span>' . esc_html__( 'US State laws', 'wt-import-export-for-woo' ) . '</span>'
                            );
                        ?>
                    </p>
                    <ul class="wt-gdpr-promotion-banner-highlights">
                        <li><?php esc_html_e( 'Cookie banner with Accept & Reject buttons', 'wt-import-export-for-woo' ); ?></li>
                        <li><?php esc_html_e( 'Opt-in and opt-out consent', 'wt-import-export-for-woo' ); ?></li>
                        <li><?php esc_html_e( 'Advanced cookie scanner', 'wt-import-export-for-woo' ); ?></li>
                        <li><?php esc_html_e( 'Automatic script blocker', 'wt-import-export-for-woo' ); ?></li>
                        <li><?php esc_html_e( 'Obtain granular consent for cookies', 'wt-import-export-for-woo' ); ?></li>
                        <li><?php esc_html_e( 'Supports Google Consent Mode v2', 'wt-import-export-for-woo' ); ?></li>
                    </ul>
                </div>
                <div class="wt-gdpr-promotion-banner-footer">
                    <a href="<?php echo esc_url( self::$promotion_link ); ?>" target="_blank" class="button button-primary product-page-btn"><?php esc_html_e( 'Get WP GDPR Cookie Consent', 'wt-import-export-for-woo' ); ?></a>
                    <a class="button banner-dismiss-btn"><?php esc_html_e( 'Dismiss forever', 'wt-import-export-for-woo' ); ?></a>
                </div>
            </div>
            <?php
            }
        }
        /**
         * To check if the banner should be shown
         *
         * @return boolean
         */
        private function is_show_banner() {
            if ( ! is_null( self::$show_banner ) ) { // Already checked
    			return self::$show_banner;
    		}
            
    		/**
    		 * 	Check current banner state
    		 */
            
    		if ( 1 === $this->banner_state ) {
    			self::$show_banner = true;
    			return self::$show_banner;
    		} else {
                self::$show_banner = false;
                return self::$show_banner;
            }
        }
        /**
         * Script to handle banner actions
         *
         * @return void
         */
        public function add_banner_scripts() {
			
			if( $this->is_show_banner() ) { // Show the banner
				$ajax_url = admin_url( 'admin-ajax.php' );
        		$nonce = wp_create_nonce( 'wt_gdpr_promotion_banner' );
				?>
		        <script type="text/javascript">
		        	(function($) {
		                "use strict";
		                /* Prepare ajax data object */
		                var data_obj = {
		                    _wpnonce: '<?php echo esc_html( $nonce ); ?>',
		                    action: '<?php echo esc_html( self::$ajax_action_name ); ?>',
		                    wt_gdpr_promotion_banner_action_type: ''
		                };
		                $(document).on('click', '.wt-gdpr-promotion-banner .product-page-btn', function(e) {
		                    
		                    e.preventDefault(); 
		                    var elm = $(this);
		                    window.open('<?php echo esc_url(self::$promotion_link); ?>'); 
		                    elm.parents('.wt-gdpr-promotion-banner').hide();
		                    data_obj['wt_gdpr_promotion_banner_action_type'] = 3; // Clicked the button
		                    
		                    $.ajax({
		                        url: '<?php echo esc_url($ajax_url); ?>',
		                        data: data_obj,
		                        type: 'POST'
		                    });
		                }).on('click', '.wt-gdpr-promotion-banner .notice-dismiss, .wt-gdpr-promotion-banner .banner-dismiss-btn', function(e) {
		                    e.preventDefault();
                            var elm = $(this);
                            elm.parents('.wt-gdpr-promotion-banner').hide();
		                    data_obj['wt_gdpr_promotion_banner_action_type'] = 2; // Closed by user
		                    
		                    $.ajax({
		                        url: '<?php echo esc_url($ajax_url); ?>',
		                        data: data_obj,
		                        type: 'POST',
		                    });
		                });
	            })(jQuery)
		        </script>
		        <?php
			}
    	}
        /**
    	 * 	Update banner state ajax hook
    	 * 
    	 */
    	public function update_banner_state() {
    		check_ajax_referer( 'wt_gdpr_promotion_banner' );
    		if ( isset( $_POST['wt_gdpr_promotion_banner_action_type'] ) ) {
	            
	            $action_type = absint( sanitize_text_field($_POST['wt_gdpr_promotion_banner_action_type']) );
	            
                // Current action is allowed?
	            if ( in_array( $action_type, array( 2, 3 ) ) ) {
	                update_option( self::$banner_state_option_name, $action_type );
	            }
	        }
	        exit();
    	}
    }
    new Wt_Gdpr_Promotion_banner();
}