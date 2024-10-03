<?php
// Admin menu functions
function social_share_menu() {
    add_menu_page('Social Share Plugin', 'Social Share', 'edit_posts', 'social-share', 'social_share_instructions', 'dashicons-share');
    add_submenu_page('social-share', 'User Instructions', 'User Instructions', 'manage_options', 'user-instructions', 'social_share_instructions');
    add_submenu_page('social-share', 'Add Credentials', 'Add Credentials', 'manage_options', 'add-credentials', 'social_share_credentials');
    add_submenu_page('social-share', 'Post Log', 'Post Log', 'manage_options', 'post-log', 'social_share_log');
}

function social_share_instructions() {
    ?>
    <div class="wrap">
        <h1>User Instructions</h1>
        <p>This plugin automatically shares WordPress posts on LinkedIn when a post is created or updated. Follow the steps below:</p>
        <ol>
            <li>Go to the 'Add Credentials' page and enter your LinkedIn API Key and Secret.</li>
            <li>Save the credentials and use the 'Connect to LinkedIn' button to establish the LinkedIn API integration.</li>
            <li>Once connected, any post you publish or update will be shared on LinkedIn.</li>
        </ol>
    </div>
    <?php
}

function social_share_log() {
    ?>
    <div class="wrap">
        <h1>Post Log</h1>
        <p>This is where the log of shared posts on LinkedIn will be displayed.</p>
    </div>
    <?php
}
