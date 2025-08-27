<?php

if (!defined('ABSPATH')) {
    exit;
}
require_once __DIR__ . '/inc/get_posts.php';
require_once __DIR__ . '/inc/jtw_token.php';
require_once __DIR__ . '/inc/get_posttaxonomies.php';
require_once __DIR__ . '/inc/client_api.php';
require_once __DIR__ . '/inc/get_tags.php';

add_theme_support('post-thumbnails');

add_action('init', function () {
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
function thdblog_theme_setup()
{
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
function thdblog_enqueue_assets()
{
    wp_enqueue_style(
        'thdblog-main',
        get_template_directory_uri() . '/css/main.css',
        array(),
        filemtime(get_template_directory() . '/css/main.css')
    );
    wp_enqueue_script(
        'thdblog-main-js',
        get_template_directory_uri() . '/js/main.js',
        array(),
        filemtime(get_template_directory() . '/js/main.js'),
        true
    );
    if (is_archive()) {
        wp_enqueue_style(
            'thdblog-archive',
            get_template_directory_uri() . '/css/archive.css',
            array('thdblog-main'),
            filemtime(get_template_directory() . '/css/archive.css')
        );
    }
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
/**
 *
 */
add_filter('acf/fields/relationship/query/name=related_posts_guias', function ($args, $field, $post_id) {
    $args['meta_query'] = [
        [
            'key'     => '_posttype',
            'value'   => 'Buying Guide',
            'compare' => '='
        ]
    ];
    return $args;
}, 10, 3);

add_filter('acf/fields/relationship/query/name=related_posts_tutoriales', function ($args, $field, $post_id) {
    $args['meta_query'] = [
        [
            'key'     => '_posttype',
            'value'   => 'Tutorial',
            'compare' => '='
        ]
    ];
    return $args;
}, 10, 3);

add_action('wp_footer', function () {
    global $template;
    echo '<!-- Current template: ' . esc_html(basename($template)) . ' -->';
});

add_action('pre_get_posts', function ($q) {
    if (!is_admin() && $q->is_main_query() && $q->is_archive()) {

        // Orden por título
        $sort_title = isset($_GET['sort_title']) ? sanitize_text_field($_GET['sort_title']) : '';
        if ($sort_title === 'asc' || $sort_title === 'desc') {
            $q->set('orderby', 'title');
            $q->set('order', strtoupper($sort_title));
        }

        // Orden por fecha (tiene prioridad si se envía)
        $sort_date = isset($_GET['sort_date']) ? sanitize_text_field($_GET['sort_date']) : '';
        if ($sort_date === 'asc' || $sort_date === 'desc') {
            $q->set('orderby', 'date');
            $q->set('order', strtoupper($sort_date));
        }

        // Si viene búsqueda dentro del archivo, ya viene el parámetro `s`
    }
});