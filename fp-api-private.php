<?php

/**
* Foliopress base class
*/
 
 /*
Usage:
* Autoupdates
In the plugin object:
var $strPluginSlug = 'fv-sharing';
var $strPrivateAPI = 'http://foliovision.com/plugins/';
In the plugin constructor:
parent::auto_updates();
* Update notices
In the plugin constructor:
$this->readme_URL = 'http://plugins.trac.wordpress.org/browser/{plugin-slug}/trunk/readme.txt?format=txt';
add_action( 'in_plugin_update_message-{plugin-dir}/{plugin-file}.php', array( &$this, 'plugin_update_message' ) );
*/

/**
* Class FVFB_Foliopress_Plugin_Private //// needs to be named as plugin + something. Then plugin object needs to include and extend this!
*/
class BusinessPress_Plugin_Private
{
/**
* Stores the path to readme.txt available on trac, needs to be set from plugin
* @var string
*/
  var $readme_URL;

/**
* Stores the special message for updates
* @var string
*/
  var $update_prefix;
  
  function auto_updates(){
    if( is_admin() ){
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'CheckPluginUpdate' ) );
        add_filter( 'plugins_api', array( $this, 'PluginAPICall' ), 10, 3 );
        add_action( 'update_option__transient_update_plugins', array( $this, 'CheckPluginUpdateOld' ) );
    }
  }
  
  function http_request($method, $url, $data = '', $auth = '', $check_status = true)
  {
      $status = 0;
      $method = strtoupper($method);
      
      if (function_exists('curl_init')) {
          $ch = curl_init();
          
          curl_setopt($ch, CURLOPT_URL, $url);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0)');
          @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
          curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
          curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
          curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
          curl_setopt($ch, CURLOPT_TIMEOUT, 10);
          
          switch ($method) {
              case 'POST':
                  curl_setopt($ch, CURLOPT_POST, true);
                  curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                  break;
              
              case 'PURGE':
                  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PURGE');
                  break;
          }
          
          if ($auth) {
              curl_setopt($ch, CURLOPT_USERPWD, $auth);
          }
          
          $contents = curl_exec($ch);
          
          $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
          
          curl_close($ch);
      } else {
          $parse_url = @parse_url($url);
          
          if ($parse_url && isset($parse_url['host'])) {
              $host = $parse_url['host'];
              $port = (isset($parse_url['port']) ? (int) $parse_url['port'] : 80);
              $path = (!empty($parse_url['path']) ? $parse_url['path'] : '/');
              $query = (isset($parse_url['query']) ? $parse_url['query'] : '');
              $request_uri = $path . ($query != '' ? '?' . $query : '');
              
              $request_headers_array = array(
                  sprintf('%s %s HTTP/1.1', $method, $request_uri),
                  sprintf('Host: %s', $host),
                  sprintf('User-Agent: %s', W3TC_POWERED_BY),
                  'Connection: close'
              );
              
              if (!empty($data)) {
                  $request_headers_array[] = sprintf('Content-Length: %d', strlen($data));
              }
              
              if (!empty($auth)) {
                  $request_headers_array[] = sprintf('Authorization: Basic %s', base64_encode($auth));
              }
              
              $request_headers = implode("\r\n", $request_headers_array);
              $request = $request_headers . "\r\n\r\n" . $data;
              $errno = null;
              $errstr = null;
              
              $fp = @fsockopen($host, $port, $errno, $errstr, 10);
              
              if (!$fp) {
                  return false;
              }
              
              $response = '';
              @fputs($fp, $request);
              
              while (!@feof($fp)) {
                  $response .= @fgets($fp, 4096);
              }
              
              @fclose($fp);
              
              list($response_headers, $contents) = explode("\r\n\r\n", $response, 2);
              
              $matches = null;
              
              if (preg_match('~^HTTP/1.[01] (\d+)~', $response_headers, $matches)) {
                  $status = (int) $matches[1];
              }
          }
      }
      
      if (!$check_status || $status == 200) {
          return $contents;
      }
      
      return false;
  }
  
  /**
* Download url via GET
*
* @param string $url
* @param string $auth
* $param boolean $check_status
* @return string
*/
  function http_get($url, $auth = '', $check_status = true)
  {
      return $this->http_request('GET', $url, null, $auth, $check_status);
  }

  function plugin_update_message()
  {
      if( $this->readme_URL ) {
        $data = $this->http_get( $this->readme_URL );
        
        if ($data) {
            $matches = null; /// not sure if this works for more than one last changelog
            //if (preg_match('~==\s*Changelog\s*==\s*=\s*[0-9.]+\s*=(.*)(=\s*[0-9.]+\s*=|$)~Uis', $data, $matches)) {
            if (preg_match('~==\s*Upgrade Notice\s*==\s*=\s*[0-9.]+\s*=(.*)(=\s*[0-9.]+\s*=|$)~Uis', $data, $matches)) {
                $changelog = (array) preg_split('~[\r\n]+~', trim($matches[1]));

                echo '<div style="color: #b51212;"">';
                $ul = false;
                
                foreach ($changelog as $index => $line) {
                    if (preg_match('~^\s*\*\s*~', $line) && 1<0 ) {
                        if (!$ul) {
                            //echo '<ul style="list-style: disc; margin-left: 20px;">';
                            $ul = true;
                        }
                        $line = preg_replace('~^\s*\*\s*~', '', htmlspecialchars($line));
                        echo '<li style="width: 50%; margin: 0; float: left; ' . ($index % 2 == 0 ? 'clear: left;' : '') . '">' . $line . '</li>';
                    } else {
                        if ($ul) {
                            //echo '</ul><div style="clear: left;"></div>';
                            $ul = false;
                        }
                        $line = preg_replace('~^\s*\*\s*~', '', htmlspecialchars($line));
                        echo '<p style="margin: 5px 0;">' . htmlspecialchars($line) . '</p>';
                    }
                }
                
                if ($ul) {
                    //echo '</ul><div style="clear: left;"></div>';
                }
                
                echo '</div>';
            }
        }
      }
  }
  /*function plugin_update_message()
{
if( $this->readme_URL ) {
$data = $this->http_get( $this->readme_URL );
if ($data) {
$matches = null; /// not sure if this works for more than one last changelog
if (preg_match('~==\s*Changelog\s*==\s*=\s*[0-9.]+\s*=(.*)(=\s*[0-9.]+\s*=|$)~Uis', $data, $matches)) {
$changelog = (array) preg_split('~[\r\n]+~', trim($matches[1]));

if( $this->update_prefix ) {
echo '<div style="color: #b51212;">'.$this->update_prefix.'</div>';
}
echo '<div>Last version improvements:</div><div style="font-weight: normal;">';
$ul = false;
foreach ($changelog as $index => $line) {
if (preg_match('~^\s*\*\s*~', $line)) {
if (!$ul) {
echo '<ul style="list-style: disc; margin-left: 20px;">';
$ul = true;
}
$line = preg_replace('~^\s*\*\s*~', '', htmlspecialchars($line));
echo '<li style="width: 50%; margin: 0; float: left; ' . ($index % 2 == 0 ? 'clear: left;' : '') . '">' . $line . '</li>';
} else {
if ($ul) {
echo '</ul><div style="clear: left;"></div>';
$ul = false;
}
echo '<p style="margin: 5px 0;">' . htmlspecialchars($line) . '</p>';
}
}
if ($ul) {
echo '</ul><div style="clear: left;"></div>';
}
echo '</div>';
}
}
}
}*/
  
  function is_min_wp( $version ) {
    return version_compare( $GLOBALS['wp_version'], $version. 'alpha', '>=' );
  }
  
  
/// ================================================================================================
/// Custom plugin repository
/// ================================================================================================

/*
Uses:
$this->strPluginSlug - this has to be in plugin object
$this->strPrivateAPI - also

*/

   private function PrepareRequest( $action, $args ){
      global $wp_version;

      return array(
         'body' => array(
            'action' => $action,
            'request' => serialize($args),
            'api-key' => md5(get_bloginfo('url'))
         ),
         'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
      );
   }

   public function CheckPluginUpdate( $checked_data ){
      if( empty( $checked_data->checked ) ) return $checked_data;



      $request_args = array(
       'slug' => $this->strPluginSlug,
       'version' => $checked_data->checked[$this->strPluginSlug.'/'.$this->strPluginSlug.'.php'],
      );

      $request_string = $this->PrepareRequest( 'basic_check', $request_args );

      // Start checking for an update
      $raw_response = wp_remote_post( $this->strPrivateAPI, $request_string );

      if( !is_wp_error( $raw_response ) && ( $raw_response['response']['code'] == 200 ) ) $response = unserialize( $raw_response['body'] );

      if( version_compare( $response->version, $request_args['version'] ) == 1 ){
         if( is_object( $response ) && !empty( $response ) ) // Feed the update data into WP updater
            $checked_data->response[$this->strPluginSlug.'/'.$this->strPluginSlug.'.php'] = $response;
      }

      //var_dump( $checked_data );

      return $checked_data;
   }

   public function CheckPluginUpdateOld( $aData = null ){
      $aData = get_transient( "update_plugins" );
      $aData = $this->CheckPluginUpdate( $aData );
      set_transient( "update_plugins", $aData );
      
      if( function_exists( "set_site_transient" ) ) set_site_transient( "update_plugins", $aData );
   }

   public function PluginAPICall( $def, $action, $args ){
      if( !isset($args->slug) || $args->slug != $this->strPluginSlug ) return $def;

      // Get the current version
      $plugin_info = get_site_transient( 'update_plugins' );
      $current_version = $plugin_info->checked[$this->strPluginSlug.'/'.$this->strPluginSlug.'.php'];
      $args->version = $current_version;

      $request_string = $this->PrepareRequest( $action, $args );

      $request = wp_remote_post( $this->strPrivateAPI, $request_string );

      if( is_wp_error( $request ) ) {
         $res = new WP_Error( 'plugins_api_failed', __( 'An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>' ), $request->get_error_message() );
      }else{
         $res = unserialize( $request['body'] );
         if( $res === false ) $res = new WP_Error( 'plugins_api_failed', __( 'An unknown error occurred' ), $request['body'] );
      }

      return $res;
   }
  

}
