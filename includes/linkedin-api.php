<?php
// LinkedIn OAuth callback and token expiry check
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
                    
                    // Fetch LinkedIn member details and store user URN
                    $response_profile = wp_remote_get('https://api.linkedin.com/v2/userinfo', [
                        'headers' => ['Authorization' => 'Bearer ' . $body['access_token']]
                    ]);

                    if (!is_wp_error($response_profile)) {
                        $profile_data = json_decode(wp_remote_retrieve_body($response_profile), true);
                        if (isset($profile_data['sub'])) {
                            update_option('linkedin_user_urn', 'urn:li:person:' . $profile_data['sub']);
                        }
                    }
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
