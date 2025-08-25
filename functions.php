<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once __DIR__ . '/inc/get_posts.php'; // Include the file that registers the REST API endpoint for posts
require_once __DIR__ . '/inc/jtw_token.php'; // Include the file that handles JWT token validation
require_once __DIR__ . '/inc/get_posttaxonomies.php'; // Include the file that registers the REST API endpoint for post taxonomies
require_once __DIR__ . '/inc/client_api.php'; // Include the file that handles API
require_once __DIR__ . '/inc/get_tags.php'; // Include the file that registers the REST API endpoint for tags
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
// Soportes básicos de tema
function thdblog_theme_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', array(
        'height'      => 100,
        'width'       => 400,
        'flex-height' => true,
        'flex-width'  => true,
        'header-text' => array('site-title', 'site-description'),
        'variants'    => array(
            'default' => array(
                'label' => __('Logo Principal', 'thdblog'),
            ),
            'dark' => array(
                'label' => __('Logo Modo Oscuro', 'thdblog'),
            ),
        ),
    ));

    add_theme_support('menus');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption'));
}
add_action('after_setup_theme', 'thdblog_theme_setup');


// Enqueue de scripts y estilos
function thdblog_enqueue_assets() {
    // Encolar el CSS principal
    wp_enqueue_style(
        'thdblog-main',
        get_template_directory_uri() . '/css/main.css',
        array(),
        filemtime(get_template_directory() . '/css/main.css')
    );
    // Aquí puedes agregar más scripts o estilos si lo necesitas

     if (is_single()) {
        wp_enqueue_style(
            'thdblog-single',
            get_template_directory_uri() . '/css/single.css',
            array('thdblog-main'),
            filemtime(get_template_directory() . '/css/single.css')
        );
    }
}
add_action('wp_enqueue_scripts', 'thdblog_enqueue_assets');

?>