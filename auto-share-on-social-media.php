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
    $linkedin_api_key = get_option('linkedin_api_key');
    $redirect_uri = urlencode(admin_url('admin.php?page=add-credentials'));
    $authorization_url = "https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id={$linkedin_api_key}&redirect_uri={$redirect_uri}&scope=w_member_social";
    
    return $authorization_url;
}

function social_share_log() {
    if (isset($_GET['delete_post']) && check_admin_referer('delete_post_action')) {
        $post_id = intval($_GET['delete_post']);
        wp_delete_post($post_id, true);
        echo "<div class='updated notice is-dismissible'><p>Post deleted successfully.</p></div>";
    }

    ?>
    <div class="wrap">
        <h1>Shared Post Log</h1>
        <table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th>Post Title</th>
                    <th>Date Shared</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $shared_posts = get_option('shared_posts_log', []);
                if (!empty($shared_posts)) {
                    foreach ($shared_posts as $post) {
                        echo "<tr>
                                <td>{$post['title']}</td>
                                <td>{$post['date_shared']}</td>
                                <td>
                                    <a href='" . get_edit_post_link($post['ID']) . "' class='button button-secondary'>Edit</a>
                                    <a href='?page=post-log&delete_post={$post['ID']}&_wpnonce=" . wp_create_nonce('delete_post_action') . "' class='button button-secondary' onclick='return confirm(\"Are you sure?\");'>Delete</a>
                                </td>
                            </tr>";
                    }
                } else {
                    echo "<tr><td colspan='3'>No posts shared yet.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}

add_action('publish_post', 'share_post_on_linkedin');
add_action('edit_post', 'share_post_on_linkedin');
add_action('init', 'handle_oauth_callback');

function share_on_linkedin($is_article = true, $post_title = '', $post_url = '', $profile_id = '') {
    $access_token = get_option('linkedin_access_token');
    
    if (!$access_token) {
        return false; // No access token found
    }

    $share_content = $is_article ? [
        'owner' => 'urn:li:person:' . $profile_id,
        'subject' => 'Check out this article!',
        'text' => ['text' => "This is a great article: $post_title - $post_url"],
        'content' => [
            'contentEntities' => [['entityLocation' => $post_url]],
            'title' => $post_title
        ],
        'distribution' => ['linkedInDistributionTarget' => []]
    ] : [
        'owner' => 'urn:li:person:' . $profile_id,
        'subject' => 'Just a text post!',
        'text' => ['text' => "This is a simple text post: $post_title - $post_url"],
        'distribution' => ['linkedInDistributionTarget' => []]
    ];

    $share_response = wp_remote_post('https://api.linkedin.com/v2/shares', [
        'body' => json_encode($share_content),
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ],
    ]);

    if (is_wp_error($share_response)) {
        return false; // Indicate failure
    }

    $share_body = wp_remote_retrieve_body($share_response);
    $share_data = json_decode($share_body, true);

    return !empty($share_data['id']); // Return true if an ID is present
}

function share_post_on_linkedin($post_id) {
    $post = get_post($post_id);
    $post_title = $post->post_title;
    $post_url = get_permalink($post_id);

    $access_token = get_option('linkedin_access_token');

    if (!$access_token) {
        return; // Exit if no access token
    }

    $profile_id = get_linkedin_profile_id();

    if (!$profile_id) {
        return; // Exit if no profile ID
    }

    $success = share_on_linkedin(true, $post_title, $post_url, $profile_id);

    if ($success) {
        // Optionally log the success message or take additional actions
    } else {
        // Optionally log the failure message or take additional actions
    }
}

// function handle_oauth_callback() {
//     if (isset($_GET['code'])) {
//         $linkedin_secret_key = get_option('linkedin_secret_key');
//         $linkedin_api_key = get_option('linkedin_api_key');
//         $redirect_uri = urlencode(admin_url('admin.php?page=add-credentials'));
//         $code = sanitize_text_field($_GET['code']);

//         $response = wp_remote_post('https://www.linkedin.com/oauth/v2/accessToken', [
//             'body' => [
//                 'grant_type' => 'authorization_code',
//                 'code' => $code,
//                 'redirect_uri' => $redirect_uri,
//                 'client_id' => $linkedin_api_key,
//                 'client_secret' => $linkedin_secret_key,
//             ],
//             'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']
//         ]);

//         if (!is_wp_error($response)) {
//             $body = wp_remote_retrieve_body($response);
//             $data = json_decode($body, true);

//             if (isset($data['access_token'])) {
//                 update_option('linkedin_access_token', $data['access_token']);
//                 echo "<div class='updated'><p>Successfully connected to LinkedIn!</p></div>";
//             } else {
//                 echo "<div class='error'><p>Failed to retrieve access token.</p></div>";
//             }
//         } else {
//             echo "<div class='error'><p>Error: " . $response->get_error_message() . "</p></div>";
//         }
//     }
// }

function handle_oauth_callback() {
    if (isset($_GET['code'])) {
        $linkedin_secret_key = get_option('linkedin_secret_key');
        $linkedin_api_key = get_option('linkedin_api_key');
        $redirect_uri = urlencode(admin_url('admin.php?page=add-credentials'));
        $code = sanitize_text_field($_GET['code']);

        // Debugging: Log request parameters
        error_log("LinkedIn OAuth Callback: Code - $code, Redirect URI - $redirect_uri");

        $response = wp_remote_post('https://www.linkedin.com/oauth/v2/accessToken', [
            'body' => http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirect_uri,
                'client_id' => $linkedin_api_key,
                'client_secret' => $linkedin_secret_key,
            ]),
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']
        ]);

        // Debugging: Log response status and body
        if (is_wp_error($response)) {
            error_log("LinkedIn OAuth Error: " . $response->get_error_message());
            echo "<div class='error'><p>Error: " . esc_html($response->get_error_message()) . "</p></div>";
        } else {
            $body = wp_remote_retrieve_body($response);
            error_log("LinkedIn OAuth Response: " . $body);
            $data = json_decode($body, true);

            if (isset($data['access_token'])) {
                update_option('linkedin_access_token', $data['access_token']);
                echo "<div class='updated'><p>Successfully connected to LinkedIn!</p></div>";
            } else {
                echo "<div class='error'><p>Failed to retrieve access token. Response: " . esc_html($body) . "</p></div>";
            }
        }
    }
}


?>
