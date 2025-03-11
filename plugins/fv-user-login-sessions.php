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

    if ( $session_tokens ) : ?>
      <p>
        <span class="description">
          <?php printf( __( 'Found %d active and %d expired login sessions.', 'genesis' ), count( $active_tokens ), count( $expired_tokens ) ); ?>
        </span>
        <?php if ( count( $active_tokens ) + count( $expired_tokens ) > $limit ) : ?>
          <?php
          echo " Showing only first " . $limit . ", click to <a href='#' data-fv-user-login-sessions-more>show all sessions</a>.";
          ?>

          <style>.fv-user-login-sessions-too-many { display: none; }</style>

          <script>jQuery('[data-fv-user-login-sessions-more]').click(function(e) {
            e.preventDefault();
            jQuery('.fv-user-login-sessions-too-many').toggle();
          });</script>
        <?php endif; ?>
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

        foreach( array(
          $active_tokens,
          $expired_tokens
        ) as $tokens ) : ?>
          <?php foreach ( $tokens as $k => $v ) :
            $count++
            ?>
            <tr<?php if ( $count > $limit ) echo " class='fv-user-login-sessions-too-many'"; ?>>
              <td><abbr title="<?php echo date( 'r', $v['login'] ); ?>"><?php echo date( 'Y-m-d', $v['login'] ); ?></abbr></td>
              <td style="background: <?php echo $v['expiration'] > time() ? '#bfb' : '#fbb'; ?>"><abbr title="<?php echo date( 'r', $v['expiration'] ); ?>"><?php echo date( 'Y-m-d', $v['expiration'] ); ?></abbr></td>
              <td><?php echo $v['ip']; ?></td>
              <td><?php echo $v['ua']; ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </table>

    <?php else : ?>
      <p><span class="description"><?php esc_html_e( 'No login sessions found.', 'genesis' ); ?></span></p>
    <?php endif;

  }
  
}

new FV_User_Login_Sessions();