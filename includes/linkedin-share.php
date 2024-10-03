<?php
function auto_share_post_on_linkedin($ID, $post) {
    check_linkedin_token_expiry();

    $access_token = get_option('linkedin_access_token');
    $user_urn = get_option('linkedin_user_urn'); // Use member's URN for personal post

    if ($access_token && $user_urn) {
        $post_title = get_the_title($ID);
        $post_link = get_permalink($ID);
        $post_content = wp_strip_all_tags(get_post_field('post_content', $ID));

        // Check if post contains a URL for sharing an article
        $post_type = 'TEXT'; // Default type is text
        $article_url = '';
        $article_description = '';

        // Assuming there's a custom field or meta box where article URL is added
        $article_url = get_post_meta($ID, 'article_url', true); // Example: Fetch the article URL dynamically
        $article_description = get_post_meta($ID, 'article_description', true); // Example: Fetch article description

        if (!empty($article_url)) {
            $post_type = 'ARTICLE'; // If URL is provided, set type to ARTICLE
        }

        // Construct LinkedIn post data
        $post_data = [
            'author' => $user_urn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                    'text' => $post_title . ' - ' . $post_link . "\n\n" . $post_content,
                    ],
                    'shareMediaCategory' => $post_type === 'ARTICLE' ? 'ARTICLE' : 'NONE',
                    'media' => []
                ]
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
            ]
        ];

        // If article, add media details
        if ($post_type === 'ARTICLE') {
            $post_data['specificContent']['com.linkedin.ugc.ShareContent']['media'][] = [
                'status' => 'READY',
                'description' => [
                    'text' => $article_description ?: 'Check out this article!',
                ],
                'originalUrl' => $article_url,
                'title' => [
                    'text' => $post_title,
                ],
            ];
        }

        // Make API request to share the post
        $response = wp_remote_post('https://api.linkedin.com/v2/ugcPosts', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0'
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











// 'text' => $post_title . ' - ' . $post_link . "\n\n" . $post_content,
