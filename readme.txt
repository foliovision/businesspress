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

WordPress has a major release three times per year, each time with a risk of breaking the way your website works. This plugin removes most of the messages to update (button from dashboard, the admin footer link and admin notice), while adding more information about versions to the updates page. You will see which version you are running and which versions which are available to you and given a choice about your updates.

WordPress also automatically updates your website to the latest minor release. We like that, but these updates can cause issues as well. That's why BusinessPress delays the updates for 5 days.

This plugin also allows you to prevent your client from installing new plugins (many of which could either cripple or slow their website) while allowing clients to safely take care of existing plugin updates themselves.

###You can disable the following capabilities

* Activate and deactivate plugins and themes
* Update plugins and themes
* Install, Edit and delete plugns and themes

###Coming soon

* Checkboxes to disable all the new WordPress features one by one. A lot of them are not needed and only make the site more fragile or slower.
* Protecting your site privacy from WordPress.org API services
* Switch help and news links to your own support and news channels.

[Support](http://foliovision.com/support/)

== Installation ==

Once the plugin is installed and activated, it checks the current user email domain address and locks down the plugin and theme install/edit permissions to users which have email address matching this domain. That gives your whole company a chance to keep maintaining the site while leaving other users out of it.

You will also see a notice about this which you need to dismiss and then it won't appear again.

To change the domain you can go to Settings -> BusinessPress. There you will be allowed to edit which permissions are locked down and also edit the domain or lock down to your own email address only.

If you turn of the plugin the domain restriction is forgotten until somebody else activates it again. The users who don't match the domain can't turn it off obviously.

== Changelog ==

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

1. the plugin settings screen
