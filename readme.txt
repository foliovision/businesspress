=== BusinessPress ===

Contributors: FolioVision
Tags: core updates,editing,installing,plugins,permissions
Requires at least: 4.0
Tested up to: 4.8
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

BusinessPress lets you control the WordPress core updates and plugin installing/editing/upgrading to prevent issues with your business websites.

== Description ==

Here are the plugin features:

**WordPress Updates**

WordPress has a major release three times per year, each time with a risk of breaking the way your website works. This plugin removes most of the messages to update (button from dashboard, the admin footer link and admin notice), while adding more information about versions to the updates page. You will see which version you are running and which versions which are available to you and given a choice about your updates.

WordPress also automatically updates your website to the latest minor release. We like that, but these updates can cause issues as well. That's why BusinessPress delays the updates for 5 days.

This plugin also allows you to prevent your client from installing new plugins (many of which could either cripple or slow their website) while allowing clients to safely take care of existing plugin updates themselves.

###You can disable the following capabilities for other admin users

* Activate and deactivate plugins and themes
* Update plugins and themes
* Update WordPress core
* Install, Edit and delete plugns and themes

**Unnecessary WordPress features**

Plugin by default disables the generator tag, REST API and Emojis to keep your site secure and fast. However you can enable these features back on if you prefer, just like the XML-RPC or oEmbed.

**Login Logo**

You can set your login logo and wp-admin color.

**Admin Notices**

Plugin by default moves the admin notices into Dashboard -> Notices screen where you can dismiss each notice. More and more plugins show their notices (some more important, some less) so BusinessPress cleans that up to keep you focused.

**Login Protection**

Plugin also supports fail2ban, see install instructions.

**Credits**

This plugin integrates some of the amazing WordPress plugins which keep it lean and removed the unnecessary features:

Disable Embeds by Pascal Birchler with our own improvements
Disable Emojis by Ryan Hellyer
Disable REST API by Dave McHale
Login Logo by Mark Jaquith with our own improvements

[Support](http://foliovision.com/support/)

== Installation ==

### Basic install

If you are using WordPress Multisite, make sure you Network Activate the plugin.

Plugin will disabled your admin notices, generator tag, REST API and Emojis out of the box - see plugin description.

To lock down your site permissions simply click the notice or go to Settings -> BusinessPress. There you will be allowed to lock down the admin privileges to your own email address or your email domain (in case you want to keep admin access for your entire company).

From that point forward, you will have to elevated admin privileges over the website.

### Fail2ban

To protect against bruteforce hacking of WordPress login form and XML-RPC:

1. Install fail2ban, you will need root access to your server running on Linux
2. Setup fail2ban filter for BusinessPress, just copy plugin file fail2ban/wordpress.conf into /etc/fail2ban/filter.d/wordpress.conf
3. Setup fail2ban jail for BusinessPress, just copy plugin file fail2ban/jail.local into /etc/fail2ban/jail.local

Note that if you are on cPanel you might need to adjust the logpath variable to /var/log/messages

4. Restart fail2ban daemon
5. Do some bad login attempts and you should be able to see entries being added at the end of /var/log/auth.log

Note that if you are on cPanel you might need to check the log at /var/log/messages

6. You can use a command like this to check ban status: fail2ban-client status wordpress
7. For troubleshooting try this command to check if your filter works: fail2ban-regex /var/log/auth.log /etc/fail2ban/filter.d/wordpress.conf
8. To remove a ban use fail2ban-client set wordpress unbanip IPADDRESSHERE

== Changelog ==

= 0.7.3 - 2017/06/08 =

* Feature - "Hide Admin Notices" - groups notices to "New" and "Viewed"
* Feature - hearthbeat update frequency increased to 60 seconds
* Login logo - using domain mapped domain when WordPres MU Domain Mapping is present
* Bugfix - "Hide Admin Notices" - Notices screen missing in WP Multisite
* Bugfix - exlcluding jpeg images from fail2ban logs, making extensions check case-insensitive
* Bugfix - settings saving

= 0.7.2 - 2017/04/05 =

* Bugfix - "Hide WP Admin Bar for subscribers" was breaking WP Ajax for logged in logged in subscribers

= 0.7.1 - 2017/03/27 =

* Bugfix - "Hide Admin Notices" breaking WP admin menu with Newsletter plugin
* Bugfix - making sure the admin restrictions remain enabled after upgrading

= 0.7 - 2017/03/24 =

* New settings screen with tabs!
* New function - Disable XML-RPC
* New function - Disable REST  API
* New function - Disable Emojis
* New function - Disable oEmbed
* New function - Impose Admin Color Scheme to all users
* New function - Enable Google style results - gives you similar layout and keyword highlight
* New function - Login logo (with image upload)
* New function - Hide Admin Notices option moves all the notices to a new screen!
* Improving the notice for password reset link to also say "Please check your Junk or Spam folder if the email doesn't seem to arrive in 10 minutes."
* Setting "Hide WP Admin Bar for subscribers" now also removes all screens except for Profile
* WP logo in Admin Bar is now removed

= 0.6.6 - 2017/01/17 =

* Added setting to Allow other admins to -> Export site content
* Disabling "WordPress x.y.z is available. Please update!" emails
* Disabling "Your site has updated to WordPress x.y.z" emails

= 0.6.5 - 2017/01/03 =

* Fail2ban - added support for MaxCDN - matching IPs are treated as proxy servers to detect the real user IP reliably

= 0.6.4 - 2016/11/08 =

* Bugfix - users able to deactivate the plugin in some cases
* DoS protection - 404 requests are now reported to fail2ban. Make sure you update your fail2ban filter a jail settings. Our settings are 12 retries (login or 404) in 20 minutes

= 0.6.3 - 2016/07/18 =

* Fix for WordPress Multisite

= 0.6.2 -2016/06/16 =

* Fail2ban - moving checks for bad XML-RPC pingbacks to a different keyword for a different filter

= 0.6 - 2016/06/07 =

* Fail2ban support added, check install instructions

= 0.5.3 - 2016/05/06 =

* Added new permission to control - "Update WordPress core" - as not every admin should be able to do that
* Allowing access to Dashboard -> Updates for everybody, but if the upgrade permissions in BusinesPress settings are not allowed, users will only see "Please contact {email for which the permissions are whitelised} to upgrade WordPress, plugins or themes." or "Please contact your site admin or your partners at {domain for which the permissions are whitelised} to upgrade WordPress, plugins or themes." note and a list of updates available.
* Core Updates control - If you are not using latest WordPress, plugin gives you a chance to upgrade to the latest WordPress version in your current branch. That means you can safely upgrade 4.1.7 to 4.1.10 without having to go to 4.5.1 directly
* Fixed compatibility with WordPress 3.7

= 0.5.2 - 2016/04/27 =

* Changing Dashboard -> Updates screen - added extra security to the upgrade button - warning user about the risks of upgrading WordPress in a more comprehensive way

= 0.5.1 - 2016/04/12 =

* Fix for the "BusinessPress must be configured before it becomes operational." link when network activated

= 0.5 =

* Reworking the activation.

= 0.4.9 =

* First public release

== Frequently Asked Questions ==

= A question that someone might have =

An answer to that question.

== Screenshots ==

1. The plugin welcome screen - if user privileges are restricted, there is a contact form to contact the site admin

2. The WordPress Updates settings tab

3. Various things you can tweak

4. Branding options

5. The improved Updates screen
