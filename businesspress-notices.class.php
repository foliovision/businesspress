<?php

class BusinessPress_Notices {
  
  var $iNoticesAvoided = 0;
  
  public function __construct() {    
    add_action( 'admin_notices', array( $this, 'trap'), 0 );    
    add_action( 'admin_footer', array( $this, 'show_count'), 0 );
      
     /*else {
      add_action( 'admin_notices', array( $this, 'remove'), 0 );
      add_action( 'admin_footer', array( $this, 'show_count'), 0 );
      
      add_action( 'admin_init', array( $this, 'remove_gravityforms'), 0 );      
      
    }*/
    
    if( is_multisite() ) add_action( 'network_admin_menu',  array( $this, 'menu' ) );
    add_action( 'admin_menu',  array( $this, 'menu' ) );
  }
  
  
  
  
  function get() {
    $aNotices = is_network_admin() ? get_site_option('businesspress_notices', array() ) : get_option( 'businesspress_notices', array() );
    usort($aNotices, array( $this, 'sort_notices' ) );
    return $aNotices;
  }
  
  
  
  
  function get_count() {
    $aStored = $this->get();
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
  
  
  
  
  function save( $aNotices ) {
    if( is_network_admin() ) {
      update_site_option('businesspress_notices', $aNotices );
    } else {
      update_option( 'businesspress_notices', $aNotices );
    }
  }  
  
  
  
  
  function show_count() {
    $this->get_count();
    
    if( $this->iNoticesAvoided == 0 ) return;
    ?>
    <script>
    (function( $ ) {
      var count = <?php echo $this->iNoticesAvoided; ?>;
      $('[href="index.php?page=businesspress-notices"]').append('<span class="update-plugins count-'+count+'"><span class="update-count">'+count+'</span></span>');
      document.getElementById('menu-dashboard').className = document.getElementById('menu-dashboard').className.replace(/wp-not-current-submenu/,'wp-has-current-submenu wp-menu-open');
    })( jQuery );
    </script>
    <?php
  }
  
  
  
  
  function screen() {
    if( isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'businesspress_notice_dismiss') ) {
      if( isset($_GET['dismiss']) ) {
        $aStored = $this->get();
        $aStored[intval($_GET['dismiss'])]['dismissed'] = true;
        $this->save($aStored);
        echo "<div class='updated'><p>Notice marked as dismissed. If it keeps coming back, we recommend that you fix the issue that is causing it or <a href='https://foliovision.com/support/businesspress/requests-and-feedback' target='_blank'>let us know about it</a>.</p></div>";
      }
    }
    
    ?>
    <p>BusinessPress works hard to make sure all the annoying plugin notices show up on this screen only and don't pollute your whole WP Admin Dashboard.</p>
    <style>
      .businesspress_notices .notice-dismiss { display: none }
    </style>
    <div class="businesspress_notices">
      <?php
      $aStored = $this->get();
      
      $sAdminURL = is_network_admin() ? site_url('wp-admin/network/index.php?page=businesspress-notices') : site_url('wp-admin/index.php?page=businesspress-notices');
      ?>
      <h3>New</h3>
      <?php
      $iNew = 0;      
      foreach( $aStored AS $key => $aNotice ) {        
        if( isset($aNotice['dismissed']) && $aNotice['dismissed'] ) continue;
        
        $iNew++;
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
      
      if( $iNew == 0) _e('No new notices.', 'businesspress' )
      
      ?>
      <h3>Viewed</h3>
      <?php
      
      $iViewed = 0;
      foreach( $aStored AS $key => $aNotice ) {        
        if( !isset($aNotice['dismissed']) || $aNotice['dismissed'] == false ) continue;
        
        $iViewed++;
        ?>
        <p>
          <?php echo date('Y-m-d h:m:s',$aNotice['time']); ?>
        </p>
        <?php echo $aNotice['html'];
      }
      
      if( $iViewed == 0) _e('No dismissed notices.', 'businesspress' )
      ?>
    </div>
    <?php
  }
  
  
  
  
  function sort_notices( $a, $b ) {
    if( isset($a['time']) && isset($b['time']) && $a['time'] > $b['time'] ) return false; 
    return true;
  }
  
  
  
  
  function store() {
    
    $junk = ob_get_clean();
    
    if( isset($_GET['businessp_show_notices']) ) {
      echo "<!--junk start-->\n".$junk."<!--junk end-->\n";
    }
    
    echo "<!--BusinessPress_Notices::store()-->\n";
    
    if( !$junk ) return;    
    
    $dom = new DOMDocument();
    @$dom->loadHTML( $junk );
    
    $aMatches = array();
    foreach( $dom->getElementsByTagName('div') as $objDiv ) {
      if( !$objDiv->hasAttribute('class')) {
        continue;
      }
      
      $sHTML = $this->outerHTML($objDiv);
      if( stripos($sHTML,'poll ') !== false ) { //  whitelist for "Polldaddy Polls & Ratings"
        echo $sHTML."<!--BusinessPress_Notices - whitelisted! -->\n";
        continue;
      }
  
      $aClass = explode(' ', $objDiv->getAttribute('class'));
      if( in_array('notice', $aClass) ) {
        $aMatches[] = $sHTML;
      }
      if( in_array('error', $aClass) ) {
        $aMatches[] = $sHTML;
      }      
      if( in_array('updated', $aClass) ) {
        $aMatches[] = $sHTML;
      }
      if( in_array('update-nag', $aClass) ) {
        $aMatches[] = $sHTML;
      }
  
    }
    
    $aStored = $this->get();
    $aNew = $aStored;
    if( count($aMatches) > 0 ) {
      foreach( $aMatches AS $sNotice ) {
        
        $check_one = $this->prepare_compare($sNotice);
        //echo "<!--compare ".$check_one." against :\n";
        
        $bSkip = false;
        foreach( $aStored AS $key => $aNotice ) {
          $check_two = $this->prepare_compare($aNotice['html']);
          
          if( $check_one == $check_two ) {  //  if the notice is already recorded
            if( isset($aNotice['dismissed']) && $aNotice['dismissed'] ) { //  and it's dismissed, then record it again
              unset($aNew[$key]);
            } else {  //  if it's already recorded and not dismissed, do nothing
              $bSkip = true;
              break;
            }
          }
        }
        
        if( !$bSkip ) {
          $aNew[] = array( 'time' => time(), 'html' => $sNotice );
        }
        
        //echo "-->\n";
        
      }
      
      $this->save($aNew);
    }
    
    //$html->find('div.updated');
    //$html->find('div.notice');

  }
  

  
  
  function trap() {
    echo "<!--BusinessPress_Notices::trap()-->\n";
    //echo "<p>BusinessPress_Notices::trap()</p>";
    
    ob_start();
    add_action( 'all_admin_notices', array( $this, 'store'), 999999 );
  }
  

  
  
}

global $BusinessPress_Notices;
$BusinessPress_Notices = new BusinessPress_Notices;