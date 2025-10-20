<?php
/*
Plugin Name: Responsible Author
Plugin URI:  https://github.com/srobotta/wp-responsible-author
Description: Adds a dropdown when a post is edited to select one or more responsible users for that post/article. Can be used on certain post types only.
Version:     1.1
Author:      Stephan Robotta <stephan.robotta@bfh.ch>
Text Domain: responsible-author
License:     GPLv3
Package:     Responible Author
Keywords:    responsible author, author, editor, post, article, post type
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class ResponsibleAuthor {

    private static $post_types_installed = null;

    public const VERSION = '1.1';

    public const SLUG = 'responsible-author';

    public const OPTION_PLUGIN_VERSION = 'responsible_author_plugin_version';
    public const OPTION_POST_TYPES = 'responsible_author_post_types';
    public const OPTION_MORE_THAN_ONE = 'responsible_author_more_than_one';

    public const POST_META_KEY = '_responsible_author';

    public function init() {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'settings_link']);
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('save_post', [$this, 'save_metabox']);
        add_action('show_user_profile', [$this, 'profile_section']);
        add_action('edit_user_profile', [$this, 'profile_section']);
        add_action('admin_init', [$this, 'maybe_upgrade']);
    }
    
    // Load translations
    public function load_textdomain() {
        load_plugin_textdomain(
            self::SLUG, // text domain, must match the one used in __() calls
            false,      // deprecated, always false
            dirname(plugin_basename(__FILE__)) . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR
        );
    }

    // Add settings page
    public function add_settings_page() {
        add_options_page(
            __('Responsible Author Settings', self::SLUG),
            __('Responsible Author', self::SLUG),
            'manage_options',
            'responsible-author-settings',
            [$this, 'render_settings_page']
        );
    }

    // Render settings page
    public function render_settings_page() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('responsible_author_settings_save')) {
            $types = isset($_POST[self::OPTION_POST_TYPES]) && is_array($_POST[self::OPTION_POST_TYPES])
                ? array_keys(array_filter($_POST[self::OPTION_POST_TYPES], function($v) { return $v === '1'; }))
                : [];
            $more_than_one = isset($_POST[self::OPTION_MORE_THAN_ONE]) && $_POST[self::OPTION_MORE_THAN_ONE] === '1' ? 1 : 0;
            update_option(self::OPTION_POST_TYPES, $types);
            update_option(self::OPTION_MORE_THAN_ONE, $more_than_one);
            echo '<div class="updated"><p>' . __('Settings saved.') . '</p></div>';
        } else {
            $types = get_option(self::OPTION_POST_TYPES, []);
            $more_than_one = (int)get_option(self::OPTION_MORE_THAN_ONE, 0);
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Responsible Author Settings', self::SLUG); ?></h1>
            <form method="post">
                <?php wp_nonce_field('responsible_author_settings_save'); ?>
                <p>
                    <?php esc_html_e('More than one person can be responsible author', self::SLUG); ?>
                    <select name="responsible_author_more_than_one">
                        <option value="0" <?php selected(0, $more_than_one); ?>><?php esc_html_e('No'); ?></option>
                        <option value="1" <?php selected(1, $more_than_one); ?>><?php esc_html_e('Yes'); ?></option>
                    </select>
                </p>
                <p><?php esc_html_e('Check all post types where the responsible author(s) can be set:', self::SLUG); ?></p>
                <?php foreach ($this->get_installed_posttypes() as $key => $label) {
                    $id = 'ra_pt_' . $key;
                    $checked = checked(in_array($key, $types), true, false);
                    printf(
                        '<div><input type="checkbox" id="%1$s" name="%2$s[%3$s]" value="1"%4$s /><label for="%1$s">%5$s</label></div>',
                        $id, self::OPTION_POST_TYPES, esc_attr($key), $checked, esc_html($label)
                    );
                } ?>
                <p><input type="submit" class="button-primary" value="<?php echo esc_attr(__('Save Changes')); ?>" /></p>
            </form>
        </div>
        <?php
    }

    /**
     * Get all installed post types with their labels.
     * @return array An associative array where keys are post type names and values are their labels
     */
    public function get_installed_posttypes(): array {
        if (self::$post_types_installed === null) {
            self::$post_types_installed = [];
            foreach (get_post_types(['public' => true]) as $post_type) {
                $obj = get_post_type_object($post_type);
                self::$post_types_installed[$post_type] = $obj && isset($obj->labels->name) ? $obj->labels->name : $post_type;
            }
        }
        return self::$post_types_installed;
    }

    // Add settings link on Plugins page
    function settings_link($links) {
        $url = admin_url('options-general.php?page=responsible-author-settings');
        $settings_link = '<a href="' . esc_url($url) . '">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    // Add the meta box
    public function add_metabox() {
        global $post;
        $types = get_option(self::OPTION_POST_TYPES, []);

        if ($post && in_array($post->post_type, $types)) {
            add_meta_box(
                'responsible_author_metabox',
                'Responsible Author',
                [$this, 'metabox_callback'],
                $post->post_type,
                'side',
                'default'
            );
        }
    }

    // Meta box content
    public function metabox_callback($post) {
        // Retrieve current value
        $selected_users = explode(',', get_post_meta($post->ID, self::POST_META_KEY, true));

        // Get all users
        $users = get_users(['fields' => ['ID', 'display_name']]);
        $more_than_one = get_option(self::OPTION_MORE_THAN_ONE, 0)
            ? '[]" multiple="multiple"' : '';

        echo '<select name="' . self::POST_META_KEY . $more_than_one . '" id="responsible_author" style="width:100%">';
        if (!$more_than_one) {
            echo '<option value="">-- ' . esc_html('Select User', self::SLUG) . ' --</option>';
        }
        foreach ($users as $user) {
            $selected = in_array($user->ID, $selected_users) ? ' selected="selected"' : '';
            echo '<option value="' . esc_attr($user->ID) . '"' . $selected . '>' . esc_html($user->display_name) . '</option>';
        }
        echo '</select>';

        // Add nonce for security
        wp_nonce_field('responsible_author_nonce_action', 'responsible_author_nonce');
    }

    // Save the selected user
    public function save_metabox($post_id) {
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
        if (isset($_POST[self::POST_META_KEY]) && !empty($_POST[self::POST_META_KEY])) {
            $value = is_array($_POST[self::POST_META_KEY])
                ? implode(',', array_map('intval', $_POST[self::POST_META_KEY]))
                : sanitize_text_field($_POST[self::POST_META_KEY]);
            $value = ',' . $value . ',';
            update_post_meta($post_id, self::POST_META_KEY, $value);
        } else {
            delete_post_meta($post_id, self::POST_META_KEY);
        }
    }

    // Add custom section to user profile page
    public function profile_section($user) {
        // In which post types can we expect the field?
        $types = get_option(self::OPTION_POST_TYPES, []);
        if (empty($types)) {
            return;
        }
        $more_than_one = (int)get_option(self::OPTION_MORE_THAN_ONE, 0);
        // Query posts where the user is set as responsible_author
        $args = [
            'post_type'      => $types,
            'posts_per_page' => -1,
            'post_status'    => 'any',
        ];
        if ($more_than_one) {
            $args['meta_query'] = [
                [
                    'key'   => self::POST_META_KEY,
                    'value' => ',' . $user->ID . ',',
                    'compare' => 'LIKE',
                ],
            ];
        } else {
            $args['meta_key'] = self::POST_META_KEY;
            $args['meta_value'] = ',' . $user->ID . ',';
        }

        $responsible_posts = get_posts($args);
        ?>
        <h2><?php esc_html_e('Posts where you are set Responsible Author', self::SLUG); ?></h2>
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
                <tr><td colspan="2"><?php esc_html_e('No posts assigned to you.', self::SLUG); ?></td></tr>
            <?php endif; ?>
        </table>
        <?php
    }

    /**
     * Maybe perform an upgrade if the installed version is older than the current version.
     */
    public function maybe_upgrade() {
        $installed_version = get_option(self::OPTION_PLUGIN_VERSION, '0.0.0');
        if (version_compare($installed_version, self::VERSION, '<')) {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'upgrade.php';
            responsible_author_upgrade($installed_version);
        }
    }
}

(new ResponsibleAuthor())->init();