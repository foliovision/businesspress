<?php
/*
Plugin Name: BusinessPress
Plugin URI: http://www.foliovision.com
Description: This plugin secures your site
Version: 0.5
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


require_once( dirname(__FILE__).'/fp-api-private.php' );


class BusinessPress extends BusinessPress_Plugin_Private {

  /* DB records
  * ( timestamp ) fvsb_capsDisabled - if true, capabilities are disabled
  * ( serialized hash map) fvsb_capsArray - contains capabilities to restrict
  * ( string {none | minor | major}) fvsb_upgrades - autoupdates 
  * ( string ) fvsb_adminMail - email adress where adress to be sent - default is programming@foliovision.com
  * (hash_map) fvsb_genSettings // contains (capsDisabled, upgradesType, adminMail, version) 
  */
  
  /* constants */
  const VERSION = '0.5';
  const FVSB_LOCK_FILE = 'fv-disallow.php';
  const FVSB_DEBUG = 0;
  const FVSB_CRON_ENABLED = 1;
  
  /* things about update */
  var $strPluginSlug = 'businesspress';
  var $strPrivateAPI = 'http://foliovision.com/plugins/';
  
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
    
    $this->aOptions = get_option( 'businesspress' ); 
    
    $this->strArrMessages['E_CANT_WRITE'] = '<div class="error"><p><span style="color:red; font-weight:bold;">FATAL ERROR</span>: Plugin cant write into Wordpress root directory. Check file permissions.</p></div>';
    
    $this->str_disallow_check_file = trailingslashit($this->get_wp_root_dir()).BusinessPress::FVSB_LOCK_FILE;
            
    if( BusinessPress::FVSB_DEBUG == 1 ) {
      $this->dump();
    }
    
    //parent::auto_updates();
    
    add_action( 'in_plugin_update_message-fv-disallow-mods/fv-disallow-mods.php', array( &$this, 'plugin_update_message' ) );
    
    register_activation_hook( __FILE__, array( $this , 'activate') );
    register_deactivation_hook( __FILE__, array( $this, 'deactivate') );        
    
    add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu',  array( $this, 'menu' ) );
    
    
    add_action( 'admin_init', array( $this, 'plugin_update_hook' ) );
    
    add_action( 'businesspress_cron', array( $this, 'cron_job' ) );
    
    if( is_multisite() ) {
      //add_action( 'network_admin_notices', array( $this, 'show_disallow_not_defined') );  //  todo: what about this?
    } else {
      //add_action( 'admin_notices', array( $this, 'show_disallow_not_defined') );
    }
    
    add_action( 'admin_notices', array( $this, 'notice_configure') );
    
    
    add_filter( 'auto_update_core', array( $this, 'delay_core_updates' ), 999, 2 );
    
    //add_filter( 'dashboard_glance_items', array( $this, 'core_updates_discard' ) );  // show only the current branch update for dashboard
    
    add_action( 'admin_init', array( $this, 'admin_screen_cleanup') );
    add_action( 'admin_head', array( $this, 'admin_screen_cleanup_css') );
    
    add_action( 'admin_init', array( $this, 'handle_post') );
    
    add_action( 'init', array( $this, 'apply_restrictions') );
    add_action( 'admin_init', array( $this, 'apply_restrictions') );
    
    add_action( 'load-update-core.php', array( $this, 'upgrade_screen_start') );
    add_action( 'core_upgrade_preamble', array( $this, 'upgrade_screen') );
    
    
    
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
      add_action( 'admin_print_scripts', array( $this, 'hide_plugin_controls' ), 1 );
      add_filter( 'map_meta_cap', array( $this, 'capability_filter' ), 999, 4 );
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
      
      
      file_put_contents( ABSPATH.'bpress-delay_core_updates.log', date('r').":\n".var_export( func_get_args(),true)."\n\n", FILE_APPEND );
      $aBlockedUpdates = get_site_option('businesspress_core_update_delay');
      if( !$aBlockedUpdates ) $aBlockedUpdates = array();
      
      if( isset($aBlockedUpdates[$item->current]) ) {
        if( $aBlockedUpdates[$item->current] + 5*24*3600 - 3600 < time() ) {  //  5 days minus 1 hour
          file_put_contents( ABSPATH.'bpress-delay_core_updates.log', "Result: 5 days old update, go on!\n\n", FILE_APPEND );
          
          unset($aBlockedUpdates[$item->current]);
          update_site_option('businesspress_core_update_delay', $aBlockedUpdates );
          $this->aCoreUpdatesWhitelist = $item->current;
          return $update;
        
        } else {
          file_put_contents( ABSPATH.'bpress-delay_core_updates.log', "Result: relatively new update (".$aBlockedUpdates[$item->current]." vs. ".time()."), blocking!\n\n", FILE_APPEND );
          return false;
        
        }
        
      } else {        
        $aBlockedUpdates[$item->current] = time();
        update_site_option('businesspress_core_update_delay', $aBlockedUpdates );
        
        file_put_contents( ABSPATH.'bpress-delay_core_updates.log', "Result: new update, blocking!\n\n", FILE_APPEND );
        
        return false;
      }      
      
    }
    
    //  todo: this might trigger (if notify_email is set in the WP API response) an email notification, consider blocking it with send_core_update_notification_email filter
    return $update;
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
  
  
  
  
  function get_disallowed_caps() {
    $aCaps = array();
    
    if( empty($this->aOptions['cap_activate']) || !$this->aOptions['cap_activate'] ) $aCaps = array_merge( $aCaps, array('activate_plugins','switch_themes','deactivate_plugins') );
    if( empty($this->aOptions['cap_update']) || !$this->aOptions['cap_update'] ) $aCaps = array_merge( $aCaps, array('update_plugins','update_themes') );
    if( empty($this->aOptions['cap_install']) || !$this->aOptions['cap_install'] ) $aCaps = array_merge( $aCaps, array('install_plugins','install_themes','delete_plugins','delete_themes','edit_plugins','edit_themes') );
    
    return $aCaps;
  }
  
  
  
  
  function get_email_domain( $email ) {
    return preg_replace( '~.+@~', '', $email );
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
      
      if( !empty($_POST['cap_activate']) ) $this->aOptions['cap_activate'] = true;
      if( !empty($_POST['cap_update']) ) $this->aOptions['cap_update'] = true;
      if( !empty($_POST['cap_install']) ) $this->aOptions['cap_install'] = true;
      
      update_option( 'businesspress', $this->aOptions ); 
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
window.onload = function(){
  var tr      = document.getElementById("businesspress");
  if (tr !== null) {
    var actions  = tr.getElementsByClassName("row-actions-visible");
    var actions2 = tr.getElementsByClassName("row-actions");
    var checkbox = tr.getElementsByClassName("check-column");
    if (actions.length > 0 )
      actions[0].style.display = "none";
    else if (actions2.length > 0)
      actions2[0].style.display = "none";
    checkbox[0].innerHTML = "";
    //console.log( actions[0] );
  }
};
</script>
JSH;
    }
  }
  
  
  
  
  function is_allowed_setting($key) {
    return in_array( $key, array( 'adminEmail', 'all', 'capsDisabled', 'upgradeType', 'version' ) );
  }
  
  
  
  
  // DONE + TODO DOCU
  function menu() {
    global $current_user;
    get_currentuserinfo();
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
    
    if( !$this->get_whitelist_domain() && !$this->get_whitelist_email() ) : ?>
      <div class="updated"><p><a href="<?php echo esc_attr( site_url('wp-admin/options-general.php?page=businesspress') ); ?>">BusinessPress</a> must be configured before it becomes operational.</p></div>
    <?php endif;
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
          <p>Allow other users to&nbsp;</p><p>&nbsp;</p><p>&nbsp;</p>
        </td>
        <td>
          <p><input type="checkbox" id="cap_activate" name="cap_activate" value="1" <?php if( !empty($this->aOptions['cap_activate']) && $this->aOptions['cap_activate'] ) echo 'checked'; ?> />
            <label for="cap_activate">Activate and deactivate plugins and themes</label></p>
          <p><input type="checkbox" id="cap_update" name="cap_update" value="1" <?php if( !empty($this->aOptions['cap_update']) && $this->aOptions['cap_update'] ) echo 'checked'; ?> />
            <label for="cap_update">Update plugins and themes</label><br /></p>
          <p><input type="checkbox" id="cap_install" name="cap_install" value="1" <?php if( !empty($this->aOptions['cap_install']) && $this->aOptions['cap_install'] ) echo 'checked'; ?> />
            <label for="cap_install">Install, Edit and delete plugins and themes </label></p>
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
      </script>         
    <?php
  }
  
  
  

  function show_disallow_not_defined() {
    global $current_user;
    get_currentuserinfo();
    if( false !== stripos( $current_user->user_email, $this->get_email_domain() )) {  //  todo: get rid of this
      if( is_super_admin() || is_admin()  ) {
        if( ( !defined('DISALLOW_FILE_EDIT' ) ) || ( defined('DISALLOW_FILE_EDIT' ) && ( DISALLOW_FILE_EDIT === false ) )  ) {
          echo '<div class="error"><p>DISALLOW_FILE_EDIT is not defined, or defined as FALSE</p></div>';
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
  
  
  
  
  function upgrade_screen_start() {
    ob_start();
  }
  
  
  
  
  function upgrade_screen() {
    $html = ob_get_clean();
    
    $new_html = "<h2>Autoupdates check by BusinessPress</h2>";
    
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
    if( $aBlockedUpdates ) {
      
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
      $new_html .= "<p>No recent actions, be careful with your upgrades!</p>";
      
    }
    
    
    $html = preg_replace( '~(<div class="wrap">\s*?<h\d.*?</h\d>[\s\S]*?)<h~', '$1'.$new_html.'<h', $html );
    
    echo $html;
    
  }


  
  
}

$businesspress = new BusinessPress();




// Remove the Export Menu - This is just visible
function hide_export_from_non_admins() {
  if( ! current_user_can( 'install_themes' ) ) {
    remove_submenu_page('tools.php', 'export.php');
  }
}
add_action( 'admin_menu', 'hide_export_from_non_admins' );

