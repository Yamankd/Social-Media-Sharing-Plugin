<?php
/**
 * Plugin Name: Auto Share Post on Social Media
 * Description: Automatically shares WordPress posts on Social Media when a post is published or updated.
 * Version: 1.4
 * Author: Aasakya Digital
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/admin-menu.php';
require_once plugin_dir_path(__FILE__) . 'includes/credentials.php';
require_once plugin_dir_path(__FILE__) . 'includes/linkedin-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/linkedin-share.php';

// Hooks for actions
add_action('admin_menu', 'social_share_menu');
add_action('publish_post', 'auto_share_post_on_linkedin', 10, 2);
add_action('admin_init', 'linkedin_oauth_callback');
