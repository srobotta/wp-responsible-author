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

require_once __DIR__ . DIRECTORY_SEPARATOR . 'responsible-author.php';

// Remove options introduced by the plugin.
delete_option(Responsible_Author::OPTION_POST_TYPES);
delete_option(Responsible_Author::OPTION_MORE_THAN_ONE);

// Remove any transients and similar which the plugin may have left behind.
$metakey= Responsible_Author::POST_META_KEY;
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE `meta_key` = '{$metakey}'");

// Remove this directory.
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