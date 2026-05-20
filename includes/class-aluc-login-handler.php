<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ALUC_Login_Handler {

    private $message = '';
    private $errorMessage = '';

    public static function init() {
        $self = new self();

        add_action('admin_menu',[ $self, 'admin_login_url_change_add_page' ] );
        add_filter( 'plugin_action_links_' . ALUC_PLUGIN_BASE,[ $self, 'admin_login_url_change_page_settings' ] );
        add_action('admin_enqueue_scripts',[ $self, 'admin_login_url_change_css' ] );

        add_action( 'init', [ $self, 'block_admin_access' ], 0 );
        add_filter( 'login_redirect', [ $self, 'fix_login_redirect' ], 10, 3 );
        add_filter( 'wp_redirect', [ $self, 'rewrite_wp_login_redirects' ], 2, 2 );

        add_action( 'init', [ $self, 'register_custom_login_rewrite' ] );
        add_filter( 'query_vars', [ $self, 'add_query_vars' ] );
        add_action( 'template_redirect', [ $self, 'load_custom_login_template' ] );

        add_action( 'plugins_loaded', [ $self, 'auto_flush_if_slug_changed' ] );
        add_action( 'admin_init', [ $self, 'auto_flush_when_plugin_update' ] );
        add_action( 'init', [ $self, 'maybe_flush_rewrites' ], 999 );

        add_action( 'wp_logout', [ $self, 'custom_logout_redirect' ] );
        add_filter( 'logout_url', [ $self, 'custom_logout_url' ], 10, 2 );
        add_filter( 'lostpassword_url', [ $self, 'custom_lostpassword_url'], 10, 2 );
        add_filter( 'login_url', [ $self, 'custom_login_url'], 10, 3 );
        add_filter( 'robots_txt', [ $self, 'block_robots' ], 99, 2 );
        add_filter( 'retrieve_password_message', [ $self, 'modify_reset_password_email' ], 10, 3 );

        add_action('wp_ajax_aluc_save_slug',  [ $self, 'aluc_save_slug_ajax' ] );
    }

    /**
     * Get saved slug
     */
    private function get_slug() {
        return get_option( 'jh_new_login_url' );
    }

    function admin_login_url_change_add_page() {
        add_submenu_page( 'options-general.php', 'Admin login URL Change', 'Admin login URL Change', 'manage_options', 'admin-login-url-change', array( &$this, 'settingsPanel' ) );
    }

    function settingsPanel() {
        if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'admin-login-url-change' ) );
        }

        $aluc_info =  !empty(get_option( 'jh_new_login_url' )) ? get_option( 'jh_new_login_url' ) : '';
        ?>
        <div class="wrap">
            <h2 class="wp-admin-change-title">
            <?php esc_html_e( 'Admin login URL Change','admin-login-url-change'); ?> &raquo; <?php esc_html_e( 'Settings','admin-login-url-change'); ?> </h2>
            <div class="jh-admin-setting-box">
                <div class="jh-admin-setting-form">
                    <div class="aluc-success updated fade" style="display: none;">
                        <p></p>
                    </div>

                    <div class="aluc-error error fade" style="display: none;">
                        <p></p>
                    </div>

                    <?php
                        $aluc_form_action = admin_url('options-general.php?page=admin-login-url-change');
                    ?>
                    <div class="admin_url_notes">
                        <ul>
                        <li><code><?php esc_html_e("No need to add your domain name. Just enter your new login slug. Example: newadmin, adminlogin, etc.","admin-login-url-change"); ?></code></li>
                        </ul>
                    </div>
                    <div class="wp-admin-change-box">
                        <form method='post' action='<?php echo esc_html($aluc_form_action); ?>'>
                        <p>
                            <label for="aluc-new-login-url"><?php esc_html_e("Add New Login Slug","admin-login-url-change"); ?></label>
                            <input type="text" name="aluc-new-login-url" id="aluc-new-login-url" placeholder="Example: newadmin/adminlogin .... etc" value="<?php echo esc_html($aluc_info); ?>" />
                        </p>
                        
                        <?php wp_nonce_field( 'aluc_login_url_nonce_action', 'aluc_login_url_nonce' ); ?>
                        <p>
                            <input type='submit' class="" name='aluc_submit' id="aluc-save-btn" value='<?php esc_attr_e("Submit","admin-login-url-change"); ?>'>
                        </p>
                        </form>
                    </div>
                </div>

                <div class="jh-link-boxs">

                    <div class="jh-link-box">
                        <a href="http://wpassisthub.com/contact/" target="_blank">
                        <img src="<?php echo esc_url(ALUC_PLUGIN_URL); ?>assets/images/jh-custom-service.png" alt="<?php esc_html_e("Custom Service","admin-login-url-change"); ?>">
                        <h3><?php esc_html_e("More Services","admin-login-url-change"); ?></h3>
                        <p><?php esc_html_e("We offer custom plugin development, website design, speed optimization, and full site customization—tailored to meet your unique needs and enhance your WordPress site's performance and functionality.","admin-login-url-change"); ?></p>
                        <span><?php esc_html_e("Contact Us","admin-login-url-change"); ?></span>
                        </a>
                    </div>
                    <div class="jh-link-box">
                        <a href="http://wpassisthub.com/contact/" target="_blank">
                        <img src="<?php echo esc_url(ALUC_PLUGIN_URL); ?>assets/images/jh-mail.png" alt="<?php esc_html_e("Mail","admin-login-url-change"); ?>">
                        <h3><?php esc_html_e("Mail Support","admin-login-url-change"); ?></h3>
                        <p><?php esc_html_e("Get reliable mail support from our team—fast, friendly assistance for your WordPress issues right in your inbox.","admin-login-url-change"); ?></p>
                        <span><?php esc_html_e("Contact Us","admin-login-url-change"); ?></span>
                        
                        </a>
                    </div>
                    <div class="jh-link-box">
                        <a href="http://wpassisthub.com/" target="_blank">
                        <img src="<?php echo esc_url(ALUC_PLUGIN_URL); ?>assets/images/jh-comment.png" alt="<?php esc_html_e("Live Chat","admin-login-url-change"); ?>">
                        <h3><?php esc_html_e("Live Chat","admin-login-url-change"); ?></h3>
                        <p><?php esc_html_e("Connect with us instantly through live chat for quick, real-time support and solutions to your WordPress questions and issues.","admin-login-url-change"); ?></p>
                        <span><?php esc_html_e("Contact Us","admin-login-url-change"); ?></span>
                        </a>
                    </div>

                </div>
            </div>
        </div>

    <?php
    }

    public function aluc_save_slug_ajax() {

        // Check capability.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
            wp_die();
        }

        // Check if a nonce is valid.
        if ( ! isset( $_POST['_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ), 'aluc-ajax-nonce' ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce' ] );
            wp_die();
        }

        // Allowed: letters, numbers, underscores
        $slug = preg_replace('/[^A-Za-z0-9_]/', '', $_POST['slug']);
    
        if ( empty($slug) ) {
            wp_send_json_error(['message' => 'Only letters, numbers, and Underscores allowed']);
        }
    
        $old = get_option('jh_new_login_url');
        update_option('jh_new_login_url', $slug);
    
        // Set rewrite flush flag
        if ($slug !== $old) {
            update_option('aluc_need_flush', 1);
        }
    
        wp_send_json_success([
            'message' => 'Saved successfully!',
            'slug'    => $slug
        ]);
        
        wp_die();
    }

    function admin_login_url_change_page_settings( $links ) {
        $link = sprintf( "<a href='%s' style='color:#2271b1;'>%s</a>", admin_url( 'options-general.php?page=admin-login-url-change' ), __( 'Settings', 'admin-login-url-change' ) );
        array_push( $links, $link );
    
        return $links;
    }

    function admin_login_url_change_css(){
        wp_enqueue_style( 'admin-login-url-change-css', ALUC_PLUGIN_URL .'/assets/css/style.css', false, VERSION);

        wp_enqueue_script('admin-login-url-change-js', ALUC_PLUGIN_URL .'/assets/js/main.js', [], VERSION, true); 
        wp_localize_script('admin-login-url-change-js', 'aluc_core', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aluc-ajax-nonce'),
        ));

    }

    /**
     * Block wp-admin & wp-login
     */
    public function block_admin_access() {
        $slug = $this->get_slug();
        if ( ! $slug ) return;

        $req = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path = trim( wp_parse_url( $req, PHP_URL_PATH ), '/' );
        
        if ( ! is_user_logged_in() && 
            ( preg_match('#(^|/)wp-admin(/|$)#i', $path) || preg_match('#(^|/)admin(/|$)#i', $path) ) ) {
            remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
            wp_safe_redirect( home_url('/404'), 302 );
            exit;
        }

        if ( strtolower($path) === 'wp-login.php' &&
             strtoupper($_SERVER['REQUEST_METHOD']) === 'GET' ) {
            status_header(404);
            exit;
        }
    }

    /** Fix redirect to dashboard */
    public function fix_login_redirect($redirect_to, $requested, $user) {
        if ( isset($user->ID) && $user->ID > 0 ) {
            if ( empty($redirect_to) || strpos($redirect_to, 'wp-login.php') !== false ) {
                return admin_url();
            }
        }
        return $redirect_to;
    }

    /** Rewrite redirects */
    public function rewrite_wp_login_redirects($location) {
        if ( stripos($location, 'wp-login.php') === false ) return $location;

        $slug = $this->get_slug();
        if ( strpos($location, 'checkemail=confirm') !== false ) {
            return home_url("/{$slug}/?checkemail=confirm");
        }
        return $location;
    }

    /** Serve login form at /slug */
    public function register_custom_login_rewrite() {
        $slug = $this->get_slug();
        if (! $slug) return;

        add_rewrite_rule("^{$slug}/?$", 'index.php?aluc_login=1', 'top');
    }

    public function add_query_vars($vars) {
        $vars[] = 'aluc_login';
        return $vars;
    }

    public function load_custom_login_template() {
        if ( get_query_var('aluc_login') ) {
            nocache_headers();
            header('X-Robots-Tag: noindex, nofollow, noarchive', true);
            global $pagenow, $user_login, $error;
            $pagenow = 'wp-login.php';
            require_once ABSPATH . 'wp-login.php';
            exit;
        }
    }

    /** Logout redirect */
    public function custom_logout_redirect() {
        $slug = $this->get_slug();
        wp_safe_redirect( home_url("/{$slug}/?loggedout=true") );
        exit;
    }

    /** Logout URL override */
    public function custom_logout_url($url, $redirect) {
        $slug = $this->get_slug();
        $url = home_url("/{$slug}/?action=logout");

        if ($redirect) {
            $url = add_query_arg('redirect_to', urlencode($redirect), $url);
        }
        return wp_nonce_url($url, 'log-out');
    }

    /** Lost password */
    public function custom_lostpassword_url($url, $redirect) {
        $slug = $this->get_slug();
        $url = home_url("/{$slug}/?action=lostpassword");

        if ($redirect) {
            $url = add_query_arg('redirect_to', urlencode($redirect), $url);
        }
        return $url;
    }

    /** Login URL */
    public function custom_login_url($login_url, $redirect, $reauth) {
        $slug = $this->get_slug();
        $url = home_url("/{$slug}/");

        if ($redirect) {
            $url = add_query_arg('redirect_to', urlencode($redirect), $url);
        }
        if ($reauth) {
            $url = add_query_arg('reauth', '1', $url);
        }
        return $url;
    }

    /**
     * Auto flush if slug changed
     */
    public function auto_flush_if_slug_changed() {

        $slug     = $this->get_slug();
        $stored   = get_option( 'aluc_runtime_slug', '' );

        if ( $slug !== $stored ) {
            update_option( 'aluc_runtime_slug', $slug );
            flush_rewrite_rules();
        }
    }

    /**
     * Auto flush when plugin update
     */
    public function auto_flush_when_plugin_update() {
        $stored   = get_option( 'aluc_runtime_slug_flush', '' );
        if ( empty($stored) ) {
            update_option( 'aluc_runtime_slug_flush', 1 );
            flush_rewrite_rules();
        }
    }

    /** Flush rewrite rules when settings are updated */
    public function maybe_flush_rewrites() {
        if ( get_option('aluc_need_flush') ) {
            flush_rewrite_rules();
            delete_option('aluc_need_flush');
        }
    }
    

    /** Block search engines */
    public function block_robots($output) {
        $slug = $this->get_slug();
        $rules  = "\n# Admin Login URL Change\nUser-agent: *\n";
        $rules .= "Disallow: /{$slug}/\n";
        $rules .= "Disallow: /wp-login.php\n";
        $rules .= "Disallow: /wp-admin/\n";
        return $output . $rules;
    }

    /** Reset email URL replacement */
    public function modify_reset_password_email($message, $key, $user_login) {
        $slug = $this->get_slug();
        $reset_url = home_url("/{$slug}/?action=rp&key={$key}&login=" . rawurlencode($user_login));

        return preg_replace('/https?:\/\/[^\s]+/i', $reset_url, $message);
    }
}
