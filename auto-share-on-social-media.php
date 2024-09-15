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
        // Check nonce for security
        check_admin_referer('save_credentials_action', 'save_credentials_nonce');

        update_option('linkedin_api_key', sanitize_text_field($_POST['linkedin_api_key']));
        update_option('linkedin_secret_key', sanitize_text_field($_POST['linkedin_secret_key']));
    }

    // Check the connection
    $connection_status = '';
    if (isset($_POST['check_connection'])) {
        // Check nonce for security
        check_admin_referer('check_connection_action', 'check_connection_nonce');

        $linkedin_api_key = get_option('linkedin_api_key');
        $linkedin_secret_key = get_option('linkedin_secret_key');

        if (!empty($linkedin_api_key) && !empty($linkedin_secret_key)) {
            $access_token = get_linkedin_access_token($linkedin_api_key, $linkedin_secret_key);

            if ($access_token) {
                $response = wp_remote_get('https://api.linkedin.com/v2/me', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $access_token
                    ]
                ]);

                if (is_wp_error($response)) {
                    $connection_status = "Connection failed: " . $response->get_error_message();
                } else {
                    $status_code = wp_remote_retrieve_response_code($response);
                    if ($status_code == 200) {
                        $connection_status = "Connection successful!";
                    } else {
                        $connection_status = "Connection failed: Invalid API credentials. Status code: " . $status_code;
                    }
                }
            } else {
                $connection_status = "Connection failed: Could not retrieve access token.";
            }
        } else {
            $connection_status = "Please enter both API Key and Secret Key.";
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
        <?php if ($connection_status) : ?>
            <p><strong><?php echo esc_html($connection_status); ?></strong></p>
        <?php endif; ?>
    </div>
    <?php
}


// Function to get LinkedIn access token (Dummy function for demonstration)
function get_linkedin_access_token($client_id, $client_secret) {
    // Implement the OAuth 2.0 flow to get the access token
    // This function should return the access token required for LinkedIn API calls
    // For demonstration purposes, we'll return a dummy token
    return 'YOUR_ACCESS_TOKEN';
}

function social_share_log() {
    ?>
    <div class="wrap">
        <h1>Shared Post Log</h1>
        <table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th id="columnname" class="manage-column column-columnname" scope="col">Post Title</th>
                    <th id="columnname" class="manage-column column-columnname" scope="col">Date Shared</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $shared_posts = get_option('shared_posts_log', []);
                if (!empty($shared_posts)) {
                    foreach ($shared_posts as $post) {
                        echo "<tr><td>{$post['title']}</td><td>{$post['date_shared']}</td></tr>";
                    }
                } else {
                    echo "<tr><td colspan='2'>No posts have been shared yet.</td></tr>";
                }
                ?>
            </tbody>
        </table>
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
        return; // No credentials or token, don't proceed
    }

    $linkedin_url = 'https://api.linkedin.com/v2/shares';
    
    $body = json_encode([
        'content' => [
            'contentEntities' => [
                [
                    'entityLocation' => $post_url,
                    'thumbnails' => []
                ]
            ],
            'title' => $post_title
        ],
        'distribution' => [
            'linkedInDistributionTarget' => []
        ],
        'owner' => 'urn:li:person:A0FZhFqY5Hr9Y2k1v4Jr9oFhzN7G', // Replace this with your LinkedIn profile ID
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
        'date_shared' => date('Y-m-d H:i:s')
    ];
    update_option('shared_posts_log', $shared_posts);
}
