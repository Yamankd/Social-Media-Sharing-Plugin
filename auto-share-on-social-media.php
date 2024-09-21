<?php
/**
 * Plugin Name: Auto Share on LinkedIn
 * Description: Automatically shares WordPress posts on LinkedIn when a post is published or updated.
 * Version: 1.0
 * Author: Your Name
 */

add_action('admin_menu', 'social_share_menu');

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
            <li>Save the credentials and use the 'Check Connection' button to verify the LinkedIn API integration.</li>
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

    if (isset($_POST['check_connection'])) {
        check_admin_referer('check_connection_action', 'check_connection_nonce');
        $access_token = get_option('linkedin_access_token');
        if ($access_token) {
            $response = wp_remote_get('https://api.linkedin.com/v2/me', [
                'headers' => ['Authorization' => 'Bearer ' . $access_token]
            ]);

            if (is_wp_error($response)) {
                $connection_status = "Connection failed: " . $response->get_error_message();
            } else {
                $status_code = wp_remote_retrieve_response_code($response);
                $connection_status = ($status_code == 200) ? "Connection successful!" : "Connection failed: Invalid access token.";
            }
        } else {
            $connection_status = "Connection failed: Access token not found.";
        }
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
                <?php if (get_option('linkedin_api_key') && get_option('linkedin_secret_key')): ?>
                    <button type="submit" name="check_connection" class="button button-secondary">Check Connection</button>
                <?php endif; ?>
            </p>
        </form>

        <?php if (get_option('linkedin_api_key') && get_option('linkedin_secret_key')): ?>
            <a href="<?php echo esc_url(redirect_to_linkedin_oauth()); ?>" class="button button-primary">Connect to LinkedIn</a>
        <?php endif; ?>

        <?php if (isset($connection_status)) : ?>
            <p><strong><?php echo esc_html($connection_status); ?></strong></p>
        <?php endif; ?>
    </div>
    <?php
}

function redirect_to_linkedin_oauth() {
    // Dynamically retrieve LinkedIn API Key (client_id)
    $linkedin_api_key = get_option('linkedin_api_key');
    
    // Generate a unique state value
    $state = bin2hex(random_bytes(16));  // Generates a random 32-character string
    $_SESSION['linkedin_oauth_state'] = $state;  // Save state in session to validate later

    // Define the redirect URI
    $redirect_uri = urlencode(admin_url('admin.php?page=add-credentials'));

    // Construct LinkedIn OAuth Authorization URL
    $authorization_url = "https://www.linkedin.com/oauth/v2/authorization"
        . "?response_type=code"
        . "&client_id={$linkedin_api_key}"
        . "&redirect_uri={$redirect_uri}"
        . "&scope=w_member_social"
        . "&state={$state}";

    // Redirect to LinkedIn OAuth URL
    wp_redirect($authorization_url);
    exit;
}


function social_share_log() {
    ?>
    <div class="wrap">
        <h1>Shared Post Log</h1>
        <table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th>Post Title</th>
                    <th>Date Shared</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Retrieve shared posts log from options
                $shared_posts = get_option('shared_posts_log', []);

                if (!empty($shared_posts)) {
                    // Display each shared post
                    foreach ($shared_posts as $post) {
                        echo "<tr>
                                <td>" . esc_html($post['title']) . "</td>
                                <td>" . esc_html($post['date_shared']) . "</td>
                            </tr>";
                    }
                } else {
                    // If no posts shared yet
                    echo "<tr>
                            <td colspan='2'>No posts shared yet.</td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}


add_action('publish_post', 'share_post_on_linkedin');
add_action('edit_post', 'share_post_on_linkedin');
add_action('admin_init', 'linkedin_oauth_callback');

function share_post_on_linkedin($post_id) {
    $post = get_post($post_id);
    $post_title = $post->post_title;
    $post_url = get_permalink($post_id);

    check_linkedin_token_expiry(); // Check and refresh token if necessary

    $access_token = get_option('linkedin_access_token');
    if (!$access_token) {
        return; // Exit if no access token
    }

    $profile_id = get_linkedin_profile_id(); // You'll need to implement this function to retrieve the profile ID

    $share_content = [
        'owner' => 'urn:li:person:' . $profile_id,
        'text' => ['text' => "Check out this article: $post_title - $post_url"],
        'content' => [
            'contentEntities' => [['entityLocation' => $post_url]],
            'title' => $post_title
        ],
        'distribution' => ['linkedInDistributionTarget' => []]
    ];

    $response = wp_remote_post('https://api.linkedin.com/v2/shares', [
        'body' => json_encode($share_content),
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ],
    ]);

    if (!is_wp_error($response)) {
        $shared_posts = get_option('shared_posts_log', []);
        $shared_posts[] = ['title' => $post_title, 'date_shared' => current_time('mysql')];
        update_option('shared_posts_log', $shared_posts);
    }
}

function linkedin_oauth_callback() {
    // Check if authorization code and state are present
    if (isset($_GET['code']) && isset($_GET['state']) && isset($_GET['page']) && $_GET['page'] === 'add-credentials') {
        $authorization_code = sanitize_text_field($_GET['code']);
        $received_state = sanitize_text_field($_GET['state']);

        // Validate the state parameter
        if ($received_state !== $_SESSION['linkedin_oauth_state']) {
            // State does not match, likely CSRF attack
            wp_die('Unauthorized: Invalid state parameter.', '401 Unauthorized', array('response' => 401));
        }

        // Continue with the token exchange if the state matches
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
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (isset($data['access_token'])) {
                // Store access token and redirect with success
                update_option('linkedin_access_token', $data['access_token']);
                update_option('linkedin_token_expiry', time() + $data['expires_in']);
                wp_redirect(admin_url('admin.php?page=add-credentials&oauth_success=1'));
                exit;
            }
        }

        // Redirect to error page if token retrieval fails
        wp_redirect(admin_url('admin.php?page=add-credentials&oauth_error=1'));
        exit;
    }
}


function check_linkedin_token_expiry() {
    $token_expiry = get_option('linkedin_token_expiry');
    if (time() > $token_expiry) {
        $linkedin_api_key = get_option('linkedin_api_key');
        $linkedin_secret_key = get_option('linkedin_secret_key');
        $refresh_token = get_option('linkedin_refresh_token'); // Retrieve refresh token if applicable

        if ($refresh_token) {
            $response = wp_remote_post('https://www.linkedin.com/oauth/v2/accessToken', [
                'body' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refresh_token,
                    'client_id' => $linkedin_api_key,
                    'client_secret' => $linkedin_secret_key,
                ]
            ]);

            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                if (isset($data['access_token'])) {
                    update_option('linkedin_access_token', $data['access_token']);
                    update_option('linkedin_token_expiry', time() + $data['expires_in']);
                }
            }
        }
    }
}
l