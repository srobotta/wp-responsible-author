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

// Load translations
function responsible_author_load_textdomain() {
    load_plugin_textdomain(
        'responsible-author', // text domain, must match the one used in __() calls
        false,                // deprecated, always false
        dirname(plugin_basename(__FILE__)) . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR
     );
}
add_action('plugins_loaded', 'responsible_author_load_textdomain');

// Add settings page
function responsible_author_add_settings_page() {
    add_options_page(
        __('Responsible Author Settings', 'responsible-author'),
        __('Responsible Author', 'responsible-author'),
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
        echo '<div class="updated"><p>' . __('Settings saved') . '</p></div>';
    }
    $types = get_option('responsible_author_post_types', []);
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Responsible Author Settings', 'responsible-author'); ?></h1>
        <form method="post">
            <?php wp_nonce_field('responsible_author_settings_save'); ?>
            <p><?php esc_html_e('Enter a comma-separated list of post types where the Responsible Author dropdown should appear:', 'responsible-author'); ?></p>
            <input type="text" name="responsible_author_post_types" value="<?php echo esc_attr(implode(',', $types)); ?>" style="width:400px;" />
            <p><input type="submit" class="button-primary" value="<?php echo esc_attr(__('Save Changes')); ?>" /></p>
            <p><?php esc_html_e('Possible post types are:', 'responsible-author'); ?></p>
            <p style="font-family: monospace;"><?php echo esc_html(implode(', ', get_post_types())) ?></p>
        </form>
    </div>
    <?php
}

// Add settings link on Plugins page
function responsible_author_settings_link($links) {
    $url = admin_url('options-general.php?page=responsible-author-settings');
    $settings_link = '<a href="' . esc_url($url) . '">' . __('Settings') . '</a>';
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

// Add custom section to user profile page
function responsible_posts_profile_section( $user ) {
    // In which post types can we expect the field?
    $types = get_option('responsible_author_post_types', []);
    if (empty($types)) {
        return;
    }
    // Query posts where the user is set as responsible_author
    $args = array(
        'post_type'      => $types,
        'posts_per_page' => -1,
        'meta_key'       => 'responsible_author',
        'meta_value'     => $user->ID,
        'post_status'    => 'any',
    );

    $responsible_posts = get_posts( $args );
    ?>
    <h2><?php esc_html_e('Posts where you are set Responsible Author', 'responsible-author'); ?></h2>
    <table class="form-table">
        <tr>
            <th><label><?php esc_html_e('Post'); ?></label></th>
            <th><label><?php esc_html_e('Status'); ?></label></th>
        </tr>
        <?php if (!empty($responsible_posts)): ?>
            <?php foreach ( $responsible_posts as $post ):
                $status_object = get_post_status_object(get_post_status($post->ID));
                ?>
                <tr>
                    <td>
                        <a href="<?php echo get_edit_post_link($post->ID); ?>">
                            <?php echo esc_html( get_the_title($post)); ?>
                        </a>
                    </td>
                    <td><?php echo esc_html($status_object->label); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="2"><?php esc_html_e('No posts assigned to you.', 'responsible-author'); ?></td></tr>
        <?php endif; ?>
    </table>
    <?php
}
add_action( 'show_user_profile', 'responsible_posts_profile_section' );
add_action( 'edit_user_profile', 'responsible_posts_profile_section' );
