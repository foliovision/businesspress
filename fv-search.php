<?php

/*
 *  Version 0.2
 */

if( !class_exists('FV_Search') ) :

class FV_Search {
  
  var $aSearchResults = false;

  function __construct() {
    if( is_admin() ) return;
    
    add_action( 'template_redirect', array($this,'template_redirect') );  //  this is where fake page is created, in the_posts it wasn't working so well, it was too late
    add_filter( 'the_content', array($this,'the_content') );  //  show the results here
    
    add_action( 'pre_get_posts', array($this,'posts_per_page') );
  }
  
  function css() {
    $ver = class_exists('BusinessPress') ? BusinessPress::VERSION : false;
    wp_enqueue_style('fv-search', plugins_url( '/css/fv-search.css', __FILE__ ), array(), $ver );
  }  
  
  function template_redirect($template) {
    global $wp_query;
    if( $wp_query->is_main_query() && $wp_query->is_search ) {
      $wp_query->is_search = false;
      $wp_query->is_search_actually = true;
      
      $this->aSearchResults = $wp_query->posts;
      $this->iSearch_post_count = $wp_query->post_count;
      $this->iSearch_found_posts = $wp_query->found_posts;
      $this->iSearch_max_num_pages = $wp_query->max_num_pages;
      
      $objPost = new stdClass;
      $objPost->ID = 999999999999;
      $objPost->post_title = 'Search';
      $objPost->post_type = 'page';
      $objPost->post_content = '<div>[fv_search]</div>';
      $objPost->post_status = 'publish';
      $objPost->comment_status = 'closed';
      $objPost->ping_status = 'closed';
      
      $wp_query->posts = 1;
      $wp_query->post_count = 1;
      $wp_query->found_posts = 1;
      $wp_query->max_num_pages = 1;
      
      $wp_query->posts = array($objPost);

      // Without this Genesis would prevent the full search results from showing, it would make Excerpt out of it
      add_filter( 'genesis_pre_get_option_content_archive', array($this,'disable_genesis_excerpt') );

      remove_action( 'genesis_before_loop', 'genesis_do_breadcrumbs' );
      add_action( 'wp_enqueue_scripts', array($this,'css') );
    }
  }

  function disable_genesis_excerpt() {
    return 'full';
  }
  
  function the_content( $html ) {
    global $wp_query;
    if( !is_main_query() || trim($html) != '<div>[fv_search]</div>' ) return $html;
    
    global $post;
    $tmp_post = $post;
    
    $search_query = get_search_query();
    $aSearchKeywords = array_map( 'trim', explode( " ", get_search_query() ) );
    $aSearchKeywords = array_merge( array( trim($search_query) ), $aSearchKeywords ); //  make sure full query is highlighted first
    $aSearchKeywords = array_unique($aSearchKeywords);
    
    $search_form = apply_filters('businesspress_search_form', '<form role="search" method="get" class="search-form businesspress-search-form" action="' . esc_url( home_url( '/' ) ) . '">
                <input type="search" class="search-field" placeholder="' . esc_attr_x( 'Search &hellip;', 'placeholder' ) . '" value="' . $search_query . '" name="s" />                
                <input type="submit" class="search-submit" value="'. esc_attr_x( 'Search', 'submit button' ) .'" />
            </form>
            ' );
            
    $html = $search_form;
    
    if( $this->aSearchResults && count($this->aSearchResults) > 0 ) {
      foreach( $this->aSearchResults AS $post ) {
					//setup_postdata( $post);
          
          // Remove HTML tags and shortcodes
          // But preserve [[shortcodes]]
          $replace_from = array( '[[', ']]' );
          $replace_to = array( 'sample-shortcode-opener-549583490i0heg', 'sample-shortcode-closing-549583490i0heg' );
          
          $process_html = str_replace( $replace_from, $replace_to, $post->post_content );
          $process_html = preg_replace( '~<table[\s\S]*?</table>~', '', $process_html );
          $process_html = strip_shortcodes( strip_tags( $process_html ) );
          $process_html = str_replace( $replace_to, $replace_from, $process_html );
          
          $aSentences = array_map( 'trim', preg_split( '~(\.\B|\n)~', $process_html, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE ) );
          
          // Get sentences and phrases matching each keyword
          $iCount = 0;
          $aExcerpt = array();          
          foreach( $aSearchKeywords AS $sKeyword ) {
            foreach($aSentences AS $k => $sSentence ) {
              if( stripos($sSentence,$sKeyword) !== false ) {
                //$aExcerpt[] = str_ireplace( $sKeyword, '<strong>'.$sKeyword.'</strong>', $sSentence );
                
                unset($aSentences[$k]);
                
                // if the next item is a dot, we consider the match a sentence
                if( isset($aSentences[$k+1]) && $aSentences[$k+1] == '.' ) {                  
                  $aExcerpt['sentence-'.$sKeyword] = $sSentence. wp_trim_words( $aSentences[$k+1], 20, '&hellip;' ) .' ';
                  unset($aSentences[$k+1]);
                  break;
                } else {
                  $iCount++;
                  $aExcerpt['phrase-'.$sKeyword.'-'.$iCount] = wp_trim_words( $sSentence, 20, '&hellip;' );
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
            $sExcerpt = apply_filters( 'businesspress_fv_search_seo_description', $sExcerpt, $post->ID );

          } else if( stripos($sExcerpt,'&hellip;') === false && $sExcerpt[strlen($sExcerpt)-1] != '.' ) {
            $sExcerpt .= '&hellip;';
          }
          
          foreach( $aSearchKeywords AS $sKeyword ) {
            $sKeyword = preg_quote($sKeyword,'/');
            if( stripos($sTitle,'<strong') === false ) $sTitle = preg_replace("/".$sKeyword."/i", "<strong>\$0</strong>", $sTitle );
            $sLink = preg_replace( '~https?://~', '', preg_replace("/".$sKeyword."/i", "<strong>\$0</strong>", $sLink ) );
            $sExcerpt = preg_replace("/".$sKeyword."/i", "<strong>\$0</strong>", $sExcerpt );
          }
          
          if( get_the_time('U') + 7*24*3600 > current_time('timestamp') ) {
            $sDate = human_time_diff( get_the_time('U'), current_time('timestamp') ).' ago';
          } else {
            $sDate = get_the_date('M j, Y');
          }
          
          $sImage = str_replace( '<img', '<img style="width: 100px"', get_the_post_thumbnail( get_the_id(), 'thumbnail', array( 'class' => 'alignleft' ) ) );
          if( !apply_filters( 'businesspress_fv_search_show_image', false ) ) {
            $sImage = '';
          }
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
      
      if( $this->iSearch_max_num_pages > 1 ) {
        $html .= "<!--businesspress fv search paging-->";
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
  
  
  function posts_per_page( $query ) {
    if( $query->is_main_query() && !empty($query->query_vars['s']) ) {
      $query->set( 'posts_per_page', 100 );
    }
  }  
  
}

$FV_Search = new FV_Search;

endif;
