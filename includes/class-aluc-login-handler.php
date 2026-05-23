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

        // Suppress all WP admin notices on our settings page
        add_action( 'admin_head', [ $self, 'suppress_admin_notices' ] );

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

        add_action('wp_ajax_aluc_save_slug',   [ $self, 'aluc_save_slug_ajax' ] );
        add_action('wp_ajax_aluc_save_option', [ $self, 'aluc_save_option_ajax' ] );
    }

    /**
     * Get saved slug
     */
    private function get_slug() {
        return get_option( 'jh_new_login_url' );
    }

    function admin_login_url_change_add_page() {
        add_menu_page( 'Admin login URL Change', 'Admin Login Slug', 'manage_options', 'admin-login-url-change', array( &$this, 'settingsPanel' ), 'dashicons-lock', 40 );
    }

    /**
     * Suppress WP admin notices on our settings page
     */
    public function suppress_admin_notices() {
        $screen = get_current_screen();
        if ( $screen && 'toplevel_page_admin-login-url-change' === $screen->id ) {
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
        }
    }

    function settingsPanel() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'admin-login-url-change' ) );
        }

        // Check if Pro plugin is active
        $is_pro = apply_filters( 'aluc_pro_active', false );

        $slug         = get_option( 'jh_new_login_url', '' );
        $home_url     = trailingslashit( home_url() );
        $current_url  = $slug ? $home_url . $slug . '/' : esc_html__( 'Not set yet', 'admin-login-url-change' );

        // Security score: 1 point per active feature
        $score = 0;
        if ( $slug )                                             $score += 40;  // custom login URL
        if ( get_option('aluc_block_direct_wp_login', 0) )      $score += 20;  // block wp-login direct
        if ( get_option('aluc_hide_login_errors', 0) )          $score += 15;  // hide login errors
        if ( get_option('aluc_limit_login_attempts', 0) )       $score += 25;  // PRO
        $score = min( $score, 100 );

        $circumference = 2 * 3.14159 * 45; // ≈ 283
        ?>
        <div class="wrap" id="aluc-settings-wrap" <?php echo $is_pro ? 'data-pro="1"' : ''; ?>>

            <!-- Page Header -->
            <div class="aluc-page-header">
                <div class="aluc-page-header-left">
                    <div class="aluc-page-logo">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                        </svg>
                    </div>
                    <div>
                        <h1><?php esc_html_e( 'Admin Login URL Change', 'admin-login-url-change' ); ?>
                            <span>v<?php echo esc_html( VERSION ); ?></span>
                            <?php if ( $is_pro ) : ?>
                            <span class="aluc-pro-version-badge">PRO</span>
                            <?php endif; ?>
                        </h1>
                    </div>
                </div>
                <div class="aluc-page-header-right">
                    <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=admin-login-url-change-pricing' ) ); ?>" class="aluc-header-upgrade-btn" id="aluc-header-upgrade-btn">
                        <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <?php esc_html_e( 'Upgrade to Pro', 'admin-login-url-change' ); ?>
                    </a>
                    <?php endif; ?>
                    <div class="aluc-header-badge"><?php esc_html_e( 'Active & Protected', 'admin-login-url-change' ); ?></div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <nav class="aluc-tab-nav" id="aluc-tab-nav">
                <button class="aluc-tab-btn aluc-active" data-tab="settings">
                    <svg viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                    <?php esc_html_e( 'Settings', 'admin-login-url-change' ); ?>
                </button>
                <button class="aluc-tab-btn" data-tab="other">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                    <?php esc_html_e( 'Other Settings', 'admin-login-url-change' ); ?>
                </button>
                <button class="aluc-tab-btn" data-tab="ip-block" data-pro="1">
                    <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                    <?php esc_html_e( 'IP Blocker', 'admin-login-url-change' ); ?>
                    <?php if ( ! $is_pro ) : ?><span class="aluc-tab-pro-badge">PRO</span><?php endif; ?>
                </button>
                <button class="aluc-tab-btn" data-tab="country-block" data-pro="1">
                    <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                    <?php esc_html_e( 'Country Block', 'admin-login-url-change' ); ?>
                    <?php if ( ! $is_pro ) : ?><span class="aluc-tab-pro-badge">PRO</span><?php endif; ?>
                </button>
                <button class="aluc-tab-btn" data-tab="login-limit" data-pro="1">
                    <svg viewBox="0 0 24 24"><path d="M11 15h2v2h-2zm0-8h2v6h-2zm.99-5C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/></svg>
                    <?php esc_html_e( 'Login Limit', 'admin-login-url-change' ); ?>
                    <?php if ( ! $is_pro ) : ?><span class="aluc-tab-pro-badge">PRO</span><?php endif; ?>
                </button>
                <button class="aluc-tab-btn" data-tab="two-fa" data-pro="1">
                    <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                    <?php esc_html_e( '2FA', 'admin-login-url-change' ); ?>
                    <?php if ( ! $is_pro ) : ?><span class="aluc-tab-pro-badge">PRO</span><?php endif; ?>
                </button>
            </nav>

            <!-- Inline notices (replace WP default ones) -->
            <div class="aluc-notice aluc-notice-success" id="aluc-notice-success">
                <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                <span class="aluc-notice-msg"></span>
            </div>
            <div class="aluc-notice aluc-notice-error" id="aluc-notice-error">
                <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                <span class="aluc-notice-msg"></span>
            </div>

            <!-- ═══════════════ TAB: Settings ═══════════════ -->
            <div class="aluc-tab-panel aluc-active" id="aluc-panel-settings">
                <div class="aluc-layout">

                    <!-- Left column -->
                    <div class="aluc-main-col">

                        <!-- Current URL status -->
                        <?php if ( $slug ) : ?>
                        <div class="aluc-card">
                            <div class="aluc-card-body" style="padding: 16px 24px;">
                                <div class="aluc-current-url-box" style="margin:0;">
                                    <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                    <div>
                                        <div class="aluc-current-url-label"><?php esc_html_e( 'Current Login URL', 'admin-login-url-change' ); ?></div>
                                        <div class="aluc-current-url-value" id="aluc-current-url-display"><?php echo esc_html( $current_url ); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Change login slug -->
                        <div class="aluc-card">
                            <div class="aluc-card-header">
                                <div class="aluc-card-header-icon blue">
                                    <svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                                </div>
                                <div>
                                    <div class="aluc-card-title"><?php esc_html_e( 'Custom Login URL', 'admin-login-url-change' ); ?></div>
                                    <div class="aluc-card-subtitle"><?php esc_html_e( 'Change your WordPress login slug to a secret URL', 'admin-login-url-change' ); ?></div>
                                </div>
                            </div>
                            <div class="aluc-card-body">
                                <div class="aluc-form-group">
                                    <label class="aluc-label" for="aluc-new-login-url">
                                        <?php esc_html_e( 'Login Slug', 'admin-login-url-change' ); ?>
                                        <span class="aluc-label-required">*</span>
                                    </label>
                                    <div class="aluc-input-wrapper">
                                        <span class="aluc-input-prefix"><?php echo esc_html( trailingslashit( home_url() ) ); ?></span>
                                        <input
                                            type="text"
                                            class="aluc-input has-prefix"
                                            id="aluc-new-login-url"
                                            name="aluc-new-login-url"
                                            placeholder="e.g. mylogin"
                                            value="<?php echo esc_attr( $slug ); ?>"
                                            autocomplete="off"
                                        />
                                    </div>
                                    <div class="aluc-hint">
                                        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                                        <?php esc_html_e( 'Only letters, numbers, and underscores. Example: mylogin, adminpanel, securelogin', 'admin-login-url-change' ); ?>
                                    </div>
                                </div>
                                <?php wp_nonce_field( 'aluc_login_url_nonce_action', 'aluc_login_url_nonce' ); ?>
                                <button type="button" class="aluc-btn aluc-btn-primary" id="aluc-save-btn">
                                    <svg viewBox="0 0 24 24"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                                    <span class="aluc-btn-text"><?php esc_html_e( 'Save Changes', 'admin-login-url-change' ); ?></span>
                                </button>
                            </div>
                        </div>

                        <!-- Security checklist -->
                        <div class="aluc-card">
                            <div class="aluc-card-header">
                                <div class="aluc-card-header-icon green">
                                    <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
                                </div>
                                <div>
                                    <div class="aluc-card-title"><?php esc_html_e( 'Security Overview', 'admin-login-url-change' ); ?></div>
                                    <div class="aluc-card-subtitle"><?php esc_html_e( 'Active protection features on your site', 'admin-login-url-change' ); ?></div>
                                </div>
                            </div>
                            <div class="aluc-card-body">
                                <ul class="aluc-checklist">
                                    <li>
                                        <span class="aluc-check-icon <?php echo $slug ? 'ok' : 'no'; ?>">
                                            <svg viewBox="0 0 24 24"><path d="<?php echo $slug ? 'M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z' : 'M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z'; ?>"/></svg>
                                        </span>
                                        <?php esc_html_e( 'Custom login URL active', 'admin-login-url-change' ); ?>
                                    </li>
                                    <li>
                                        <span class="aluc-check-icon ok">
                                            <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                        </span>
                                        <?php esc_html_e( 'wp-login.php direct access blocked', 'admin-login-url-change' ); ?>
                                    </li>
                                    <li>
                                        <span class="aluc-check-icon ok">
                                            <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                        </span>
                                        <?php esc_html_e( 'Login URL hidden from robots.txt', 'admin-login-url-change' ); ?>
                                    </li>
                                    <li>
                                        <span class="aluc-check-icon warn">
                                            <svg viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                                        </span>
                                        <?php esc_html_e( 'Login attempt limiting — upgrade to Pro', 'admin-login-url-change' ); ?>
                                    </li>
                                    <li>
                                        <span class="aluc-check-icon warn">
                                            <svg viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                                        </span>
                                        <?php esc_html_e( 'Country / IP blocking — upgrade to Pro', 'admin-login-url-change' ); ?>
                                    </li>
                                </ul>
                            </div>
                        </div>

                    </div><!-- .aluc-main-col -->

                    <!-- Sidebar -->
                    <?php echo $this->render_sidebar( $score, $circumference, $is_pro ); ?>

                </div><!-- .aluc-layout -->
            </div><!-- #aluc-panel-settings -->

            <!-- ═══════════════ TAB: Other Settings ════════════ -->
            <div class="aluc-tab-panel" id="aluc-panel-other">
                <div class="aluc-layout">
                    <div class="aluc-main-col">
                        <div class="aluc-card">
                            <div class="aluc-card-header">
                                <div class="aluc-card-header-icon purple">
                                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                                </div>
                                <div>
                                    <div class="aluc-card-title"><?php esc_html_e( 'Security Enhancements', 'admin-login-url-change' ); ?></div>
                                    <div class="aluc-card-subtitle"><?php esc_html_e( 'Additional free security options', 'admin-login-url-change' ); ?></div>
                                </div>
                            </div>
                            <div class="aluc-card-body">
                                <div class="aluc-toggle-row">
                                    <div class="aluc-toggle-info">
                                        <h4><?php esc_html_e( 'Block Direct wp-login.php Access', 'admin-login-url-change' ); ?></h4>
                                        <p><?php esc_html_e( 'Return 404 when someone visits wp-login.php directly', 'admin-login-url-change' ); ?></p>
                                    </div>
                                    <label class="aluc-toggle">
                                        <input type="checkbox" class="aluc-free-toggle" data-option="aluc_block_direct_wp_login" <?php checked( get_option('aluc_block_direct_wp_login', 0), 1 ); ?>>
                                        <span class="aluc-toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="aluc-toggle-row">
                                    <div class="aluc-toggle-info">
                                        <h4><?php esc_html_e( 'Hide Login Error Messages', 'admin-login-url-change' ); ?></h4>
                                        <p><?php esc_html_e( 'Prevent revealing whether a username or password is incorrect', 'admin-login-url-change' ); ?></p>
                                    </div>
                                    <label class="aluc-toggle">
                                        <input type="checkbox" class="aluc-free-toggle" data-option="aluc_hide_login_errors" <?php checked( get_option('aluc_hide_login_errors', 0), 1 ); ?>>
                                        <span class="aluc-toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="aluc-toggle-row">
                                    <div class="aluc-toggle-info">
                                        <h4><?php esc_html_e( 'Disable XML-RPC', 'admin-login-url-change' ); ?></h4>
                                        <p><?php esc_html_e( 'Block XML-RPC requests which are often used in brute-force attacks', 'admin-login-url-change' ); ?></p>
                                    </div>
                                    <label class="aluc-toggle">
                                        <input type="checkbox" class="aluc-free-toggle" data-option="aluc_disable_xmlrpc" <?php checked( get_option('aluc_disable_xmlrpc', 0), 1 ); ?>>
                                        <span class="aluc-toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="aluc-toggle-row">
                                    <div class="aluc-toggle-info">
                                        <h4><?php esc_html_e( 'Remove WordPress Version from Source', 'admin-login-url-change' ); ?></h4>
                                        <p><?php esc_html_e( 'Hide the WP version number from page source and feeds', 'admin-login-url-change' ); ?></p>
                                    </div>
                                    <label class="aluc-toggle">
                                        <input type="checkbox" class="aluc-free-toggle" data-option="aluc_remove_wp_version" <?php checked( get_option('aluc_remove_wp_version', 0), 1 ); ?>>
                                        <span class="aluc-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php echo $this->render_sidebar( $score, $circumference, $is_pro ); ?>
                </div>
            </div><!-- #aluc-panel-other -->

            <!-- ═══════ PRO TAB PANELS ══════ -->
            <?php
            $pro_tabs = [
                'ip-block' => [
                    'label'    => __( 'IP Address Blocker', 'admin-login-url-change' ),
                    'icon'     => 'red',
                    'subtitle' => __( 'Block specific IP addresses from accessing your login page', 'admin-login-url-change' ),
                    'desc'     => __( 'Block individual IPs or entire IP ranges that are attacking your site. Supports both IPv4 and IPv6.', 'admin-login-url-change' ),
                    'features' => [
                        [ 'title' => __( 'Block individual IP addresses', 'admin-login-url-change' ),    'desc' => __( 'Add single IPs to block list instantly', 'admin-login-url-change' ) ],
                        [ 'title' => __( 'Block entire IP ranges (CIDR)', 'admin-login-url-change' ),    'desc' => __( 'Support for both IPv4 and IPv6 CIDR ranges', 'admin-login-url-change' ) ],
                        [ 'title' => __( 'Import / export block lists', 'admin-login-url-change' ),      'desc' => __( 'Manage large block lists with ease', 'admin-login-url-change' ) ],
                        [ 'title' => __( 'Automatic IP ban after failed logins', 'admin-login-url-change' ), 'desc' => __( 'Auto-ban bots and brute-force attackers', 'admin-login-url-change' ) ],
                    ],
                ],
                'country-block' => [
                    'label'    => __( 'Country Blocker', 'admin-login-url-change' ),
                    'icon'     => 'amber',
                    'subtitle' => __( 'Restrict login access by country / geo-location', 'admin-login-url-change' ),
                    'desc'     => __( 'Only allow logins from specific countries. Perfect if your team is in one location.', 'admin-login-url-change' ),
                    'features' => [
                        [ 'title' => __( 'Block countries with 1 click', 'admin-login-url-change' ),         'desc' => __( 'Simple country select UI', 'admin-login-url-change' ) ],
                        [ 'title' => __( 'Allowlist-only mode', 'admin-login-url-change' ),                  'desc' => __( 'Only let whitelisted countries log in', 'admin-login-url-change' ) ],
                        [ 'title' => __( 'GeoIP database auto-updated', 'admin-login-url-change' ),          'desc' => __( 'Always up-to-date geo-location data', 'admin-login-url-change' ) ],
                        [ 'title' => __( 'Custom redirect for blocked countries', 'admin-login-url-change' ), 'desc' => __( 'Show a custom page to blocked visitors', 'admin-login-url-change' ) ],
                    ],
                ],
                'login-limit' => [
                    'label'    => __( 'Login Attempt Limiter', 'admin-login-url-change' ),
                    'icon'     => 'blue',
                    'subtitle' => __( 'Stop brute-force attacks by limiting login attempts', 'admin-login-url-change' ),
                    'desc'     => __( 'Automatically lock out IPs that exceed a configurable number of failed login attempts.', 'admin-login-url-change' ),
                    'features' => [
                        [ 'title' => __( 'Configurable attempt threshold', 'admin-login-url-change' ), 'desc' => __( 'Set max attempts before lockout', 'admin-login-url-change' ) ],
                        [ 'title' => __( 'Auto lockout with custom duration', 'admin-login-url-change' ), 'desc' => __( 'Choose lockout time in minutes', 'admin-login-url-change' ) ],
                        [ 'title' => __( 'Email alerts on lockout', 'admin-login-url-change' ),         'desc' => __( 'Get notified about lockout events', 'admin-login-url-change' ) ],
                        [ 'title' => __( 'Allowlist trusted IPs', 'admin-login-url-change' ),           'desc' => __( 'Never lock out your own IP', 'admin-login-url-change' ) ],
                    ],
                ],
                'two-fa' => [
                    'label'    => __( 'Two-Factor Authentication', 'admin-login-url-change' ),
                    'icon'     => 'purple',
                    'subtitle' => __( 'Add an extra layer of security with 2FA', 'admin-login-url-change' ),
                    'desc'     => __( 'Require a time-based one-time password (TOTP) or email code on every login.', 'admin-login-url-change' ),
                    'features' => [
                        [ 'title' => __( 'Email-based OTP fallback', 'admin-login-url-change' ),            'desc' => __( 'Works without an authenticator app', 'admin-login-url-change' ) ],
                        [ 'title' => __( 'Trusted device remember (30 days)', 'admin-login-url-change' ),   'desc' => __( 'Skip 2FA on trusted devices', 'admin-login-url-change' ) ],
                    ],
                ],
            ];

            foreach ( $pro_tabs as $tab_id => $tab ) :
            ?>
            <div class="aluc-tab-panel" id="aluc-panel-<?php echo esc_attr( $tab_id ); ?>">
                <div class="aluc-layout">
                    <div class="aluc-main-col">
                        <?php if ( ! $is_pro ) : ?>
                        <!-- FREE: Clean upgrade page -->
                        <div class="aluc-upgrade-page-card">
                            <div class="aluc-upgrade-page-top">
                                <div class="aluc-upgrade-page-icon <?php echo esc_attr( $tab['icon'] ); ?>">
                                    <svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                                </div>
                                <div class="aluc-upgrade-page-badge"><?php esc_html_e( 'Pro Feature', 'admin-login-url-change' ); ?></div>
                                <h2><?php echo esc_html( $tab['label'] ); ?></h2>
                                <p class="aluc-upgrade-page-subtitle"><?php echo esc_html( $tab['desc'] ); ?></p>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=admin-login-url-change-pricing' ) ); ?>" class="aluc-upgrade-page-btn">
                                    <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                    <?php esc_html_e( 'Upgrade to Pro', 'admin-login-url-change' ); ?>
                                </a>
                            </div>
                            <div class="aluc-upgrade-page-features">
                                <h3><?php esc_html_e( 'What you get with Pro', 'admin-login-url-change' ); ?></h3>
                                <div class="aluc-upgrade-feature-grid">
                                    <?php foreach ( $tab['features'] as $f ) : ?>
                                    <div class="aluc-upgrade-feature-item">
                                        <div class="aluc-upgrade-feature-check">
                                            <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                        </div>
                                        <div>
                                            <strong><?php echo esc_html( $f['title'] ); ?></strong>
                                            <span><?php echo esc_html( $f['desc'] ); ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php else : ?>
                        <!-- PRO: Actual settings rendered by pro plugin -->
                        <div class="aluc-card">
                            <div class="aluc-card-header">
                                <div class="aluc-card-header-icon <?php echo esc_attr( $tab['icon'] ); ?>">
                                    <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
                                </div>
                                <div>
                                    <div class="aluc-card-title"><?php echo esc_html( $tab['label'] ); ?></div>
                                    <div class="aluc-card-subtitle"><?php echo esc_html( $tab['subtitle'] ); ?></div>
                                </div>
                            </div>
                            <div class="aluc-card-body">
                                <?php do_action( 'aluc_pro_tab_content_' . str_replace( '-', '_', $tab_id ) ); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php echo $this->render_sidebar( $score, $circumference, $is_pro ); ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ( ! $is_pro ) : ?>
            <!-- Footer Upgrade Banner -->
            <div class="aluc-footer-upgrade-banner" id="aluc-footer-upgrade-banner">
                <div class="aluc-footer-upgrade-inner">
                    <div class="aluc-footer-upgrade-left">
                        <div class="aluc-footer-upgrade-emoji">🚀</div>
                        <div>
                            <strong><?php esc_html_e( 'Unlock the full power of Admin Login URL Change Pro', 'admin-login-url-change' ); ?></strong>
                            <span><?php esc_html_e( 'IP Blocker · Country Block · Login Limiter · Two-Factor Auth · Priority Support', 'admin-login-url-change' ); ?></span>
                        </div>
                    </div>
                    <div class="aluc-footer-upgrade-right">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=admin-login-url-change-pricing' ) ); ?>" class="aluc-footer-upgrade-btn">
                            <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            <?php esc_html_e( 'Upgrade to Pro', 'admin-login-url-change' ); ?>
                        </a>
                        <button type="button" class="aluc-footer-upgrade-dismiss" id="aluc-banner-dismiss" title="Dismiss">
                            <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- #aluc-settings-wrap -->
    <?php
    }

    /**
     * Render the shared sidebar (security score + support links + upgrade card)
     */
    private function render_sidebar( $score, $circumference, $is_pro = false ) {
        ob_start();
        $slug = get_option( 'jh_new_login_url', '' );
        ?>
        <div class="aluc-sidebar">

            <!-- Security Score -->
            <div class="aluc-card">
                <div class="aluc-card-header">
                    <div class="aluc-card-header-icon blue">
                        <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
                    </div>
                    <div>
                        <div class="aluc-card-title"><?php esc_html_e( 'Security Score', 'admin-login-url-change' ); ?></div>
                        <div class="aluc-card-subtitle"><?php esc_html_e( 'Based on active features', 'admin-login-url-change' ); ?></div>
                    </div>
                </div>
                <div class="aluc-card-body" style="text-align:center;">
                    <div class="aluc-score-wrapper" style="display:inline-flex;">
                        <svg width="100" height="100" viewBox="0 0 100 100">
                            <defs>
                                <linearGradient id="scoreGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#1a73e8"/>
                                    <stop offset="100%" stop-color="#22c55e"/>
                                </linearGradient>
                            </defs>
                            <circle class="track" cx="50" cy="50" r="45"/>
                            <circle class="fill" id="aluc-score-fill" cx="50" cy="50" r="45"
                                data-total="<?php echo esc_attr( round( $circumference, 2 ) ); ?>"
                                data-pct="<?php echo esc_attr( $score ); ?>"
                                stroke-dasharray="<?php echo esc_attr( round( $circumference, 2 ) ); ?>"
                                stroke-dashoffset="<?php echo esc_attr( round( $circumference - ( $circumference * $score / 100 ), 2 ) ); ?>"
                            />
                        </svg>
                        <div class="aluc-score-center" style="position:absolute;">
                            <div class="score-num"><?php echo esc_html( $score ); ?></div>
                            <div class="score-label"><?php esc_html_e( '/100', 'admin-login-url-change' ); ?></div>
                        </div>
                    </div>
                    <p style="font-size:12px;color:#80868b;margin:10px 0 0;">
                        <?php
                        if ( $score >= 70 ) {
                            esc_html_e( 'Good protection! Upgrade to Pro for full cover.', 'admin-login-url-change' );
                        } elseif ( $score >= 40 ) {
                            esc_html_e( 'Fair — set a custom login URL to improve your score.', 'admin-login-url-change' );
                        } else {
                            esc_html_e( 'Set a custom login URL to start protecting your site.', 'admin-login-url-change' );
                        }
                        ?>
                    </p>
                </div>
            </div>

            <!-- Status widget -->
            <div class="aluc-status-widget">
                <h3><?php esc_html_e( 'Plugin Status', 'admin-login-url-change' ); ?></h3>
                <div class="aluc-status-row">
                    <span><?php esc_html_e( 'Version', 'admin-login-url-change' ); ?></span>
                    <span class="aluc-status-ok"><?php echo esc_html( VERSION ); ?></span>
                </div>
                <div class="aluc-status-row">
                    <span><?php esc_html_e( 'Login Slug', 'admin-login-url-change' ); ?></span>
                    <span class="aluc-status-ok"><?php echo $slug ? esc_html( $slug ) : '<span class="aluc-status-warn">' . esc_html__('Not set', 'admin-login-url-change') . '</span>'; ?></span>
                </div>
                <div class="aluc-status-row">
                    <span><?php esc_html_e( 'wp-login.php', 'admin-login-url-change' ); ?></span>
                    <span class="aluc-status-ok"><?php esc_html_e( 'Blocked', 'admin-login-url-change' ); ?></span>
                </div>
                <div class="aluc-status-row">
                    <span><?php esc_html_e( 'Robots.txt', 'admin-login-url-change' ); ?></span>
                    <span class="aluc-status-ok"><?php esc_html_e( 'Protected', 'admin-login-url-change' ); ?></span>
                </div>
            </div>

            <!-- Support links -->
            <div class="aluc-support-card">
                <a href="https://wordpress.org/support/plugin/admin-login-url-change/" target="_blank">
                    <div class="aluc-support-icon blue">
                        <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                    </div>
                    <div class="aluc-support-text">
                        <h4><?php esc_html_e( 'Community Support', 'admin-login-url-change' ); ?></h4>
                        <p><?php esc_html_e( 'Get help on WordPress.org forums', 'admin-login-url-change' ); ?></p>
                    </div>
                    <div class="aluc-support-arrow"><svg viewBox="0 0 24 24"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg></div>
                </a>
                <a href="https://wordpress.org/support/plugin/admin-login-url-change/reviews/?filter=5/#new-post" target="_blank">
                    <div class="aluc-support-icon orange">
                        <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                    </div>
                    <div class="aluc-support-text">
                        <h4><?php esc_html_e( 'Rate Us ⭐⭐⭐⭐⭐', 'admin-login-url-change' ); ?></h4>
                        <p><?php esc_html_e( 'Love the plugin? Leave a 5-star review', 'admin-login-url-change' ); ?></p>
                    </div>
                    <div class="aluc-support-arrow"><svg viewBox="0 0 24 24"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg></div>
                </a>
                <a href="https://wpassisthub.com/contact/" target="_blank">
                    <div class="aluc-support-icon green">
                        <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                    </div>
                    <div class="aluc-support-text">
                        <h4><?php esc_html_e( 'Email Support', 'admin-login-url-change' ); ?></h4>
                        <p><?php esc_html_e( 'Contact us for priority help', 'admin-login-url-change' ); ?></p>
                    </div>
                    <div class="aluc-support-arrow"><svg viewBox="0 0 24 24"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg></div>
                </a>
            </div>

            <!-- Upgrade CTA (only for free users) -->
            <?php if ( ! $is_pro ) : ?>
            <div class="aluc-upgrade-card">
                <h3>🔒 <?php esc_html_e( 'Go Pro & Stay Safe', 'admin-login-url-change' ); ?></h3>
                <p><?php esc_html_e( 'Unlock advanced security features and protect your site from every angle.', 'admin-login-url-change' ); ?></p>
                <ul class="aluc-upgrade-features">
                    <li><?php esc_html_e( 'IP Address Blocker', 'admin-login-url-change' ); ?></li>
                    <li><?php esc_html_e( 'Country-level blocking', 'admin-login-url-change' ); ?></li>
                    <li><?php esc_html_e( 'Login attempt limiter', 'admin-login-url-change' ); ?></li>
                    <li><?php esc_html_e( 'Two-factor authentication (2FA)', 'admin-login-url-change' ); ?></li>
                    <li><?php esc_html_e( 'Real-time attack dashboard', 'admin-login-url-change' ); ?></li>
                    <li><?php esc_html_e( 'Priority support', 'admin-login-url-change' ); ?></li>
                </ul>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=admin-login-url-change-pricing' ) ); ?>" class="aluc-btn-upgrade">
                    <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    <?php esc_html_e( 'Upgrade to Pro', 'admin-login-url-change' ); ?>
                </a>
            </div>
            <?php else : ?>
            <!-- Pro Active badge in sidebar -->
            <div class="aluc-pro-active-card">
                <div class="aluc-pro-active-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                </div>
                <h3><?php esc_html_e( 'Pro Active', 'admin-login-url-change' ); ?></h3>
                <p><?php esc_html_e( 'All pro features are unlocked and protecting your site.', 'admin-login-url-change' ); ?></p>
            </div>
            <?php endif; ?>

        </div><!-- .aluc-sidebar -->
        <?php
        return ob_get_clean();
    }

    /* ────────────────────────────────────────────────────────────
     * PRO TAB: IP Address Blocker
     * ──────────────────────────────────────────────────────────── */
    private function render_pro_tab_ip_block( $score, $circumference ) {
        $is_pro   = apply_filters( 'aluc_pro_active', false );
        $disabled = $is_pro ? '' : 'disabled';
        ob_start(); ?>
        <div class="aluc-tab-panel" id="aluc-panel-ip-block">
            <div class="aluc-layout">
                <div class="aluc-main-col">
                    <div class="aluc-card aluc-pro-panel">
                        <div class="aluc-card-header aluc-pro-header-wrap">
                            <div class="aluc-pro-header-title">
                                <div class="aluc-card-header-icon red">
                                    <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                                </div>
                                <div>
                                    <div class="aluc-card-title"><?php esc_html_e( 'IP Address Blocker', 'admin-login-url-change' ); ?></div>
                                    <div class="aluc-card-subtitle"><?php esc_html_e( 'Block individual IPs or entire CIDR ranges from your login page', 'admin-login-url-change' ); ?></div>
                                </div>
                            </div>
                            <?php if ( ! $is_pro ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=admin-login-url-change-pricing' ) ); ?>" class="aluc-btn aluc-btn-pro aluc-btn-sm" style="margin-left: auto;">
                                    <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                    <?php esc_html_e( 'Upgrade to Pro', 'admin-login-url-change' ); ?>
                                </a>
                            <?php else : ?>
                                <button type="button" class="aluc-btn aluc-btn-sm" id="aluc-save-ip-block" style="margin-left: auto;">
                                    <svg viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                                    <span class="aluc-btn-text"><?php esc_html_e( 'Save Settings', 'admin-login-url-change' ); ?></span>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="aluc-card-body">
                            <!-- Blocked IPs textarea -->
                            <div class="aluc-form-group">
                                <label class="aluc-label"><?php esc_html_e( 'Blocked IP Addresses', 'admin-login-url-change' ); ?></label>
                                <textarea name="blocked_ips" class="aluc-input aluc-textarea" rows="5" <?php echo $disabled; ?> placeholder="<?php esc_attr_e( "192.168.1.100\n10.0.0.0/8\n203.0.113.55", 'admin-login-url-change' ); ?>"></textarea>
                                <div class="aluc-hint">
                                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                                    <?php esc_html_e( 'One IP or CIDR range per line. e.g. 192.168.1.1 or 10.0.0.0/8', 'admin-login-url-change' ); ?>
                                </div>
                            </div>
                            <!-- Auto-ban threshold -->
                            <div class="aluc-form-group">
                                <label class="aluc-label"><?php esc_html_e( 'Auto-ban after failed attempts', 'admin-login-url-change' ); ?></label>
                                <div class="aluc-input-wrapper">
                                    <input type="number" name="auto_ban_threshold" class="aluc-input" value="5" <?php echo $disabled; ?> min="1" max="100">
                                </div>
                            </div>
                            <!-- Ban duration -->
                            <div class="aluc-form-group">
                                <label class="aluc-label"><?php esc_html_e( 'Ban Duration', 'admin-login-url-change' ); ?></label>
                                <div class="aluc-input-wrapper">
                                    <span class="aluc-input-prefix"><?php esc_html_e( 'Minutes', 'admin-login-url-change' ); ?></span>
                                    <input type="number" name="ban_duration" class="aluc-input" value="30" <?php echo $disabled; ?> min="1">
                                </div>
                            </div>
                            <!-- Email notification toggle -->
                            <div class="aluc-toggle-row">
                                <div class="aluc-toggle-info">
                                    <h4><?php esc_html_e( 'Email alert on new ban', 'admin-login-url-change' ); ?></h4>
                                    <p><?php esc_html_e( 'Send an email to admin when an IP is auto-banned', 'admin-login-url-change' ); ?></p>
                                </div>
                                <label class="aluc-toggle"><input type="checkbox" name="email_alert" <?php echo $disabled; ?> checked><span class="aluc-toggle-slider"></span></label>
                            </div>
                            <div class="aluc-toggle-row">
                                <div class="aluc-toggle-info">
                                    <h4><?php esc_html_e( 'Log blocked attempts', 'admin-login-url-change' ); ?></h4>
                                    <p><?php esc_html_e( 'Keep a record of all blocked login attempts in the database', 'admin-login-url-change' ); ?></p>
                                </div>
                                <label class="aluc-toggle"><input type="checkbox" name="log_attempts" <?php echo $disabled; ?>><span class="aluc-toggle-slider"></span></label>
                            </div>
                        </div>
                    </div>
                </div>
                <?php echo $this->render_sidebar( $score, $circumference ); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ────────────────────────────────────────────────────────────
     * PRO TAB: Country Blocker
     * ──────────────────────────────────────────────────────────── */
    private function render_pro_tab_country_block( $score, $circumference ) {
        $is_pro   = apply_filters( 'aluc_pro_active', false );
        $disabled = $is_pro ? '' : 'disabled';
        ob_start(); ?>
        <div class="aluc-tab-panel" id="aluc-panel-country-block">
            <div class="aluc-layout">
                <div class="aluc-main-col">
                    <div class="aluc-card aluc-pro-panel">
                        <div class="aluc-card-header aluc-pro-header-wrap">
                            <div class="aluc-pro-header-title">
                                <div class="aluc-card-header-icon red">
                                    <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                                </div>
                                <div>
                                    <div class="aluc-card-title"><?php esc_html_e( 'Country Blocker', 'admin-login-url-change' ); ?></div>
                                    <div class="aluc-card-subtitle"><?php esc_html_e( 'Restrict login access by country / geo-location', 'admin-login-url-change' ); ?></div>
                                </div>
                            </div>
                            <?php if ( ! $is_pro ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=admin-login-url-change-pricing' ) ); ?>" class="aluc-btn aluc-btn-pro aluc-btn-sm" style="margin-left: auto;">
                                    <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                    <?php esc_html_e( 'Upgrade to Pro', 'admin-login-url-change' ); ?>
                                </a>
                            <?php else : ?>
                                <button type="button" class="aluc-btn aluc-btn-sm" id="aluc-save-country-block" style="margin-left: auto;">
                                    <svg viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                                    <span class="aluc-btn-text"><?php esc_html_e( 'Save Settings', 'admin-login-url-change' ); ?></span>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="aluc-card-body">
                            <div class="aluc-form-group">
                                <label class="aluc-label"><?php esc_html_e( 'Blocking Mode', 'admin-login-url-change' ); ?></label>
                                <select name="country_mode" class="aluc-input aluc-input-full" <?php echo $disabled; ?>>
                                    <option value="blocklist"><?php esc_html_e( 'Blocklist (Deny selected countries)', 'admin-login-url-change' ); ?></option>
                                    <option value="allowlist"><?php esc_html_e( 'Allowlist (Allow ONLY selected countries)', 'admin-login-url-change' ); ?></option>
                                </select>
                            </div>
                            <div class="aluc-form-group">
                                <label class="aluc-label"><?php esc_html_e( 'Select Countries', 'admin-login-url-change' ); ?></label>
                                <div class="aluc-country-grid">
                                    <?php
                                    $countries = [ 'US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia', 'IN' => 'India', 'CN' => 'China', 'RU' => 'Russia' ];
                                    foreach ( $countries as $code => $name ) {
                                        echo '<label class="aluc-country-chip"><input type="checkbox" name="countries[]" value="'.esc_attr($code).'" '.$disabled.'> '.esc_html($name).'</label>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="aluc-form-group">
                                <label class="aluc-label"><?php esc_html_e( 'Redirect URL for blocked visitors', 'admin-login-url-change' ); ?></label>
                                <input type="url" name="redirect_url" class="aluc-input aluc-input-full" <?php echo $disabled; ?> placeholder="https://">
                            </div>
                            <div class="aluc-toggle-row">
                                <div class="aluc-toggle-info">
                                    <h4><?php esc_html_e( 'Log visits from blocked countries', 'admin-login-url-change' ); ?></h4>
                                    <p><?php esc_html_e( 'Keep track of who is trying to access your site from restricted regions.', 'admin-login-url-change' ); ?></p>
                                </div>
                                <label class="aluc-toggle"><input type="checkbox" name="log_visits" <?php echo $disabled; ?>><span class="aluc-toggle-slider"></span></label>
                            </div>
                        </div>
                    </div>
                </div>
                <?php echo $this->render_sidebar( $score, $circumference ); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ────────────────────────────────────────────────────────────
     * PRO TAB: Login Limiter
     * ──────────────────────────────────────────────────────────── */
    private function render_pro_tab_login_limit( $score, $circumference ) {
        $is_pro   = apply_filters( 'aluc_pro_active', false );
        $disabled = $is_pro ? '' : 'disabled';
        ob_start(); ?>
        <div class="aluc-tab-panel" id="aluc-panel-login-limit">
            <div class="aluc-layout">
                <div class="aluc-main-col">
                    <div class="aluc-card aluc-pro-panel">
                        <div class="aluc-card-header aluc-pro-header-wrap">
                            <div class="aluc-pro-header-title">
                                <div class="aluc-card-header-icon amber">
                                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                                </div>
                                <div>
                                    <div class="aluc-card-title"><?php esc_html_e( 'Login Attempt Limiter', 'admin-login-url-change' ); ?></div>
                                    <div class="aluc-card-subtitle"><?php esc_html_e( 'Protect against brute force attacks by limiting login retries', 'admin-login-url-change' ); ?></div>
                                </div>
                            </div>
                            <?php if ( ! $is_pro ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=admin-login-url-change-pricing' ) ); ?>" class="aluc-btn aluc-btn-pro aluc-btn-sm" style="margin-left: auto;">
                                    <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                    <?php esc_html_e( 'Upgrade to Pro', 'admin-login-url-change' ); ?>
                                </a>
                            <?php else : ?>
                                <button type="button" class="aluc-btn aluc-btn-sm" id="aluc-save-login-limit" style="margin-left: auto;">
                                    <svg viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                                    <span class="aluc-btn-text"><?php esc_html_e( 'Save Settings', 'admin-login-url-change' ); ?></span>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="aluc-card-body">
                            <div class="aluc-form-group">
                                <label class="aluc-label"><?php esc_html_e( 'Max Login Attempts', 'admin-login-url-change' ); ?></label>
                                <div class="aluc-input-wrapper">
                                    <input type="number" name="max_attempts" class="aluc-input" value="5" <?php echo $disabled; ?> min="1">
                                </div>
                            </div>
                            <div class="aluc-form-group">
                                <label class="aluc-label"><?php esc_html_e( 'Lockout Duration', 'admin-login-url-change' ); ?></label>
                                <div class="aluc-input-wrapper">
                                    <span class="aluc-input-prefix"><?php esc_html_e( 'Minutes', 'admin-login-url-change' ); ?></span>
                                    <input type="number" name="lockout_mins" class="aluc-input" value="15" <?php echo $disabled; ?> min="1">
                                </div>
                            </div>
                            <div class="aluc-form-group">
                                <label class="aluc-label"><?php esc_html_e( 'Retry Time Window', 'admin-login-url-change' ); ?></label>
                                <div class="aluc-input-wrapper">
                                    <span class="aluc-input-prefix"><?php esc_html_e( 'Minutes', 'admin-login-url-change' ); ?></span>
                                    <input type="number" name="window_mins" class="aluc-input" value="30" <?php echo $disabled; ?> min="1">
                                </div>
                            </div>
                            <div class="aluc-form-group">
                                <label class="aluc-label"><?php esc_html_e( 'Allowlisted IP Addresses', 'admin-login-url-change' ); ?></label>
                                <textarea name="allowlist" class="aluc-input aluc-textarea" rows="2" <?php echo $disabled; ?> placeholder="<?php esc_attr_e( 'These IPs will never be locked out', 'admin-login-url-change' ); ?>"></textarea>
                            </div>
                            <div class="aluc-toggle-row">
                                <div class="aluc-toggle-info">
                                    <h4><?php esc_html_e( 'Show remaining attempts on login page', 'admin-login-url-change' ); ?></h4>
                                    <p><?php esc_html_e( 'Displays a warning message to the user about how many attempts they have left.', 'admin-login-url-change' ); ?></p>
                                </div>
                                <label class="aluc-toggle"><input type="checkbox" name="show_remaining" <?php echo $disabled; ?> checked><span class="aluc-toggle-slider"></span></label>
                            </div>
                        </div>
                    </div>
                </div>
                <?php echo $this->render_sidebar( $score, $circumference ); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ────────────────────────────────────────────────────────────
     * PRO TAB: Two-Factor Authentication
     * ──────────────────────────────────────────────────────────── */
    private function render_pro_tab_two_fa( $score, $circumference ) {
        $is_pro   = apply_filters( 'aluc_pro_active', false );
        $disabled = $is_pro ? '' : 'disabled';
        ob_start(); ?>
        <div class="aluc-tab-panel" id="aluc-panel-two-fa">
            <div class="aluc-layout">
                <div class="aluc-main-col">
                    <div class="aluc-card aluc-pro-panel">
                        <div class="aluc-card-header aluc-pro-header-wrap">
                            <div class="aluc-pro-header-title">
                                <div class="aluc-card-header-icon blue">
                                    <svg viewBox="0 0 24 24"><path d="M16 11h5V6h-5v5zm-2 2h-4v11h4V13zm-6-2H3V6h5v5z"/></svg>
                                </div>
                                <div>
                                    <div class="aluc-card-title"><?php esc_html_e( 'Two-Factor Authentication (2FA)', 'admin-login-url-change' ); ?></div>
                                    <div class="aluc-card-subtitle"><?php esc_html_e( 'Require a second step to login via Authenticator App or Email OTP', 'admin-login-url-change' ); ?></div>
                                </div>
                            </div>
                            <?php if ( ! $is_pro ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=admin-login-url-change-pricing' ) ); ?>" class="aluc-btn aluc-btn-pro aluc-btn-sm" style="margin-left: auto;">
                                    <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                    <?php esc_html_e( 'Upgrade to Pro', 'admin-login-url-change' ); ?>
                                </a>
                            <?php else : ?>
                                <button type="button" class="aluc-btn aluc-btn-sm" id="aluc-save-2fa" style="margin-left: auto;">
                                    <svg viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                                    <span class="aluc-btn-text"><?php esc_html_e( 'Save Settings', 'admin-login-url-change' ); ?></span>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="aluc-card-body">
                            <div class="aluc-form-group">
                                <label class="aluc-label"><?php esc_html_e( 'Authentication Method', 'admin-login-url-change' ); ?></label>
                                <select name="method" class="aluc-input aluc-input-full" <?php echo $disabled; ?>>
                                    <option value="email"><?php esc_html_e( 'Email OTP (One-time password)', 'admin-login-url-change' ); ?></option>
                                </select>
                            </div>
                            <div class="aluc-form-group">
                                <label class="aluc-label"><?php esc_html_e( 'Require 2FA for these roles', 'admin-login-url-change' ); ?></label>
                                <div class="aluc-country-grid">
                                    <label class="aluc-country-chip"><input type="checkbox" name="roles[]" value="administrator" <?php echo $disabled; ?> checked> Administrator</label>
                                    <label class="aluc-country-chip"><input type="checkbox" name="roles[]" value="editor" <?php echo $disabled; ?>> Editor</label>
                                    <label class="aluc-country-chip"><input type="checkbox" name="roles[]" value="author" <?php echo $disabled; ?>> Author</label>
                                </div>
                            </div>
                            <div class="aluc-toggle-row">
                                <div class="aluc-toggle-info">
                                    <h4><?php esc_html_e( 'Remember Device', 'admin-login-url-change' ); ?></h4>
                                    <p><?php esc_html_e( 'Allow users to trust their device for 30 days and skip 2FA', 'admin-login-url-change' ); ?></p>
                                </div>
                                <label class="aluc-toggle"><input type="checkbox" name="remember_device" <?php echo $disabled; ?> checked><span class="aluc-toggle-slider"></span></label>
                            </div>
                            <div class="aluc-toggle-row">
                                <div class="aluc-toggle-info">
                                    <h4><?php esc_html_e( 'Backup Codes', 'admin-login-url-change' ); ?></h4>
                                    <p><?php esc_html_e( 'Provide 10 single-use recovery codes in case the user loses their device', 'admin-login-url-change' ); ?></p>
                                </div>
                                <label class="aluc-toggle"><input type="checkbox" name="backup_codes" <?php echo $disabled; ?> checked><span class="aluc-toggle-slider"></span></label>
                            </div>
                        </div>
                    </div>
                </div>
                <?php echo $this->render_sidebar( $score, $circumference ); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
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
        $link = sprintf( "<a href='%s' style='color:#2271b1;'>%s</a>", admin_url( 'admin.php?page=admin-login-url-change' ), __( 'Settings', 'admin-login-url-change' ) );
        array_push( $links, $link );
    
        return $links;
    }

    function admin_login_url_change_css(){
        // Only load on our settings page
        $screen = get_current_screen();
        if ( ! $screen || 'toplevel_page_admin-login-url-change' !== $screen->id ) {
            return;
        }

        wp_enqueue_style( 'admin-login-url-change-css', ALUC_PLUGIN_URL . 'assets/css/style.css', [], VERSION );
        wp_enqueue_script( 'admin-login-url-change-js', ALUC_PLUGIN_URL . 'assets/js/main.js', [ 'jquery' ], VERSION, true );
        wp_localize_script( 'admin-login-url-change-js', 'aluc_core', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'aluc-ajax-nonce' ),
            'is_pro'   => apply_filters( 'aluc_pro_active', false ) ? 1 : 0,
        ] );
    }

    /**
     * Save a single free option via AJAX (toggle switches)
     */
    public function aluc_save_option_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }
        if ( ! isset( $_POST['_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ), 'aluc-ajax-nonce' ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce' ] );
        }

        $allowed_keys = [
            'aluc_block_direct_wp_login',
            'aluc_hide_login_errors',
            'aluc_disable_xmlrpc',
            'aluc_remove_wp_version',
        ];

        $key = isset( $_POST['option_key'] ) ? sanitize_key( $_POST['option_key'] ) : '';
        if ( ! in_array( $key, $allowed_keys, true ) ) {
            wp_send_json_error( [ 'message' => 'Invalid option' ] );
        }

        $value = isset( $_POST['option_value'] ) ? absint( $_POST['option_value'] ) : 0;
        update_option( $key, $value );
        wp_send_json_success( [ 'message' => 'Saved' ] );
        wp_die();
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
            ( preg_match('#(^|/)wp-admin(/|$)#i', $path) || preg_match('#(^|/)admin(/|$)#i', $path) ) &&
            ! preg_match('#admin-ajax\.php$#i', $path) ) {
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
