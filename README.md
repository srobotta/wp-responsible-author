# Wordpress Plugin Repsonsible Author

This plugin adds a custom field to the post objects named `responsible_author`. The
value is filled with the user id.

## Installation

1. Create a new diretory `responsible-author` in your Wordpress plugins directory.
1. Copy the contents of thius repository inside the new directory.
1. Login to your Wordpress admin panel and go to Plugins. The new plugin should be listed there.
1. Click on "Activate" to enable the plugin.
1. Go to the plugin settings and add the post types where you wish that this field appears and save the setting.

## Usage

Whenever you edit a post that is of the type where the meta field  should apperar, you
see in the right column a box "Responsible Author" where you can select one user. Then save
the post to update the meta data field.
