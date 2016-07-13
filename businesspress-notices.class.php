<?php

class BusinessPress_Notices {
  
  var $iNoticesAvoided = 0;
  
  public function __construct() {
    if( isset($_GET['martinv']) ) {      
      add_action( 'admin_notices', array( $this, 'trap'), 0 );
      add_action( 'admin_notices', array( $this, 'store'), 999999 );      
      
    } else {
      add_action( 'admin_notices', array( $this, 'remove'), 0 );
      add_action( 'admin_footer', array( $this, 'show_count'), 0 );
      
      add_action( 'admin_init', array( $this, 'remove_gravityforms'), 0 );      
      
    }
    
    add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu',  array( $this, 'menu' ) );
  }
  
  
  
  
  function outerHTML($e) {
    $doc = new DOMDocument();
    $doc->appendChild($doc->importNode($e, true));
    return $doc->saveHTML();
  }

  
  
  
  function menu() {
    add_dashboard_page( 'Notices', 'Notices', 'read', 'businesspress-notices', array( $this, 'screen' ) );
  }
  
  
  
  
  function remove() {
    global $woothemes_updater;
    if( isset($woothemes_updater) && isset($woothemes_updater->admin) && has_action( 'admin_notices', array( $woothemes_updater->admin, 'maybe_display_activation_notice' ) ) ) {
      remove_action( 'network_admin_notices', array( $woothemes_updater->admin, 'maybe_display_activation_notice' ) );
      remove_action( 'admin_notices', array( $woothemes_updater->admin, 'maybe_display_activation_notice' ) );
      
      add_action( 'businesspress_admin_notices', array( $woothemes_updater->admin, 'maybe_display_activation_notice' ) );
      
      ob_start();
      $woothemes_updater->admin->maybe_display_activation_notice();
      if( strlen(ob_get_clean()) > 0 ) {      
        $this->iNoticesAvoided++;
      }
    }
  }
  
  
  
  
  function remove_gravityforms() {
    if( !class_exists('GFCommon') || !method_exists('GFCommon','get_version_info') ) return;
    
		$ary_dismissed = get_option( 'gf_dismissed_upgrades' );

    $version_info = GFCommon::get_version_info();
    
		if(
      version_compare( GFCommon::$version, $version_info['version'], '<' ) &&
      ( empty( $ary_dismissed ) || !in_array( $version_info['version'], $ary_dismissed ) )
    ) {
      $this->iNoticesAvoided++;
      add_filter( 'pre_option_gf_dismissed_upgrades', array( $this, 'remove_gravityforms_action' ) );
    }
    
  }
  
  
  
  
  function remove_gravityforms_action( $value ) {
    $version_info = GFCommon::get_version_info();
    $value = array( $version_info['version'] );
    return $value;
  }  
  
  
  
  
  function show_count() {
    if( $this->iNoticesAvoided == 0 ) return;
    ?>
    <script>
    (function( $ ) {
      var count = <?php echo $this->iNoticesAvoided; ?>;
      $('[href="index.php?page=businesspress-notices"]').append('<span class="update-plugins count-'+count+'"><span class="update-count">'+count+'</span></span>');    
    })( jQuery );
    </script>
    <?php
  }
  
  
  
  
  function screen() {
    echo "<p>BusinessPress works hard to make sure all the annoying plugin notices show up on this screen only and don't pollute your whole WP Admin Dashboard.</p>";
    
    if( isset($_GET['martinv']) ) {
      $aStored = get_option( 'businesspress_notices', array() );      
      foreach( $aStored AS $aNotice ) {
        echo "<h5>".date('Y-m-d h:m:s',$aNotice['time'])."</h5>".$aNotice['html'];
      }
    } else {   
    
      do_action('businesspress_admin_notices');
      
      if( method_exists('GFForms','dashboard_update_message') ) {
        remove_filter( 'pre_option_gf_dismissed_upgrades', array( $this, 'remove_gravityforms_action' ) );
        GFForms::dashboard_update_message();
      }
    
    }

    
  }  
  
  
  
  
  function store() {
    $junk = ob_get_clean();
    $dom = new DOMDocument();
    $dom->loadHTML( $junk );
    
    $aMatches = array();
    foreach( $dom->getElementsByTagName('div') as $objDiv ) {
      if( !$objDiv->hasAttribute('class')) {
        continue;
      }
  
      $aClass = explode(' ', $objDiv->getAttribute('class'));  
      if( in_array('updated', $aClass) ) {
        $aMatches[] = $this->outerHTML($objDiv);
      }
      if( in_array('update-nag', $aClass) ) {
        $aMatches[] = $this->outerHTML($objDiv);
      }
  
    }
    
    $aStored = get_option( 'businesspress_notices', array() );
    $aNew = $aStored;
    foreach( $aMatches AS $sNotice ) {
      
      $check_one = preg_replace( '~[^a-z0-9]~', '', strip_tags($sNotice) );
      
      $bSkip = false;
      foreach( $aStored AS $sStored ) {
        $check_two = preg_replace( '~[^a-z0-9]~', '', strip_tags($sStored['html']) );
        if( $check_one == $check_two ) {
          $bSkip = true;
          break;
        }
      }
      
      if( !$bSkip ) {
        $aNew[] = array( 'time' => time(), 'html' => $sNotice );
      }
      
    }
    
    update_option( 'businesspress_notices', $aNew );
    
    //$html->find('div.updated');
    //$html->find('div.notice');

  }
  

  
  
  function trap() {
    ob_start();
  }
  

  
  
}

$BusinessPress_Notices = new BusinessPress_Notices;