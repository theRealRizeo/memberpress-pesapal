<?php
/*
 Plugin Name:         PesaPal MemberPress
 Description:         PesaPal integration for MemberPress plugin 
 Version:             1.0.0
 Author:              Paul Kevin
 Author URI:          https://www.hubloy.com
 Text Domain:         pesapal-memberpress
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( !class_exists( 'MemberPress_PesaPal' ) ) :

    class MemberPress_PesaPal {
        /**
		 * Current plugin version.
		 *
		 * @since 1.0.0
		 * 
		 * @var string
		 */
		public $version = '1.0.0';

		/**
		 * The single instance of the class
		 *
		 * @since 1.0.0
		 */
		protected static $_instance = null;

		/**
		 * Get the instance
		 * 
		 * @since 1.0.0
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Main plugin constructor
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
            $this->define_constants();
            
            require_once( MPPPAL_PLUGIN_INCLUDES_DIR . 'pesapal/PesaPalOAuth.php' );

			add_filter( 'mepr-currency-codes', array( $this, 'currency_codes' ) );
			add_filter( 'mepr-currency-symbols', array( $this, 'currency_symbols' ) );
			add_filter( 'mepr-gateway-paths', array( $this, 'init_gateway' ) );
        }

        /**
		 * Define plugin constants
		 *
		 * @since 1.0.0
		 */
		protected function define_constants() {
			$this->define( 'MPPPAL_VERSION', $this->version );
			$this->define( 'MPPPAL_PLUGIN_FILE', __FILE__ );
			$this->define( 'MPPPAL_PLUGIN', plugin_basename( __FILE__ ) );
			$this->define( 'MPPPAL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
            $this->define( 'MPPPAL_PLUGIN_BASE_DIR', dirname( __FILE__ ) );
            $this->define( 'MPPPAL_PLUGIN_INCLUDES_DIR', MPPPAL_PLUGIN_BASE_DIR . '/includes/' );
			$this->define( 'MPPPAL_PLUGIN_URL', plugin_dir_url(__FILE__));
			$this->define( 'MPPPAL_ASSETS_URL', MPPPAL_PLUGIN_URL.'assets');
		}

		/**
		 * Define constant helper if not already set
		 *
		 * @param  string $name
		 * @param  string|bool $value
		 *
		 * @since 1.0.0
		 */
		private function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}
		
		function currency_codes( $codes ) {
			if ( !in_array( 'TZS', $codes ) ) {
				array_push( $codes, 'TZS' );
			}
			if ( !in_array( 'KES', $codes ) ) {
				array_push( $codes, 'KES' );
			}
			return $codes;
		}

        function currency_symbols( $symbols ) {
			if ( !in_array( 'Tsh', $symbols ) ) {
				array_push( $symbols, 'Tsh' );
			}
			if ( !in_array( 'KSh', $symbols ) ) {
				array_push( $symbols, 'KSh' );
			}
			return $symbols;
		}
		
		
        function init_gateway( $paths ) {
            array_push( $paths, MPPPAL_PLUGIN_INCLUDES_DIR . '/gateway' );
            return $paths;
        }
    }

    MemberPress_PesaPal::instance();

endif;
?>