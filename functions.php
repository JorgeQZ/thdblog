<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once __DIR__ . '/inc/get_posts.php'; // Include the file that registers the REST API endpoint for posts
require_once __DIR__ . '/inc/jtw_token.php'; // Include the file that handles JWT token validation
require_once __DIR__ . '/inc/get_posttaxonomies.php'; // Include the file that registers the REST API endpoint for post taxonomies
require_once __DIR__ . '/inc/client_api.php'; // Include the file that handles API

add_theme_support('post-thumbnails'); // Enable post thumbnails support for the theme


// Enable CORS for REST API requests
// This code allows cross-origin requests to the REST API, enabling access from different domains.
add_action('init', function () {
    // $allowed = [get_site_url()];
    // if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed, true)) {
    //     header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    //     header('Access-Control-Allow-Methods: GET, OPTIONS');
    //     header('Access-Control-Allow-Headers: Content-Type');
    // }

     if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
        header("Access-Control-Allow-Methods: GET, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        status_header(200);
        exit;
    }
});


?>    $key = 'token_fail_' . md5($username . '|' . $_SERVER['REMOTE_ADDR']);
    $attempts = (int) get_transient($key);
    if ($attempts >= 5) {
        return new WP_Error(
            'too_many_attempts',
            'Too many failed attempts, please try again later',
            ['status' => 429]
        );
    }

        set_transient($key, $attempts + 1, 15 * MINUTE_IN_SECONDS);
    delete_transient($key);
    update_user_meta($user->ID, '_api_token_exp', time() + HOUR_IN_SECONDS);
        'expires_in' => HOUR_IN_SECONDS
            'description' => wp_kses_post($cat->description),
