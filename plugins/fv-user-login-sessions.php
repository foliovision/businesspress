<?php

class FV_User_Login_Sessions {

  function __construct() {
    // Show on wp-admin user profile screen
    add_action( 'show_user_profile', array( $this, 'user_session_tokens' ) );
    add_action( 'edit_user_profile', array( $this, 'user_session_tokens' ) );
  }

  
  function user_session_tokens( $user ) {
    if ( !current_user_can( 'edit_users', $user->ID ) ) {
      return;
    }

    ?>
    <h3 id="login-sessions"><?php esc_html_e( 'Login Sessions', 'businesspress' ); ?></h3>
    <?php

    $session_tokens = get_user_meta( $user->ID, 'session_tokens', true );

    if ( is_array( $session_tokens ) ) {
      $active_tokens = array_filter( $session_tokens, function( $item ) {
        return $item['expiration'] > time();
      } );

      $expired_tokens = array_filter( $session_tokens, function( $item ) {
        return $item['expiration'] <= time();
      } );
    }

    $limit = 10;

    if ( $session_tokens ) :
      $visible_edge_count = max( 1, (int) floor( $limit / 2 ) );
      $total_sessions = count( $active_tokens ) + count( $expired_tokens );
      $hidden_sessions_count = max( 0, $total_sessions - ( 2 * $visible_edge_count ) );
      ?>
      <style>.fv-user-login-sessions-too-many { display: none; }</style>
      <p>
        <span class="description">
          <?php printf( __( 'Found %d active and %d expired login sessions.', 'genesis' ), count( $active_tokens ), count( $expired_tokens ) ); ?>
        </span>
      </p>

      <table class="widefat">
        <thead>
          <tr>
            <td>Login Time</td>
            <td>Expiration Time</td>
            <td>IP Address</td>
            <td>User Agent</td>
          </tr>
        </thead>
        <?php
        $count = 0;
        $gap_row_rendered = false;

        foreach( array(
          $active_tokens,
          $expired_tokens
        ) as $tokens ) : ?>
          <?php foreach ( $tokens as $k => $v ) :
            $count++;
            $is_middle_row = $total_sessions > $limit && $count > $visible_edge_count && $count <= ( $total_sessions - $visible_edge_count );

            if ( $is_middle_row && ! $gap_row_rendered ) {
              $gap_row_rendered = true;
              ?>
              <tr class="fv-user-login-sessions-gap">
                <td colspan="4" style="text-align: center;">
                  <?php
                  printf(
                    /* translators: %d: number of hidden login sessions */
                    wp_kses_post( sprintf( __( '%d middle sessions are hidden. Click to <a href="#" data-fv-user-login-sessions-more>show all sessions</a>.', 'businesspress' ), (int) $hidden_sessions_count ) ),
                    (int) $hidden_sessions_count
                  );
                  ?>
                </td>
              </tr>
              <?php
            }
            ?>
            <tr<?php if ( $is_middle_row ) { echo " class='fv-user-login-sessions-too-many'"; } ?>>
              <td><abbr title="<?php echo date( 'r', $v['login'] ); ?>"><?php echo date( 'Y-m-d', $v['login'] ); ?></abbr></td>
              <td style="background: <?php echo $v['expiration'] > time() ? '#bfb' : '#fbb'; ?>"><abbr title="<?php echo date( 'r', $v['expiration'] ); ?>"><?php echo date( 'Y-m-d', $v['expiration'] ); ?></abbr></td>
              <td><?php echo $v['ip']; ?></td>
              <td><?php echo $v['ua']; ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </table>
      <script>jQuery('[data-fv-user-login-sessions-more]').click(function(e) {
        e.preventDefault();
        jQuery('.fv-user-login-sessions-too-many').toggle();
        jQuery('.fv-user-login-sessions-gap').toggle();
      });</script>

    <?php else : ?>
      <p><span class="description"><?php esc_html_e( 'No login sessions found.', 'genesis' ); ?></span></p>
    <?php endif;

  }
  
}

new FV_User_Login_Sessions();