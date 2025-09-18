<?php
/**
 * Perform plugin installation routines.
 *
 * @package Responible Author
 */

global $wpdb;

// Make sure the uninstall file can't be accessed directly.
if (! defined( 'WP_UNINSTALL_PLUGIN' )) {
	die;
}

// Remove options introduced by the plugin.
delete_option('responsible_author_post_types');

// Remove any transients and similar which the plugin may have left behind.
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE `meta_key` = 'responsible_author'");

// Remove this directory.
echo (__DIR__);

function responsible_author_rrmdir($dir) {
   if (is_dir($dir)) {
       $objects = scandir($dir);
       foreach ($objects as $object) {
           if ($object != "." && $object != "..") {
              if (is_dir($dir. DIRECTORY_SEPARATOR . $object) && !is_link($dir. DIRECTORY_SEPARATOR .$object)) {
                  responsible_author_rrmdir($dir. DIRECTORY_SEPARATOR . $object);
              } else {
                  wp_delete_file($dir. DIRECTORY_SEPARATOR . $object);
              }
           }
       }
       rmdir($dir);
   }
}

responsible_author_rrmdir(__DIR__);