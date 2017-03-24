<?php

/*
 *  Version 0.2
 */

class FV_Search {
  
  var $aSearchResults = false;

  function __construct() {
    if( is_admin() ) return;
    
    add_action( 'template_redirect', array($this,'template_redirect') );  //  this is where fake page is created, in the_posts it wasn't working so well, it was too late
    add_filter( 'the_content', array($this,'the_content') );  //  show the results here
  }
  
  function template_redirect($template) {
    global $wp_query;
    if( $wp_query->is_main_query() && $wp_query->is_search ) {
      $wp_query->is_search = false;
      $wp_query->is_search_actually = true;
      
      $this->aSearchResults = $wp_query->posts;
      
      $objPost = new stdClass;
      $objPost->ID = 999999999999;
      $objPost->post_title = 'Search';
      $objPost->post_type = 'page';
      $objPost->post_content = 'wtf';
      $objPost->post_status = 'publish';
      $objPost->comment_status = 'closed';
      $objPost->ping_status = 'closed';
      
      $wp_query->posts = 1;
      $wp_query->post_count = 1;
      $wp_query->found_posts = 1;
      $wp_query->max_num_pages = 1;
      
      $wp_query->posts = array($objPost);
      
      remove_action( 'genesis_before_loop', 'genesis_do_breadcrumbs' );
    }
  }
  
  function the_content( $html ) {
    global $wp_query;
    if( !$wp_query->is_search_actually ) return $html;
    
    global $post;
    $tmp_post = $post;
    
    $search_query = get_search_query();
    $aSearchKeywords = array_map( 'trim', explode( " ", get_search_query() ) );
    $aSearchKeywords = array_merge( array( $search_query ), $aSearchKeywords ); //  make sure full query is highlighted first
    
    $html = '<form role="search" method="get" class="search-form businesspress-search-form" action="' . esc_url( home_url( '/' ) ) . '">
                <input type="search" class="search-field" placeholder="' . esc_attr_x( 'Search &hellip;', 'placeholder' ) . '" value="' . $search_query . '" name="s" />                
                <input type="submit" class="search-submit" value="'. esc_attr_x( 'Search', 'submit button' ) .'" />
            </form>
            ';
    
    if( $this->aSearchResults && count($this->aSearchResults) > 0 ) {
      foreach( $this->aSearchResults AS $post ) {
					//setup_postdata( $post);
          
          $aSentences = array_map( 'trim', preg_split( '~(\. |\n)~', strip_shortcodes( strip_tags( $post->post_content ) ) ) );
          
          $aExcerpt = array();          
          foreach( $aSearchKeywords AS $sKeyword ) {
            foreach($aSentences AS $k => $sSentence ) {
              if( stripos($sSentence,$sKeyword) !== false ) {
                //$aExcerpt[] = str_ireplace( $sKeyword, '<strong>'.$sKeyword.'</strong>', $sSentence );
                $aExcerpt[] = $sSentence;
                unset($aSentences[$k]); //  make sure each sentence is in the search excerpt only once
                break;
              }  
            }
          }
          
          $sTitle = get_the_title();
          $sLink = get_the_permalink();
          $sExcerpt = implode('&hellip; ',$aExcerpt).'&hellip;';          
          
          foreach( $aSearchKeywords AS $sKeyword ) {            
            //$sExcerpt = str_ireplace( $sKeyword, '<strong>'.$sKeyword.'</strong>', $sExcerpt );
            $sTitle = preg_replace("/".$sKeyword."/i", "<b>\$0</b>", $sTitle );
            $sLink = preg_replace( '~https?://~', '', preg_replace("/".$sKeyword."/i", "<b>\$0</b>", $sLink ) );
            $sExcerpt = preg_replace("/".$sKeyword."/i", "<b>\$0</b>", $sExcerpt );
          }
          
          if( get_the_time('U') + 7*24*3600 > current_time('timestamp') ) {
            $sDate = human_time_diff( get_the_time('U'), current_time('timestamp') ).' ago';
          } else {
            $sDate = get_the_date('M j, Y');
          }
          
          
          $html .= '<div class="businesspress-search-result">
                
                <h2><a href="'.get_permalink().'">'.$sTitle.'</a></h2>
                '.str_replace( '<img', '<img style="width: 100px"', get_the_post_thumbnail( get_the_id(), 'thumbnail', array( 'class' => 'alignleft' ) ) ).'
                <span><a href="'.get_permalink().'">'.$sLink.'</a></span>
                <p><em>'.$sDate.'</em> '.$sExcerpt .'</p>
            </div>
            <div style="clear:both"></div>
            ';          
        
      }
    }    
    
    $post = $tmp_post;
    return $html;
  }
  
}

$FV_Search = new FV_Search;

add_action( 'wp_footer', 'user_screen_css' );
function user_screen_css() {
    ?>
    <style>
    /* Custom search results */
    .businesspress-search-form {
        max-width: 500px;
        border: 1px solid #ccc;
        border-radius: 3px;
        box-shadow: 0 1px 3px #ccc;
    }
    .businesspress-search-form .search-field {
        border: none;
        background-color: transparent;
    }
    .businesspress-search-form .search-submit {
        text-indent: -9999px;
        background: url('http://styling.domainearl.com/site/wp-content/plugins/businesspress/css/search-icon.png') no-repeat center center;
        background-color: transparent;
    }
    .businesspress-search-form search-submit:before {
        display: none;
    }
    .businesspress-search-result {
        font-family: Arial, sans-serif;
        margin: 24px 0;
    }
    .businesspress-search-result h2 {
        font-size: 18px;
        margin-bottom: 0;
        font-weight: 400;
    }
    .businesspress-search-result h2 a,
    .businesspress-search-result .bpsr-link a {
        color: #1a0dab;
        text-decoration: none;
        box-shadow: none !important;
        border-bottom: none !important;
    }
    .businesspress-search-result .bpsr-link a {
        color: #006621;
    }
    .businesspress-search-result h2 a:hover,
    .businesspress-search-result .bpsr-link a:hover {
        text-decoration: underline;
    }
    .businesspress-search-result .bpsr-date {
        color: #808080;
        font-size: 14px;
    }
    .businesspress-search-result p {
        line-height: 1.35;
        font-size: 14px;
    }
    </style>
    <?php
  }


