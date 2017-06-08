<?php

class BusinessPress_Settings {
  
  public function __construct() {
    add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu',  array( $this, 'menu' ) );
  }

  
  
  
  function admin_show_setting( $name, $option_key, $title, $help = false, $type = 'checkbox', $class = false ) {
    global $businesspress;
    $name = esc_attr($name);
    $class = 'businesspress-setting-'.$name.' ' .$class;
    ?>
      <tr class="<?php echo $class; ?>">
        <th>
          <label for="<?php echo $name; ?>"><?php _e($title, 'businesspress' ); ?></label>
        </th>
        <td>
          <p class="description">
            <?php if( $type == 'textarea' ) : ?>
              <textarea id="<?php echo $name; ?>" name="<?php echo $name; ?>" class="large-text code" rows="8"><?php echo esc_textarea( $businesspress->get_setting($option_key) ); ?></textarea><br />
            <?php elseif( $type == 'text' ) : ?>
              <input type="text" id="<?php echo $name; ?>" name="<?php echo $name; ?>" value="<?php echo esc_attr( $businesspress->get_setting($option_key) ); ?>" />
            <?php else : ?>
              <input type="hidden" name="<?php echo $name; ?>" value="0" /> 
              <input type="checkbox" id="<?php echo $name; ?>" name="<?php echo $name; ?>" value="1" <?php if( $businesspress->get_setting($option_key) ) echo 'checked="checked"'; ?> /> 
            <?php endif; ?>
            <?php if( $help ) : ?>
              <label for="<?php echo $name; ?>"><?php echo $help; ?></p>
            <?php endif; ?>
        </td>
      </tr>
    <?php
  }  
  
  
  
  
  function menu() {  
    if( is_multisite() && is_super_admin() ) {
      add_submenu_page( 'settings.php', 'BusinessPress', 'BusinessPress','manage_network_options', 'businesspress', array( $this, 'screen') );
    } else if (!is_multisite()) {
      $current_user = wp_get_current_user();    
      if( $current_user && $current_user->user_level >= '8' ) {
        add_options_page('BusinessPress', 'BusinessPress', 'manage_options', 'businesspress', array( $this, 'screen') );
      }
    }
  }
  
  
  
  
  function screen() {
    global $businesspress;
    $this->aOptions = $businesspress->aOptions;
    
    ?>        
    <div class="businesspress-header">
      <h2 class="nav-tab-wrapper businesspress-header-nav" id="businesspress-header-nav">
        <a class="nav-tab nav-tab-access" href="#welcome"><span>BusinessPress</span></a>
        <?php if( $businesspress->check_user_permission() ) : ?>
          <a class="nav-tab nav-tab-updates" href="#updates"><span><?php _e('Updates', 'businesspress' ); ?></span></a>
          <a class="nav-tab nav-tab-prefs" href="#preferences"><span><?php _e('Preferences', 'businesspress' ); ?></span></a>
          <a class="nav-tab nav-tab-branding" href="#branding"><span><?php _e('Branding', 'businesspress' ); ?></span></a>
          <?php if( is_multisite() ) : ?>
            <a class="nav-tab nav-tab-multisite" href="#multisite"><span><?php _e('Multisite', 'businesspress' ); ?></span></a>
          <?php endif; ?>
          <a class="nav-tab nav-tab-help nav-tab-right" href="#" target="_blank" title="<?php _e('Go to foliovision.com Docs page', 'businesspress' ); ?>"><span><?php _e('Docs', 'businesspress' ); ?></span></a>
          <a class="nav-tab nav-tab-help nav-tab-right" href="#credits"><span><?php _e('Credits', 'businesspress' ); ?></span></a>
        <?php endif; ?>
      </h2>
		</div>    
    
    <div class="wrap">
    <h2>BusinessPress</h2>
      
      <form id="businesspress-form" method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
        <div id="dashboard-widgets" class="metabox-holder columns-1">
          <?php
          add_meta_box( 'businesspress_welcome', __('Welcome', 'businesspress'), array( $this, 'settings_box_welcome' ), 'businesspress_settings_welcome', 'normal' );
          
          add_meta_box( 'businesspress_updates', __('Restrictions', 'businesspress'), array( $this, 'settings_box_updates' ), 'businesspress_settings_updates', 'normal' );
          
          add_meta_box( 'businesspress_security', __('Security Preferences', 'businesspress'), array( $this, 'settings_box_security' ), 'businesspress_settings_preferences', 'normal' );
          add_meta_box( 'businesspress_performance', __('Performance Preferences', 'businesspress'), array( $this, 'settings_box_performance' ), 'businesspress_settings_preferences', 'normal' );
          
          add_meta_box( 'businesspress_search', __('Search', 'businesspress'), array( $this, 'settings_box_search' ), 'businesspress_settings_preferences', 'normal' );
          add_meta_box( 'businesspress_notices', __('Admin Notices', 'businesspress'), array( $this, 'settings_box_notices' ), 'businesspress_settings_preferences', 'normal' );
          
          add_meta_box( 'businesspress_login', __('Login Protection', 'businesspress'), array( $this, 'settings_box_login' ), 'businesspress_settings_preferences', 'normal' );
          add_meta_box( 'businesspress_cdn', __('CDN', 'businesspress'), array( $this, 'settings_box_cdn' ), 'businesspress_settings_preferences', 'normal' );
          
          add_meta_box( 'businesspress_branding', __('Branding', 'businesspress'), array( $this, 'settings_box_branding' ), 'businesspress_settings_branding', 'normal' );
          add_meta_box( 'businesspress_user', __('User Profiles', 'businesspress'), array( $this, 'settings_box_user' ), 'businesspress_settings_branding', 'normal' );
          
          add_meta_box( 'businesspress_extra_multisite', __('Extra Site-Wide Settings', 'businesspress'), array( $this, 'settings_box_extra_multisite' ), 'businesspress_settings_multisite', 'normal' );
          
          add_meta_box( 'businesspress_credits', __('Credits', 'businesspress'), array( $this, 'settings_box_credits' ), 'businesspress_settings_credits', 'normal' );
          ?>
          <div id='welcome' class='postbox-container'>
            <?php do_meta_boxes('businesspress_settings_welcome', 'normal', false ); ?>     
          </div>
          
          <?php if( $businesspress->check_user_permission() ) : ?>
          
            <div id='updates' class='postbox-container'>
              <?php do_meta_boxes('businesspress_settings_updates', 'normal', false ); ?>
              <?php if( $businesspress->check_user_permission() ) : ?>
                <input type="submit" name="businesspress-submit" class="button-primary" value="<?php _e('Save All Changes', 'businesspress'); ?>" />
              <?php endif; ?>
            </div>
            
            <div id='preferences' class='postbox-container'>
              <?php do_meta_boxes('businesspress_settings_preferences', 'normal', false ); ?>            
              <input type="submit" name="businesspress-submit" class="button-primary" value="<?php _e('Save All Changes', 'businesspress'); ?>" />            
            </div>
            
            <div id='branding' class='postbox-container'>
              <?php do_meta_boxes('businesspress_settings_branding', 'normal', false ); ?>            
              <input type="submit" name="businesspress-submit" class="button-primary" value="<?php _e('Save All Changes', 'businesspress'); ?>" />            
            </div>
            
            <?php if( is_multisite() ) : ?>
              <div id='multisite' class='postbox-container'>
                <?php do_meta_boxes('businesspress_settings_multisite', 'normal', false ); ?>
                <input type="submit" name="businesspress-submit" class="button-primary" value="<?php _e('Save All Changes', 'businesspress'); ?>" />              
              </div>
            <?php endif; ?>
            
            <div id='credits' class='postbox-container'>
              <?php do_meta_boxes('businesspress_settings_credits', 'normal', false ); ?>
            </div>
            
          <?php endif; ?>
          
        </div>
        <?php if( $businesspress->check_user_permission() || !$businesspress->get_whitelist_domain() && !$businesspress->get_whitelist_email() ) wp_nonce_field( 'businesspress_settings_nonce', 'businesspress_settings_nonce' ); ?>
        
      </form>
        
    </div>
    
    
  <script>
  (function($) {
    
    /*  Tabs */      
    function tab_switch(anchor) {      
      $('#businesspress-header-nav .nav-tab').removeClass('nav-tab-active');
      $('[href=#'+anchor+']').addClass('nav-tab-active');
      $('#dashboard-widgets .postbox-container').hide();
      $('#' + anchor).show();      
      
      $('#businesspress-form').attr('action', $('#businesspress-form').attr('action').replace(/#.+/,'') +'#'+anchor);
    }    
    
    $(document).ready(function(){
      var anchor = window.location.hash.substring(1);
      if( !anchor || jQuery('#' + anchor).length == 0 ) {
        anchor = 'welcome';
      }
      
      tab_switch(anchor);
    });
    $('#businesspress-header-nav a').on('click',function(e){
      e.preventDefault();
      
      var anchor = $(this).attr('href').substring(1);
      window.location.hash = anchor;
      tab_switch(anchor);
    });
    
    /* Color scheme */
    $('.color-option').click( function(e) {      
      $('input[name=admin_color]').prop('checked','');
      $(this).find('input[name=admin_color]').prop('checked','true');
      $('.color-option').removeClass('selected');
      $(this).addClass('selected');
    });
    
    $('.color-option').click( function(e) {      
      $('input[name=admin_color]').prop('checked','');
      $(this).find('input[name=admin_color]').prop('checked','true');
      $('.color-option').removeClass('selected');
      $(this).addClass('selected');
    });
    
    $('.xml-rpc-obscure').click( function(e) {
      $('.xml-rpc-obscured').show();
    } );
    
  })(jQuery);
    
  /*  Logo upload */
  jQuery(document).ready(function($) {    
    var fv_flowplayer_uploader;
    var fv_flowplayer_uploader_button;
    $(document).on( 'click', '.upload_image_button', function(e) {
        e.preventDefault();
        
        fv_flowplayer_uploader_button = jQuery(this);
        jQuery('.fv_flowplayer_target').removeClass('fv_flowplayer_target' );
        fv_flowplayer_uploader_button.parents('tr').find('input[type=hidden]').addClass('fv_flowplayer_target' );
                         
        //If the uploader object has already been created, reopen the dialog
        if (fv_flowplayer_uploader) {
            fv_flowplayer_uploader.open();
            return;
        }
        //Extend the wp.media object
        fv_flowplayer_uploader = wp.media.frames.file_frame = wp.media({
            title: 'Pick the image',
            button: {
                text: 'Choose'
            },
            multiple: false
        });
        
        fv_flowplayer_uploader.on('open', function() {
          jQuery('.media-frame-title h1').text(fv_flowplayer_uploader_button.attr('alt'));
        });      
        //When a file is selected, grab the URL and set it as the text field's value
        fv_flowplayer_uploader.on('select', function() {
            attachment = fv_flowplayer_uploader.state().get('selection').first().toJSON();
            console.log(attachment);
            $('.fv_flowplayer_target').val(attachment.id);
            $('.businesspress-login-logo').remove();
            
            var url = attachment.url;
            if( typeof(attachment.sizes) != "undefined" && typeof(attachment.sizes.medium) != "undefined" ) {
              url = attachment.sizes.medium.url;
            }
            $('.fv_flowplayer_target').after('<img src="'+url+'" class="businesspress-login-logo" />');
            $('.fv_flowplayer_target').removeClass('fv_flowplayer_target' );
            $('.remove_image_button').show();
        });
        //Open the uploader dialog
        fv_flowplayer_uploader.open();
    });
    
    $(document).on( 'click', '.remove_image_button', function(e) {
      $(this).parents('tr').find('img').remove();
      $(this).parents('tr').find('input[type=hidden]').val(0);
    });
   
    $('.businesspress-enable').click( function() { jQuery('.nav-tab-updates').click() } );
  });     
  </script>

  
  <?php if( !$businesspress->check_user_permission()/* || !$businesspress->get_whitelist_domain() && !$businesspress->get_whitelist_email() */) :
    $sSelector1 = '#preferences input, #branding input';
    $sSelector2 = '#preferences td, #preferences th, #preferences h2, #branding td, #branding th, #branding h2';
    if( !$businesspress->check_user_permission() ) {
      $sSelector1 .= ', #updates input';
      $sSelector2 .= ', #updates td, #updates th, #updates h2';
    }
    ?>
    <script>
    (function($) {
      $('<?php echo $sSelector1; ?>').prop('disabled','true');
      $('<?php echo $sSelector2; ?>').css('color','gray');
      //$('.businesspress-enable').prop('disabled','');
      
      $('.contact-admin').click( function() { jQuery('.form-admin-contact').slideDown() } );
      
      $('.form-admin-contact input').click( function(e) {
        var button = $(this);
        button.prop('disabled','true');
        e.preventDefault();
        var message = $('.form-admin-contact textarea').val();
        if( message ) {
          $.post('<?php echo site_url('wp-admin/admin-ajax.php'); ?>', {
            'action' : 'businesspress_contact_admin',
            'message' : message 
            },
            function( response ) {
              var result = '<p>Error sending your message.</p>';
              if ( response == 1 ) {
                var result = '<p>Sent!</p>';
                button.prop('disabled','');
              }
              $('.form-admin-contact').append(result);
            });
        }
      });
      
    })(jQuery);      
    </script>
  <?php endif; ?>
  
    <?php
  }
  
  
  
  
  function settings_activation_notice() {
    ?>
      <p><?php _e('Update restrictions are not currently activated.','businesspress'); ?></p>
      <input id="businesspress-enable" class="button button-primary businesspress-enable" type="button" value="Enable Restriction Mode" />
    <?php  
  }
  
  
  
  
  function settings_box_branding() {
    global $businesspress;
    ?>
    <table class="form-table">
      <?php $this->admin_show_setting(
                    'wp_admin_bar_subscribers',
                    'wp_admin_bar_subscribers',
                    'Hide WP Admin Bar for subscribers',
                    __("With this setting it's up to you to provide the front-end interface for profile editing and so on. WP Admin Dashboard remains accessible, but is restricted to the Profile screen.", 'businesspress' ) );
      ?>
      <tr>
        <th>
          <label for="login-logo"><?php _e('Login Logo', 'businesspress' ); ?></label>
        </th>
        <td>
          <p class="description"><input type="hidden" id="login-logo" name="businesspress-login-logo" value="<?php echo esc_attr($businesspress->get_setting('login-logo') ); ?>" class="regular-text code" />
            <?php
            $sRemoveButtonStyle = ' style="display: none; "';
            if( $businesspress->get_setting('login-logo') > 0 ) {
              echo wp_get_attachment_image($businesspress->get_setting('login-logo'),'medium', false, array( 'class' => 'businesspress-login-logo' ) );
              $sRemoveButtonStyle = '';
            }
            ?>
            <input id="upload_image_button" class="upload_image_button button no-margin small" type="button" value="<?php _e('Upload Image', 'businesspress'); ?>" />
            <input id="upload_image_button" class="remove_image_button button" type="button" value="<?php _e('Remove', 'businesspress'); ?>"<?php echo $sRemoveButtonStyle; ?> />
            <label for="login-logo"><?php _e('This will the default Wordpress logo on the login screen to the one you chose. The uploaded logo will also be used on the search results page template.', 'businesspress' ); ?></p>
        </td>
      </tr>
    </table>           
    <?php
  }
  
  
  
  
  function settings_box_cdn() {
    global $businesspress;
    ?>
    <p><?php _e("These settings are required to make sure BusinessPress Login Protection won't ban your CDN server. MaxCDN IP ranges are already part of the plugin code.",'businesspress'); ?></p>
    <table class="form-table">
      <?php $this->admin_show_setting(
                    'businesspress-xpull-key',
                    'xpull-key',
                    'X-Pull Key',
                    __('Requests with matching X-Pull HTTP header will be considered as behind a proxy. Works well with KeyCDN.', 'businesspress' ),
                    'text' );
      ?>        
    </table>           
    <?php
  }
  
  
  
  
  function settings_box_credits() {
    global $businesspress;
    ?>
      <p>This plugin integrates some of the amazing WordPress plugins which keep it lean and removed the unnecessary features:</p>
      <ul>
        <li><a href="https://wordpress.org/plugins/disable-embeds/" target="_blank">Disable Embeds</a> by <a href="https://pascalbirchler.com/" target="_blank">Pascal Birchler </a> with our own improvements</li>
        <li><a href="https://wordpress.org/plugins/disable-emojis/" target="_blank">Disable Emojis</a> by <a href="https://geek.hellyer.kiwi/" target="_blank">Ryan Hellyer</a></li>
        <li><a href="https://wordpress.org/plugins/disable-json-api/" target="_blank">Disable REST API</a> by <a href="http://www.binarytemplar.com/" target="_blank">Dave McHale</a></li>
        <li><a href="https://wordpress.org/plugins/login-logo/" target="_blank">Login Logo</a> by <a href="http://coveredwebservices.com/" target="_blank">Mark Jaquith </a> with our own improvements</li>
        
      </ul>        
    <?php
  }    
  
  
  
  
  function settings_box_extra_multisite() {
    global $businesspress;
    ?>
    <table class="form-table">
      <p><?php _e('All BusinessPress settings are site-wide, but here are few extras.', 'businesspress'); ?></p>
      <?php $this->admin_show_setting(
                    'businesspress-multisite-tracking',
                    'multisite-tracking',
                    'Tracking Scripts',
                    __('The above will be added into footer of each site.', 'businesspress' ),
                    'textarea' );
      ?>
    </table>           
    <?php
  }  
  
  
  
  
  function settings_box_login() {
    global $businesspress;
    ?>
    <p><?php _e('Failed login attempts are logged into auth.log, so you can setup fail2ban on your server to read these entries and ban the IP addresses for brute-force login hacking protection. Check the <a href="https://wordpress.org/plugins/businesspress/installation/" target="_blank">installation instructions</a>.', 'businesspress' ); ?></p>
    <p><?php _e("If you don't have fail2ban available, we recommend <a href='https://wordpress.org/plugins/login-lockdown/' target='_blank'>Login LockDown</a>.", 'businesspress' ); ?></p>
    <?php
  }
  
  
  
  
  function settings_box_notices() {
    global $businesspress;
    
    $extra_note = is_multisite() ? ' '. __('Each blog in your Multisite gets one.', 'businesspress' ) : '';    
    ?>
    <table class="form-table">
      <?php $this->admin_show_setting(
                    'businesspress-hide-notices',
                    'hide-notices',
                    'Hide Admin Notices',
                    __('Moves them all to a new screen in Dashboard -> Notices.', 'businesspress' ).$extra_note );
      ?>
      
      <?php if( $businesspress->get_setting('hide-notices') ) :
        global $BusinessPress_Notices;
        if( $BusinessPress_Notices ) :
        $iCount = $BusinessPress_Notices->get_count();
        ?>
        <tr>
          <th></th>
          <td>There <?php if( $iCount == 1) echo 'is'; else echo 'are'; ?> <?php echo $iCount; ?> new notice<?php if( $iCount != 1 ) echo 's'; ?>, see them all <a href='<?php echo site_url('wp-admin/index.php?page=businesspress-notices'); ?>'>here</a>.</td>
        </tr>
        <?php endif;
      endif; ?>
    </table>           
    <?php
  }  
  
  
  
  
  function settings_box_performance() {
    global $businesspress;
    ?>
    <table class="form-table">
      <?php $this->admin_show_setting(
                    'businesspress-disable-emojis',
                    'disable-emojis',
                    'Disable',
                    __('Emojis', 'businesspress' ) );
      ?>
      
      <?php $this->admin_show_setting(
                    'businesspress-disable-oembed',
                    'disable-oembed',
                    '',
                    __('oEmbed', 'businesspress' ) );
      ?>
    </table>           
    <?php
  }
  
  
  
  
  function settings_box_search() {
    global $businesspress;
    ?>
    <table class="form-table">
      <?php $this->admin_show_setting(
                    'businesspress-search-results',
                    'search-results',
                    'Enable Google style results',
                    sprintf( __('Gives you similar layout and keyword highlight.', 'businesspress' ), plugin_dir_path(__FILE__).'fv-search.php' ) );
      ?>
    </table>           
    <?php
  }
  
  
  
  
  function settings_box_security() {
    global $businesspress;
    ?>
    <table class="form-table">
      
      <?php $this->admin_show_setting(
                    'businesspress-remove-generator',
                    'remove-generator',
                    __('Disable'),
                    __('Generator Tag (WP, EDD)', 'businesspress' ) );
      ?>
      
      <?php $this->admin_show_setting(
                    'businesspress-disable-rest-api',
                    'disable-rest-api',
                    '',
                    __('REST API', 'businesspress' ) );
      ?>      
      
      <?php $this->admin_show_setting(
                    'businesspress-disable-xml-rpc',
                    'disable-xml-rpc',
                    '',
                    __('XML-RPC', 'businesspress').' (<a href="#" class="xml-rpc-obscure">'.__('Protect', 'businesspress').'</a>)' );
      ?>
      
      <?php
      $token = rand();
      $this->admin_show_setting(
                    'businesspress-xml-rpc-key',
                    'xml-rpc-key',
                    __('XML-RPC Protection'),
                    ( $businesspress->get_setting('xml-rpc-key') ?
                      sprintf( __( 'Use <code>%s</code> to connect to XML-RPC on your site.', 'businesspress' ), site_url('xmlrpc.php?'.$businesspress->get_setting('xml-rpc-key'))  )
                      : sprintf( __( 'Put in something like <code>secret=%s</code> and then access the XML-RPC for your site as <code>%s</code>. Other requests will be blocked.', 'businesspress' ), $token, site_url('xmlrpc.php?secret='.$token)  ) ),
                    'text',
                    $businesspress->get_setting('xml-rpc-key') ? '' : 'hidden xml-rpc-obscured' );
      ?>         
      

      

        
    </table>           
    <?php
  }

  
  
  
  function settings_box_updates() {
    global $businesspress;
    
    $styleDomain = $businesspress->get_whitelist_domain() ? '' : ' style="display:none"';
    $styleEmail = $businesspress->get_whitelist_email() ? '' : ' style="display:none"';
    
    if( strlen($styleDomain) && strlen($styleEmail) ) {
      $styleEmail = '';
    }
    
    $checkedDomain = $businesspress->get_whitelist_domain() ? 'checked="checked"' : '';
    $requiredEmail = $businesspress->get_whitelist_domain() ? 'required' : '';
    $checkedEmail = $businesspress->get_whitelist_email() ? 'checked="checked"' : '';    
    
    if( !$checkedDomain && !$checkedEmail ) {
      $checkedEmail = 'checked="checked"';
    }
    
    $current_user = wp_get_current_user();
    $domain = $businesspress->get_whitelist_domain() ? $businesspress->get_whitelist_domain() : $businesspress->get_email_domain($current_user->user_email);
    $contact_email = $businesspress->get_setting('contact_email');
    $email = $businesspress->get_whitelist_email() ? $businesspress->get_whitelist_email() : $current_user->user_email;
    ?>       
    <table class="form-table">
      <?php $this->admin_show_setting(
                    'restrictions_enabled',
                    'restrictions_enabled',
                    'Enable',
                    __('Until you check this box the restrictions are not active.','businesspress') ); ?>
      
      <tr>
        <th><label><?php _e('Please enter the', 'businesspress' ); ?></label></th>
        <td>
          <p>
            <input type="radio" id="whitelist-email" name="whitelist" class="businessp-checkbox" value="email"<?php echo $checkedEmail; ?>>
            <label for="whitelist-email"><?php _e('admin email address', 'businesspress' ); ?></label>            <?php _e('or', 'businesspress' ); ?>
            <input type="radio" id="whitelist-domain" name="whitelist" class="businessp-checkbox" value="domain"<?php echo $checkedDomain; ?>>
            <label for="whitelist-domain"><?php _e('domain', 'businesspress' ); ?></label>.
          </p>
          <p class="description"><?php _e('Only user with a matching email address or domain will be able to change the settings here.', 'businesspress' ); ?></p>
        </td>
      </tr>
      <tr class="whitelist-domain"<?php echo $styleDomain; ?>>
        <th><label for="domain"><?php _e('Domain','businesspress'); ?></label></th>
        <td><input class="regular-text" type="text" id="domain" name="domain" class="text" value="<?php echo esc_attr($domain); ?>" readonly /></td>
      </tr>
      <tr class="whitelist-domain"<?php echo $styleDomain; ?>>
        <th><label for="contact_email"><?php _e('Contact Email','businesspress'); ?></label></th>
        <td><input class="regular-text" type="email" id="contact_email" name="contact_email" class="text" value="<?php echo esc_attr($contact_email); ?>" <?php $requiredEmail; ?> /></td>
      </tr>        
      <tr class="whitelist-email"<?php echo $styleEmail; ?>>
        <th><label for="email"><?php _e('Email','businesspress'); ?></label></th>
        <td><input class="regular-text" type="text" id="email" name="email" class="text" value="<?php echo esc_attr($email); ?>" readonly /></td>
      </tr>
      <tr>
        <th>
          <p><label>Allow other admins to</label></p>
        </th>
        <td>
          <p>
            <input type="hidden" name="cap_activate" value="0" />
            <input type="checkbox" id="cap_activate" name="cap_activate" value="1" <?php if( !empty($this->aOptions['cap_activate']) && $this->aOptions['cap_activate'] ) echo 'checked'; ?> />
            <label for="cap_activate">Activate and deactivate plugins and themes</label>
          </p>
          <p>
            <input type="hidden" name="cap_update" value="0" />
            <input type="checkbox" id="cap_update" name="cap_update" value="1" <?php if( !empty($this->aOptions['cap_update']) && $this->aOptions['cap_update'] ) echo 'checked'; ?> />
            <label for="cap_update">Update plugins and themes</label><br />
          </p>
          <p>
            <input type="hidden" name="cap_core" value="0" />
            <input type="checkbox" id="cap_core" name="cap_core" value="1" <?php if( !empty($this->aOptions['cap_core']) && $this->aOptions['cap_core'] ) echo 'checked'; ?> disabled="true" />
            <label for="cap_core">Update WordPress core</label><br />
          </p>          
          <p>
            <input type="hidden" name="cap_install" value="0" />
            <input type="checkbox" id="cap_install" name="cap_install" value="1" <?php if( !empty($this->aOptions['cap_install']) && $this->aOptions['cap_install'] ) echo 'checked'; ?> />
            <label for="cap_install">Install, edit and delete plugins and themes </label>
          </p>
          <p>
            <input type="hidden" name="cap_export" value="0" />
            <input type="checkbox" id="cap_export" name="cap_export" value="1" <?php if( !empty($this->aOptions['cap_export']) && $this->aOptions['cap_export'] ) echo 'checked'; ?> />
            <label for="cap_export">Export site content</label>
          </p>
        </td>
      </tr>
      <tr>
        <th>
          <p><label>Core auto updates</label></p>
        </th>
        <td>
          <select id="autoupgrades" name="autoupgrades">
            <option value="none"  <?php if( !empty($this->aOptions['core_auto_updates']) && $this->aOptions['core_auto_updates'] == "none" ) echo 'selected' ?>>None</option>
            <option value="minor" <?php if( empty($this->aOptions['core_auto_updates']) || $this->aOptions['core_auto_updates'] == "minor" ) echo 'selected' ?>>Minor</option>
            <option value="major" <?php if( !empty($this->aOptions['core_auto_updates']) && $this->aOptions['core_auto_updates'] == "major" ) echo 'selected' ?>>Major</option>
          </select>
        </td>
      </tr>        
    </table>
    <script>
      if( jQuery('#restrictions_enabled:checked').length ) {
        jQuery('.businesspress-setting-restrictions_enabled td label').hide();
      }
      
      jQuery('#restrictions_enabled').click( function() {;
        if( jQuery('#restrictions_enabled:checked').length ) {
          jQuery('.businesspress-setting-restrictions_enabled td label').hide();
        } else {
          jQuery('.businesspress-setting-restrictions_enabled td label').show();
        }
      });
      
      if( jQuery('#whitelist-domain:checked').length ) {
        jQuery('tr.whitelist-email').hide();
        jQuery('tr.whitelist-domain').show();
      }
      
      jQuery('#whitelist-domain').change( function() {
        jQuery('tr.whitelist-email').hide();
        jQuery('tr.whitelist-domain').show();
        jQuery('#contact_email').attr('required','true');
      });
      jQuery('#whitelist-email').change( function() {
        jQuery('tr.whitelist-email').show();
        jQuery('tr.whitelist-domain').hide();
        jQuery('#contact_email').removeAttr('required');
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
  
  
  
  
  function settings_box_user() {
    global $businesspress;
    ?>
    <table class="form-table">
      <tr>
        <th><label><?php _e('Impose Admin Color Scheme', 'businesspress' ); ?></label></th>
        <td><?php
        //  copy of core code from wp-admin/includes/misc.php admin_color_scheme_picker() with adjustments
        global $_wp_admin_css_colors;
        
          ksort( $_wp_admin_css_colors );
        
          if ( isset( $_wp_admin_css_colors['fresh'] ) ) {
            // Set Default ('fresh') and Light should go first.
            $_wp_admin_css_colors = array_filter( array_merge( array( 'fresh' => '', 'light' => '' ), $_wp_admin_css_colors ) );
          }
        
          $current_color = $businesspress->get_setting('admin-color');
          
          if ( empty( $current_color ) || ! isset( $_wp_admin_css_colors[ $current_color ] ) ) {
            $current_color = 'fresh';
          }
        
          ?>
          <fieldset id="color-picker" class="scheme-list">
            <legend class="screen-reader-text"><span><?php _e( 'Admin Color Scheme' ); ?></span></legend>
            <?php
            wp_nonce_field( 'save-color-scheme', 'color-nonce', false );
            foreach ( $_wp_admin_css_colors as $color => $color_info ) :
        
              ?>
              <div class="color-option <?php echo ( $color == $current_color ) ? 'selected' : ''; ?>">
                <input name="admin_color" id="admin_color_<?php echo esc_attr( $color ); ?>" type="radio" value="<?php echo esc_attr( $color ); ?>" class="tog" <?php checked( $color, $current_color ); ?> />
                <input type="hidden" class="css_url" value="<?php echo esc_url( $color_info->url ); ?>" />
                <input type="hidden" class="icon_colors" value="<?php echo esc_attr( wp_json_encode( array( 'icons' => $color_info->icon_colors ) ) ); ?>" />
                <label for="admin_color_<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $color_info->name ); ?></label>
                <table class="color-palette">
                  <tr>
                  <?php
        
                  foreach ( $color_info->colors as $html_color ) {
                    ?>
                    <td style="background-color: <?php echo esc_attr( $html_color ); ?>">&nbsp;</td>
                    <?php
                  }
        
                  ?>
                  </tr>
                </table>
              </div>
              <?php
        
            endforeach;
        
          ?>
          </fieldset>
          <?php        
        ?></td>
      </tr>
    </table>           
    <?php
  }  
  
  
  
  
  function settings_box_welcome() {
    global $businesspress;
    ?>       
<table class="form-table2">
  <tr>
    <td></td>
    <td>
      <div class="one-half first">
        <p class="description"><?php _e("BusinessPress makes sure you get the essential features required to run your site for business use - without constant core WordPress upgrades and various WordPress enhancemens which you don't need - reducing the security vulnerabilities and speeding up the site.", 'businesspress' ); ?></p>
        <ol>
          <li><?php _e("Major core auto-updates disabled.", 'businesspress' ); ?></li>
          <li><?php _e("Minor core auto-updates delayed to make sure they are stable.", 'businesspress' ); ?></li>
          <li><?php _e("Login protection (if you have fail2ban installed).", 'businesspress' ); ?></li>
          <li><?php _e("Disable REST API and XML-RPC for security.", 'businesspress' ); ?></li>
          <li><?php _e("Disable oEmbed and Emojis for performance.", 'businesspress' ); ?></li>
          <li><?php _e("Hide admin notices to keep your wp-admin focused.", 'businesspress' ); ?></li>
        </ol>
      </div>
      <div class="one-half">
        
        <?php if( !$businesspress->get_setting('email') && !$businesspress->get_setting('domain') ) : ?>
          <?php $this->settings_activation_notice(); ?>
          
        <?php else : ?>  
          <p class="description">
            <?php if( $domain = $businesspress->get_whitelist_domain() ) {
              printf( __('Access to this screen is limited to users with email address on %s.'), $domain );
            } elseif( $email = $businesspress->get_whitelist_email() ) {
              printf( __('Access to this screen is limited to user with email address equal to %s.'), $email );
            } ?>        
          </p>
          <?php if( !$businesspress->check_user_permission() ) :
            $sFormStyle = isset($_GET['contact_form']) ? '' : ' style="display: none"';
            ?>          
            <p class="description">
              <?php if( !isset($_GET['contact_form']) ) : ?><a href="#" class="button-primary contact-admin"><?php endif; ?>
                <?php _e('Contact the admin', 'businesspress' ); ?>
              <?php if( !isset($_GET['contact_form']) ) : ?></a><?php endif; ?>
              <?php _e('if you need to make any changes, that are not available to you.', 'businesspress' ); ?></p>
            <div class="form-admin-contact"<?php echo $sFormStyle; ?>>
              <textarea class="large-text" name="message" rows="5"></textarea>
              <input type="submit" class="button-primary" value="Send" />
            </div>

          <?php endif; ?>
          
        <?php endif; ?>
          

      </div>            
    </td>
  </tr>
  <tr>    		
    <td colspan="2">
    </td>
  </tr>                                    
</table>  
    <?php
  }  
  
  
  

  function show_disallow_not_defined() {
    global $businesspress;
    $current_user = wp_get_current_user();
    if( false !== stripos( $current_user->user_email, $businesspress->get_email_domain() )) {  //  todo: get rid of this
      if( is_super_admin() || is_admin()  ) {
        if( ( !defined('DISALLOW_FILE_EDIT' ) ) || ( defined('DISALLOW_FILE_EDIT' ) && ( DISALLOW_FILE_EDIT === false ) )  ) {
          echo '<div class="error"><p>DISALLOW_FILE_EDIT is not defined, or defined as FALSE</p></div>';
        }
      }
    }
  
  }  

  
}

global $BusinessPress_Settings;
$BusinessPress_Settings = new BusinessPress_Settings;