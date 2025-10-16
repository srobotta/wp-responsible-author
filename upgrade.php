<?php
/**
 * Upgrade script for Responsible Author Plugin
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

function responsible_author_upgrade($installed_version) {
    global $wpdb;

    if (version_compare($installed_version, '1.1', '<')) {
        // Make meta for ratings invisible in admin menu when editing a post.
        $meta_key = ResponsibleAuthor::POST_META_KEY;
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . $wpdb->postmeta . ' SET `meta_key` = %s WHERE meta_key = %s',
            $meta_key,
            substr($meta_key, 1)
        ));
    }

    // Update the stored plugin version
    update_option(ResponsibleAuthor::OPTION_PLUGIN_VERSION, ResponsibleAuthor::VERSION);
}