<?php
/**
 * Plugin Name: Auto Share on LinkedIn
 * Description: Automatically shares WordPress posts on LinkedIn when a post is published or updated.
 * Version: 1.0
 * Author: Aasakya Digital
 */

// Ensure proper session handling using WordPress functions
add_action('init', function() {
    if (!session_id()) {
        session_start();
    }
});

add_action('admin_menu', 'social_share_menu');

// Create an admin menu for the plugin
function social_share_menu() {
    add_menu_page('Social Share Plugin', 'Social Share', 'manage_options', 'social-share', 'social_share_instructions', 'dashicons-share');
    add_submenu_page('social-share', 'User Instructions', 'User Instructions', 'manage_options', 'user-instructions', 'social_share_instructions');
    add_submenu_page('social-share', 'Add Credentials', 'Add Credentials', 'manage_options', 'add-credentials', 'social_share_credentials');
    add_submenu_page('social-share', 'Post Log', 'Post Log', 'manage_options', 'post-log', 'social_share_log');
}

function social_share_instructions() {
    ?>
    <div class="wrap">
        <h1>User Instructions</h1>
        <p>This plugin automatically shares your WordPress posts on LinkedIn when a post is created or updated. Follow the steps below:</p>
        <ol>
            <li>Go to the 'Add Credentials' page and enter your LinkedIn API Key and Secret.</li>
            <li>Save the credentials and use the 'Connect to LinkedIn' button to establish the LinkedIn API integration.</li>
            <li>Once connected, any post you publish or update will be shared on LinkedIn.</li>
        </ol>
    </div>
    <?php
}

function social_share_credentials() {
    if (isset($_POST['save_credentials'])) {
        check_admin_referer('save_credentials_action', 'save_credentials_nonce');
        update_option('linkedin_api_key', sanitize_text_field($_POST['linkedin_api_key']));
        update_option('linkedin_secret_key', sanitize_text_field($_POST['linkedin_secret_key']));
    }

    // Connection status message
    $connection_status = '';
    if (get_option('linkedin_api_key') && get_option('linkedin_secret_key')) {
        $access_token = get_option('linkedin_access_token');
        if ($access_token) {
            $connection_status = "Connected to LinkedIn!";
        } else {
            $connection_status = "Not connected. Please connect to LinkedIn.";
        }
    } else {
        $connection_status = "Please enter your LinkedIn API Key and Secret.";
    }

    ?>
    <div class="wrap">
        <h1>Add LinkedIn Credentials</h1>
        <form method="post">
            <?php wp_nonce_field('save_credentials_action', 'save_credentials_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="linkedin_api_key">LinkedIn API Key</label></th>
                    <td><input type="text" id="linkedin_api_key" name="linkedin_api_key" value="<?php echo esc_attr(get_option('linkedin_api_key')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="linkedin_secret_key">LinkedIn Secret Key</label></th>
                    <td><input type="text" id="linkedin_secret_key" name="linkedin_secret_key" value="<?php echo esc_attr(get_option('linkedin_secret_key')); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="save_credentials" class="button button-primary">Save Credentials</button>
            </p>
        </form>

        <p><strong><?php echo esc_html($connection_status); ?></strong></p>

        <?php if (get_option('linkedin_api_key') && get_option('linkedin_secret_key')): ?>
            <a href="<?php echo esc_url(redirect_to_linkedin_oauth()); ?>" class="button button-primary">Connect to LinkedIn</a>
        <?php endif; ?>
    </div>
    <?php
}

function social_share_log() {
    ?>
    <div class="wrap">
        <h1>Post Log</h1>
        <p>This is where the log of shared posts on LinkedIn will be displayed.</p>
        <!-- You can extend this section to retrieve and display log data from a database or a log file -->
    </div>
    <?php
}

function redirect_to_linkedin_oauth() {
    $linkedin_api_key = get_option('linkedin_api_key');
    
    // Generate a unique state value for CSRF protection
    $state = bin2hex(random_bytes(16));
    set_transient('linkedin_oauth_state', $state, 3600);  // Use transients instead of sessions

    $redirect_uri = admin_url('admin.php?page=add-credentials');

    // Construct LinkedIn OAuth Authorization URL
    $authorization_url = "https://www.linkedin.com/oauth/v2/authorization"
        . "?response_type=code"
        . "&client_id={$linkedin_api_key}"
        . "&redirect_uri={$redirect_uri}"
        . "&scope=w_member_social"
        . "&state={$state}";

    return $authorization_url;
}

function linkedin_oauth_callback() {
    if (isset($_GET['code']) && isset($_GET['state']) && isset($_GET['page']) && $_GET['page'] === 'add-credentials') {
        $authorization_code = sanitize_text_field($_GET['code']);
        $received_state = sanitize_text_field($_GET['state']);
        $saved_state = get_transient('linkedin_oauth_state');

        if (!$saved_state || $received_state !== $saved_state) {
            wp_die('Unauthorized: Invalid state parameter.', '401 Unauthorized', array('response' => 401));
        }

        $linkedin_api_key = get_option('linkedin_api_key');
        $linkedin_secret_key = get_option('linkedin_secret_key');
        $redirect_uri = admin_url('admin.php?page=add-credentials');

        $response = wp_remote_post('https://www.linkedin.com/oauth/v2/accessToken', [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $authorization_code,
                'redirect_uri' => $redirect_uri,
                'client_id' => $linkedin_api_key,
                'client_secret' => $linkedin_secret_key,
            ]
        ]);

        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['access_token'])) {
                update_option('linkedin_access_token', $body['access_token']);
                // Set token expiry (assuming 60 minutes)
                update_option('linkedin_token_expiry_time', time() + 3600);
                delete_transient('linkedin_oauth_state'); // Clear the state after successful auth
            }
        } else {
            error_log('LinkedIn OAuth error: ' . $response->get_error_message());
            wp_die('OAuth failed: Unable to retrieve access token.', '400 Bad Request', array('response' => 400));
        }
    }
}

function check_linkedin_token_expiry() {
    $access_token = get_option('linkedin_access_token');
    
    if ($access_token) {
        $expiry_time = get_option('linkedin_token_expiry_time');
        if (time() >= $expiry_time) {
            // Token expired, clear it
            delete_option('linkedin_access_token');
            delete_option('linkedin_token_expiry_time');
        }
    }
}

function get_linkedin_profile_id() {
    $access_token = get_option('linkedin_access_token');

    $response = wp_remote_get('https://api.linkedin.com/v2/me', [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
        ],
    ]);

    if (!is_wp_error($response)) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['id']) ? $body['id'] : null;
    }

    return null;
}

// Hook to share the post on LinkedIn when a post is published or updated
add_action('publish_post', 'share_post_on_linkedin');

function share_post_on_linkedin($post_id) {
    // Ensure this is not a revision
    if (wp_is_post_revision($post_id)) {
        return;
    }

    // Get LinkedIn access token
    $access_token = get_option('linkedin_access_token');
    if (!$access_token) {
        return;
    }

    // Get post data
    $post = get_post($post_id);
    $post_title = $post->post_title;
    $post_content = wp_trim_words($post->post_content, 40);  // Limit content to 40 words for LinkedIn
    $post_url = get_permalink($post_id);

    // Prepare LinkedIn post data
    $linkedin_post_data = [
        'author' => 'urn:li:person:' . get_linkedin_profile_id(),
        'lifecycleState' => 'PUBLISHED',
        'specificContent' => [
            'com.linkedin.ugc.ShareContent' => [
                'shareCommentary' => [
                    'text' => $post_title . ' - ' . $post_url
                ],
                'shareMediaCategory' => 'NONE',
            ],
        ],
        'visibility' => [
            'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
        ],
    ];

    // Post to LinkedIn
    $response = wp_remote_post('https://api.linkedin.com/v2/ugcPosts', [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($linkedin_post_data),
    ]);

    // Log success or failure
    if (!is_wp_error($response)) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['id'])) {
            error_log('Post shared on LinkedIn successfully: ' . $body['id']);
        } else {
            error_log('Error sharing post on LinkedIn.');
        }
    } else {
        error_log('LinkedIn API error: ' . $response->get_error_message());
    }
}
?>
