<?php
/**
 * Plugin Name: Auto Share Text Post on LinkedIn
 * Description: Automatically shares text-only WordPress posts on LinkedIn when a post is published or updated.
 * Version: 1.4
 * Author: Aasakya Digital
 */

// Adding menu for the LinkedIn share plugin
add_action('admin_menu', 'social_share_menu');

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
        <p>This plugin automatically shares text-only WordPress posts on LinkedIn when a post is created or updated. Follow the steps below:</p>
        <ol>
            <li>Go to the 'Add Credentials' page and enter your LinkedIn API Key and Secret.</li>
            <li>Save the credentials and use the 'Connect to LinkedIn' button to establish the LinkedIn API integration.</li>
            <li>Once connected, any text post you publish or update will be shared on LinkedIn.</li>
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

    if (isset($_POST['disconnect_linkedin'])) {
        check_admin_referer('disconnect_linkedin_action', 'disconnect_linkedin_nonce');
        delete_option('linkedin_access_token');
        delete_option('linkedin_token_expiry_time');
        $connection_status = "Disconnected from LinkedIn!";
    }

    $connection_status = get_connection_status();

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
            <?php if (get_option('linkedin_access_token')): ?>
                <form method="post" style="margin-top: 20px;">
                    <?php wp_nonce_field('disconnect_linkedin_action', 'disconnect_linkedin_nonce'); ?>
                    <button type="submit" name="disconnect_linkedin" class="button button-secondary">Disconnect from LinkedIn</button>
                </form>
            <?php else: ?>
                <a href="<?php echo esc_url(redirect_to_linkedin_oauth()); ?>" class="button button-primary">Connect to LinkedIn</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

function get_connection_status() {
    if (get_option('linkedin_api_key') && get_option('linkedin_secret_key')) {
        $access_token = get_option('linkedin_access_token');
        return $access_token ? "Connected to LinkedIn!" : "Not connected. Please connect to LinkedIn.";
    }
    return "Please enter your LinkedIn API Key and Secret.";
}

function social_share_log() {
    ?>
    <div class="wrap">
        <h1>Post Log</h1>
        <p>This is where the log of shared posts on LinkedIn will be displayed.</p>
    </div>
    <?php
}

function redirect_to_linkedin_oauth() {
    $linkedin_api_key = get_option('linkedin_api_key');
    
    $state = bin2hex(random_bytes(16));
    set_transient('linkedin_oauth_state', $state, 3600);

    $redirect_uri = admin_url('admin.php?page=add-credentials&action=linkedin_oauth_callback');
    $scopes = 'openid profile email w_member_social'; // Scope for member post sharing

    $authorization_url = "https://www.linkedin.com/oauth/v2/authorization"
        . "?response_type=code"
        . "&client_id={$linkedin_api_key}"
        . "&redirect_uri=" . urlencode($redirect_uri)
        . "&scope=" . urlencode($scopes)
        . "&state={$state}";

    return $authorization_url;
}

add_action('admin_init', 'linkedin_oauth_callback');

function linkedin_oauth_callback() {
    if (isset($_GET['action']) && $_GET['action'] === 'linkedin_oauth_callback') {
        if (isset($_GET['code']) && isset($_GET['state'])) {
            $authorization_code = sanitize_text_field($_GET['code']);
            $received_state = sanitize_text_field($_GET['state']);
            $saved_state = get_transient('linkedin_oauth_state');

            if (!$saved_state || $received_state !== $saved_state) {
                wp_die('Unauthorized: Invalid state parameter.', '401 Unauthorized', array('response' => 401));
            }

            $linkedin_api_key = get_option('linkedin_api_key');
            $linkedin_secret_key = get_option('linkedin_secret_key');
            $redirect_uri = admin_url('admin.php?page=add-credentials&action=linkedin_oauth_callback');

            $response = wp_remote_post('https://www.linkedin.com/oauth/v2/accessToken', [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => http_build_query([
                    'grant_type' => 'authorization_code',
                    'code' => $authorization_code,
                    'redirect_uri' => $redirect_uri,
                    'client_id' => $linkedin_api_key,
                    'client_secret' => $linkedin_secret_key,
                ]),
            ]);

            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($body['access_token'])) {
                    update_option('linkedin_access_token', $body['access_token']);
                    update_option('linkedin_token_expiry_time', time() + 3600);
                    
                    if (isset($body['refresh_token'])) {
                        update_option('linkedin_refresh_token', $body['refresh_token']);
                    }

                    // Fetch LinkedIn member details and store user URN
                    $response_profile = wp_remote_get('https://api.linkedin.com/v2/userinfo', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $body['access_token']
                        ]
                    ]);

                    if (!is_wp_error($response_profile)) {
                        $profile_data = json_decode(wp_remote_retrieve_body($response_profile), true);
                        if (isset($profile_data['sub'])) {
                            update_option('linkedin_user_urn', 'urn:li:person:' . $profile_data['sub']);
                        }
                    }

                    delete_transient('linkedin_oauth_state');
                    wp_redirect(admin_url('admin.php?page=add-credentials&connected=1'));
                    exit;
                }
            } else {
                wp_die('OAuth failed: Unable to retrieve access token.', '400 Bad Request', array('response' => 400));
            }
        }
    }
}

function check_linkedin_token_expiry() {
    $access_token = get_option('linkedin_access_token');
    $refresh_token = get_option('linkedin_refresh_token');

    if ($access_token) {
        $expiry_time = get_option('linkedin_token_expiry_time');
        if (time() >= $expiry_time && $refresh_token) {
            $linkedin_api_key = get_option('linkedin_api_key');
            $linkedin_secret_key = get_option('linkedin_secret_key');

            $response = wp_remote_post('https://www.linkedin.com/oauth/v2/accessToken', [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => http_build_query([
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refresh_token,
                    'client_id' => $linkedin_api_key,
                    'client_secret' => $linkedin_secret_key,
                ]),
            ]);

            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($body['access_token'])) {
                    update_option('linkedin_access_token', $body['access_token']);
                    update_option('linkedin_token_expiry_time', time() + 3600);
                }
            }
        }
    }
}

// Sharing the post on LinkedIn after publishing or updating
add_action('publish_post', 'auto_share_post_on_linkedin', 10, 2);

function auto_share_post_on_linkedin($ID, $post) {
    check_linkedin_token_expiry();

    $access_token = get_option('linkedin_access_token');
    $user_urn = get_option('linkedin_user_urn'); // Use member's URN for personal post

    if ($access_token && $user_urn) {
        $post_title = get_the_title($ID);
        $post_link = get_permalink($ID);
        $post_content = wp_strip_all_tags(get_post_field('post_content', $ID));

        // Construct LinkedIn post data (for text-only posts)
        $post_data = [
            'author' => $user_urn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'text' => $post_title . ' - ' . $post_link . "\n\n" . $post_content,
                    ],
                    'shareMediaCategory' => 'NONE'
                ]
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
            ]
        ];

        // Make API request to share the post
        $response = wp_remote_post('https://api.linkedin.com/v2/ugcPosts', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0' // Restli protocol version
            ],
            'body' => json_encode($post_data),
        ]);

        if (is_wp_error($response)) {
            error_log('LinkedIn API error: ' . $response->get_error_message());
        } else {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['id'])) {
                error_log('LinkedIn Post ID: ' . $body['id']);
            } else {
                error_log('LinkedIn Post failed: ' . wp_remote_retrieve_body($response));
            }
        }
    } else {
        error_log('LinkedIn Access Token or User URN is missing.');
    }
}
