<?php
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
