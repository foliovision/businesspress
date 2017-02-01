<?php
/*
Plugin Name: BusinessPress
Plugin URI: http://www.foliovision.com
Description: This plugin secures your site
Version: 0.6.6
Author: Foliovision
Author URI: http://foliovision.com
*/

/*
Version: 0.5: Reworking settings from scratch
Version: 0.4.9: Set to only allow minor updates by default
Version: 0.4.8: Changed name to BusinessPress and code reformated
Version: 0.4.7: Javascript even if its not erroneous not showing in other places than plugins-list
Version: 0.4.6: Fixed plugins_loaded EVIL!, Retyping EVIL, and my ONE ADMIN sites
Version: 0.4.5: Fixed JS error in WP-Admin, 
Version: 0.4.4: Plugin folder/file was renamed to FV Security Bundle, added comments, added possibility to turn on/off security checks
Version: 0.4.3: Re-Added showing Message about not defined "DISALLOW_FILE_EDIT", repaired after-upgrade cleaning scripts
Version: 0.4.2: Repaired disabling of "Deactivate" link when Restriction mode == ON
Version: 0.4.1: Better uses of MU options + messagess are more clear
Version: 0.4:   Now FV Security Bundle, whole plugin rewritten, uses filters, multisite support, OOP aproach, lots of functions
Version: 0.3.1: Lots of bugfixes!
Version: 0.3:   Added possibility to choose restricted capabilities 
Version: 0.2.2: The plugin now edits (add/remove) capabilities only to admin users
*/




class BusinessPress {

  /* DB records
  * ( timestamp ) fvsb_capsDisabled - if true, capabilities are disabled
  * ( serialized hash map) fvsb_capsArray - contains capabilities to restrict
  * ( string {none | minor | major}) fvsb_upgrades - autoupdates 
  * ( string ) fvsb_adminMail - email adress where adress to be sent - default is programming@foliovision.com
  * (hash_map) fvsb_genSettings // contains (capsDisabled, upgradesType, adminMail, version) 
  */
  
  /* constants */
  const VERSION = '0.6.4';
  const FVSB_LOCK_FILE = 'fv-disallow.php';
  const FVSB_DEBUG = 0;
  const FVSB_CRON_ENABLED = 1;
  
  
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
  private $str_disallow_check_file = "";  // string, path to checkfile
  private $strArrMessages = array(); // string array, contains messages used in this plugin

  var $aCoreUpdatesDismiss = array();
  
  var $aCoreUpdatesWhitelist = false;


  public function __construct() {
    if( !function_exists('add_action') ) {
      exit( 0 );
    }
    
    $this->adminEmail = get_option('admin_email');
    
    $this->aOptions = is_multisite() ? get_site_option('businesspress') : get_option( 'businesspress' ); 
    
    $this->strArrMessages['E_CANT_WRITE'] = '<div class="error"><p><span style="color:red; font-weight:bold;">FATAL ERROR</span>: Plugin cant write into Wordpress root directory. Check file permissions.</p></div>';
    
    $this->str_disallow_check_file = trailingslashit($this->get_wp_root_dir()).BusinessPress::FVSB_LOCK_FILE;
            
    if( BusinessPress::FVSB_DEBUG == 1 ) {
      $this->dump();
    }
    
    
    add_action( 'in_plugin_update_message-fv-disallow-mods/fv-disallow-mods.php', array( &$this, 'plugin_update_message' ) );
    
    register_activation_hook( __FILE__, array( $this , 'activate') );
    register_deactivation_hook( __FILE__, array( $this, 'deactivate') );        
    
    add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu',  array( $this, 'menu' ) );
    
    
    add_action( 'admin_init', array( $this, 'plugin_update_hook' ) );
    
    //add_action( 'businesspress_cron', array( $this, 'cron_job' ) );
    
    if( is_multisite() ) {
      //add_action( 'network_admin_notices', array( $this, 'show_disallow_not_defined') );  //  todo: what about this?
    } else {
      //add_action( 'admin_notices', array( $this, 'show_disallow_not_defined') );
    }
    
    add_action( 'admin_notices', array( $this, 'notice_configure') );
    add_action( 'network_admin_notices', array( $this, 'notice_configure') );
    
    
    add_filter( 'auto_update_core', array( $this, 'delay_core_updates' ), 999, 2 );
    
    //add_filter( 'dashboard_glance_items', array( $this, 'core_updates_discard' ) );  // show only the current branch update for dashboard
    
    add_action( 'admin_init', array( $this, 'admin_screen_cleanup') );
    add_action( 'admin_head', array( $this, 'admin_screen_cleanup_css') );
    
    add_action( 'admin_init', array( $this, 'handle_post') );
    
    add_action( 'admin_init', array( $this, 'stop_disable_wordpress_core_updates') );
    
    add_action( 'init', array( $this, 'apply_restrictions') );
    add_action( 'admin_init', array( $this, 'apply_restrictions') );
    
    add_action( 'load-update-core.php', array( $this, 'upgrade_screen_start') );
    add_action( 'core_upgrade_preamble', array( $this, 'upgrade_screen') );
    
    add_filter( 'wp_login_failed', array( $this, 'fail2ban_login' ) );
    add_filter( 'xmlrpc_login_error', array( $this, 'fail2ban_xmlrpc' ) );
    add_filter( 'xmlrpc_pingback_error', array( $this, 'fail2ban_xmlrpc_ping' ), 5 );

    add_filter( 'template_redirect', array( $this, 'fail2ban_404' ) );
    
    add_filter( 'send_core_update_notification_email', '__return_false' );  //  disabling WP_Automatic_Updater::send_email() with subject of "WordPress x.y.z is available. Please update!"
    add_filter( 'auto_core_update_send_email', '__return_false' );  //  disabling WP_Automatic_Updater::send_email() with subject of "Your site has updated to WordPress x.y.z"

    add_filter( 'wp_login_errors', array( $this, 'wp_login_errors' ) );
  }
  
  
  
  
  function activate() {
    $this->aOptions = array();
    $this->aOptions['core_auto_updates'] = 'minor';
    update_option( 'businesspress', $this->aOptions ); 
  }
  
  
  
  
  function admin_screen_cleanup() {
    
    if( isset($_GET['businesspress_cron_debug']) ) {
      $this->cron_job();
    }
    
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
      #adminmenu .update-plugins .plugin-count { display: none }
      #wp-version-message .button { display: none }
    </style>
    <?php
  }

  
  
  
  function apply_restrictions() {
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
    
    if( !empty($this->aOptions['wp_admin_bar_subscribers']) && $this->aOptions['wp_admin_bar_subscribers'] && get_current_user_id() > 0 ) {
      $objUser = get_userdata( get_current_user_id() );
      if( $objUser && isset($objUser->roles) && count($objUser->roles) == 2 && ( $objUser->roles[0] == 'subscriber' && $objUser->roles[1] == 'bbp_participant' || $objUser->roles[0] == 'bbp_participant' && $objUser->roles[1] == 'subscriber' ) ) {  //  this is silly, but we can't rely on !current_user_can() with edit_posts or delete_posts to detect Subscribers because of bbPress
        add_filter('show_admin_bar', '__return_false');
      }
    }
    
  }
  
  
  
  
  function can_update_core() {
    if( !empty($this->aOptions['cap_update']) && $this->aOptions['cap_update'] && !empty($this->aOptions['cap_core']) && $this->aOptions['cap_core'] ) {
      return true;
    }
    return false;
  }
  
  
  
  
  function cache_core_version_info() {
    $aVersions = get_option( 'businesspress_core_versions' );
    if( isset($_GET['martinv5']) || !$aVersions || !isset($aVersions['ttl']) || $aVersions['ttl'] < time()  ) {
      $bSuccess = false;
      $aResponse = wp_remote_get( 'https://codex.wordpress.org/WordPress_Versions' );
      if( !is_wp_error($aResponse) ) {      
        preg_match_all( '~<tr[^>]*?>[\s\S]*?([0-9.-]+)[\s\S]*?(\S+ \d+, 20\d\d)[\s\S]*?</tr>~', $aResponse['body'], $aMatches );
               
        $aVersions = array( 'data' => array() );
        $aVersions['ttl'] = time() + 900;
        if( count($aMatches[2]) > 0 ) {
          $bSuccess = true;
          foreach( $aMatches[2] AS $k => $v ) {          
            $aVersions['data'][$aMatches[1][$k]] = $v;
          }
        }
        
      }
      
      if( !$bSuccess ) {
        $aVersions = get_option( 'businesspress_core_versions', array() );
        $aVersions['ttl'] =  time()+120;
        
      }
      
      update_option( 'businesspress_core_versions', $aVersions );
      
    }
    
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
    if( !empty($this->aOptions['email']) && $this->aOptions['email'] == $current_user->user_email ) {
      return true;
    } else if( !empty($this->aOptions['domain']) && $this->aOptions['domain'] == $this->get_email_domain($current_user->user_email) ) {
      return true;
    }
    
    if( empty($this->aOptions['email']) && empty($this->aOptions['domain']) ) {
      return true;
    }
    
    return false;
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
  


  
  function cron_job() {
    global $wp_filter;
    
    $AssocErr = array();  
    $AssocErr['bDisallowMods'] = 'OK';  // DISALLOW_FILE_MODS should not be defined  
    $AssocErr['bDisallowEdits'] = 'OK'; // DISALLOW_FILE_EDIT must be defined  
    $AssocErr['bUpdateSettingsMismatch'] = 'OK';  // Mismatch in settings  
    $AssocErr['bCurrentUserCan1'] = 'OK'; // Current user can?
    $AssocErr['bCurrentUserCan2'] = 'OK';
    
    $checks = $this->get_setting_db('fvsb_checks');
    
    
    // BASIC CHECKS 
    if( $checks['disallow_edits_defined'] === 1 ) {
      if( defined('DISALLOW_FILE_EDIT') ) {
        if( DISALLOW_FILE_EDIT === false ) {
          $AssocErr['bDisallowEdits'] = "Disallow File Edit is not defined!"; //  todo: check what this really means
        }
      }
    }
    
    if( $checks['disallow_mods_defined'] === 1 ) {
      if( defined('DISALLOW_FILE_MODS') ) {
        if( DISALLOW_FILE_MODS === true ) {
          $AssocErr['bDisallowMods'] = "Disallow File Mods is defined!";
        }
      }
    }
    
    
    // CHECKS RELATED TO UPDATES - we dont want updates!
    if( $checks['update_filters_mismatch'] === 1 ) {
  
      if( $this->get_setting('upgradeType') == 'none' ) {
        // defines
        if( defined('AUTOMATIC_UPDATER_DISABLED') && AUTOMATIC_UPDATER_DISABLED === false ) {
          $AssocErr['bUpdateSettingsMismatch'] = 'Core autoupdates is set to None, but AUTOMATIC_UPDATER_DISABLED is defined as false.';
        }
        if( defined('WP_AUTO_UPDATE_CORE') && ( WP_AUTO_UPDATE_CORE === true || WP_AUTO_UPDATE_CORE === 'minor' ) ) {
          $AssocErr['bUpdateSettingsMismatch'] = 'Core autoupdates is set to None, but AUTOMATIC_UPDATE_CORE is defined as true or minor.';
        }
        if( array_key_exists('allow_minor_auto_core_updates', $wp_filter) ||
          array_key_exists('allow_major_auto_core_updates', $wp_filter)  ||
          array_key_exists('allow_dev_auto_core_updates', $wp_filter) ) {
          $AssocErr['bUpdateSettingsMismatch'] = 'Core autoupdates is set to None, but there is some additional plugin changing this behavior.';
        }
        
      }    
      else if ($this->get_setting('upgradeType') == 'minor') {
        if( defined('AUTOMATIC_UPDATER_DISABLED') && AUTOMATIC_UPDATER_DISABLED === TRUE ) {
          $AssocErr['bUpdateSettingsMismatch'] = 'Core autoupdates is set to Minor, but AUTOMATIC_UPDATER_DISABLED is defined as true.';
        }
        if( defined('WP_AUTO_UPDATE_CORE') && ( WP_AUTO_UPDATE_CORE === true || WP_AUTO_UPDATE_CORE === false ) ) {
          $AssocErr['bUpdateSettingsMismatch'] = 'Core autoupdates is set to Minor, but AUTOMATIC_UPDATE_CORE is defined as true or false.';
        }
        if( array_key_exists('allow_major_auto_core_updates', $wp_filter) ||
          array_key_exists('automatic_updater_disabled', $wp_filter) ||
          array_key_exists('auto_update_core', $wp_filter) ||
          array_key_exists('allow_dev_auto_core_updates', $wp_filter) ) {
          $AssocErr['bUpdateSettingsMismatch'] = 'Core autoupdates is set to Minor, but there is some additional plugin changing this behavior.';
        }
      
      }
      else if ($this->get_setting('upgradeType') == 'major') {
        if( defined('AUTOMATIC_UPDATER_DISABLED') && AUTOMATIC_UPDATER_DISABLED === TRUE ) {
          $AssocErr['bUpdateSettingsMismatch'] = 'Core autoupdates is set to Major, but AUTOMATIC_UPDATER_DISABLED is defined as true.';
        }
        if( defined('WP_AUTO_UPDATE_CORE') && ( WP_AUTO_UPDATE_CORE === 'minor' || WP_AUTO_UPDATE_CORE === false ) ) {
          $AssocErr['bUpdateSettingsMismatch'] = 'Core autoupdates is set to Major, but AUTOMATIC_UPDATE_CORE is defined as minor or false.';
        }
        if (  array_key_exists("allow_minor_auto_core_updates", $wp_filter) ||
          array_key_exists("automatic_updater_disabled", $wp_filter) ||
          array_key_exists("auto_update_core", $wp_filter) ||
          array_key_exists("allow_dev_auto_core_updates", $wp_filter) ) {
        $AssocErr['bUpdateSettingsMismatch'] = 'Core autoupdates is set to Major, but there is some additional plugin changing this behavior.';
        }
      
      }
      
    }
    
    
    //  Admin capabilities check - check two random (super)admins
    if( $checks['user_can_if_cant'] === 1 ) {
      $aAdmins = is_multisite() ? get_super_admins() : get_users('role=administrator');      
      $iCount = count($aAdmins);
      
      $first = rand(0, $iCount - 1);
      $second = $first;
      
      if( $iCount > 1 ) {
        while ($first == $second) {
          $second = rand(0, $iCount - 1);
        }
      }
      
      $disabled = $this->get_setting('capsDisabled');
      $caps = $this->get_setting_db('fvsb_capsArray');
      
      $user_first = get_user_by('login', $aAdmins[$first]);
      $user_second = get_user_by('login', $aAdmins[$second]);
      
      if( $caps['install_plugins'] == 1 && is_multisite() ) {
        if( user_can($user_first,'install_plugins') && $disabled === '0' ) {
          $AssocErr['bCurrentUserCan1'] = 'User '.$aAdmins[$first].' can install plugins, even though install_plugins is set as restricted' ;
        }
        if( user_can($user_second,'install_plugins') && $disabled == 0  ) {
          $AssocErr['bCurrentUserCan2'] = 'User '.$aAdmins[$second].' can install plugins, even though install_plugins is set as restricted' ;
        }
        
      } else {
        if( user_can($aAdmins[$first], 'install_plugins') && $disabled == 0 ) {
          $AssocErr['bCurrentUserCan1'] = 'User '.$aAdmins[$first].' can install plugins, even though install_plugins is set as restricted' ;
        }
        
        if( user_can($aAdmins[$second], 'install_plugins') && $disabled == 0 ) {
          $AssocErr['bCurrentUserCan2'] = 'User '.$aAdmins[$second].' can install plugins, even though install_plugins is set as restricted';
        }
        
      }
    
    }
    
    
    // Check if capabilities are set the way we want
    if( $this->get_setting('capsDisabled') > 0 ) {
      $turnedOff = $this->get_setting('capsDisabled');    
      if( (time() - $turnedOff) > 3600 ) {  //  if it's turned off more than 1 hour
        $this->store_setting('capsDisabled', 0);
      }
      
    }
    
    // Auto-repairs
    // if Restriction Mode is disabled we need to be sure file is there, if not -> enable Restriction Mode
    if( $this->get_setting('capsDisabled') > 0 ) {
      if( !file_exists( $this->str_disallow_check_file ) ) {
        $this->store_setting('capsDisabled', 0);
      }
    }
    
    // if Restriction Mode is enabled so we need to delete the file if it's present
    if( $this->get_setting('capsDisabled') === 0 ) {
      if( file_exists( $this->str_disallow_check_file ) ) {
        unlink($this->str_disallow_check_file); 
      }
    }
    
    // file caps not defined -> repair it
    $capArray = $this->get_setting_db('fvsb_capsArray');
    if( false === $capArray ) {
      $this->store_setting_db( 'fvsb_capsArray', $this->disallowed_caps_default );
    }
    
    
    $bSomethingWrong = 0;
    foreach ($AssocErr as $key => $value ) {
      if( $value !== 'OK' ) $bSomethingWrong = 1;
    }
    
    if ($bSomethingWrong) {
      $to = $this->get_setting('adminEmail');
      $subject = 'BusinessPress encountered a possible problem at ' . home_url();
  
      $message = "<h3>Debug</h3>";
      $message .= "<ul>";
      foreach($AssocErr as $key => $value ) {
        $message .= "<li>Disallow File Mods Constant - ".$key.":".$value."</li>";
      }
      $message .= "</ul>";
      
      $headers  = 'MIME-Version: 1.0' . "\n";
      $headers .= 'Content-type: text/html; charset="UTF-8";' . "\n";
      
      if( isset($_GET['businesspress_cron_debug']) ) {
        echo $message;
        die("That's it for BusinessPress debug.");  
      }
      
      wp_mail( $to, $subject, $message, $headers );
      
      // reload from cron ???
      //$this->script_reload();
    }
    
    if( isset($_GET['businesspress_cron_debug']) ) {
      echo "<p>No issues found!</p>";
      die("That's it for BusinessPress debug.");  
    }
  }
  
  


  function cron_schedule() {
    $timestamp = wp_next_scheduled( 'businesspress_cron' );
    if( $timestamp == false ) {
      wp_schedule_event( time(), 'hourly', 'businesspress_cron' );
    }
  }
  
  
  
  
  function deactivate() {
    
  }
  
  
  
  
  function delay_core_updates( $update, $item ) {
    if( $update ) {
      
      if( $this->aCoreUpdatesWhitelist == $item->current ) return $update;  //  this it to prevent endless loop in the process
      
      
      //file_put_contents( ABSPATH.'bpress-delay_core_updates.log', date('r').":\n".var_export( func_get_args(),true)."\n\n", FILE_APPEND );
      $aBlockedUpdates = get_site_option('businesspress_core_update_delay');
      if( !$aBlockedUpdates ) $aBlockedUpdates = array();
      
      if( isset($aBlockedUpdates[$item->current]) ) {
        if( $aBlockedUpdates[$item->current] + 5*24*3600 - 3600 < time() ) {  //  5 days minus 1 hour
          //file_put_contents( ABSPATH.'bpress-delay_core_updates.log', "Result: 5 days old update, go on!\n\n", FILE_APPEND );
          
          unset($aBlockedUpdates[$item->current]);
          update_site_option('businesspress_core_update_delay', $aBlockedUpdates );
          $this->aCoreUpdatesWhitelist = $item->current;
          return $update;
        
        } else {
          //file_put_contents( ABSPATH.'bpress-delay_core_updates.log', "Result: relatively new update (".$aBlockedUpdates[$item->current]." vs. ".time()."), blocking!\n\n", FILE_APPEND );
          return false;
        
        }
        
      } else {        
        $aBlockedUpdates[$item->current] = time();
        update_site_option('businesspress_core_update_delay', $aBlockedUpdates );
        
        //file_put_contents( ABSPATH.'bpress-delay_core_updates.log', "Result: new update, blocking!\n\n", FILE_APPEND );
        
        return false;
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
  
  
  
  
  private function dump(){
    global $wp_filter;
    
    $caps = $this->get_setting_db('fvsb_capsArray');
    $genInfo = $this->get_setting_db('fvsb_genSettings');
    
    echo '<!-- DB STUFF' . PHP_EOL; 
    echo var_export($caps, true);
    echo var_export($genInfo, true); 
    echo PHP_EOL.'-->';
  }
  
  
  
  
  function fail2ban_404( $username ) {
    if( preg_match( '~\.(jpg|png|gif|css|js)~', $_SERVER['REQUEST_URI'] ) ) return;

    if( stripos($_SERVER['REQUEST_URI'], 'fv-gravatar-cache' ) !== false ) return;

    if( preg_match( '~(Mediapartners-Google|googlebot|bingbot)~i', $_SERVER['HTTP_USER_AGENT'] ) ) return;

    if( !is_404() ) return;

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
  
  
  
  
  function fail2ban_openlog($log = LOG_AUTH, $daemon = 'wordpress') {
		$host	= array_key_exists('WP_FAIL2BAN_HTTP_HOST',$_ENV) ? $_ENV['WP_FAIL2BAN_HTTP_HOST'] : $_SERVER['HTTP_HOST'];
		openlog($daemon."($host)", LOG_NDELAY|LOG_PID, $log);
	}
    
  
  
  
  function fail2ban_xmlrpc() {
    $this->fail2ban_openlog();
    syslog( LOG_INFO,'BusinessPress fail2ban login error - XML-RPC authentication failure from '.$this->get_remote_addr() );
  }
  
  
  
  
  function fail2ban_xmlrpc_ping( $ixr_error ) {
    if( $ixr_error->code === 48 ) return $ixr_error;
    
    $this->fail2ban_openlog();
    syslog( LOG_INFO,'BusinessPress fail2ban pingback error - XML-RPC Pingback error '.$ixr_error->code.' generated from '.$this->get_remote_addr() );
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
    
    if( empty($this->aOptions['cap_activate']) || !$this->aOptions['cap_activate'] ) $aCaps = array_merge( $aCaps, array('activate_plugins','switch_themes','deactivate_plugins') );
    if( empty($this->aOptions['cap_update']) || !$this->aOptions['cap_update'] ) $aCaps = array_merge( $aCaps, array('update_plugins','update_themes') );
    if( empty($this->aOptions['cap_install']) || !$this->aOptions['cap_install'] ) $aCaps = array_merge( $aCaps, array('install_plugins','install_themes','delete_plugins','delete_themes','edit_plugins','edit_themes') );
    if( empty($this->aOptions['cap_export']) || !$this->aOptions['cap_export'] ) $aCaps = array_merge( $aCaps, array('export') );
    
    return $aCaps;
  }
  
  
  
  
  function get_email_domain( $email ) {
    return preg_replace( '~.+@~', '', $email );
  }
  
  
  
  
  function get_remote_addr() {
    $aProxies = array();
    $aMaxCDN = array( '108.161.176.0/20', '94.46.144.0/20', '146.88.128.0/20', '198.232.124.0/22', '23.111.8.0/22', '217.22.28.0/22', '64.125.76.64/27', '64.125.76.96/27', '64.125.78.96/27', '64.125.78.192/27', '64.125.78.224/27', '64.125.102.32/27', '64.125.102.64/27', '64.125.102.96/27', '94.31.27.64/27', '94.31.33.128/27', '94.31.33.160/27', '94.31.33.192/27', '94.31.56.160/27', '177.54.148.0/24', '185.18.207.64/26', '50.31.249.224/27', '50.31.251.32/28', '119.81.42.192/27', '119.81.104.96/28', '119.81.67.8/29', '119.81.0.104/30', '119.81.1.144/30', '27.50.77.226/32', '27.50.79.130/32', '119.81.131.130/32', '119.81.131.131/32', '216.12.211.59/32', '216.12.211.60/32', '37.58.110.67/32', '37.58.110.68/32', '158.85.206.228/32', '158.85.206.231/32', '174.36.204.195/32', '174.36.204.196/32', '151.139.0.0/19', '94.46.144.0/21', '103.66.28.0/22', '103.228.104.0/22' );
      
    $aProxies = array_merge( $aProxies, $aMaxCDN );
    
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
  
  
  
  
  function get_whitelist_domain() {
    return !empty($this->aOptions['domain']) ? $this->aOptions['domain'] : false;
  }  
  
  
  
  
  function get_whitelist_email() {
    return !empty($this->aOptions['email']) ? $this->aOptions['email'] : false;
  }
  
  
  
  
  /* this is basic handler for getting FVSB data from database
  * @param $key = can contain {all | version | upgradeType | capsDisabled | adminEmail}
  */
  function get_setting( $key = 'all' ) {
    $aSettings = $this->get_setting_db('fvsb_genSettings');
    if( $aSettings === false ) return -1;
    
    if( $this->is_allowed_setting($key) === true ) {
      if ($key == 'all') return $aSettings;
      if( !empty($aSettings[$key]) ) return $aSettings[$key];
    }
    return false;
  }
  
  
  
  
  function get_setting_db($key) {
    return is_multisite() ? get_site_option($key) : get_option($key);
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
      $this->aOptions = array();
      if( !empty($_POST['whitelist']) && $_POST['whitelist'] == 'domain' ) {
        $this->aOptions['domain'] = trim($_POST['domain']);
        $this->aOptions['email'] = '';
      } else if( is_email(trim($_POST['email'])) ) {
        $this->aOptions['email'] = trim($_POST['email']);
        $this->aOptions['domain'] = '';  
      }
      
      $this->aOptions['core_auto_updates'] = trim($_POST['autoupgrades']);
      
      if( isset($_POST['wp_admin_bar_subscribers']) ) {
        $this->aOptions['wp_admin_bar_subscribers'] = trim($_POST['wp_admin_bar_subscribers']);
      }
      
      if( !empty($_POST['cap_activate']) ) $this->aOptions['cap_activate'] = true;
      if( !empty($_POST['cap_core']) ) $this->aOptions['cap_core'] = true;
      if( !empty($_POST['cap_update']) ) $this->aOptions['cap_update'] = true;
      if( !empty($_POST['cap_install']) ) $this->aOptions['cap_install'] = true;
      if( !empty($_POST['cap_export']) ) $this->aOptions['cap_export'] = true;
      
      if( empty($this->aOptions['cap_update']) ) {
        unset($this->aOptions['cap_core']);
      }
      
      if( is_multisite() ){
        update_site_option( 'businesspress', $this->aOptions );
      } else {
        update_option( 'businesspress', $this->aOptions );
      }
    }
    
    return;
    
    $bStatus = file_exists( $this->str_disallow_check_file );
    
    
    
    if( isset($_POST['change_mod_permits']) && isset($_POST['change_mod_permits_do'])  ) {
      if( !$bStatus ) {
        $strContents = "//test string";  
        if( !file_put_contents( $this->str_disallow_check_file, $strContents )  ) {
          // WP_ROOT is not writable by script
          die( $this->strArrMessages['E_CANT_WRITE'] );
        }
        $this->store_setting('capsDisabled', time());
      } else {
        unlink( $this->str_disallow_check_file );
        $this->store_setting('capsDisabled', 0);
      }
      
      $this->script_reload( 'act=modchange' );
    
    } elseif( isset($_POST['change_cap_array']) && isset($_POST['admin_email']) && isset($_POST['autoupgrades']) ) {
      if( isset( $_POST['capabilitiesCheckbox'] ) && !empty( $_POST['capabilitiesCheckbox'] ) ) {
        $aSettings = $this->get_setting_db('fvsb_capsArray');
        foreach( $this->disallowed_caps_default as $cap => $value ) {
          $aSettings[$cap] = 0;
        }
        foreach( $_POST['capabilitiesCheckbox'] as $cap ) {
          $aSettings[$cap] = 1;
        }
      
      } else {
        $aSettings = $this->get_setting_db('fvsb_capsArray');
        foreach( $aSettings as $cap => $value ) {
          $aSettings[$cap] = 0;
        }
        
      }
      
      // so now, we have storedCapArray filled.
      $this->store_setting_db( 'fvsb_capsArray', $aSettings );
      
      if( !empty($_POST['admin_email']) && is_email($_POST['admin_email']) ) {
        $this->store_setting('adminEmail', $_POST['admin_email']);
      } //  todo: report error
      
      if( !empty($_POST['autoupgrades']) && in_array( $_POST['autoupgrades'], array( 'minor', 'major', 'none' ) ) ) {
        $this->store_setting('upgradeType', $_POST['autoupgrades']);
      }
      
      $this->script_reload( 'act=saved' );
    } elseif( isset( $_GET['act'] ) && 'saved' === $_GET['act'] ) {
      $this->script_reload( 'act=saved2' );
    }
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
  
  
  
  
  // DONE + TODO DOCU
  function menu() {
    $current_user = wp_get_current_user();    
    if( !$this->check_user_permission() ) {
      return;
    }
    
    if( is_multisite() && is_super_admin() ) {
      add_submenu_page( 'settings.php', 'BusinessPress', 'BusinessPress','manage_network_options', 'businesspress', array( $this, 'screen') );
    } else if (!is_multisite()) {
      if( $current_user->user_level >= '8' ) {
        add_options_page('BusinessPress', 'BusinessPress', 'manage_options', 'businesspress', array( $this, 'screen') );
      }
    }
  }
  
  
  
  
  function notice_configure() {
    if( !empty($_GET['page']) && $_GET['page'] == 'businesspress' ) return;
    
    if( !$this->get_whitelist_domain() && !$this->get_whitelist_email() ) :
      $sURL = site_url('wp-admin/options-general.php?page=businesspress');      
      if( $aSitewidePlugins = get_site_option( 'active_sitewide_plugins') ) {
        if( is_array($aSitewidePlugins) && stripos( ','.implode( ',', array_keys($aSitewidePlugins) ), ',businesspress' ) !== false ) {
          $sURL = site_url('wp-admin/network/settings.php?page=businesspress');
        }
      } ?>
      <div class="updated"><p><a href="<?php echo esc_attr($sURL); ?>">BusinessPress</a> must be configured before it becomes operational.</p></div>
    <?php endif;
  }




  function plugin_action_links( $actions, $plugin_file ) {
    if( stripos($plugin_file,'businesspress') !== false ) {
      unset($actions['deactivate']);
    }

    return $actions;
  }

  
  
  
  function plugin_update_hook() {
    if( empty($this->aOptions['version']) ) {
      $this->aOptions['version'] = BusinessPress::VERSION;
      update_option( 'businesspress', $this->aOptions );
    }

    if( version_compare( $this->aOptions['version'], BusinessPress::VERSION, '<' ) ) { 
      
    }
    
  }
  
  
  
  
  function screen() { 
    if( is_multisite() && !is_super_admin() ) {
      exit(0);
    } else if( !is_admin() ) {
      exit(0);
    }
  
    $bStatus = file_exists( $this->str_disallow_check_file );
    
    $capArray = $this->get_setting_db('fvsb_capsArray');
    if( false === $capArray ) {
      $this->store_setting_db( 'fvsb_capsArray', $this->disallowed_caps_default );
    } else {
      $bChange = false;
      foreach($this->disallowed_caps_default as $cap => $value ) {
        if( !isset( $capArray[$cap] ) ) {
          $capArray[$cap] = 0;
          $bChange = true;
        }
      }
      if( $bChange ) $this->store_setting_db( 'fvsb_capsArray', $capArray );
    }
    
    ?>
    <style>
    #postbox-container-1 {width: 100% !important;}
    </style>
    
    <div class="wrap">
    <h2>BusinessPress</h2>
    
      <?php if( !$this->get_whitelist_domain() && !$this->get_whitelist_email() ) : ?>
        <p>You must configure the plugin before it becomes operational.</p>
        <input id="businesspress-enable" class="button button-primary" type="submit" value="Enable Restriction Mode" />
        <script>
          jQuery('#businesspress-enable').click( function() { jQuery('.options-hidden').slideToggle() } );
        </script>
        <div class="options-hidden" style="display: none; ">
      <?php endif; ?>
      
    
      <?php if( $domain = $this->get_whitelist_domain() ) : ?>
        <div class="message error"><p>Access to this screen is limited to users with email on <?php echo $domain; ?>.</p></div>
      <?php elseif( $email = $this->get_whitelist_email() ) : ?>
        <div class="message error"><p>Access to this screen is limited to user with email address equal to <?php echo $email; ?>.</p></div>
      <?php endif; ?>
      <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
        <div id="dashboard-widgets" class="metabox-holder columns-1">
          <div id='postbox-container-1' class='postbox-container'>    
            <?php
            add_meta_box( 'businesspress_settings', __('Settings', 'fv_flowplayer'), array( $this, 'settings_box' ), 'businesspress_settings', 'normal' );
            add_meta_box( 'businesspress_tweaks', __('Tweaks', 'fv_flowplayer'), array( $this, 'settings_box_tweaks' ), 'businesspress_settings', 'normal' );
            
            do_meta_boxes('businesspress_settings', 'normal', false );
            //wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
            //wp_nonce_field( 'meta-box-order-nonce', 'meta-box-order-nonce', false );
            ?>
          </div>
        </div>
        <?php wp_nonce_field( 'businesspress_settings_nonce', 'businesspress_settings_nonce' ); ?>
      </form>
      
      <!--<hr>
    <?php
    if( !$bStatus ) {
      $strMes = 'Restriction Mode is turned <strong>ON</strong>.';
      $strVal = 'Enable Capabilities => Turn restriction mode OFF';
      $intVal = '1';
    } else {
      $strMes = 'Restriction Mode is turned <strong>OFF</strong>.';
      $strVal = 'Disable Capabilities => Turn restriction mode ON';
      $intVal = '0';
    }
    ?>
      <p><?php echo $strMes; ?></p>
      <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>"> 
        <input type="hidden" name="change_mod_permits_do" value="<?php echo $intVal; ?>" />
        <input type="submit" class="button" name="change_mod_permits" value="<?php echo $strVal; ?> &raquo;" />
      </form>-->
      
      <?php if( !$this->get_whitelist_domain() && !$this->get_whitelist_email() ) : ?>
        </div>
      <?php endif; ?>      
    </div>
    <?php
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
  
  
  
  
  function settings_box() {
    
    $styleDomain = $this->get_whitelist_domain() ? '' : ' style="display:none"';
    $styleEmail = $this->get_whitelist_email() ? '' : ' style="display:none"';
    
    if( strlen($styleDomain) && strlen($styleEmail) ) {
      $styleEmail = '';
    }
    
    $checkedDomain = $this->get_whitelist_domain() ? 'checked="checked"' : '';
    $checkedEmail = $this->get_whitelist_email() ? 'checked="checked"' : '';    
    
    if( !$checkedDomain && !$checkedEmail ) {
      $checkedEmail = 'checked="checked"';
    }
    
    $current_user = wp_get_current_user();
    $domain = $this->get_whitelist_domain() ? $this->get_whitelist_domain() : $this->get_email_domain($current_user->user_email);
    $email = $this->get_whitelist_email() ? $this->get_whitelist_email() : $current_user->user_email;
    ?>       
    <table class="form-table2">
      <tr>
        <p>Please enter the
          <input type="radio" id="whitelist-email" name="whitelist" class="businessp-checkbox" value="email"<?php echo $checkedEmail; ?>>
          <label for="whitelist-email">admin email address</label> or
          <input type="radio" id="whitelist-domain" name="whitelist" class="businessp-checkbox" value="domain"<?php echo $checkedDomain; ?>>
          <label for="whitelist-domain">domain</label>. Only user with a matching email address or domain will be able to change the settings here.</p>
      </tr>
      <tr class="whitelist-domain"<?php echo $styleDomain; ?>>
        <td><label for="email">Domain</label></td>
        <td><input class="regular-text" type="text" id="domain" name="domain" class="text" value="<?php echo esc_attr($domain); ?>" readonly /></td>
      </tr>      
      <tr class="whitelist-email"<?php echo $styleEmail; ?>>
        <td><label for="email">Email</label></td>
        <td><input class="regular-text" type="text" id="email" name="email" class="text" value="<?php echo esc_attr($email); ?>" readonly /></td>
      </tr>
      <tr>
        <td>
          <p>Allow other admins to&nbsp;</p><p>&nbsp;</p><p>&nbsp;</p><p>&nbsp;</p><p>&nbsp;</p>
        </td>
        <td>
          <p><input type="checkbox" id="cap_activate" name="cap_activate" value="1" <?php if( !empty($this->aOptions['cap_activate']) && $this->aOptions['cap_activate'] ) echo 'checked'; ?> />
            <label for="cap_activate">Activate and deactivate plugins and themes</label></p>
          <p><input type="checkbox" id="cap_update" name="cap_update" value="1" <?php if( !empty($this->aOptions['cap_update']) && $this->aOptions['cap_update'] ) echo 'checked'; ?> />
            <label for="cap_update">Update plugins and themes</label><br /></p>
          <p><input type="checkbox" id="cap_core" name="cap_core" value="1" <?php if( !empty($this->aOptions['cap_core']) && $this->aOptions['cap_core'] ) echo 'checked'; ?> disabled="true" />
            <label for="cap_core">Update WordPress core</label><br /></p>          
          <p><input type="checkbox" id="cap_install" name="cap_install" value="1" <?php if( !empty($this->aOptions['cap_install']) && $this->aOptions['cap_install'] ) echo 'checked'; ?> />
            <label for="cap_install">Install, edit and delete plugins and themes </label></p>
          <p><input type="checkbox" id="cap_export" name="cap_export" value="1" <?php if( !empty($this->aOptions['cap_export']) && $this->aOptions['cap_export'] ) echo 'checked'; ?> />
            <label for="cap_export">Export site content</label></p>
        </td>
      </tr>
      <tr>
        <td>Core auto updates</td>
        <td>
          <select id="autoupgrades" name="autoupgrades">
            <option value="none"  <?php if( !empty($this->aOptions['core_auto_updates']) && $this->aOptions['core_auto_updates'] == "none" ) echo 'selected' ?>>None</option>
            <option value="minor" <?php if( empty($this->aOptions['core_auto_updates']) || $this->aOptions['core_auto_updates'] == "minor" ) echo 'selected' ?>>Minor</option>
            <option value="major" <?php if( !empty($this->aOptions['core_auto_updates']) && $this->aOptions['core_auto_updates'] == "major" ) echo 'selected' ?>>Major</option>
          </select>
        </td>
      </tr>
        <tr>    		
          <td colspan="2">
            <input type="submit" name="businesspress-submit" class="button-primary" value="<?php _e('Save All Changes', 'businesspress'); ?>" />
          </td>
        </tr>                                    
      </table>
      <script>
      if( jQuery('#whitelist-domain:checked').length ) {
        jQuery('tr.whitelist-email').hide();
        jQuery('tr.whitelist-domain').show();
      }
      
      jQuery('#whitelist-domain').change( function() {
        jQuery('tr.whitelist-email').hide();
        jQuery('tr.whitelist-domain').show();
      });
      jQuery('#whitelist-email').change( function() {
        jQuery('tr.whitelist-email').show();
        jQuery('tr.whitelist-domain').hide();
      });
      
      if( jQuery('#cap_update:checked').length ) {
        jQuery('#cap_core').prop('disabled',false);
      }
      jQuery('#cap_update').change( function() {
        if( jQuery('#cap_update:checked').length ) {
          jQuery('#cap_core').prop('disabled',false);
        } else {
          jQuery('#cap_core').prop('disabled',true);
          jQuery('#cap_core').prop('checked',false);
        }
      });
      </script>         
    <?php
  }
  
  
  
  
  function settings_box_tweaks() {
    ?>       
    <table class="form-table2">
      <tr>
        <td>
          <p><label for="wp_admin_bar_subscribers">Hide WP Admin Bar for subscribers</label></p>
        </td>
        <td>
          <p class="description"><input type="checkbox" id="wp_admin_bar_subscribers" name="wp_admin_bar_subscribers" value="1" <?php if( !empty($this->aOptions['wp_admin_bar_subscribers']) && $this->aOptions['wp_admin_bar_subscribers'] ) echo 'checked'; ?> />
            With this setting it's up to you to provide the front-end interface for profile editing and so on</p>
        </td>
      </tr>
        <tr>    		
          <td colspan="2">
            <input type="submit" name="businesspress-submit" class="button-primary" value="<?php _e('Save All Changes', 'businesspress'); ?>" />
          </td>
        </tr>                                    
      </table>    
    <?php
  }  
  
  
  

  function show_disallow_not_defined() {
    $current_user = wp_get_current_user();
    if( false !== stripos( $current_user->user_email, $this->get_email_domain() )) {  //  todo: get rid of this
      if( is_super_admin() || is_admin()  ) {
        if( ( !defined('DISALLOW_FILE_EDIT' ) ) || ( defined('DISALLOW_FILE_EDIT' ) && ( DISALLOW_FILE_EDIT === false ) )  ) {
          echo '<div class="error"><p>DISALLOW_FILE_EDIT is not defined, or defined as FALSE</p></div>';
        }
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
    if( $this->get_whitelist_domain() ) {
      return "Please contact your site admin or your partners at ".$this->get_whitelist_domain()." to ".$what.".";
    } else if( $this->get_whitelist_email() ) {
      return "Please contact ".$this->get_whitelist_email()." to ".$what.".";
    }
    return false;
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
        $iTTL = $iDate + 3600*24*30*30; //  the current version is good has time to live set to 30 months
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
      $new_html .= "Projected security updates: Negative ".abs($iRemaining)." months. Expired or expiration imminent.";
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
      $new_html .= "<p><a href='".site_url('wp-admin/options-general.php?page=businesspress')."'>BusinessPress</a> delays these updates 5 days to make sure you are not affected by any bugs in them.</p>";
      
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
    
    $updates = get_core_updates();
    
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

      foreach ( (array) $updates as $update ) {        
        if( stripos($update->version,$this->get_version_branch()) === 0 ) {
          continue; //  don't show the minor updates here!
        }
        
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

    if( preg_match( '~<h\d[^>]*?>Plugins</h\d>~', $html ) ) {
      $html = preg_replace( '~(<div class="wrap">)([\s\S]*?)(<h\d[^>]*?>Plugins</h\d>)~', '$1'.$new_html.'$3', $html );
    } else {
      $html = preg_replace( '~(<div class="wrap">)([\s\S]*?)$~', '$1'.$new_html, $html );
    }
    
    echo $html;
    
    ?>
    <script>
    jQuery(function($){
      $('form').submit( function(e) {
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


  
  
}

$businesspress = new BusinessPress();
