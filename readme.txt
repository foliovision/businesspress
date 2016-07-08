=== BusinessPress ===

Contributors: FolioVision
Tags: core updates,editing,installing,plugins,permissions
Requires at least: 4.0
Tested up to: 4.5
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

BusinessPress lets you control the WordPress core updates and plugin installing/editing/upgrading to prevent issues with your business websites.

== Description ==

WordPress has a major release three times per year, each time with a risk of breaking the way your website works. This plugin removes most of the messages to update (button from dashboard, the admin footer link and admin notice), while adding more information about versions to the updates page. You will see which version you are running and which versions which are available to you and given a choice about your updates.

WordPress also automatically updates your website to the latest minor release. We like that, but these updates can cause issues as well. That's why BusinessPress delays the updates for 5 days.

This plugin also allows you to prevent your client from installing new plugins (many of which could either cripple or slow their website) while allowing clients to safely take care of existing plugin updates themselves.

###You can disable the following capabilities for other admin users

* Activate and deactivate plugins and themes
* Update plugins and themes
* Update WordPress core
* Install, Edit and delete plugns and themes

Plugin also supports fail2ban, see install instructions.

Plugin also removes annoying plugin notices from WP Admin Dashboard. See changelog for what's supported.

###Coming soon

* Checkboxes to disable all the new WordPress features one by one. A lot of them are not needed and only make the site more fragile or slower.
* Protecting your site privacy from WordPress.org API services
* Switch help and news links to your own support and news channels.

[Support](http://foliovision.com/support/)

== Installation ==

Once the plugin is installed, you will be prompted to configure it, otherwise it won't do anything.

Simply click the notice or go to Settings -> BusinessPress. There you will be allowed to lock down the admin privileges to your own email address or your email domain (in case you want to keep admin access for your entire company).

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

= 0.6.3 =

* Removing annoying plugin notices - WooThemes Updater (WooCommerce - activation/renewal notices), Gravity Forms (new update notice)

= 0.6.2 =

* Fail2ban - moving checks for bad XML-RPC pingbacks to a different keyword for a different filter

= 0.6 =

* Fail2ban support added, check install instructions

= 0.5.3 =

* Added new permission to control - "Update WordPress core" - as not every admin should be able to do that
* Allowing access to Dashboard -> Updates for everybody, but if the upgrade permissions in BusinesPress settings are not allowed, users will only see "Please contact {email for which the permissions are whitelised} to upgrade WordPress, plugins or themes." or "Please contact your site admin or your partners at {domain for which the permissions are whitelised} to upgrade WordPress, plugins or themes." note and a list of updates available.
* Core Updates control - If you are not using latest WordPress, plugin gives you a chance to upgrade to the latest WordPress version in your current branch. That means you can safely upgrade 4.1.7 to 4.1.10 without having to go to 4.5.1 directly
* Fixed compatibility with WordPress 3.7

= 0.5.2 =

* Changing Dashboard -> Updates screen - added extra security to the upgrade button - warning user about the risks of upgrading WordPress in a more comprehensive way

= 0.5.1 =

* Fix for the "BusinessPress must be configured before it becomes operational." link when network activated

= 0.5 =

* Reworking the activation.

= 0.4.9 =

* First public release

== Frequently Asked Questions ==

= A question that someone might have =

An answer to that question.

== Screenshots ==

1. The plugin settings screen

2. The improved Updates screen
