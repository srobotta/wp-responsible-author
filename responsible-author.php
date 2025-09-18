<?php
/*
Plugin Name: Responsible Author
Plugin URI:  https://github.com/srobotta/wp-responsible-author
Description: Adds a dropdown in post editor to select a responsible user and saves it in the 'responsible_author' custom field.
Version:     1.0
Author:      Stephan Robotta <stephan.robotta@bfh.ch>
License:     GPLv3
Package:     Responible Author
*/

// Add settings page
function responsible_author_add_settings_page() {
    add_options_page(
        'Responsible Author Settings',
        'Responsible Author',
        'manage_options',
        'responsible-author-settings',
        'responsible_author_render_settings_page'
    );
}
add_action('admin_menu', 'responsible_author_add_settings_page');
    
// Render settings page
function responsible_author_render_settings_page() {
    if (isset($_POST['responsible_author_post_types']) && check_admin_referer('responsible_author_settings_save')) {
        $types = array_filter(array_map('trim', explode(',', $_POST['responsible_author_post_types'])));
        update_option('responsible_author_post_types', $types);
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }
    $types = get_option('responsible_author_post_types', []);
    ?>
    <div class="wrap">
        <h1>Responsible Author Settings</h1>
        <form method="post">
            <?php wp_nonce_field('responsible_author_settings_save'); ?>
            <p>Enter a comma-separated list of post types where the Responsible Author dropdown should appear:</p>
            <input type="text" name="responsible_author_post_types" value="<?php echo esc_attr(implode(',', $types)); ?>" style="width:400px;" />
            <p><input type="submit" class="button-primary" value="Save Settings" /></p>
            <p>Possible post types are:</p>
            <p style="font-family: monospace;"><?php echo esc_html(implode(', ', get_post_types())) ?></p>
        </form>
    </div>
    <?php
}

// Add settings link on Plugins page
function responsible_author_settings_link($links) {
    $url = admin_url('options-general.php?page=responsible-author-settings');
    $settings_link = '<a href="' . esc_url($url) . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'responsible_author_settings_link');


// Add the meta box
function responsible_author_add_metabox() {
    global $post;
    $types = get_option('responsible_author_post_types', []);

    if ($post && in_array($post->post_type, $types)) {
        add_meta_box(
            'responsible_author_metabox',
            'Responsible Author',
            'responsible_author_metabox_callback',
            $post->post_type,
            'side',
            'default'
        );
    }

}
add_action('add_meta_boxes', 'responsible_author_add_metabox');

// Meta box content
function responsible_author_metabox_callback($post) {
    // Retrieve current value
    $selected_user = get_post_meta($post->ID, 'responsible_author', true);

    // Get all users
    $users = get_users(['fields' => ['ID', 'display_name']]);

    echo '<select name="responsible_author" id="responsible_author" style="width:100%">';
    echo '<option value="">-- Select User --</option>';
    foreach ($users as $user) {
        $selected = selected($selected_user, $user->ID, false);
        echo '<option value="' . esc_attr($user->ID) . '" ' . $selected . '>' . esc_html($user->display_name) . '</option>';
    }
    echo '</select>';

    // Add nonce for security
    wp_nonce_field('responsible_author_nonce_action', 'responsible_author_nonce');
}

// Save the selected user
function responsible_author_save_metabox($post_id) {
    // Verify nonce
    if (!isset($_POST['responsible_author_nonce']) || !wp_verify_nonce($_POST['responsible_author_nonce'], 'responsible_author_nonce_action')) {
        return;
    }

    // Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save or delete meta
    if (isset($_POST['responsible_author']) && !empty($_POST['responsible_author'])) {
        update_post_meta($post_id, 'responsible_author', sanitize_text_field($_POST['responsible_author']));
    } else {
        delete_post_meta($post_id, 'responsible_author');
    }
}
add_action('save_post', 'responsible_author_save_metabox');
