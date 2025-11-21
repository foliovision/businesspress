<?php

class BusinessPress_WAF {

	public function __construct( $businesspress ) {

		// If a phrase starts with / it means it only triggers if it's the start of the request URL or requested from a folder, but it will work if used in query string without /
		// This way you can search for .ssh or phpmyadmin in articles using site.com/?s=phpmyadmin and not get banned
		// TODO: What if I search for phpmyadmin in bbPress ? site.com/support/search/phpmyadmin
		$rules = array(
			'/.env',
			'/.github/COMMIT_EDITMSG',
			'/.github/config',
			'/.github/description',
			'/.github/HEAD',
			'/.github/index',
			'/.github/workflows',
			'/.ssh',
			'/boot.ini',
			'/data/admin/allowurl.txt',
			'etc/passwd',
			'/ftpsync.settings',
			' onerror=',
			' onload=',
			'/phpMyAdmin/server_import.php',
			'/phpmyadmin/scripts/setup.php',
			'ueditor/net/controller.ashx',
			'/win.ini',
			'/wp-config.php',
			's=/admin/index/dologin',
		);

		$match = false;

		foreach( $rules AS $rule ) {
			if(
				stripos( $_SERVER['REQUEST_URI'], $rule ) !== false ||
				stripos( $_SERVER['REQUEST_URI'], urlencode($rule) ) !== false ||
				stripos( $_SERVER['REQUEST_URI'], urlencode( urlencode($rule) ) ) !== false ||
				stripos( $_SERVER['REQUEST_URI'], urlencode( urlencode( urlencode($rule) ) ) ) !== false
			) {
				$match = $rule;
			}
		}

		// Requests like:
		// /?s=/index/%5Cthink%5Capp/invokefunction&function=call_user_func_array&vars[0]=file_put_contents&vars[1][]=xml1.php&vars[1][]=%3C?php%20@eval(_POST[terry]);print(md5(123));?%3E
		if( !empty($_GET['function']) && $_GET['function'] == 'call_user_func_array' ) {
			if( !empty($_GET['vars']) ) {
				$vars = json_encode($_GET['vars']);
				foreach( array(
					'eval',
					'file_put_contents',
				) AS $keyword ) {
					if( stripos( $vars, $keyword ) !== false ) {
						$match = 'call_user_func_array with '.$keyword;
					}
				}
			}
		}

		if( $match ) {
			$businesspress->fail2ban_openlog();
			syslog( LOG_INFO,'BusinessPress WAF - '.$match.' request - '.$_SERVER['REQUEST_URI'].' from '.$businesspress->get_remote_addr() );
			exit;
		}
	}

}
