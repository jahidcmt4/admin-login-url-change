<?php
/**
 * Plugin Name:       Admin login URL Change
 * Plugin URI:        https://wordpress.org/plugins/admin-login-url-change/
 * Description:       Allows you to Change your WordPress WebSite Login URL.
 * Version:           1.1.6
 * Requires at least: 4.7
 * Tested up to:      6.9
 * Requires PHP:      5.3
 * Author:            jahidcse
 * Author URI:        https://profiles.wordpress.org/jahidcse/
 * Text Domain:       admin-login-url-change
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main Plugin Class
 */
final class ALUC_Plugin {

    const VERSION = '1.1.6';
    const TEXT_DOMAIN = 'admin-login-url-change';

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    private function define_constants() {
        define( 'VERSION', self::VERSION );
        define( 'ALUC_PLUGIN_FILE', __FILE__ );
        define( 'ALUC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
        define( 'ALUC_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
        define( 'ALUC_PLUGIN_BASE',  plugin_basename( __FILE__ ) );
    }

    private function includes() {
        require_once ALUC_PLUGIN_PATH . 'includes/class-aluc-login-handler.php';
    }

    private function init_hooks() {
        add_action( 'plugins_loaded', [ 'ALUC_Login_Handler', 'init' ] );
        add_action( 'activated_plugin', array($this, 'aluc_activated_callback'));
    }

    public function aluc_activated_callback($plugin){
        if ( plugin_basename( __FILE__ ) == $plugin ) {
            wp_redirect( admin_url( 'options-general.php?page=admin-login-url-change') );
            die();
        }
    }
}

/**
 * Init plugin
 */
function ALUC() {
    return ALUC_Plugin::instance();
}
ALUC();

/**
 * Activation / Deactivation
 */
register_activation_hook( __FILE__, function () {
    ALUC(); // load plugin
    flush_rewrite_rules();
});

register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
});
