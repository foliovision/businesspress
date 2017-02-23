<?php

class BusinessPress_Notices {
  
  var $iNoticesAvoided = 0;
  
  public function __construct() {    
    add_action( 'admin_notices', array( $this, 'trap'), 0 );
    add_action( 'all_admin_notices', array( $this, 'store'), 999999 );
    add_action( 'admin_footer', array( $this, 'show_count'), 0 );
      
     /*else {
      add_action( 'admin_notices', array( $this, 'remove'), 0 );
      add_action( 'admin_footer', array( $this, 'show_count'), 0 );
      
      add_action( 'admin_init', array( $this, 'remove_gravityforms'), 0 );      
      
    }*/
    
    add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu',  array( $this, 'menu' ) );
  }
  
  
  
  
  function get_count() {
    $aStored = get_option( 'businesspress_notices', array() );
    foreach( $aStored AS $aNotice ) {
      if( !isset($aNotice['dismissed']) || $aNotice['dismissed'] == false ) {
        $this->iNoticesAvoided++;
      }
    }
    return $this->iNoticesAvoided;
  }
  
  
  
  
  function prepare_compare( $html ) {
    $html = preg_replace( '~nonce=[0-9a-z_-]+~', '', $html);
    $html = preg_replace( '~[^A-Za-z0-9]~', '', strip_tags($html) );
    return $html;
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
    $this->get_count();
    
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
    if( isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'businesspress_notice_dismiss') ) {
      if( isset($_GET['dismiss']) ) {
        $aStored = get_option( 'businesspress_notices', array() );
        $aStored[intval($_GET['dismiss'])]['dismissed'] = true;
        update_option( 'businesspress_notices', $aStored );
        
        echo "<div class='updated'><p>Notice marked as dismissed.</p></div>";
      }
    }
    
    ?>
    <p>BusinessPress works hard to make sure all the annoying plugin notices show up on this screen only and don't pollute your whole WP Admin Dashboard.</p>
    <style>
      .businesspress_notices .notice-dismiss { display: none }
    </style>
    <div class="businesspress_notices">
      <?php
      $aStored = get_option( 'businesspress_notices', array() );
      
      $sAdminURL = site_url('wp-admin/index.php?page=businesspress-notices');
      foreach( $aStored AS $key => $aNotice ) {
        $sDismiss = ( !isset($aNotice['dismissed']) || $aNotice['dismissed'] == false ) ? " - <a href='".wp_nonce_url($sAdminURL,'businesspress_notice_dismiss')."&dismiss=".$key."'>Dismiss</a>" : false;
        ?>
        <p>
          <?php if( $sDismiss ) : ?><strong><?php endif; ?>
          <?php echo date('Y-m-d h:m:s',$aNotice['time']); ?>
          <?php if( $sDismiss ) : ?></strong><?php endif; ?>
          <?php echo $sDismiss; ?>
        </p>
        <?php echo $aNotice['html'];
      }
      ?>
    </div>
    <?php
  }  
  
  
  
  
  function store() {
    
    $junk = ob_get_clean();
    
    if( isset($_GET['businessp_show_notices']) ) {
      echo "<!--junk start-->\n".$junk."<!--junk end-->\n";
    }
    
    echo "<!--BusinessPress_Notices::store()-->\n";
    //echo "<p>BusinessPress_Notices::store()</p>";
    
    $dom = new DOMDocument();
    $dom->loadHTML( $junk );
    
    $aMatches = array();
    foreach( $dom->getElementsByTagName('div') as $objDiv ) {
      if( !$objDiv->hasAttribute('class')) {
        continue;
      }
  
      $aClass = explode(' ', $objDiv->getAttribute('class'));
      if( in_array('notice', $aClass) ) {
        $aMatches[] = $this->outerHTML($objDiv);
      }
      if( in_array('error', $aClass) ) {
        $aMatches[] = $this->outerHTML($objDiv);
      }      
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
      
      $check_one = $this->prepare_compare($sNotice);
      //echo "<!--compare ".$check_one." against :\n";
      
      $bSkip = false;
      foreach( $aStored AS $aNotice ) {
        $check_two = $this->prepare_compare($aNotice['html']);
        
        //echo $check_two."\n";
        
        if( $check_one == $check_two ) {
          $bSkip = true;
          break;
        }
      }
      
      if( !$bSkip ) {
        $aNew[] = array( 'time' => time(), 'html' => $sNotice );
      }
      
      //echo "-->\n";
      
    }
    
    update_option( 'businesspress_notices', $aNew );
    
    //$html->find('div.updated');
    //$html->find('div.notice');

  }
  

  
  
  function trap() {
    echo "<!--BusinessPress_Notices::trap()-->\n";
    //echo "<p>BusinessPress_Notices::trap()</p>";
    
    ob_start();
  }
  

  
  
}

global $BusinessPress_Notices;
$BusinessPress_Notices = new BusinessPress_Notices;