<?php
/**
 * Plugin Name: Auto Share on Social Media
 * Description: Automatically shares blog posts on social media like LinkedIn when a post is published or updated.
 * Version: 1.0
 * Author: Your Name
 */

// Hook to add the admin menu
add_action('admin_menu', 'social_share_menu');

// Function to add admin menu
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
        <p>This plugin automatically shares your WordPress posts on social media platforms such as LinkedIn when a post is created or updated. Follow the steps below:</p>
        <ol>
            <li>Go to the 'Add Credentials' page and enter the API Key and Secret for your social media platform (LinkedIn).</li>
            <li>Save the credentials and check the connection to ensure everything is working.</li>
            <li>Once connected, every time you publish or update a post, it will be shared on the social media platforms.</li>
        </ol>
        <h2>Customize Using CSS</h2>
        <p>You can modify the appearance of this plugin's elements using custom CSS:</p>
        <pre>
        .wrap h1 {
            color: #0073aa;
            font-size: 24px;
        }
        .wrap p {
            font-size: 16px;
            line-height: 1.5;
        }
        .wrap ol {
            padding-left: 20px;
        }
        .wrap ol li {
            margin-bottom: 10px;
        }
        </pre>
    </div>
    <?php
}

function social_share_credentials() {
    // Save the credentials
    if (isset($_POST['save_credentials'])) {
        check_admin_referer('save_credentials_action', 'save_credentials_nonce');

        update_option('linkedin_api_key', sanitize_text_field($_POST['linkedin_api_key']));
        update_option('linkedin_secret_key', sanitize_text_field($_POST['linkedin_secret_key']));
    }

    // Check the connection
    if (isset($_POST['check_connection'])) {
        check_admin_referer('check_connection_action', 'check_connection_nonce');

        // Fetch the stored access token (assume it's saved after the OAuth flow)
        $access_token = get_option('linkedin_access_token');

        if ($access_token) {
            // Make an API request to check if the access token is valid
            $response = wp_remote_get('https://api.linkedin.com/v2/me', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token
                ]
            ]);

            if (is_wp_error($response)) {
                $connection_status = "Connection failed: " . $response->get_error_message();
            } else {
                $status_code = wp_remote_retrieve_response_code($response);

                // Check if the status code is 200 (success)
                if ($status_code == 200) {
                    $connection_status = "Connection successful!";
                } else {
                    $connection_status = "Connection failed: Invalid access token. Status code: " . $status_code;
                }
            }
        } else {
            $connection_status = "Connection failed: Access token not found.";
        }
    }

    ?>
    <div class="wrap">
        <h1>Add Social Media Credentials</h1>
        <form method="post">
            <?php wp_nonce_field('save_credentials_action', 'save_credentials_nonce'); ?>
            <?php wp_nonce_field('check_connection_action', 'check_connection_nonce'); ?>
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
                <button type="submit" name="check_connection" class="button button-secondary">Check Connection</button>
            </p>
        </form>
        <?php if (isset($connection_status)) : ?>
            <p><strong><?php echo esc_html($connection_status); ?></strong></p>
        <?php endif; ?>
    </div>
    <?php
}


function get_linkedin_access_token($authorization_code) {
    $client_id = get_option('linkedin_api_key');
    $client_secret = get_option('linkedin_secret_key');
    $redirect_uri = 'https://www.linkedin.com/developers/tools/oauth/redirect'; // Define your authorized redirect URI here

    $response = wp_remote_post('https://www.linkedin.com/oauth/v2/accessToken', [
        'body' => [
            'grant_type' => 'authorization_code',
            'code' => $authorization_code,
            'redirect_uri' => $redirect_uri, // Must match the one set in the LinkedIn Developer Portal
            'client_id' => $client_id,
            'client_secret' => $client_secret,
        ],
        'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);

    return isset($data['access_token']) ? $data['access_token'] : false;
}


function social_share_log() {
    // Handle delete request
    if (isset($_GET['delete_post']) && check_admin_referer('delete_post_action')) {
        $post_id = intval($_GET['delete_post']);
        $result = wp_delete_post($post_id, true);

        $message = $result ? 'Post deleted successfully.' : 'Failed to delete the post.';
        $class = $result ? 'updated' : 'error';
        echo "<div class=\"$class notice is-dismissible\"><p>$message</p></div>";
    }

    // Handle edit request
    if (isset($_POST['update_post']) && check_admin_referer('update_post_action')) {
        $post_id = intval($_POST['post_id']);
        $post_title = sanitize_text_field($_POST['post_title']);
        $post_content = wp_kses_post($_POST['post_content']);
        
        $updated_post = [
            'ID'           => $post_id,
            'post_title'    => $post_title,
            'post_content'  => $post_content,
        ];

        $result = wp_update_post($updated_post);

        $message = $result ? 'Post updated successfully.' : 'Failed to update the post.';
        $class = $result ? 'updated' : 'error';
        echo "<div class=\"$class notice is-dismissible\"><p>$message</p></div>";
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
                                    <a href='" . get_edit_post_link($post['ID']) . "' class='button button-secondary'>Edit Post</a>
                                    <a href='?page=post-log&delete_post={$post['ID']}&_wpnonce=" . wp_create_nonce('delete_post_action') . "' class='button button-secondary' onclick='return confirm(\"Are you sure you want to delete this post?\");'>Delete Post</a>
                                </td>
                            </tr>";
                    }
                } else {
                    echo "<tr><td colspan='3'>No posts have been shared yet.</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <h2>Edit Post</h2>
        <?php
        if (isset($_GET['edit_post'])) {
            $post_id = intval($_GET['edit_post']);
            $post = get_post($post_id);
            if ($post) {
                ?>
                <form method="post">
                    <?php wp_nonce_field('update_post_action'); ?>
                    <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>" />
                    <table class="form-table">
                        <tr>
                            <th><label for="post_title">Post Title</label></th>
                            <td><input type="text" id="post_title" name="post_title" value="<?php echo esc_attr($post->post_title); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th><label for="post_content">Post Content</label></th>
                            <td><?php wp_editor($post->post_content, 'post_content'); ?></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" name="update_post" class="button button-primary">Update Post</button>
                    </p>
                </form>
                <?php
            } else {
                echo '<div class="error notice is-dismissible"><p>Post not found.</p></div>';
            }
        }
        ?>
    </div>
    <?php
}

add_action('publish_post', 'share_post_on_social_media');
add_action('edit_post', 'share_post_on_social_media');

function share_post_on_social_media($post_id) {
    $post = get_post($post_id);
    $post_title = $post->post_title;
    $post_url = get_permalink($post_id);

    $linkedin_api_key = get_option('linkedin_api_key');
    $linkedin_secret_key = get_option('linkedin_secret_key');
    $access_token = get_linkedin_access_token($linkedin_api_key, $linkedin_secret_key);

    if (empty($linkedin_api_key) || empty($linkedin_secret_key) || empty($access_token)) {
        return;
    }

    // Retrieve profile ID via LinkedIn API
    $response = wp_remote_get('https://api.linkedin.com/v2/me', [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token
        ]
    ]);

    if (is_wp_error($response)) {
        error_log('Error retrieving LinkedIn profile ID: ' . $response->get_error_message());
        return;
    }

    $profile_data = json_decode(wp_remote_retrieve_body($response), true);
    $profile_id = $profile_data['id'];

    $linkedin_url = 'https://api.linkedin.com/v2/shares';

    $body = json_encode([
        'content' => [
            'contentEntities' => [
                [
                    'entityLocation' => $post_url
                ]
            ],
            'title' => $post_title
        ],
        'distribution' => [
            'linkedInDistributionTarget' => []
        ],
        'owner' => 'urn:li:person:' . $profile_id, // Use the retrieved LinkedIn profile ID
        'text' => [
            'text' => "Check out my new post: $post_title - $post_url"
        ]
    ]);

    $args = [
        'body' => $body,
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
            'x-li-format' => 'json'
        ]
    ];

    $response = wp_remote_post($linkedin_url, $args);

    if (is_wp_error($response)) {
        error_log('Error sharing post on LinkedIn: ' . $response->get_error_message());
        return;
    }

    $status_code = wp_remote_retrieve_response_code($response);

    if ($status_code != 201) {
        error_log('Failed to share post on LinkedIn. Status code: ' . $status_code);
        return;
    }

    // Log the shared post
    $shared_posts = get_option('shared_posts_log', []);
    $shared_posts[] = [
        'title' => $post_title,
        'date_shared' => date('Y-m-d H:i:s'),
        'ID' => $post_id
    ];
    update_option('shared_posts_log', $shared_posts);
}

