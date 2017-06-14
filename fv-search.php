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
  
  function css() {
    ?>
    <style>
    /* Custom search results */
    .businesspress-search-form {
        max-width: 500px;
        border: 1px solid #ccc;
        border-radius: 3px;
        box-shadow: 0 1px 3px #ccc;
    }
    .businesspress-search-form input.search-field {
        border: none;
        background-color: transparent;
        width: 88%;
        margin: 0;
        padding: 7px;
    }
    .businesspress-search-form input.search-field:focus {
        border: none;
        background-color: transparent;
    }
    .businesspress-search-form input.search-submit {
        text-indent: -9999px;
        background: url('<?php echo esc_url( plugins_url( 'css/search-icon.png', __FILE__ ) ); ?>') no-repeat right center;
        background-color: transparent;
        width: 11%;
        height: auto;
        position: relative;
        float: right;
        display: block;
        border: 0;
        margin: 0;
        padding: 7px;
    }
    .businesspress-search-form input.search-submit:hover {
        background: url('<?php echo esc_url( plugins_url( 'css/search-icon.png', __FILE__ ) ); ?>') no-repeat right center;
        background-color: transparent;
    }
    .businesspress-search-form search-submit:before {
        display: none;
    }
    .entry-content .businesspress-search-result {
        font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;
        line-height: 1.25;
        margin: 24px 0;
    }
    .entry-content .businesspress-search-result h2 {
        font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;
        font-size: 18px;
        font-weight: 400;
        line-height: 1;
        margin-bottom: 0;
    }
    .entry-content .businesspress-search-result h2 a,
    .entry-content .businesspress-search-result .bpsr-link a {
        color: #1a0dab;
        text-decoration: none;
        box-shadow: none;
        border-bottom: none;
    }
    .entry-content .businesspress-search-result .bpsr-link a,
    .entry-content .businesspress-search-result span a {
        color: #006621;
        font-size: 14px;
        text-decoration: none;
        box-shadow: none;
        border-bottom: none;
    }
    .entry-content .businesspress-search-result h2 a:hover {
        color: #1a0dab;
        text-decoration: underline;
    }
    .entry-content .businesspress-search-result .bpsr-link a:hover,
    .entry-content .businesspress-search-result span a:hover {
        color: #006621;
        text-decoration: underline;
    }
    .entry-content .businesspress-search-result em,
    .entry-content .businesspress-search-result .bpsr-date {
        color: #808080;
        font-size: 14px;
        font-style: normal;
    }
    .entry-content .businesspress-search-result p {
        font-size: 14px;
        line-height: 1.35;
    }
    </style>
    <?php
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
      add_action( 'wp_head', array($this,'css') );
    }
  }
  
  function the_content( $html ) {
    global $wp_query;
    if( !$wp_query->is_search_actually ) return $html;
    
    global $post;
    $tmp_post = $post;
    
    $search_query = get_search_query();
    $aSearchKeywords = array_map( 'trim', explode( " ", get_search_query() ) );
    $aSearchKeywords = array_merge( array( trim($search_query) ), $aSearchKeywords ); //  make sure full query is highlighted first
    $aSearchKeywords = array_unique($aSearchKeywords);
    
    $html = '<form role="search" method="get" class="search-form businesspress-search-form" action="' . esc_url( home_url( '/' ) ) . '">
                <input type="search" class="search-field" placeholder="' . esc_attr_x( 'Search &hellip;', 'placeholder' ) . '" value="' . $search_query . '" name="s" />                
                <input type="submit" class="search-submit" value="'. esc_attr_x( 'Search', 'submit button' ) .'" />
            </form>
            ';
    
    if( $this->aSearchResults && count($this->aSearchResults) > 0 ) {
      foreach( $this->aSearchResults AS $post ) {
					//setup_postdata( $post);
          
          $aSentences = array_map( 'trim', preg_split( '~(\. |\n)~', strip_shortcodes( strip_tags( $post->post_content ) ), -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE ) );          
          
          $iCount = 0;
          $aExcerpt = array();          
          foreach( $aSearchKeywords AS $sKeyword ) {
            foreach($aSentences AS $k => $sSentence ) {
              if( stripos($sSentence,$sKeyword) !== false ) {
                //$aExcerpt[] = str_ireplace( $sKeyword, '<strong>'.$sKeyword.'</strong>', $sSentence );
                
                unset($aSentences[$k]);
                
                if( isset($aSentences[$k+1]) && $aSentences[$k+1] == '.' ) {                  
                  $aExcerpt['sentence-'.$sKeyword] = $sSentence.$aSentences[$k+1].' ';
                  unset($aSentences[$k+1]);
                  break;
                } else {
                  $iCount++;
                  $aExcerpt['phrase-'.$sKeyword.'-'.$iCount] = $sSentence;
                }
                
              }  
            }
          }
          
          $iCount = 0;
          foreach( $aSearchKeywords AS $sKeyword ) {
            if( isset($aExcerpt['sentence-'.$sKeyword]) ) {
              foreach( $aExcerpt AS $k => $v ) {
                if( stripos($k,'phrase-'.$sKeyword) === 0 ) unset($aExcerpt[$k]);
              }
            } else {
              foreach( $aExcerpt AS $k => $v ) {
                if( stripos($k,'phrase-'.$sKeyword) === 0 ) {
                  $iCount++;
                  if( $iCount > 2 ) unset($aExcerpt[$k]);
                }
              }
            }
          }
          
          $sTitle = get_the_title();
          $sLink = get_the_permalink();
          $sExcerpt = trim(implode('&hellip; ',$aExcerpt));
          if( strlen($sExcerpt) == 0 ) {
            $sExcerpt = get_post_meta( $post->ID, '_aioseop_description', true );
          } else if( stripos($sExcerpt,'&hellip;') === false && $sExcerpt[strlen($sExcerpt)-1] != '.' ) {
            $sExcerpt .= '&hellip;';
          }
          
          foreach( $aSearchKeywords AS $sKeyword ) {                        
            $sTitle = preg_replace("/".$sKeyword."/i", "<b>\$0</b>", $sTitle );
            $sLink = preg_replace( '~https?://~', '', preg_replace("/".$sKeyword."/i", "<b>\$0</b>", $sLink ) );
            $sExcerpt = preg_replace("/".$sKeyword."/i", "<b>\$0</b>", $sExcerpt );
          }
          
          if( get_the_time('U') + 7*24*3600 > current_time('timestamp') ) {
            $sDate = human_time_diff( get_the_time('U'), current_time('timestamp') ).' ago';
          } else {
            $sDate = get_the_date('M j, Y');
          }
          
          $sImage = false; //str_replace( '<img', '<img style="width: 100px"', get_the_post_thumbnail( get_the_id(), 'thumbnail', array( 'class' => 'alignleft' ) ) );
          $sDate = false; //'<em>'.$sDate.'</em> ';
          
          $html .= '<div class="businesspress-search-result">
                
                <h2><a href="'.get_permalink().'">'.$sTitle.'</a></h2>
                '.$sImage.'
                <span><a href="'.get_permalink().'">'.$sLink.'</a></span>
                <p>'.$sExcerpt .'</p>
            </div>
            <div style="clear:both"></div>
            ';          
        
      }
    } else {
      $html .= '<div class="businesspress-search-no-result"><p>'.__('Your search did not match any documents.','businesspress').'</p>';
      $html .= '<p>'.__('Suggestions:','businesspress').'</p>';
      $html .= '<ul><li>'.__('Make sure that all words are spelled correctly.','businesspress').'</li>';
      $html .= '<li>'.__('Try different keywords.','businesspress').'</li>';
      $html .= '<li>'.__('Try more general keywords.','businesspress').'</li>';
      $html .= '<li>'.__('Try fewer keywords.','businesspress').'</li></ul></div>';
    }
    
    $post = $tmp_post;
    return $html;
  }
  
}

$FV_Search = new FV_Search;
