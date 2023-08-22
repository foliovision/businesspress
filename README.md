BusinessPress lets you control the WordPress core updates and plugin installing/editing/upgrading to prevent issues with your business websites.

## Testing

* Install the plugin

  1. Go to Settings -> BusinessPress

  2. Enable restrictions for your email address or your email domain

  3. Second admin user should not be able to update plugins, themes or WordPress core

* Go to Settings -> BusinessPress -> Branding and set theme for user profile to impose admin color theme

  1. All users should have the same admin color theme

* Go to Settings -> BusinessPress -> Preferences and enable "Clickjacking Protection"

  1. The .htaccess file should be modified

* Go to Settings -> BusinessPress -> Preferences and enable "Set Featured Images Automatically"

  1. Create a new post and add an image to the post

  2. The image should be set as featured image

* Go to Settings -> BusinessPress -> Preferences and enable "Hide Admin Notices"

  1. All admin notices should be moved to Dashboard -> Notices

* Go to Settings -> BusinessPress -> Preferences and enable "Require Email Address for Login"

  1. The login form should now require email address instead of username

  2. If there are more than 20 login attempts, the user wont be able to login and will receive email with instructions how to unlock the account