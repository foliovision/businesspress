=== BusinessPress ===

Contributors: FolioVision
Tags: core updates,editing,installing,plugins,permissions
Requires at least: 4.0
Tested up to: 4.4.2
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

BusinessPress lets you control the WordPress core updates and plugin installing/editing/upgrading to prevent issues with your business websites.

== Description ==

WordPress has a major release twice year, each time with a risk of breaking the way your website works. This plugin removes all the messages to update (button from dashboard, the admin footer link and admin notice).

WordPress also automatically updates your website to the latest minor release. We like that, but these updates can cause issues as well. That's why BusinessPress delays the updates for 5 days.

This plugin also allows you to stop your client from installing new plugins (to break the website) while allowing them to take care of the plugin updates.

###You can disable the following capabilities

* Install plugins/themes
* Delete plugins/themes
* Edit plugins/themes
* Update plugins/themes
* Activate plugins

###Coming soon

* Checkboxes to disable all the new WordPress features one by one. A lot of them are not needed and only make the site more fragile or slower.
* Protecting your site privacy from WordPress.org API services

[Support](http://foliovision.com/support/)

== Installation ==

Once the plugin is installed, only admin users who have email on the same address as the General Settings -> Email Address are allowed to change the settings (allow installing plugins etc.).

If you are maintaining the site for your client and you need to move this permission to you, create the wp-content/businesspress-domain.php file and enter your domain into it:

<?php // my-domain.com

== Changelog ==

= 0.4.9 =

* First public release

== Frequently Asked Questions ==

= A question that someone might have =

An answer to that question.