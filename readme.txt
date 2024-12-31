=== BusinessPress ===

Contributors: FolioVision
Tags: core updates,editing,installing,plugins,permissions
Requires at least: 4.0
Tested up to: 6.7
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

**Tweaks**

* Enable Google style results - Gives you similar layout and keyword highlight.
* Enable Link Manager - Legacy feature of WordPress, hidden since version 3.5.
* Enhance wp-admin Dropdowns - Makes long, unwieldy select boxes much more user-friendly, including search functionality.
* Login redirection - After you log in you will be redirected back to the page where you clicked wp-login.php link, unless there was a custom redirect_to parameter. Uses HTTP referer.
* Set Featured Images Automatically - First image in the post becomes the featured image on save.
* WordPress core hotfix - are you getting error like "Unable to create directory ../images/2019/11. Is its parent directory writable by the server?" Since October 2019 WordPress doesn't support ../ in the upload path. So we translate that path to absolute path and check if it's in the site webroot and then let it pass
* Disables the "Password Changed" admin email notifications when a Subscriber level user changes email address
* Alphabetically sorting the wp-admin -> Settings menu, making it much easier to find any settings screen!
* Hide Password Protected Posts - Password protected posts won't show up anywhere unless you have the direct link or your are the admin or editor.

**Credits**

This plugin integrates some of the amazing WordPress plugins which keep it lean and removed the unnecessary features:

Disable Embeds by Pascal Birchler with our own improvements
Disable Emojis by Ryan Hellyer
Disable REST API by Dave McHale
Login Logo by Mark Jaquith with our own improvements
WP Chosen by John James Jacoby with some small performance improvements

[Support](http://foliovision.com/support/)

== Installation ==

### Basic install

If you are using WordPress Multisite, make sure you Network Activate the plugin.

Plugin will disabled your admin notices, generator tag, REST API and Emojis out of the box - see plugin description.

To lock down your site permissions simply click the notice or go to Settings -> BusinessPress. There you will be allowed to lock down the admin privileges to your own email address or your email domain (in case you want to keep admin access for your entire company).

From that point forward, you will have to elevated admin privileges over the website.

### Fail2ban

BusinessPress works with **Fail2Ban** Linux utility to protect against bruteforce hacking of WordPress login form and XML-RPC.

Guides:

* [How to setup login protection](https://foliovision.com/wordpress/plugins/businesspress/login-protection)
* [How to block repeated offenders with BusinessPress](https://foliovision.com/wordpress/plugins/businesspress/repeated-offenders-businesspress)
* [How to block malicious web requests with BusinessPress](https://foliovision.com/wordpress/plugins/businesspress/malicious-requests-businesspress)

== Changelog ==

= 1.1 - 2024-12-31 =

* Tested up to: 6.7
* Require Email Address for Login: Do not show email address requirement on wp-login.php
* WAF - More .git/ folder rules
* Bugfix - Front-end Login Check: Improve browser back button detection
* Bugfix - Require Email Address for Login: Fix for Easy Digital Downloads
* Bugfix - Enhance wp-admin Dropdowns: Avoid max-width for select boxes

= 1.0 - 2024-04-25 =

* Button to purge the Surge plugin HTML page cache
* Dashboard: Hide "PHP Update Recommended, if user is doesn't have the full admin rights
* Dashboard: Remove Welcome box
* Dashboard: Remove WordPress Events and News
* "Hide Admin Notices" - allow User Switching plugin notices
* "Hide Admin Notices" - allow WP Rocket Unused CSS and Used CSS
* "Hide Admin Notices" - block ShortPixel sale offers which would still show
* Hide XYZ Html plugin ads on its settings screen
* New plugin: Users by Date Registered - allows you to sort wp-admin -> Users by registration date
* New setting: "Clickjacking Protection" - adds X-Frame-Options header to prevent clickjacking
* New setting: "Login Lockout" - The tranditional IP banning is not effective against botnets. The only way is to block further login attempts on a per account basis. If a user account gets more than 20 bad login attempts, login is disabled and user get an email notification. Link in that email let user re-enable login for his account.
* New setting: "Hide Password Protected Posts" - Password protected posts won't show up anywhere unless you have the direct link or your are the admin or editor.
* New setting: "Require Email Address for Login" - avoids bots being able to find out about your login name and use that in login attempts
* Search template - "Enable domain name" setting for search results, on by default
* Search template - fix issue when searching for keyword and white space after it
* Search template - WP Rocket Remoe Unused CSS fix
* User login sessions - Show on wp-admin user profile screen
* WAF - detect bad requests and log the issue for fail2ban to take action: https://foliovision.com/wordpress/plugins/businesspress/malicious-requests-businesspress

= 0.9.13 - 2022-02-23 =

* "Hide Admin Notices" - whiteslisting all error notices (matched by 'class="error ')

= 0.9.12 - 2021-11-26 =

* New setting - Settings -> Media -> Maximum size - normally WordPress limits the size of linked images in the posts to 2560 pixels

= 0.9.11 - 2021/08/10 =

* Search template - fix output when using Genesis -> Content Archive -> Display -> Entry excerpts

= 0.9.10 - 2021/07/16 =

* "Plain text editing" is now deprecated, only active where already enabled

= 0.9.9 - 2021/06/24 =

* "Hide Admin Notices" - whitelisting FV Swiftype, RCP, User Switching, WooCommerce and WP Crontrol

= 0.9.8 - 2021/05/11 =

* Extending "Projected security updates" to allow WP 4.9 for another 9 months

= 0.9.7 - 2020/11/30 =

* Fixing admin notices dismissing

= 0.9.6 - 2020/05/05 =

* New post setting - Front-end Login Check
* New post setting - Plain text editing
* Bugfix - Alphabetically sorting the wp-admin -> Settings menu - breaking redirections for logged in subscribers in some cases

= 0.9.5 - 2020/04/01 =

* New feature - Alphabetically sorting the wp-admin -> Settings menu, making it much easier to find any settings screen!
* New feature - Stopping subscriber password reset notification emails

= 0.9.4 - 2019/11/05 =

* WordPress core hotfix - are you getting error like "Unable to create directory ../images/2019/11. Is its parent directory writable by the server?" Since October 2019 WordPress doesn't support ../ in the upload path. So we translate that path to absolute path and check if it's in the site webroot and then let it pass

= 0.9.3 - 2019/08/13 =

* Bugfix - Login redirection - preventing redirection back to the password reset link

= 0.9.2 - 2019/01/04 =

* New updates setting - "Version Control System - Forces WordPress to use auto-updates even on websites which use Git or SVN" and it's on by default

= 0.9.1 - 2018/10/01 =

* Fixing notice dismissing and whitelisting.

= 0.9 - 2018/06/29 =

* Login redirection - After you log in you will be redirected back to the page where you clicked wp-login.php link, unless there was a custom redirect_to parameter. Uses HTTP referer.

= 0.8.9 - 2018/06/11 =

* Fail2ban protection for bad password reset attempts
* Bugfix - Enhance wp-admin Dropdowns - excluding EDD variable prices
* Bugfix - search term highlight issues when using FV Swiftyp

= 0.8.8 - 2018/05/30 =

* Bugfix - number of plugin updates invisible, but red circle appearing in the menu

= 0.8.7 - 2018/05/11 =

* Enhance wp-admin Dropdowns - fixed to ignore select boxes which are initially not visible as it was causing these to disappear (appearing with almost zero width)

= 0.8.6 - 2018/05/07 =

* Enhance Author Dropdown - using WP Chosen instead, as it improves all the select boxes in wp-admin!

= 0.8.5 - 2018/05/03 =

* New feature - Enhance Author Dropdown - Changes the old school HTML dropdown for post author to a modern select box with search functionality. On by default.

= 0.8.4 - 2018/03/05 =

* New setting - Redirect WP Admin for subscribers
* Search hightlight - not using <b> anymore
* Bugfix - login logo not saving
* Bugfix - WordPress version release dates couldn't be parsed

= 0.8.3 - 2017/10/18 =

* New feature, on by default! - Set Featured Images Automatically - First image in the post becomes the featured image on save

= 0.8.2 - 2017/10/09 =

* Hiding number of updates from admin bar
* Adding option to bring back the legacy WordPress Link Manager
* Google style results - fix for results being injected into any sidebar content that uses the_content filter

= 0.8.1 - 2017/07/20 =

* Bugfix - X-Pull key saving

= 0.8 - 2017/07/19 =

* You can now pick the WordPress major branch version to upgrade to

= 0.7.5 - 2017/07/14 =

* Bugfix - exlcluding vtt and apple-app-site-association from fail2ban logs

= 0.7.4 - 2017/06/14 =

* Google style results - greatly improved the search excerpts

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