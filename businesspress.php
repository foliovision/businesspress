<?php
/*
Plugin Name: BusinessPress
Plugin URI: http://www.foliovision.com
Description: This plugin secures your site
Version: 1.1
Author: Foliovision
Author URI: http://foliovision.com
Requires PHP: 5.6
*/

require_once( dirname(__FILE__) . '/fp-api.php' );

class BusinessPress extends BusinessPress_Plugin {
  
  
  const VERSION = '1.1';
  
  
  private $disallowed_caps_default = array( 
        'install_plugins' => 1,
        'install_themes' => 1, 
      'delete_plugins' => 1,
      'delete_themes' => 1,
      'edit_plugins' => 1,
      'edit_themes' => 1,
      'update_plugins' => 0,
      'update_themes' => 0,
      'activate_plugins' => 0
    );
  
  private $checks = array(
      'disallow_edits_defined' => 1,
      'disallow_mods_defined' => 1,
      'update_filters_mismatch' => 1,
      'user_can_if_cant' => 1
    );
  
  private $autoupdateType = 'minor';
  private $adminEmail;
  private $strArrMessages = array(); // string array, contains messages used in this plugin

  var $aCoreUpdatesDismiss = array();

  var $aOptions = array();

  public function __construct() {
    if( !function_exists('add_action') ) {
      exit( 0 );
    }
    
    /*
    Since October 2019 WordPress doesn't support ../ in the upload paths: https://core.trac.wordpress.org/changeset/46476
    
    Here is a hotfix:
    */
    $upload_path = get_option('upload_path');
    if( $upload_path && ( false !== strpos( $upload_path, '../' ) || false !== strpos( $upload_path, '..' . DIRECTORY_SEPARATOR ) ) ) {
      include( dirname(__FILE__).'/upload-path-fix.php' );
    }
    
    $this->adminEmail = get_option('admin_email');
    
    $this->aOptions = is_multisite() ? get_site_option('businesspress') : get_option( 'businesspress' );
    
    $this->disable_xmlrpc();
    
    if( $this->get_setting('hide-notices') ) include( dirname(__FILE__).'/businesspress-notices.class.php' );
    
    add_action( 'in_plugin_update_message-businesspress/businesspress.php', array( &$this, 'plugin_update_message' ) );
    
    register_activation_hook( __FILE__, array( $this , 'activate') );
    register_deactivation_hook( __FILE__, array( $this, 'deactivate') );
    

    add_action( 'wp', array( $this, 'anti_clickjacking_headers') );
    add_action( 'admin_init', array( $this, 'pointer_defaults') );
    add_action( 'admin_init', array( $this, 'plugin_update_hook' ) );
    add_action( 'admin_init', array( $this, 'apply_restrictions') );
    add_action( 'plugins_loaded', array( $this, 'load_extensions' ) );
    add_action( 'plugins_loaded', array( $this, 'load_extensions_later' ), 11 );    
    
    //add_filter( 'dashboard_glance_items', array( $this, 'core_updates_discard' ) );  // show only the current branch update for dashboard
    
    /*
     *  WordPress Upgrade tweaks
     */
    
    add_filter( 'auto_update_core', array( $this, 'delay_core_updates' ), 999, 2 );
    add_action( 'admin_init', array( $this, 'stop_disable_wordpress_core_updates') );
    add_action( 'core_upgrade_preamble', array( $this, 'upgrade_screen') );
    add_action( 'load-update-core.php', array( $this, 'upgrade_screen_start') );
    
    add_filter( 'send_core_update_notification_email', '__return_false' );  //  disabling WP_Automatic_Updater::send_email() with subject of "WordPress x.y.z is available. Please update!"
    add_filter( 'auto_core_update_send_email', '__return_false' );  //  disabling WP_Automatic_Updater::send_email() with subject of "Your site has updated to WordPress x.y.z"

    /*
     *  Admin screen
     */
    
    add_action( 'admin_enqueue_scripts', array( $this, 'admin_style' ) );
    add_action( 'admin_init', array( $this, 'handle_post') );
    add_filter( 'plugin_action_links', array( $this, 'admin_plugin_action_links' ), 10, 2);
    add_action( 'wp_ajax_businesspress_contact_admin', array( $this, 'contact_admin') );
    add_filter( 'auth_cookie_expiration', array( $this, 'admin_login_duration' ), 10, 3 );

    // Hide "Welcome" on Dashboard
    add_action( 'welcome_panel', array( $this, 'dashboard_hide_welcome' ), 0 );

    // Hide "WordPress Events and News" on dashboard
    add_action( 'wp_dashboard_setup', array( $this, 'dashboard_hide_wordpress_events_and_news' ) );

    // Hide "PHP Update Recommended, if user is doesn't have the full permissions
    add_action( 'wp_dashboard_setup', array( $this, 'dashboard_hide_php_update_recommended' ) );

    /*
     *  Visual WP-Admin tweaks
     */
    
    add_filter( 'heartbeat_settings', array( $this, 'heartbeat_frequency' ), 999);
    add_filter( 'admin_footer_text', array( $this, 'remove_wp_footer' ) );
    add_action( 'admin_init', array( $this, 'admin_screen_cleanup') );
    add_action( 'admin_head', array( $this, 'admin_screen_cleanup_css') );
    add_action( 'wp_before_admin_bar_render', array( $this, 'remove_wp_admin_bar_items' ) );
    
    remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );
    add_filter( 'get_user_option_admin_color', array( $this, 'admin_color_force' ) );
    add_filter( 'login_title', array( $this, 'login_title' ) );
    
    /*
     *  Frontend
     */
    
    add_action( 'init', array( $this, 'apply_restrictions') );
    add_action( 'init', array( $this, 'remove_generator_tag') );  //  Generator tags
    add_action( 'wp_footer', array( $this, 'multisite_footer'), 999 );
    add_filter( 'wp_login_errors', array( $this, 'wp_login_errors' ) );
    
    if( $this->get_setting('search-results') || isset($_GET['bpsearch']) ) include( dirname(__FILE__).'/fv-search.php' );

    /*
     *  Login protection
     */
    
    add_action( 'template_redirect', array( $this, 'fail2ban_404' ) );
    add_action( 'wp_login_failed', array( $this, 'fail2ban_login' ) );
    add_filter( 'xmlrpc_login_error', array( $this, 'fail2ban_xmlrpc' ) );
    add_filter( 'xmlrpc_pingback_error', array( $this, 'fail2ban_xmlrpc_ping' ), 5 );
    add_action( 'lostpassword_post', array( $this, 'fail2ban_lostpassword' ) );

    if( $this->get_setting('disable-user-login-scanning') ) {
      if( !empty($_GET['author']) ) {
        if( is_admin() && !defined('DOING_AJAX') ) return;
        die();
      }
    }

    /*
     * WAF
     */
    $this->fail2ban_waf();
    
    add_filter( 'login_redirect', array( $this, 'tweak_login_redirect' ) );
    add_filter( 'logout_redirect', array( $this, 'tweak_login_redirect' ) );

    add_filter( 'login_redirect', array( $this, 'fix_login_redirect_domain' ), PHP_INT_MAX );

    /*
    * Email notification disable
    */

    add_action( 'after_password_reset', array( $this, 'subscriber_notification_disable' ), 0);
    
    /*
    Editor disabling
    */
    add_action( 'post_submitbox_start', array( $this, 'editor_disable_checkbox' ) ); 
    add_action( 'save_post', array( $this, 'editor_disable_checkbox_save' ) ); 
    add_filter( 'user_can_richedit',  array( $this, 'editor_disable' ) );
    add_filter( 'the_content', array( $this, 'editor_disabled_thus_no_wpautop'), 0);
    
    /*
    Quick tweaks
    */
    
    // WooCommerce message "Connect your store to WooCommerce.com to receive extensions updates and support."
    add_filter( 'woocommerce_helper_suppress_connect_notice', '__return_true' );

    add_filter( 'wp_mail_from_name', array( $this, 'wp_mail_from' ), PHP_INT_MAX );

    /*
     *  Email blocking
     */
    if( $this->get_setting('email-blocking') ) {
      add_filter( 'wp_mail', array( $this, 'wp_mail_block_stage_1' ) );
    }

    /*
    Front-end login check
    */
    add_action( 'wp_footer', array( $this, 'login_check_js'), 999 );
    add_action( 'wp_ajax_bpress_login_check', array( $this, 'login_check_ajax') );
    add_action( 'wp_ajax_nopriv_bpress_login_check', array( $this, 'login_check_ajax') );

    /*
     * Setting for the "BIG image" threshold value.
     */
    add_action( 'admin_init', array( $this, 'big_image_size_threshold_setting') );
    add_action( 'big_image_size_threshold', array( $this, 'big_image_size_threshold'), PHP_INT_MAX, 4 );

    /*
     * Hide Password Protected Posts
     */
    add_action( 'pre_get_posts', array( $this, 'hide_password_protected_posts' ) );

    /**
     * Error reporting
     */
    add_filter( 'recovery_mode_email', array($this , 'recovery_email') );

    parent::__construct();
    
  }
  
  
  
  
  function activate() {
    $this->aOptions = array();
    $this->aOptions['core_auto_updates'] = 'minor';
    update_option( 'businesspress', $this->aOptions ); 
  }
  
  
  
  
  function admin_color_force() {
    return $this->get_setting('admin-color');
  }
  
  
  
  
  function admin_plugin_action_links($links, $file) {
  	$plugin_file = basename(__FILE__);
  	if( basename($file) == $plugin_file ) {
      $settings_link =  '<a href="'.site_url('wp-admin/options-general.php?page=businesspress').'">'.__('Settings', 'businesspress').'</a>';
  		array_unshift($links, $settings_link);
  	}
  	return $links;
  }  
  
  
  
  
  function admin_screen_cleanup() {
    remove_filter( 'update_footer', 'core_update_footer' );
    remove_action( 'admin_notices', 'update_nag', 3 );
    remove_action( 'network_admin_notices', 'update_nag', 3 );
    
    global $WPMUDEV_Dashboard_Notice3;
    
    if( $WPMUDEV_Dashboard_Notice3 !== null ){
      remove_action( 'admin_print_styles', array( &$WPMUDEV_Dashboard_Notice3, 'notice_styles' ) );
      remove_action( 'all_admin_notices', array( &$WPMUDEV_Dashboard_Notice3, 'activate_notice' ), 5 );
      remove_action( 'all_admin_notices', array( &$WPMUDEV_Dashboard_Notice3, 'install_notice' ), 5 );
    }
    
  }
  
  

  function admin_screen_cleanup_css() {
    //  todo: also hide theme updates
    //  todo: also hide in Network admin
    ?>
    <style>
      #adminmenu .menu-icon-plugins .update-plugins { display: none }
      #wp-version-message .button { display: none }
      /* Yasr – Yet Another Stars Rating promotions */
      #wpbody-content div.fs-notice.promotion { display: none !important }
      #wpbody-content .yasr-donatedivdx { display: none !important }
      #adminmenu .toplevel_page_yasr_settings_page .update-plugins.fs-trial { display: none }
      #wp-admin-bar-searchwp { display: none !important }
      .searchwp-settings-header-nav ul li a.searchwp-settings-nav-tab-support > span > span { display: none !important }
      #xyz-ihs-premium, .xyz_ihs_social_media, .xyz_ihs_sugession, .xyz_ihs_new_subscribe, .xyz_ihs_inmotion, .xyz_our_plugins_new, .xyz_poweredBy { display: none }
    </style>
    <?php
  }
  
  
  
  
  function admin_style() {
    if( is_admin() && isset($_GET['page']) && $_GET['page'] == 'businesspress' ) {
      wp_register_style( 'businesspress_admin', plugins_url('/css/admin.css',__FILE__), false, BusinessPress::VERSION );
      wp_enqueue_style( 'businesspress_admin' );
      
      wp_enqueue_media();
    }
  }

  
  
  
  function apply_restrictions() {
    if( $this->get_setting('link-manager') ) add_filter( 'pre_option_link_manager_enabled', '__return_true' );
    
    if( !$this->check_user_permission() )  {
      add_action( 'admin_footer', array( $this, 'hide_plugin_controls' ), 1 );
      add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 999, 2 );
      add_filter( 'map_meta_cap', array( $this, 'capability_filter' ), 999, 4 );
      add_filter( 'admin_init', array( $this, 'disable_deactivation' ), 999, 4 );
    }
    
    if( !empty($this->aOptions['core_auto_updates']) ) {
      if( $this->aOptions['core_auto_updates'] === 'major' ) {
        add_filter( 'allow_major_auto_core_updates', '__return_true', 1000 );
      } else if( $this->aOptions['core_auto_updates'] === 'minor' ) {
        add_filter( 'allow_minor_auto_core_updates', '__return_true', 1000 );
      } else if( $this->aOptions['core_auto_updates'] === 'none' ) {
        add_filter( 'automatic_updater_disabled', '__return_true', 1000 );
      }
    }
    
    if( $this->get_setting('autoupdates_vcs') ) add_filter( 'automatic_updates_is_vcs_checkout', '__return_false', 999 );
    
    if( ( $this->get_setting('wp_admin_bar_subscribers') || $this->get_setting('wp_admin_redirect_subscribers') ) && get_current_user_id() > 0 ) {
      $objUser = get_userdata( get_current_user_id() );
      if( $objUser && isset($objUser->roles) && is_array($objUser->roles) ) {
        $roles = $objUser->roles;
        if(
          // this is silly, but we can't rely on !current_user_can() with edit_posts or delete_posts to detect Subscribers because of bbPress
          count($roles) == 2 && ( $roles[0] == 'subscriber' && $roles[1] == 'bbp_participant' || $roles[0] == 'bbp_participant' && $roles[1] == 'subscriber' ) ||
          count($roles) > 0 && $roles[0] == 'subscriber' ||
          // Easy Digital Downloads with subscriptions
          count($roles) > 0 && $roles[0] == 'edd_subscriber' ||
          count($roles) == 0
        ) {
          add_filter('show_admin_bar', '__return_false');
          add_action( 'admin_init', array( $this, 'subscriber__dashboard_redirect' ) );
          add_action( 'admin_head', array( $this, 'subscriber__hide_menus' ) );
        }
      }
    }  
    
  }
  
  
  
  
  function can_update_core() {
    if( !empty($this->aOptions['cap_update']) && $this->aOptions['cap_update'] && !empty($this->aOptions['cap_core']) && $this->aOptions['cap_core'] ) {
      return true;
    }
    return false;
  }
  
  
  
  
  function cache_core_version_info() { // TODO
    $aVersions = get_option( 'businesspress_core_versions' );
    if( !$aVersions || !isset($aVersions['ttl']) || $aVersions['ttl'] < time()  ) {
      $bSuccess = false;
      $aResponse = wp_remote_get( 'https://codex.wordpress.org/WordPress_Versions' );
      if( !is_wp_error($aResponse) ) {      
        if( preg_match_all( '~<tr[\s\S]*?</tr>~', $aResponse['body'], $aTableRows ) ) {
          $aVersions = array( 'data' => array() );
          $aVersions['ttl'] = time() + 900;
          if( count($aTableRows) > 0 ) {
            foreach( $aTableRows[0] as $sTableRow ) {
              preg_match( '~>([0-9.-]+)</a>~', $sTableRow, $aVersion );
              preg_match( '~\S+ \d+, 20\d\d~', $sTableRow, $aDate );
              if( $aVersion && $aDate ) {
                $bSuccess = true;
                $aVersions['data'][$aVersion[1]] = $aDate[0];
              }
            }
            
          }
        }
        
      }

      if( !$bSuccess ) {
        $aVersions = get_option( 'businesspress_core_versions', array() );
        $aVersions['ttl'] =  time()+120;
        
      }
      
      update_option( 'businesspress_core_versions', $aVersions, false );
      
    }

    if( !isset($aVersions['data']['5.7']) ) $aVersions['data']['5.7'] = 'March 9, 2021'; // fix 5.7 if missing date

    return $aVersions;
  }
  
  
  
  
  function capability_filter( $required_caps, $cap, $user_id, $args ) {
    $blocked_caps = $this->get_disallowed_caps();
    if( in_array( $cap, $blocked_caps ) ) { 
      $required_caps[] = 'do_not_allow';
    }
    return $required_caps;
  }
  
  
  
  
  function check_user_permission() {
    global $current_user;
    if( !$this->get_setting('restrictions_enabled') ) return true;
    
    if( $this->get_setting('email') == $current_user->user_email ) {
      return true;
    } else if( $this->get_setting('domain') == $this->get_email_domain($current_user->user_email) ) {
      return true;
    }    
    
    return false;
  }
  
  
  
  
  function contact_admin() {
    $current_user = wp_get_current_user();
    if( $this->get_contact_email() == -1 ) die();
    
    wp_mail( $this->get_contact_email(), 'BusinessPress contact form submission', $_POST['message'], array( 'Reply-To: '.$current_user->display_name.' <'.$current_user->user_email.'>' ) );    
    die('1');
  }




  function admin_login_duration( $expiration, $user_id, $remember) {
    $duration = $this->get_setting('login-duration');

    // check if remember is set, if not, return the default expiration
    if( !$remember ) {
      return $expiration;
    }

    // get new expiration based on the setting
    if( strcmp($duration, '2_weeks') == 0 ) {
      return $expiration; // 2 weeks is the default
    } else if ( strcmp($duration, '2_months') == 0 ) {
      $expiration = 2 * MONTH_IN_SECONDS;
    } else if ( strcmp($duration, '6_months') == 0 ) {
      $expiration = 6 * MONTH_IN_SECONDS;
    }

    return $expiration;
  }
  
  
  
  function core_updates_discard( $items ) {
    $objUpdates = get_site_transient( 'update_core' );
    if( $objUpdates && !empty($objUpdates->updates) ) {
      foreach( $objUpdates->updates AS $update ) {
        if( stripos($update->current,get_bloginfo( 'version' )) === 0 ) continue;
        $this->aCoreUpdatesDismiss[$update->current.'|'.$update->locale] = 'Hell yeah';
      }
    }
    
    //  here's where we mess with the dismissed_update_core option as there is no filtering for available updates
    add_filter( 'pre_site_option_dismissed_update_core', array( $this, 'core_updates_discard_do' ) );
    return $items;
  }
  
  
  
  
  function core_updates_discard_do( $items ) {
    //  todo: what if ther are existing discarded updates?
    return $this->aCoreUpdatesDismiss;
  }
  



  function cron_schedule() {
    $timestamp = wp_next_scheduled( 'businesspress_cron' );
    if( $timestamp == false ) {
      wp_schedule_event( time(), 'hourly', 'businesspress_cron' );
    }
  }




  function dashboard_hide_php_update_recommended() {
    if( !$this->check_user_permission() )  {
      remove_meta_box( 'dashboard_php_nag', get_current_screen(), 'normal' );
    }
  }




  function dashboard_hide_welcome() {
    if( !$this->check_user_permission() )  {
      remove_action( 'welcome_panel', 'wp_welcome_panel' );
    }
  }




  function dashboard_hide_wordpress_events_and_news() {
    if( !$this->check_user_permission() )  {
      remove_meta_box( 'dashboard_primary', get_current_screen(), 'side' );
    }
  }




  function deactivate() {
    
  }
  
  
  
  
  // checks the release date for the WordPress version and if it's less than 5 days old it's not permitted for auto update
  function delay_core_updates( $update, $item ) {
    if( $update ) {
      
      $aBlockedUpdates = get_site_option('businesspress_core_update_delay',array());
      
      $aVersions = $this->cache_core_version_info();
      if( $aVersions && !empty($aVersions['data']) && !empty($aVersions['data'][$item->current]) ) {
        if( strtotime($aVersions['data'][$item->current]) + 5*24*3600 > time() ) {
          $aBlockedUpdates[$item->current] = time();
          update_site_option('businesspress_core_update_delay', $aBlockedUpdates );
          return false; // block the update if it's less than 5 days old
        } else {
          unset($aBlockedUpdates[$item->current]);
          update_site_option('businesspress_core_update_delay', $aBlockedUpdates );
        }
      }
      
    }
    
    //  todo: this might trigger (if notify_email is set in the WP API response) an email notification, consider blocking it with send_core_update_notification_email filter
    return $update;
  }




  function disable_deactivation() {
    if( isset($_POST['action']) && $_POST['action'] == 'deactivate-selected' && isset($_POST['checked']) && is_array($_POST['checked']) ) {
      foreach( $_POST['checked'] AS $key => $value ) {
        if( stripos($value,'businesspress') !== false ) {
          unset($_POST['checked'][$key]);
        }
      }
    }
    if( isset($_GET['action']) && $_GET['action'] == 'deactivate' && isset($_GET['plugin']) ) {
      if( stripos($_GET['plugin'],'businesspress') !== false ) {
        wp_die(__('Sorry, you are not allowed to deactivate plugins for this site.'));
      }
    }
  }
  
  
  
  
  function disable_xmlrpc() {
    if( $this->get_setting('disable-xml-rpc') ) {
      add_filter('xmlrpc_enabled', '__return_false');
      remove_action( 'wp_head', 'rsd_link' );
      remove_action( 'wp_head', 'wlwmanifest_link' );
      if( stripos($_SERVER['REQUEST_URI'],'/xmlrpc.php') !== false ) die();
    }
    
    if( $this->get_setting('xml-rpc-key') ) {
      remove_action( 'wp_head', 'rsd_link' );
      remove_action( 'wp_head', 'wlwmanifest_link' );
      if( stripos($_SERVER['REQUEST_URI'],'/xmlrpc.php') !== false && stripos($_SERVER['REQUEST_URI'], $this->get_setting('xml-rpc-key') ) === false ) die();
    }      
  }
  
  
  
  
  function editor_disable( $can ) {
    if( $this->editor_is_disabled() ) {
      return false;  
    }
    
    return $can;
  }
  
  
  
  
  function editor_is_disabled( $post_id = false ) {
    global $post;
    if( !$post_id && $post && !empty($post->ID)) {
      $post_id = $post->ID;
    }
    
    $disabled = false;
    
    // check the old Foliopress WYSIWYG postmeta meta too
    // if Foliopress WYSIWYG meta is present and it was the last editor used to modify the post
    $fp_wysiwyg = get_post_meta( $post_id, 'wysiwyg', true );
    if( $fp_wysiwyg && !empty($fp_wysiwyg['plain_text_editing']) && $fp_wysiwyg['plain_text_editing'] ) {
      $disabled = true;
    }
    
    if( !$disabled ) {
      $plain_text_editing = get_post_meta( $post_id, 'plain_text_editing', true );
      if( !empty($plain_text_editing['plain_text_editing']) && $plain_text_editing['plain_text_editing'] ) {
        $disabled = true;
      }
    }

    return $disabled;
  }
    
    
    
    
  function editor_disable_checkbox() {
    $disabled = $this->editor_is_disabled();
    if( $disabled ) : ?>
      <label for="plain_text_editing">
        <input name="plain_text_editing" type="checkbox" id="plain_text_editing" value="true" <?php checked(1,$disabled); ?> />
        <?php _e('Plain text editing', 'businesspress'); ?>
        <abbr title="<?php _e('This will disable Visual editor for this post, as well as the WP formating routine (wpautop). Turn this option off only if you are sure this post won\'t get destroyed by it.', 'businesspress') ?>">(?)</abbr>
      </label>
    <?php endif;
  }
  
  
  
  
  function editor_disable_checkbox_save($post_id) {
    // If this is a revision, bail
    if( wp_is_post_revision($post_id) ) {
      return;
    }
    
    if( !empty($_POST['plain_text_editing']) ) {
      update_post_meta($post_id,'plain_text_editing',true);
    } else {
      delete_post_meta($post_id,'plain_text_editing');
    }
    
    // update the old Foliopress WYSIWYG postmeta meta too, if it's there
    $fp_wysiwyg = get_post_meta( $post_id, 'wysiwyg', true );
    if( $fp_wysiwyg ) {
      $fp_wysiwyg['plain_text_editing'] = !empty($_POST['plain_text_editing']);
      update_post_meta($post_id,'plain_text_editing',$fp_wysiwyg);
    }
  }
  
  
  
  
  function editor_disabled_thus_no_wpautop( $post_content ) {
    if( $this->editor_is_disabled() ) {
      remove_filter ('the_content',  'wpautop');
    }
    
    return $post_content;
  }
  
  
  
  
  function fail2ban_404() {
    if( preg_match( '~\.(bmp|css|eot|gif|ico|jpe|jpeg|jpg|js|m3u8|mp3|mp4|ogg|pdf|png|svg|tiff|ts|ttf|txt|vtt|webm|webp|woff|woff2)~i', $_SERVER['REQUEST_URI'] ) ) return;
    
    if( $_SERVER['REQUEST_URI'] == '/apple-app-site-association' || $_SERVER['REQUEST_URI'] == '/.well-known/apple-app-site-association' ) return;

    if( stripos($_SERVER['REQUEST_URI'], 'fv-gravatar-cache' ) !== false ) return;

    if( stripos($_SERVER['REQUEST_URI'], 'null' ) !== false ) return;
	  
    if( ! empty( $_SERVER['HTTP_USER_AGENT'] ) && preg_match( '~(Mediapartners-Google|googlebot|bingbot)~i', $_SERVER['HTTP_USER_AGENT'] ) ) return;

    if( !is_404() || function_exists('bbp_is_single_user') && bbp_is_single_user() ) return;

    $this->fail2ban_openlog(LOG_AUTH);
    syslog( LOG_INFO,'BusinessPress fail2ban 404 error - '.$_SERVER['REQUEST_URI'].' from '.$this->get_remote_addr() );
  }




  function fail2ban_login( $username ) {
    $msg = (wp_cache_get($username, 'userlogins'))
							? "Authentication failure for $username from "
							: "Authentication attempt for unknown user $username from ";
    
    $this->fail2ban_openlog();
    syslog( LOG_INFO,'BusinessPress fail2ban login error - '.$msg.$this->get_remote_addr() );
  }
  
  
  
  
  function fail2ban_lostpassword( $errors ) {
    if( $errors && method_exists($errors,'get_error_codes') && $errors->get_error_codes() ) {
      $this->fail2ban_openlog();
      syslog( LOG_INFO,'BusinessPress fail2ban login lostpassword error - user '.( !empty($_POST['user_login']) ? $_POST['user_login'] : '?' ).' doesn\'t exists from '.$this->get_remote_addr() );      
    }
  }  
  
  
  
  
  function fail2ban_openlog($log = LOG_AUTH, $daemon = 'wordpress') {
		$host	= array_key_exists('WP_FAIL2BAN_HTTP_HOST',$_ENV) ? $_ENV['WP_FAIL2BAN_HTTP_HOST'] : $_SERVER['HTTP_HOST'];
		openlog($daemon."($host)", LOG_NDELAY|LOG_PID, $log);
	}
    
  
  
  
  function fail2ban_waf() {
    // If a phrase starts with / it means it only triggers if it's the start of the request URL or requested from a folder, but it will work if used in query string without /
    // This way you can search for .ssh or phpmyadmin in articles using site.com/?s=phpmyadmin and not get banned
    // TODO: What if I search for phpmyadmin in bbPress ? site.com/support/search/phpmyadmin
    $rules = array(
      '/.env',
      '/.github/COMMIT_EDITMSG',
      '/.github/config',
      '/.github/description',
      '/.github/HEAD',
      '/.github/index',
      '/.github/workflows',
      '/.ssh',
      '/boot.ini',
      '/data/admin/allowurl.txt',
      'etc/passwd',
      '/ftpsync.settings',
      ' onerror=',
      ' onload=',
      '/phpMyAdmin/server_import.php',
      '/phpmyadmin/scripts/setup.php',
      'ueditor/net/controller.ashx',
      '/win.ini',
      '/wp-config.php',
      's=/admin/index/dologin',
    );

    $match = false;

    foreach( $rules AS $rule ) {
      if(
        stripos( $_SERVER['REQUEST_URI'], $rule ) !== false ||
        stripos( $_SERVER['REQUEST_URI'], urlencode($rule) ) !== false ||
        stripos( $_SERVER['REQUEST_URI'], urlencode( urlencode($rule) ) ) !== false ||
        stripos( $_SERVER['REQUEST_URI'], urlencode( urlencode( urlencode($rule) ) ) ) !== false
      ) {
        $match = $rule;
      }
    }

    // Requests like:
    // /?s=/index/%5Cthink%5Capp/invokefunction&function=call_user_func_array&vars[0]=file_put_contents&vars[1][]=xml1.php&vars[1][]=%3C?php%20@eval(_POST[terry]);print(md5(123));?%3E
    if( !empty($_GET['function']) && $_GET['function'] == 'call_user_func_array' ) {
      if( !empty($_GET['vars']) ) {
        $vars = json_encode($_GET['vars']);
        foreach( array(
          'eval',
          'file_put_contents',
        ) AS $keyword ) {
          if( stripos( $vars, $keyword ) !== false ) {
            $match = 'call_user_func_array with '.$keyword;
          }
        }
      }
    }

    // Check HTTP_X_FORWARDED_FOR header for non-IP addresses
    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
      $forwarded_ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
      foreach ( $forwarded_ips as $ip ) {
        $ip = trim( $ip );
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 ) ) {
          $match = 'invalid_ip_in_x_forwarded_for';
          break;
        }
      }
    }

    if( $match ) {
      $this->fail2ban_openlog();
      syslog( LOG_INFO,'BusinessPress WAF - '.$match.' request - '.$_SERVER['REQUEST_URI'].' from '.$this->get_remote_addr() );
      exit;
    }
  }




  function fail2ban_xmlrpc( $error ) {
    $this->fail2ban_openlog();
    syslog( LOG_INFO,'BusinessPress fail2ban login error - XML-RPC authentication failure from '.$this->get_remote_addr() );

    return $error;
  }




  function fail2ban_xmlrpc_ping( $ixr_error ) {
    if( $ixr_error->code === 48 ) return $ixr_error;
    
    $this->fail2ban_openlog();
    syslog( LOG_INFO,'BusinessPress fail2ban pingback error - XML-RPC Pingback error '.$ixr_error->code.' generated from '.$this->get_remote_addr() );

    return $ixr_error;
  }

  /**
   * Make sure the login redirection URL does use www. if the site is set to use www.
   *
   * Otherwise the cookies might not be present after logging in.
   *
   * @param string $redirect The URL to redirect to.
   *
   * @return string The URL to redirect to.
   */
  function fix_login_redirect_domain( $redirect ) {
    $home_domain         = wp_parse_url( home_url(), PHP_URL_HOST );
    $home_url_non_www    = str_replace( '//www.', '//', home_url() );
    $home_domain_non_www = wp_parse_url( $home_url_non_www, PHP_URL_HOST );
    $redirect_domain     = wp_parse_url( $redirect, PHP_URL_HOST );

    // Are we redirecting to the website URL at all?
    if (
      $redirect_domain === $home_domain ||
      $redirect_domain === $home_domain_non_www ||
      $redirect_domain === "www." . $home_domain_non_www
    ) {
      $home_has_www     = stripos( $home_domain, 'www.' ) === 0;
      $redirect_has_www = stripos( $redirect_domain, 'www.' ) === 0;

      if ( $home_has_www && ! $redirect_has_www ) {
        $redirect = str_replace( $home_url_non_www, home_url(), $redirect );

      } else if ( ! $home_has_www && $redirect_has_www ) {
        $redirect = str_replace( str_replace( '://', '://www.', home_url() ), home_url(), $redirect );
      }
    }

    return $redirect;
  }

  function get_branch_latest() {
    $sLatest = false;
    if( $branch = $this->get_version_branch() ) {
      $aVersions = $this->cache_core_version_info();
      if( $aVersions && count($aVersions['data']) > 0 ) {
        foreach( $aVersions['data'] AS $version => $date ) {
          if( stripos($version,$branch) === 0 && version_compare($version,$sLatest) == 1 ) {
            $sLatest = $version;
          }
        }
      }
    }
    return $sLatest;
  }
  
  
  
  
  function get_disallowed_caps() {
    $aCaps = array();

    $settings_to_capabilities = array( 
      'cap_activate' => array('activate_plugins', 'switch_themes', 'deactivate_plugins'),
      'cap_update' => array('update_plugins', 'update_themes'),
      'cap_install' => array('install_plugins', 'install_themes', 'delete_plugins', 'delete_themes', 'edit_plugins', 'edit_themes'),
      'cap_export' => array('export'),
      'cap_core' => array('update_core', 'view_site_health_checks', 'update_languages')
    );

    // Disallow different capabilities based on the "Allow other admins to" settings NOT enabled
    foreach( $settings_to_capabilities AS $setting => $capabilities ) {
      if( empty($this->aOptions[$setting]) || !$this->aOptions[$setting] ) {
        $aCaps = array_merge( $aCaps, $capabilities );
      }
    }


    return $aCaps;
  }
  
  
  
  
  function get_email_domain( $email ) {
    return preg_replace( '~.+@~', '', $email );
  }
  
  
  
  
  function get_remote_addr() {
    if( isset($_SERVER['HTTP_X_PULL']) && strlen($_SERVER['HTTP_X_PULL']) > 0 && $_SERVER['HTTP_X_PULL'] == $this->aOptions['xpull-key'] ) {
      return (false===($len = strpos($_SERVER['HTTP_X_FORWARDED_FOR'],',')))
              ? $_SERVER['HTTP_X_FORWARDED_FOR']
              : substr($_SERVER['HTTP_X_FORWARDED_FOR'],0,$len);
    }
    
    $aProxies = array();
    
    //  https://www.maxcdn.com/one/tutorial/ip-blocks/
    $aMaxCDN = array( '108.161.176.0/20', '94.46.144.0/20', '146.88.128.0/20', '198.232.124.0/22', '23.111.8.0/22', '217.22.28.0/22', '64.125.76.64/27', '64.125.76.96/27', '64.125.78.96/27', '64.125.78.192/27', '64.125.78.224/27', '64.125.102.32/27', '64.125.102.64/27', '64.125.102.96/27', '94.31.27.64/27', '94.31.33.128/27', '94.31.33.160/27', '94.31.33.192/27', '94.31.56.160/27', '177.54.148.0/24', '185.18.207.64/26', '50.31.249.224/27', '50.31.251.32/28', '119.81.42.192/27', '119.81.104.96/28', '119.81.67.8/29', '119.81.0.104/30', '119.81.1.144/30', '27.50.77.226/32', '27.50.79.130/32', '119.81.131.130/32', '119.81.131.131/32', '216.12.211.59/32', '216.12.211.60/32', '37.58.110.67/32', '37.58.110.68/32', '158.85.206.228/32', '158.85.206.231/32', '174.36.204.195/32', '174.36.204.196/32', '151.139.0.0/19', '94.46.144.0/21', '103.66.28.0/22', '103.228.104.0/22' );
    $aProxies = array_merge( $aProxies, $aMaxCDN );
    
    // https://www.cloudflare.com/ips-v4
    $aCloudFlareIP4 = array( '103.21.244.0/22','103.22.200.0/22','103.31.4.0/22','104.16.0.0/12','108.162.192.0/18','131.0.72.0/22','141.101.64.0/18','162.158.0.0/15','172.64.0.0/13','173.245.48.0/20','188.114.96.0/20','190.93.240.0/20','197.234.240.0/22','198.41.128.0/17','199.27.128.0/21' );
    $aProxies = array_merge( $aProxies, $aCloudFlareIP4 );
    
    // https://www.cloudflare.com/ips-v6
    $aCloudFlareIP6 = array( '2400:cb00::/32', '2405:8100::/32', '2405:b500::/32', '2606:4700::/32', '2803:f800::/32', '2c0f:f248::/32', '2a06:98c0::/29' );
    $aProxies = array_merge( $aProxies, $aCloudFlareIP6 );
    
    if (defined('WP_FAIL2BAN_PROXIES')) { //  todo: check this out      
      $aProxies = array_merge( $aProxies, explode( ',' ,WP_FAIL2BAN_PROXIES ) );
    }
    
    if (array_key_exists('HTTP_X_FORWARDED_FOR',$_SERVER)) {
      $ip = ip2long($_SERVER['REMOTE_ADDR']);
      foreach( $aProxies as $proxy ) {
        if (2 == count($cidr = explode('/',$proxy))) {
          $net = ip2long($cidr[0]);
          $mask = ~ ( pow(2, (32 - $cidr[1])) - 1 );
        } else {
          $net = ip2long($proxy);
          $mask = -1;
        }
        if ($net == ($ip & $mask)) {
          return (false===($len = strpos($_SERVER['HTTP_X_FORWARDED_FOR'],',')))
              ? $_SERVER['HTTP_X_FORWARDED_FOR']
              : substr($_SERVER['HTTP_X_FORWARDED_FOR'],0,$len);
        }
      }
    }

    return $_SERVER['REMOTE_ADDR'];    
  }
  
  
  
  
  function get_contact_email() {
    if( $this->get_setting('contact_email') ) {
      return $this->get_setting('contact_email');
    }
        
    /*$current_user = wp_get_current_user();  //  gets admin email if it matches the domain
    $domain = $this->get_whitelist_domain();
    if( stripos(get_option('admin_email'),'@'.$domain) !== false ) {
      return get_option('admin_email');
    }
    
    $aUsers = get_users( 'role=administrator' );  //  gets first admin user if his email matches domain
    if( $aUsers ) {
      foreach( $aUsers AS $objUser ) {
         if( stripos($objUser->user_email,'@'.$domain) !== false ) {
            return $objUser->user_email;
         }
      }
    }*/
    
    return -1;
  }
  
  
  
  
  function get_whitelist_domain() {
    return !empty($this->aOptions['domain']) ? $this->aOptions['domain'] : false;
  }  
  
  
  
  
  function get_whitelist_email() {
    return !empty($this->aOptions['email']) ? $this->aOptions['email'] : false;
  }
  
  
  
  
  /* this is basic handler for getting FVSB data from database
  * @param $key = can contain {all | version | upgradeType | capsDisabled | adminEmail}
  */
  function get_setting( $key ) {
    $this->aOptions = is_multisite() ? get_site_option('businesspress') : get_option( 'businesspress' );

    if( isset($this->aOptions[$key]) ) {
      if( $this->aOptions[$key] === true || $this->aOptions[$key] === 'true' ) return true;
      return trim($this->aOptions[$key]);
    }

    //default settings
    if( $key == 'search-results-domain' ) return true;
    if( $key == 'disable-rest-api' ) return true;
    if( $key == 'disable-emojis' ) return true;
    if( $key == 'remove-generator' ) return true;
    if( $key == 'hide-notices' ) return false;
    if( $key == 'autoupdates_vcs' ) return true;
    if( $key == 'clickjacking-protection' ) return true;
    if( $key == 'disable-user-login-scanning' ) return true;
    if( $key == 'login-lockout' ) return true;
    if( $key == 'login-duration') return '2_weeks';

    return false;
  }
  
  
  
  
  function get_setting_db($key) {
    return is_multisite() ? get_site_option($key) : get_option($key);
  }
  
  
  
  
  function get_settings_url() {
    $sURL = site_url('wp-admin/options-general.php?page=businesspress');      
    if( is_multisite() && $aSitewidePlugins = get_site_option( 'active_sitewide_plugins') ) {
      if( is_array($aSitewidePlugins) && stripos( ','.implode( ',', array_keys($aSitewidePlugins) ), ',businesspress' ) !== false ) {
        $sURL = site_url('wp-admin/network/settings.php?page=businesspress');
      }
    }
    return $sURL;
  }
  
  
  
  
  function get_version_branch() {
    global $wp_version;
    if( preg_match( '~\d+\.\d+~', $wp_version, $aMatch ) ) {
      return $aMatch[0];
    }
  }

  
  
  
  function get_wp_root_dir() {
    $full_path = getcwd();
    $ar = explode("wp-", $full_path);
    return $ar[0]; 
  }
  
  
  

  function handle_post() {
    if( isset($_POST['businesspress_settings_nonce']) && check_admin_referer( 'businesspress_settings_nonce', 'businesspress_settings_nonce' ) ) {
      
      $this->aOptions['restrictions_enabled'] = isset($_POST['restrictions_enabled']) && $_POST['restrictions_enabled'] == 1 ? true : false;
      
      if( !empty($_POST['whitelist']) && $_POST['whitelist'] == 'domain' ) {
        $this->aOptions['domain'] = trim($_POST['domain']);
        $this->aOptions['email'] = '';
      } else if( is_email(trim($_POST['email'])) ) {
        $this->aOptions['email'] = trim($_POST['email']);
        $this->aOptions['domain'] = '';  
      }
      
      $this->aOptions['login-duration'] = trim($_POST['login-duration']);

      $this->aOptions['core_auto_updates'] = trim($_POST['autoupgrades']);
      
      $this->aOptions['autoupdates_vcs'] = trim($_POST['autoupdates_vcs']);
      
      $this->aOptions['wp_admin_bar_subscribers'] = isset($_POST['wp_admin_bar_subscribers']) && $_POST['wp_admin_bar_subscribers'] == 1 ? true : false;
      $this->aOptions['wp_admin_redirect_subscribers'] = isset($_POST['wp_admin_redirect_subscribers']) && $_POST['wp_admin_redirect_subscribers'] == 1 ? true : false;
      
      $this->aOptions['cap_activate'] = isset($_POST['cap_activate']) && $_POST['cap_activate'] == 1 ? true : false;
      $this->aOptions['cap_core'] = isset($_POST['cap_core']) && $_POST['cap_core'] == 1 ? true : false;
      $this->aOptions['cap_update'] = isset($_POST['cap_update']) && $_POST['cap_update'] == 1 ? true : false;
      $this->aOptions['cap_install'] = isset($_POST['cap_install']) && $_POST['cap_install'] == 1 ? true : false;
      $this->aOptions['cap_export'] = isset($_POST['cap_export']) && $_POST['cap_export'] == 1 ? true : false;
      
      if( empty($this->aOptions['cap_update']) && !empty($this->aOptions['cap_core']) ) {
        unset($this->aOptions['cap_core']);
      }
      
      $this->aOptions['search-results'] = isset($_POST['businesspress-search-results']) && $_POST['businesspress-search-results'] == 1 ? true : false;
      $this->aOptions['search-results-domain'] = isset($_POST['businesspress-search-results-domain']) && $_POST['businesspress-search-results-domain'] == 1 ? true : false;
      $this->aOptions['link-manager'] = isset($_POST['businesspress-link-manager']) && $_POST['businesspress-link-manager'] == 1 ? true : false;
      $this->aOptions['auto-set-featured-image'] = isset($_POST['businesspress-auto-set-featured-image']) && $_POST['businesspress-auto-set-featured-image'] == 1 ? true : false;
      $this->aOptions['admin-posts-yearly-dropdowns'] = isset($_POST['businesspress-admin-posts-yearly-dropdowns']) && $_POST['businesspress-admin-posts-yearly-dropdowns'] == 1 ? true : false;
      $this->aOptions['admin-woocommerce-search-speed'] = isset($_POST['businesspress-admin-woocommerce-search-speed']) && $_POST['businesspress-admin-woocommerce-search-speed'] == 1 ? true : false;
      $this->aOptions['disable-emojis'] = isset($_POST['businesspress-disable-emojis']) && $_POST['businesspress-disable-emojis'] == 1 ? true : false;
      $this->aOptions['disable-oembed'] = isset($_POST['businesspress-disable-oembed']) && $_POST['businesspress-disable-oembed'] == 1 ? true : false;
      $this->aOptions['disable-rest-api'] = isset($_POST['businesspress-disable-rest-api']) && $_POST['businesspress-disable-rest-api'] == 1 ? true : false;
      $this->aOptions['disable-xml-rpc'] = isset($_POST['businesspress-disable-xml-rpc']) && $_POST['businesspress-disable-xml-rpc'] == 1 ? true : false;
      $this->aOptions['login-logo'] = !empty($_POST['businesspress-login-logo']) ? trim($_POST['businesspress-login-logo']) : false;

      $this->aOptions['admin-color'] = !empty($_POST['admin_color']) ? trim($_POST['admin_color']) : false;
      $this->aOptions['hide-notices'] = isset($_POST['businesspress-hide-notices']) && $_POST['businesspress-hide-notices'] == 1 ? true : false;
      $this->aOptions['remove-generator'] = isset($_POST['businesspress-remove-generator']) && $_POST['businesspress-remove-generator'] == 1 ? true : false;
      $this->aOptions['xpull-key'] = !empty($_POST['businesspress-xpull-key']) ? trim($_POST['businesspress-xpull-key']) : false;
      
      $this->aOptions['xml-rpc-key'] = !empty($_POST['businesspress-xml-rpc-key']) ? trim($_POST['businesspress-xml-rpc-key']) : false;
      
      $this->aOptions['contact_email'] = !empty($_POST['contact_email']) ? trim($_POST['contact_email']) : false;
      $this->aOptions['multisite-tracking'] = !empty($_POST['businesspress-multisite-tracking']) ? stripslashes($_POST['businesspress-multisite-tracking']) : false;
      $this->aOptions['email-blocking'] = !empty($_POST['businesspress-email-blocking']) ? stripslashes($_POST['businesspress-email-blocking']) : false;
      
      $this->aOptions['admin-dropdown'] = isset($_POST['businesspress-admin-dropdown']) && $_POST['businesspress-admin-dropdown'] == 1 ? true : false;

      $this->aOptions['frontend_login_check'] = isset($_POST['frontend_login_check']) && $_POST['frontend_login_check'] == 1 ? true : false;

      $this->aOptions['hide_password_posts'] = isset($_POST['hide_password_posts']) && $_POST['hide_password_posts'] == 1 ? true : false;

      $this->aOptions['clickjacking-protection'] = isset($_POST['businesspress-clickjacking-protection']) && $_POST['businesspress-clickjacking-protection'] == 1 ? true : false;

      $this->aOptions['fix-new-user-nicenames'] = isset($_POST['businesspress-fix-new-user-nicenames']) && $_POST['businesspress-fix-new-user-nicenames'] == 1 ? true : false;

      $this->aOptions['disable-user-login-scanning'] = isset($_POST['businesspress-disable-user-login-scanning']) && $_POST['businesspress-disable-user-login-scanning'] == 1 ? true : false;

      $this->aOptions['login-lockout'] = isset($_POST['businesspress-login-lockout']) && $_POST['businesspress-login-lockout'] == 1 ? true : false;

      $this->aOptions['login-email-address'] = isset($_POST['businesspress-login-email-address']) && $_POST['businesspress-login-email-address'] == 1 ? true : false;

      if( is_multisite() ) {
        update_site_option( 'businesspress', $this->aOptions );
      } else {
        update_option( 'businesspress', $this->aOptions );
      }
      
      $this->prevent_clickjacking();

      wp_redirect( $this->get_settings_url() );
      die();
    }
    
    return;
  }  
  
  
  

  function hide_plugin_controls() {  
    $aUrlPath = explode( "/", $_SERVER["REQUEST_URI"] ) ;
    
    if( ( $aUrlPath[2] === "wp-admin" ) && ( $aUrlPath[3] === "plugins.php" )  ) {
      echo <<< JSH
<script>
jQuery('input[value^=businesspress]').remove();
</script>
JSH;
    }
  }
  
  
  
  
  function is_allowed_setting($key) {
    return in_array( $key, array( 'adminEmail', 'all', 'capsDisabled', 'upgradeType', 'version' ) );
  }
  



  function heartbeat_frequency($settings) {
    $settings['interval'] = 60;
    return $settings;
  }




  function hide_password_protected_posts( $query ) {
    if (
      $this->get_setting('hide_password_posts') &&
      !$query->is_singular() &&
      !is_admin() &&
      !current_user_can('edit_posts') 
    ) {
      $query->set( 'has_password', false );
    }
  }




  function list_core_update( $update, $show_checkboxes = true ) {
    global $wp_local_package, $wpdb, $wp_version;
      static $first_pass = true;
  
    if ( 'en_US' == $update->locale && 'en_US' == get_locale() )
      $version_string = $update->current;
    // If the only available update is a partial builds, it doesn't need a language-specific version string.
    elseif ( 'en_US' == $update->locale && $update->packages->partial && $wp_version == $update->partial_version && ( $updates = get_core_updates() ) && 1 == count( $updates ) )
      $version_string = $update->current;
    else
      $version_string = sprintf( "%s&ndash;<strong>%s</strong>", $update->current, $update->locale );
  
    $current = false;
    if ( !isset($update->response) || 'latest' == $update->response )
      $current = true;
    $submit = __('Upgrade to WordPress '.$version_string);
    $form_action = 'update-core.php?action=do-core-upgrade';
    $php_version    = phpversion();
    $mysql_version  = $wpdb->db_version();
    $show_buttons = true;
    if ( 'development' == $update->response ) {
      $message = __('You are using a development version of WordPress. You can update to the latest nightly build automatically or download the nightly build and install it manually:');
      $download = __('Download nightly build');
    } else {
      if ( $current ) {
        $message = sprintf( __( 'If you need to re-install version %s, you can do so here or download the package and re-install manually:' ), $version_string );
        $submit = __('Re-install Now');
        $form_action = 'update-core.php?action=do-core-reinstall';
      } else {
        $php_compat     = version_compare( $php_version, $update->php_version, '>=' );
        if ( file_exists( WP_CONTENT_DIR . '/db.php' ) && empty( $wpdb->is_mysql ) )
          $mysql_compat = true;
        else
          $mysql_compat = version_compare( $mysql_version, $update->mysql_version, '>=' );
  
        if ( !$mysql_compat && !$php_compat )
          $message = sprintf( __('You cannot update because <a href="https://codex.wordpress.org/Version_%1$s">WordPress %1$s</a> requires PHP version %2$s or higher and MySQL version %3$s or higher. You are running PHP version %4$s and MySQL version %5$s.'), $update->current, $update->php_version, $update->mysql_version, $php_version, $mysql_version );
        elseif ( !$php_compat )
          $message = sprintf( __('You cannot update because <a href="https://codex.wordpress.org/Version_%1$s">WordPress %1$s</a> requires PHP version %2$s or higher. You are running version %3$s.'), $update->current, $update->php_version, $php_version );
        elseif ( !$mysql_compat )
          $message = sprintf( __('You cannot update because <a href="https://codex.wordpress.org/Version_%1$s">WordPress %1$s</a> requires MySQL version %2$s or higher. You are running version %3$s.'), $update->current, $update->mysql_version, $mysql_version );
        else
          $message = '';//sprintf(__('You can update to <a href="https://codex.wordpress.org/Version_%1$s">WordPress %2$s</a> automatically or download the package and install it manually:'), $update->current, $version_string);
        
        if ( !$mysql_compat || !$php_compat )
          $show_buttons = false;
      }
      $download = sprintf(__('download %s'), $version_string);
    }
  
    echo '<p>';
    echo $message;
    echo '</p>';
    echo '<form method="post" action="' . $form_action . '" name="upgrade" class="upgrade">';
    wp_nonce_field('upgrade-core');
    echo '<p>';
    
    if( $show_checkboxes ) {
      echo '<p><input type="checkbox" class="check-1" /> I would like to do a core upgrade now.</p>';
      echo '<p><input type="checkbox" class="check-2" /> I have checked my plugins are up to date and/or compatible.</p>';
      echo '<p><input type="checkbox" class="check-3" /> I have a recent backup.</p>';
    } else {
      echo '<div style="display: none"><input type="checkbox" class="check-1" checked="checked" /><input type="checkbox" class="check-2"checked="checked" /><input type="checkbox" class="check-3" checked="checked" /></div>';
    }
    
    echo '<input name="version" value="'. esc_attr($update->current) .'" type="hidden"/>';
    echo '<input name="locale" value="'. esc_attr($update->locale) .'" type="hidden"/>';
    if ( $show_buttons ) {
      if ( $first_pass ) {
        submit_button( $submit, $current ? 'button' : 'regular', 'upgrade', false );
        $first_pass = false;
      } else {
        submit_button( $submit, 'button', 'upgrade', false );
      }
      echo '<p>Alternatively you can <a href="' . esc_url( $update->download ) . '">' . $download . '</a> and upload it via FTP.</p>';
    }
    if ( 'en_US' != $update->locale )
      if ( !isset( $update->dismissed ) || !$update->dismissed )
        submit_button( __('Hide this update'), 'button', 'dismiss', false );
      else
        submit_button( __('Bring back this update'), 'button', 'undismiss', false );
    echo '</p>';
    if ( 'en_US' != $update->locale && ( !isset($wp_local_package) || $wp_local_package != $update->locale ) )
        echo '<p class="hint">'.__('This localized version contains both the translation and various other localization fixes. You can skip upgrading if you want to keep your current translation.').'</p>';
    // Partial builds don't need language-specific warnings.
    elseif ( 'en_US' == $update->locale && get_locale() != 'en_US' && ( ! $update->packages->partial && $wp_version == $update->partial_version ) ) {
        echo '<p class="hint">'.sprintf( __('You are about to install WordPress %s <strong>in English (US).</strong> There is a chance this update will break your translation. You may prefer to wait for the localized version to be released.'), $update->response != 'development' ? $update->current : '' ).'</p>';
    }
    echo '</form>';
  
  }
  
  
  
  
  function load_extensions() {
    include( dirname(__FILE__).'/businesspress-settings.class.php' );
    
    if( !class_exists('CWS_Login_Logo_Plugin') ) {
      include( dirname(__FILE__).'/plugins/login-logo.php' );
    }
    
    if( !function_exists('disable_emojis') && $this->get_setting('disable-emojis') ) {
      include( dirname(__FILE__).'/plugins/disable-emojis.php' );
      
      remove_filter( 'the_content', 'convert_smilies', 20 );
      remove_filter( 'the_excerpt', 'convert_smilies' );
      remove_filter( 'the_post_thumbnail_caption', 'convert_smilies' );
      remove_filter( 'comment_text', 'convert_smilies', 20 );
    }
    
    if( !function_exists('disable_embeds_init') && $this->get_setting('disable-oembed') ) {
      include( dirname(__FILE__).'/plugins/disable-embeds.php' );
      add_filter( 'template_redirect', array( $this, 'oembed_template' ) );
    }
    
    if( !function_exists('DRA_Disable_Via_Filters') && $this->get_setting('disable-rest-api') ) include( dirname(__FILE__).'/plugins/disable-json-api.php' );
    
    if( !function_exists('apt_publish_post') && $this->get_setting('auto-set-featured-image') ) {
      include( dirname(__FILE__).'/plugins/auto-post-thumbnail.php' );
    }

    if ( $this->get_setting('admin-posts-yearly-dropdowns') ) {
      include( dirname(__FILE__).'/plugins/admin-posts-yearly-dropdowns.php' );
    }

    if ( $this->get_setting('admin-woocommerce-search-speed') ) {
      include( dirname(__FILE__).'/plugins/admin-woocommerce-search-speed.php' );
    }

    if( !function_exists('sort_settings_menu_wpse_2331') && !function_exists('sort_arra_asc_so_1597736') ) {
      include( dirname(__FILE__).'/plugins/wp-admin-settings-sort.php' );
    }

    include( dirname(__FILE__).'/plugins/wp-live-chat-software-for-wordpress.php' );

    if( $this->get_setting('login-lockout') ) {
      include( dirname(__FILE__).'/plugins/fv-user-lock-out.php' );
    }

    if( $this->get_setting('fix-new-user-nicenames') ) {
      include( dirname(__FILE__).'/plugins/fv-fix-new-user-nicenames.php' );
    }

    if( $this->get_setting('login-email-address') ) {
      include( dirname(__FILE__).'/plugins/fv-require-email-address-for-login.php' );
    }

    include( dirname(__FILE__).'/plugins/fv-simpler-login-errors.php' );

    if ( function_exists( 'initialize_social_warfare_pro' ) ) {
      include( dirname(__FILE__).'/plugins/social-warfare-pro-tweaks.php' );
    }

    if( get_option( 'surge_installed' ) ) {
      include( dirname(__FILE__).'/plugins/surge-cache-purge.php' );
    }

    include( dirname(__FILE__).'/plugins/users-by-date-registered.php' );

    include( dirname(__FILE__).'/plugins/fv-user-login-sessions.php' );

    include( dirname(__FILE__) . '/plugins/simple-history-clean-up.php' );

    include( dirname(__FILE__) . '/plugins/improve-user-activation.php' );

    include( dirname(__FILE__) . '/plugins/login-after-password-reset.php' );
  }
  
  
  
  
  function load_extensions_later() {
    if( !function_exists('wp_chosen_enqueue_assets') && $this->get_setting('admin-dropdown') ) {
      include( dirname(__FILE__).'/plugins/wp-chosen/includes/admin.php' );
      include( dirname(__FILE__).'/plugins/wp-chosen/includes/hooks.php' );
    }
  }
  
  
  
  function login_title( $title ) {
    $title = str_replace( ' &#8212; WordPress', '', $title );
    return $title;
  }

  // Apply BusinessPress setting to big_image_size_threshold value
  function big_image_size_threshold( $threshold, $imagesize, $file, $attachment_id) {
    if( $custom = get_option('big_image_size_threshold') ) {
      return $custom;
    }
    return $threshold;
  }

  // Get the current big_image_size_threshold, it migh be adjusted by some theme or plugin already
  function big_image_size_threshold_get() {
    // Do not let BusinessPress affect the value we are getting here
    remove_action( 'big_image_size_threshold', array( $this, 'big_image_size_threshold'), PHP_INT_MAX, 4 );
    $big_image_size_threshold = apply_filters( 'big_image_size_threshold', 2560, array( 8192, 8192 ), 'no-file', -1 ); 
    add_action( 'big_image_size_threshold', array( $this, 'big_image_size_threshold'), PHP_INT_MAX, 4 );
    return $big_image_size_threshold; 
  }

  // If the value being stored is the same as deafult, we keep it empty
  function big_image_size_threshold_sanitize( $value ) {
    if( $value == $this->big_image_size_threshold_get() ) {
      $value = null;
    }
    return $value;
  }

  function big_image_size_threshold_setting() {
    register_setting(
      'media',
      'big_image_size_threshold',
      array(
        'type' => 'integer',
        'show_in_rest' => true,
        'sanitize_callback' => array( $this, 'big_image_size_threshold_sanitize' )
      )
    );

    add_settings_field( 'big_image_size_threshold',
      'Maximum size<span title="Works with JPEG images. This is a hidden WordPress setting made available to you by BusinessPress." class="dashicons dashicons-editor-help"></span>',
      array( $this, 'maximum_size_show' ),
      'media'
    );
  }

  function maximum_size_show() {
    $big_image_size_threshold = $this->big_image_size_threshold_get();

    // Making the placeholder value more pale
    ?>
    <style>#big_image_size_threshold::placeholder { color: #aaa }</style>
    <fieldset>
      <legend class="screen-reader-text"><span><?php _e( 'Maximum size' ); ?></span></legend>
      <label for="big_image_size_threshold"><?php _e( 'Max Width or Height' ); ?></label>
      <input name="big_image_size_threshold" type="number" step="1" min="0" id="big_image_size_threshold" value="<?php form_option( 'big_image_size_threshold' ); ?>" class="small-text" placeholder="<?php echo intval($big_image_size_threshold); ?>" />
    </fieldset>
    <script>
    jQuery( function($) {
      // We use placeholder as the value to make the up and down arrows work and give user the default
      $('#big_image_size_threshold').on('mouseenter focus', function() {
        if( $(this).val() == '' ) {
          $(this).val( $(this).attr( 'placeholder' ) );
        }

      // If there was no change, we revert to the placeholder
      }).on('mouseleave blur', function() {
        if( $(this).val() == $(this).attr( 'placeholder' ) ) {
          $(this).val('');
        }
      });
    });
    </script>
    <?php
  }
  
  function multisite_footer() {
    if( !is_multisite() ) return;
    
    if( !empty($this->aOptions['multisite-tracking']) ) echo $this->aOptions['multisite-tracking'];
  }




  function anti_clickjacking_headers() {
    $options = get_option('businesspress');

    if( $this->get_setting('clickjacking-protection') && empty(get_query_var('fv_player_embed')) && empty($options['anticlickjack_rewrite']) ) {
      header( 'X-Frame-Options: SAMEORIGIN' );
      header( "Content-Security-Policy: frame-ancestors 'self'" );
    }
  }




  function prevent_clickjacking() {
    global $wp_rewrite;

    if ( is_multisite() ) {
      return;
    }

    $options = get_option('businesspress');

    if( strpos( $_SERVER['SERVER_SOFTWARE'], 'Apache') === false) {
      $options['anticlickjack_rewrite_result'] = __('Not using Apache, using header() fallback.', 'businesspress');
      update_option('businesspress', $options);
      return;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';

    $home_path     = get_home_path();
    $htaccess_file = $home_path . '.htaccess';

    $can_edit_htaccess = file_exists( $htaccess_file ) && is_writable( $home_path ) && $wp_rewrite->using_mod_rewrite_permalinks()
    || is_writable( $htaccess_file );

    $anti_clickjacking_rule = array(
      '# BEGIN Businesspress',
      '<IfModule mod_headers.c>',
      'Header set X-Frame-Options "SAMEORIGIN"',
      'Header set Content-Security-Policy "frame-ancestors \'self\'"',
      '</IfModule>',
      '# END Businesspress'
    );

    if( $this->get_setting('clickjacking-protection') ) {
      if ( $can_edit_htaccess && got_mod_rewrite() ) {
        if( empty($options['anticlickjack_rewrite']) ) {
          $rules = explode( "\n", $wp_rewrite->mod_rewrite_rules() );
          $rules =  array_merge($rules, $anti_clickjacking_rule);
  
          $result = insert_with_markers( $htaccess_file, 'WordPress', $rules );
          if($result) {
            $options['anticlickjack_rewrite'] = true;
            $options['anticlickjack_rewrite_result'] = __('Success: .htaccess modified.', 'businesspress');
          } else {
            $options['anticlickjack_rewrite'] = false;
            $options['anticlickjack_rewrite_result'] = __('Error: failed to modify .htaccess.', 'businesspress');
          }

          update_option('businesspress', $options);
        }
      } else {
        if(!$can_edit_htaccess) {
          $options['anticlickjack_rewrite_result'] = __('Error: .htaccess is not writable.', 'businesspress');
        } else {
          $options['anticlickjack_rewrite_result'] = __('Error: mod_rewrite is not loaded.', 'businesspress');
        }

        $options['anticlickjack_rewrite'] = false;

        update_option('businesspress', $options);
      }
    } else if ( !empty($options['anticlickjack_rewrite']) ) {
      $this->store_setting_db('anticlickjack_rewrite', false);

      $rules = explode( "\n", $wp_rewrite->mod_rewrite_rules() );

      $options['anticlickjack_rewrite'] = false;
      update_option('businesspress', $options);

      insert_with_markers( $htaccess_file, 'WordPress', $rules );
    }
  }




  function oembed_template() {
    if( get_query_var('embed') ) {
      add_filter( 'template_include', '__return_false' );
    }
  }




  function plugin_action_links( $actions, $plugin_file ) {
    if( stripos($plugin_file,'businesspress') !== false ) {
      unset($actions['deactivate']);
    }

    return $actions;
  }

  
  
  
  function plugin_update_hook() {
    
    $bStore = false;
    if( !isset($this->aOptions['version']) || version_compare($this->aOptions['version'], '0.6.6') < 1 ) {
      if( $this->get_whitelist_domain() || $this->get_whitelist_email() ) {
        $this->aOptions['restrictions_enabled'] = true;
        $bStore = true;
      }
    }    
    
    if( $bStore || empty($this->aOptions['version']) || $this->aOptions['version'] != BusinessPress::VERSION ) {
      
      if( empty($this->aOptions['auto-set-featured-image']) ) {
        $this->aOptions['auto-set-featured-image'] = true;
      }
      
      if( empty($this->aOptions['admin-dropdown']) ) {
        $this->aOptions['admin-dropdown'] = true;
      }

      if( empty($this->aOptions['admin-posts-yearly-dropdowns']) ) {
        $this->aOptions['admin-posts-yearly-dropdowns'] = true;
      }

      if( empty($this->aOptions['admin-woocommerce-search-speed']) ) {
        $this->aOptions['admin-woocommerce-search-speed'] = true;
      }      

      $this->aOptions['version'] = BusinessPress::VERSION;
      if( is_multisite() ){
        update_site_option( 'businesspress', $this->aOptions );
      } else {
        update_option( 'businesspress', $this->aOptions );
      }

      $this->prevent_clickjacking();
    }
    
  }
  
  
  
  
  function pointer_ajax() {
    if( isset($_POST['key']) && $_POST['key'] == 'businesspress_default_settings' && isset($_POST['value']) ) {
      check_ajax_referer('businesspress_default_settings');

      $this->aOptions['pointer_defaults'] = true;
      if( is_multisite() ){
        update_site_option( 'businesspress', $this->aOptions );
      } else {
        update_option( 'businesspress', $this->aOptions );
      }
      die();
    }

    if( isset($_POST['key']) && $_POST['key'] == 'businesspress_login_lockout' && isset($_POST['value']) ) {
      check_ajax_referer('businesspress_login_lockout');

      $this->aOptions['pointer_login_lockout'] = true;
      if( is_multisite() ){
        update_site_option( 'businesspress', $this->aOptions );
      } else {
        update_option( 'businesspress', $this->aOptions );
      }
      die();
    }
  }
  
  
  
  
  function pointer_defaults() {
    if( !$this->get_setting('pointer_defaults') ) {
      $this->pointer_boxes['businesspress_default_settings'] = array(
            'id' => '#wp-admin-bar-new-content',
            'pointerClass' => 'businesspress_default_settings',
            'heading' => __('BusinessPress', 'fv-wordpress-flowplayer'),
            'content' => sprintf( __('<p>To improve your site security and performance BusinessPress had disabled your REST API, WordPress Generator Tag and Emojis.</p><!--<p>The plugin is also moving all the Admin Notices into the Dashboard -> Notices screen to keep your WP Admin Dashboard clean.</p>-->', 'businesspress'), $this->get_settings_url() ),
            'position' => array( 'edge' => 'top', 'align' => 'center' ),
            'button1' => __('Open Settings', 'businesspress'),
            'button2' => __('Dismiss', 'businesspress')
          );
    }

    if( $this->get_setting('login-lockout') && !$this->get_setting('pointer_login_lockout') ) {
      $this->pointer_boxes['businesspress_login_lockout'] = array(
            'id' => '#wp-admin-bar-new-content',
            'pointerClass' => 'businesspress_login_lockout',
            'heading' => __('BusinessPress', 'fv-wordpress-flowplayer'),
            'content' => sprintf( __('<p>We have enabled the <strong>Login Lockout</strong> to protect your user accounts from botnets password guessing.</p><p>Please turn off if you have disabled the standard WordPress Password Reset form.</p>', 'businesspress'), $this->get_settings_url() ),
            'position' => array( 'edge' => 'top', 'align' => 'center' ),
            'button1' => __('Open Settings', 'businesspress'),
            'button2' => __('Dismiss', 'businesspress')
          );
    }
    
    if( !empty($this->pointer_boxes) ) {
      add_action( 'admin_print_footer_scripts', array($this,'pointer_scripts'), 999 );
    }
    
    add_action( 'wp_ajax_fv_foliopress_ajax_pointers', array($this,'pointer_ajax') );
  }
  
  
  
  
  function pointer_scripts() {
    ?>
    <script>
      (function ($) {
        $(document).ready( function() {
          $('.businesspress_default_settings .button-primary').click( function(e) {
            $(document).ajaxComplete( function() {
              window.location = '<?php echo $this->get_settings_url(); ?>#preferences';
            });
          });

          $('.businesspress_login_lockout .button-primary').click( function(e) {
            $(document).ajaxComplete( function() {
              window.location = '<?php echo $this->get_settings_url(); ?>#businesspress_login';
            });
          });
        });
			})(jQuery);        
    </script>
    <?php 
  }




  function recovery_email($email_data) {
    if( $this->get_whitelist_email() ) {
      $email_data['to'] = $this->get_whitelist_email();
    }

    return $email_data;
  }




  function remove_generator_tag() {
    if( $this->get_setting('remove-generator') ) {
      remove_action( 'wp_head', 'edd_version_in_header' );
      remove_action( 'wp_head', 'wp_generator' );      
    }
  }
  
  
  
  
  function remove_wp_footer($html) {
    $text = sprintf( __( 'Thank you for creating with <a href="%s">WordPress</a>.' ), __( 'https://wordpress.org/' ) );
    $html = str_replace( $text, '', $html );
    return $html;
  }  
  
  
  
  
  function remove_wp_admin_bar_items() {
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu('wp-logo');
    $wp_admin_bar->remove_menu('updates');
    $wp_admin_bar->remove_menu('customize');
  }
  
  
  
  
  function script_reload( $arg = null ) {
    if( isset( $arg ) ) {
      $arg = str_replace( '&amp;', '', $arg );
      $arg = str_replace( '&', '', $arg );
      $arg = '&' . $arg;
    } else {
      $arg = '';
    }
    echo <<< JSR
<script>
var goto = window.location.href;
goto = goto.replace( /\&act=[^&]+/g, '' );
goto += "$arg";
window.location.href = goto;
</script>
JSR;

  }
  
  
  
  
  function subscriber__dashboard_redirect() {
		global $pagenow;
    
    if( defined('DOING_AJAX') ) {
      return;
    }
    
    if( $this->get_setting('wp_admin_redirect_subscribers') ) {
      wp_redirect( home_url() );
      exit;
    }
    
		if ( 'profile.php' != $pagenow ) {
			wp_redirect( site_url('wp-admin/profile.php') );
			exit;
		}
	}
  
  
  
  
  function subscriber_notification_disable( $user ) {
    if ( in_array( 'subscriber', $user->roles ) ) {
      remove_action( 'after_password_reset', 'wp_password_change_notification' );
    }
  }




  function subscriber__hide_menus() {
		global $menu;

		$menu_ids = array();

		// Gather menu IDs (minus profile.php).
		foreach ( $menu as $index => $values ) {
			if ( isset( $values[2] ) ) {
				if ( 'profile.php' == $values[2] ) {
					continue;
				}

				// Remove menu pages.
				remove_menu_page( $values[2] );
			}
		}    
  }
  
  
  
  
  function stop_disable_wordpress_core_updates() {
    global $wp_filter;
    if( !isset($wp_filter['pre_site_transient_update_core']) ) return;
    
    foreach( $wp_filter['pre_site_transient_update_core'] AS $key => $filters ) {
      foreach( $filters AS $k => $v ) {
        if( stripos($k,'lambda') !== false ) {
          unset( $wp_filter['pre_site_transient_update_core'][$key][$k] );
        }
      }
    }
  }
  
  
  
  
  function store_setting( $key, $value ) {return false;
    $data = $this->get_setting('all') ? $this->get_setting('all') : array();
    if( $this->is_allowed_setting($key) === false ) return -1;

    $data[$key] = $value;
    $this->store_setting_db('fvsb_genSettings', $data);
  }  


  
  
  function store_setting_db( $key, $value ) {
    if( is_multisite() ){
      update_site_option($key, $value);
    } else{
      update_option($key, $value);
    }
  }
  
  
  
  
  function talk_no_permissions( $what ) {
    $contact_form = ' <a href="'.$this->get_settings_url().'&contact_form">'.__("Contact form",'businesspress').'</a>';
    if( $this->get_whitelist_domain() ) {
      return sprintf( __("Please contact your site admin or your partners at %s to %s.",'businesspress'), $this->get_whitelist_domain(), $what ).$contact_form;
    } else if( $this->get_whitelist_email() ) {
      return sprintf( __("Please contact %s to %s.",'businesspress'), $this->get_whitelist_email(), $what ).$contact_form;
    }
    return false;
  }
  
  
  
  
  function tweak_login_redirect( $url ) {
    if( empty($_REQUEST['redirect_to']) && !empty($_SERVER["HTTP_REFERER"]) && stripos($_SERVER["HTTP_REFERER"],'wp-login.php') === false ){
      $url = $_SERVER["HTTP_REFERER"];
    }
    return $url;
  }  
  
  
  
  
  function upgrade_screen_start() {
    ob_start();
  }
  
  
  
  
  function upgrade_screen() {
    $html = ob_get_clean();
    
    if( !$this->check_user_permission() && !$this->can_update_core() ) {
      $html = preg_replace( '~<form[^>]*?>~', '<!--form opening tag removed by BusinessPres-->', $html );
      $html = str_replace( '</form>', '<!--form closing tag removed by BusinessPres-->', $html );
    }
      
    if( !$this->check_user_permission() && ( empty($this->aOptions['cap_update']) || !$this->aOptions['cap_update'] ) ) {
      $html = preg_replace( '~<input[^>]*?type=["\']checkbox["\'][^>]*?>~', '', $html );
      $html = preg_replace( '~<thead[\s\S]*?</thead>~', '', $html );
      $html = preg_replace( '~<tfoot[\s\S]*?</tfoot>~', '', $html ); 
      $html = preg_replace( '~<input[^>]*?upgrade-plugins[^>]*?>~', '', $html );      
      $html = preg_replace( '~<input[^>]*?upgrade-themes[^>]*?>~', '', $html );
    }
    
    global $wp_version;
    $new_html = '';
    if( !$this->check_user_permission() && !$this->can_update_core() ) {
      $new_html .= "<div class='error'><p>".$this->talk_no_permissions('upgrade WordPress core')."</p></div>";
    }
    $new_html .= "<h4>WordPress ".$wp_version." installed<br />";
    
    global $wp_version;
    $sStatus = false;
    $iTTL = 0;
    $aVersions = $this->cache_core_version_info();

    if( $aVersions && isset($aVersions['data']) && count($aVersions['data']) > 0 ) {      
      if( $this->get_version_branch() && isset($aVersions['data'][$this->get_version_branch()]) ) {
        $iDate = strtotime($aVersions['data'][$this->get_version_branch()]);
        $iTTL = $iDate + 3600*24*30 * 52; //  the current version is good has time to live set to 30 months, based on 4.7 which started in Dec 2016 and still got an update in April 2021
        if( $iTTL - time() < 0 ) { 
          $sStatus = "Not Secure - Major Upgrade Required";
        } else if( $iTTL - time() < 3600 * 24 * 30 * 3 ) { //  if the current version is older than 23 monts, warn the user
          $sStatus = "Update Recommended Soon";
        } else {  
          $sStatus = "Secure";
        }
      }
      
      if( $this->get_branch_latest() != $wp_version && strtotime($aVersions['data'][$this->get_branch_latest()]) + 3600 * 24 * 5 < time() ) {
        $sStatus = "Not Secure - Minor Upgrade Required";
      }
      
    }
        
    $new_html .= "Last updated: ".date( 'j F Y', strtotime($aVersions['data'][$this->get_branch_latest()]) )."<br />";
    $new_html .= "Status: ".$sStatus."<br />";
    $iRemaining = floor( ($iTTL-time())/(3600*24)/30 );
    if( $iRemaining > 0 ) {
      $new_html .= "Projected security updates: ".$iRemaining." months.";
    } else {
      $new_html .= "Projected security updates: Negative ".abs($iRemaining)." months. Expired or expiration imminent - we expect there will be no more security updates to ".$this->get_version_branch().".";
    }
    $new_html .= "</h4>\n";
    
    if( !class_exists('Core_Upgrader') ) {
      include_once( ABSPATH . '/wp-admin/includes/admin.php' );
      include_once( ABSPATH . '/wp-admin/includes/class-wp-upgrader.php' );
    }

    if( class_exists('Core_Upgrader') ) {
      $new_html .= "<p>Core auto-updates status: ";
      
      $bDisabled = false;
      if( class_exists('Core_Upgrader') ) {
        $objUpdater = new WP_Automatic_Updater;
        if( $objUpdater->is_disabled() ) {
          $new_html .= "disabled";
          $bDisabled = true;
        }
      }
      
      if( !$bDisabled ) {
        if( Core_Upgrader::should_update_to_version('100.1.2.3') ) {
          $new_html .= "<strong>Major version updates enabled</strong>";
        } else if( Core_Upgrader::should_update_to_version( get_bloginfo( 'version' ).'.0.1') ) {
          $new_html .= "only Minor version updates enabled";
        }
      }
      $new_html .= "</p>";
    }
    
    $aBlockedUpdates = get_site_option('businesspress_core_update_delay');
    $bFound = false;
    if( $aBlockedUpdates ) {
      foreach( $aBlockedUpdates AS $key => $value ) {
        if( stripos($key,'.next.minor') === false ) {
          $bFound = true;
        }
      }
    }
      
    if( $bFound && $aBlockedUpdates ) {
      
      ksort( $aBlockedUpdates );
      $aBlockedUpdates = array_reverse( $aBlockedUpdates );
      
      $new_html .= "<p>Recently blocked updates:</p>";      
      $new_html .= "<ul>\n";
      foreach( $aBlockedUpdates AS $key => $value ) {
        if( stripos($key,'.next.minor') !== false ) {
          $new_html .= "<li>WP core internal autoupdate check ".human_time_diff(time(),$value)." ago</li>\n";
          continue;
        }
        $new_html .= "<li><a href='https://codex.wordpress.org/Version_".$key."' target='_blank'>".$key."</a> ".human_time_diff(time(),$value)." ago</li>\n";
      }
      $new_html .= "</ul>\n";
      $new_html .= "<p><a href='".$this->get_settings_url()."'>BusinessPress</a> delays these updates 5 days to make sure you are not affected by any bugs in them.</p>";
      
    } else {
      //$new_html .= "<p>No recent actions, be careful with your upgrades!</p>";
      
    }
    
    /*if( stripos($html,'update-core.php?action=do-core-upgrade') !== false ) {

      preg_match( '~<input name="version" value="4.5"~', $html, $aVersion );
      $new_html .= "<p>Alternatively you can download 4.4.2 and upload it via FTP.</p><p>While your site is being updated, it will be in maintenance mode. As soon as your updates are complete, your site will return to normal.</p>";
    }*/
    
    
    //  this bit if from update-core.php
    
    
    ob_start();

    global $wp_version, $required_php_version, $required_mysql_version;
    
    $aShowed = array();
    if( $this->check_user_permission() || $this->can_update_core() ) {
      $aUpdates = get_site_transient( 'update_core' );
      if( !$aUpdates ) $aUpdates = get_option( '_site_transient_update_core' );
      
      if( $aUpdates && count($aUpdates->updates) ) {
        foreach( $aUpdates->updates AS $update ) {        
          if( stripos($update->version,$this->get_version_branch()) === 0 ) {
            if( $update->version == $wp_version ) {
              echo "<strong>You have the latest version of WordPress.</strong>";
              continue;
            }
            
            if( isset($aShowed[$update->version]) ) continue;
            
            $aShowed[$update->version] = true;
            
            echo '<ul class="core-updates-businespress">';
            echo '<strong class="response">';
            _e( 'There is a security update of WordPress available.', 'businesspress' );
            echo '</strong>';
            echo '<li>';
            $this->list_core_update( $update, false );
            echo '</li>';
            echo '</ul>';
          }
        }
      }  
    }
    
    $updates = get_site_transient( 'update_core' );
    $updates = !empty($updates->updates) ? $updates->updates : get_core_updates();
    
    $bMajorUpdate = false;

    foreach ( (array) $updates as $update ) {
      if( stripos($update->version,$this->get_version_branch()) === false ) {
        $bMajorUpdate = true;
      }
    }
    
    if ( !isset($updates[0]->response) || 'latest' == $updates[0]->response ) {
      /*echo '<h2>';
      _e('You have the latest version of WordPress.');
  
      if ( wp_http_supports( array( 'ssl' ) ) ) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        $upgrader = new WP_Automatic_Updater;
        $future_minor_update = (object) array(
          'current'       => $wp_version . '.1.next.minor',
          'version'       => $wp_version . '.1.next.minor',
          'php_version'   => $required_php_version,
          'mysql_version' => $required_mysql_version,
        );
        $should_auto_update = $upgrader->should_update( 'core', $future_minor_update, ABSPATH );
        if ( $should_auto_update )
          echo ' ' . __( 'Future security updates will be applied automatically.' );
      }
      echo '</h2>';*/
    } else if( $bMajorUpdate ) {
  
      echo '<strong class="response">';
      _e( 'There is a core upgrade version of WordPress available.', 'businesspress' );
      echo '</strong>';
      if( $this->check_user_permission() || $this->can_update_core() ) {
        echo '<p>';
        _e( 'Be very careful before you upgrade: in addition to causing your site to fail to load, core upgrades can corrupt your database or cause plugins important to your business to fail, such as membership and ecommerce solutions. <strong>Please be sure to upgrade all your plugins to their most recent version before a major version upgrade.</strong>', 'businesspress' );
        echo '</p>';
      }
      
    }
  
    if ( isset( $updates[0] ) && $updates[0]->response == 'development' ) {
      /*require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
      $upgrader = new WP_Automatic_Updater;
      if ( wp_http_supports( 'ssl' ) && $upgrader->should_update( 'core', $updates[0], ABSPATH ) ) {
        echo '<div class="updated inline"><p>';
        echo '<strong>' . __( 'BETA TESTERS:' ) . '</strong> ' . __( 'This site is set up to install updates of future beta versions automatically.' );
        echo '</p></div>';
      }*/
    }
  
    if( $bMajorUpdate && ( $this->check_user_permission() || $this->can_update_core() ) ) {
      echo '<ul class="core-updates-businespress">';
      
      $aShowed = array();      
      foreach ( (array) $updates as $update ) {
        if( stripos($update->version,$this->get_version_branch()) === 0 ) {
          continue; //  don't show the minor updates here!
        }
        
        if( isset($aShowed[$update->version]) ) continue;
              
        $aShowed[$update->version] = true;        
        
        echo '<li>';
        if( !isset($update->response) || 'latest' == $update->response ) {
          list_core_update( $update );
        } else {          
          $this->list_core_update( $update );
        }
        echo '</li>';
      }
      echo '</ul>';
      // Don't show the maintenance mode notice when we are only showing a single re-install option.
      if ( $updates && ( count( $updates ) > 1 || $updates[0]->response != 'latest' ) ) {
        echo '<p>' . __( 'While your site is being updated, it will be in maintenance mode. As soon as your updates are complete, your site will return to normal.' ) . '</p>';
      } elseif ( ! $updates ) {
        list( $normalized_version ) = explode( '-', $wp_version );
        echo '<p>' . sprintf( __( '<a href="%s">Learn more about WordPress %s</a>.' ), esc_url( self_admin_url( 'about.php' ) ), $normalized_version ) . '</p>';
      }
      
    }
  
    $new_html .= ob_get_clean();

    $plugins_translated = __( 'Plugins' );

    if( preg_match( '~<h\d[^>]*?>[\s\S]*'.$plugins_translated.'[\s\S]*</h\d>~', $html ) ) {
      $html = preg_replace( '~(<div class="wrap">)([\s\S]*?)(<h\d[^>]*?>[\s\S]*'.$plugins_translated.'[\s\S]*</h\d>)~', '$1'.$new_html.'$3', $html );
    } else {
      $html = preg_replace( '~(<div class="wrap">)([\s\S]*?)$~', '$1'.$new_html, $html );
    }
    
    echo $html;
    
    ?>
    <script>
    jQuery(function($){
      $('form[name=upgrade]').submit( function(e) {
        var form = $(this);
        
        if( form.find('.check-1').prop('checked') && form.find('.check-2').prop('checked') && form.find('.check-3').prop('checked') ) {

        } else {
          e.preventDefault();
          alert("Please confirm your site is ready for a core upgrade by checking the boxes above.");
        }
        
      });
    });
    </script>
    <?php

  }
  
  function wp_login_errors( $errors ) {
    if( isset($_GET['checkemail']) && $_GET['checkemail'] == 'confirm' && isset($errors->errors) && isset($errors->errors['confirm']) && isset($errors->errors['confirm'][0]) ) {
      $errors->errors['confirm'][0] .= "<br /><br />".__("Please check your Junk or Spam folder if the email doesn't seem to arrive in 10 minutes.",'businesspress');
    }
    return $errors;
  }

  function wp_mail_from( $from ) {
    if( $from == 'WordPress' ) {
      $from = get_bloginfo();
    }
    return $from;
  }

  function wp_mail_block_stage_1( $args ) {
    $to = $args['to'];
    $blocked = explode("\n",$this->get_setting('email-blocking'));
    $blocked = array_map('trim', $blocked);
    if( in_array($to, $blocked) ) {
      unset ( $args['to'] );
      add_action( 'phpmailer_init', array( $this, 'wp_mail_block_stage_2' ), 99, 1 );
    }
    return $args;
  }

  function wp_mail_block_stage_2( $phpmailer ) {
    $phpmailer->ClearAllRecipients();
    $phpmailer->ClearAttachments();
    $phpmailer->ClearCustomHeaders();
    $phpmailer->ClearReplyTos();
  }  

  function login_check_ajax() {
    if( !is_user_logged_in() ) {
      echo "Not logged in";
    } else {
      die();
    }
  }


  function login_check_js() {
    if( !is_user_logged_in() || !$this->get_setting('frontend_login_check') ) return;

    ?>
<script>
(function($) {
  function bpress_login_check() {
    $.post( '<?php echo admin_url('admin-ajax.php'); ?>?bpress_login_check', { action: 'bpress_login_check' }, function(response) {
      if( response ) {
        location.href = '<?php echo add_query_arg( 'redirect_to', sanitize_url( $_SERVER['REQUEST_URI'] ), site_url('wp-login.php') ); ?>';
      }
    });
  }

  $(document).on('visibilitychange', function() {
    if( !document.hidden ) {
      bpress_login_check();
    }
  }).on('popstate pageshow', bpress_login_check );
})(jQuery);    
</script>
    <?php
  }
  
}

global $businesspress;
$businesspress = new BusinessPress();
