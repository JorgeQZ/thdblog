<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_theme_support('post-thumbnails'); // Enable post thumbnails support for the theme


// Enable CORS for REST API requests
// This code allows cross-origin requests to the REST API, enabling access from different domains.
add_action('init', function () {
    $allowed = [get_site_url()];
    if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        status_header(200);
        exit;
    }
});

/**
 * Código para habilitar el endpoint REST API que devuelve los posts del blog.
 * Este código permite acceder a los posts del blog a través de la REST API de WordPress.
 * Los posts se devuelven en formato JSON con información básica como título, contenido, categorías, etiquetas y autor.
 */
// Add a REST API endpoint to get blog posts
// This endpoint retrieves all published posts with their details.
// It includes title, excerpt, content, slug, link, date, categories, tags,
/**
 * Permission callback for custom REST endpoints.
 *
 * Allows access to authenticated users via WordPress cookies or via a
 * temporary API token provided in the Authorization header (Bearer
 * "user|token") or a `token` request parameter.
 */
function blog_rest_permission($request) {
    if (is_user_logged_in() && current_user_can('read')) {
        return true;
    }

    $auth = $request->get_header('authorization');
    if ($auth && stripos($auth, 'Bearer ') === 0) {
        $raw = trim(substr($auth, 7));
    } else {
        $raw = $request->get_param('token');
    }

    if (!$raw) {
        return false;
    }

    list($uid, $token) = array_pad(explode('|', $raw, 2), 2, null);
    $uid = (int) $uid;
    if (!$uid || !$token) {
        return false;
    }

    $hash = get_user_meta($uid, '_api_token_hash', true);
    $exp  = (int) get_user_meta($uid, '_api_token_exp', true);
    if (!$hash || !$exp || time() > $exp) {
        return false;
    }

    if (wp_check_password($token, $hash, $uid)) {
        return user_can($uid, 'read');
    }

    return false;
}

add_action('rest_api_init', function () {
    register_rest_route('blog/v1', '/token', [
        'methods'  => 'POST',
        'callback' => 'blog_generate_token',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('blog/v1', '/posts', [
        'methods'  => 'GET',
        'callback' => 'blog_get_posts',
        'permission_callback' => 'blog_rest_permission'
    ]);
});

/**
 * Generate a temporary API token for a user.
 *
 * Expects 'username' and 'password' parameters via POST. Returns a token that
 * must be sent as a Bearer token in subsequent requests.
 */
function blog_generate_token($request) {
    $username = sanitize_text_field($request->get_param('username'));
    $password = $request->get_param('password');

    if (!$username || !$password) {
        return new WP_Error('missing_credentials', 'Username and password required', ['status' => 400]);
    }

    $user = wp_authenticate($username, $password);
    if (is_wp_error($user)) {
        return new WP_Error('invalid_credentials', 'Invalid credentials', ['status' => 403]);
    }

    $token = wp_generate_password(32, false);
    update_user_meta($user->ID, '_api_token_hash', wp_hash_password($token));
    update_user_meta($user->ID, '_api_token_exp', time() + DAY_IN_SECONDS);

    return [
        'token' => $user->ID . '|' . $token,
        'expires_in' => DAY_IN_SECONDS
    ];
}

function blog_get_posts($request) {
    $args = [
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'post_status'    => 'publish'
    ];



    $query = new WP_Query($args);
    $posts = [];

    foreach ($query->posts as $post) {
        $id = $post->ID;
        $content = $post->post_content;
        $author_id = $post->post_author;

        // Extraer campos personalizados
        $get_meta = fn($key) => get_post_meta($id, $key, true);

        $steps = get_post_meta($post->ID, 'how-to__steps', true);
        $steps_html = '';
        if (is_array($steps)) {
            foreach ($steps as $i => $step) {
                $id = 'step' . ($i + 1);
                $title = $step['how-to__step-name'] ?? 'Paso';
                $desc = $step['how-to__step-description'] ?? '';
                $steps_html .= "<div id=\"$id\"><h3>$title</h3><p>$desc</p></div>";
            }
        }

        $posts[] = [
            'id'                => $id,
            'date'              => $post->post_date,
            'modified'          => $post->post_modified,
            'status'            => $post->post_status,
            'link'              => get_permalink($post),
            'title'             => get_the_title($post),
            'content'           => wp_kses_post(apply_filters('the_content', $post->post_content)) . render_steps_as_html($post->ID),
            'author'            => get_the_author_meta('display_name', $author_id),
            'authorDescription' => wp_kses_post(get_the_author_meta('description', $author_id)),
            'postType'          => $post->post_type,
            'steps' => get_post_meta($post->ID, 'how-to__steps', true),

            'difficulty'        => $get_meta('_difficulty'),
            'duration'          => estimate_post_duration($post->ID),
            'thumbnail'         => get_the_post_thumbnail_url($id, 'medium'),
            'mainImage'         => get_the_post_thumbnail_url($id, 'full'),
            'shortDescription'  => $get_meta('_short_description'),
            'video'             => $get_meta('_video_url'),
            'categories'        => wp_get_post_categories($id),
            'tags'              => wp_get_post_tags($id, ['fields' => 'ids']),
            'navigator'         => generate_navigator_from_steps($post->ID),
            'relatedPosts'      => get_related_posts($post->ID),
            'attributes'        => json_decode($get_meta('_attributes')) ?: new stdClass(),
        ];
    }

    return rest_ensure_response($posts);
}

function render_steps_as_html($post_id) {
    $total = (int) get_post_meta($post_id, 'how-to__steps', true);
    if ($total === 0) return '';

    $html = '';

    for ($i = 0; $i < $total; $i++) {
        $id = 'step' . ($i + 1);
        $title = esc_html(get_post_meta($post_id, "how-to__steps_{$i}_how-to__step-name", true)) ?: 'Paso ' . ($i + 1);
        $desc  = wp_kses_post(get_post_meta($post_id, "how-to__steps_{$i}_how-to__step-description", true));
        $img   = esc_url(get_post_meta($post_id, "how-to__steps_{$i}_how-to__step-image", true));
        $video = esc_url(get_post_meta($post_id, "how-to__steps_{$i}_how-to__step-video", true));

        $html .= "<div id=\"{$id}\">";
        $html .= "<h3>{$title}</h3>";
        $html .= "<p>{$desc}</p>";

        if ($img) {
            $html .= "<img src=\"{$img}\" alt=\"{$title}\" />";
        }

        if ($video) {
            $html .= "<div class=\"video-embed\"><iframe src=\"{$video}\" frameborder=\"0\" allowfullscreen></iframe></div>";
        }

        $html .= "</div>";
    }

    return $html;
}

function generate_navigator_from_steps($post_id) {
    $total = (int) get_post_meta($post_id, 'how-to__steps', true);
    if ($total === 0) return [];

    $navigator = [];

    for ($i = 0; $i < $total; $i++) {
        $id = 'step' . ($i + 1);
        $title = esc_html(get_post_meta($post_id, "how-to__steps_{$i}_how-to__step-name", true)) ?: 'Paso ' . ($i + 1);
        $navigator[] = "<a href=\"#{$id}\">" . ($i + 1) . ". {$title}</a>";
    }

    return $navigator;
}

function get_related_posts($post_id, $limit = 4) {
    $manual_related = json_decode(get_post_meta($post_id, '_related_posts', true));
    if (is_array($manual_related) && !empty($manual_related)) {
        return $manual_related;
    }

    $related_ids = [];
    $tags = wp_get_post_tags($post_id, ['fields' => 'ids']);
    $cats = wp_get_post_categories($post_id);

    $query = new WP_Query([
        'post__not_in' => [$post_id],
        'posts_per_page' => $limit,
        'ignore_sticky_posts' => true,
        'orderby' => 'date',
        'order' => 'DESC',
        'fields' => 'ids',
        'tax_query' => [
            'relation' => 'OR',
            [
                'taxonomy' => 'post_tag',
                'field'    => 'term_id',
                'terms'    => $tags,
            ],
            [
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => $cats,
            ]
        ]
    ]);

    if (!empty($query->posts)) {
        $related_ids = $query->posts;
    }

    return $related_ids;
}

function estimate_post_duration($post_id) {
       $content = strip_tags(get_post_field('post_content', $post_id));
    $word_count = str_word_count($content);
    $minutes = ceil($word_count / 200); // promedio de 200 palabras por minuto

    return $minutes . ' minuto' . ($minutes === 1 ? '' : 's');
}
/**
 * Código para habilitar el endpoint REST API que devuelve las páginas del blog.
 */

 // Add a REST API endpoint to get clean pages
add_action('rest_api_init', function () {
    register_rest_route('blog/v1', '/pages', [
        'methods'  => 'GET',
        'callback' => 'blog_get_clean_pages',
        'permission_callback' => 'blog_rest_permission'
    ]);
});

function blog_get_clean_pages($request) {// Function to get clean pages
    // This function retrieves a paginated list of published pages.
    // It accepts 'page' and 'per_page' parameters to control pagination.
    $page = max(1, (int) $request->get_param('page'));
    $per_page = max(1, min(100, (int) $request->get_param('per_page')));

    // Build query args
    $args = [
        'post_type'      => 'page', // Changed from 'post' to 'page'
        'post_status'    => 'publish',// Only published pages
        'paged'          => $page,// Current page number
        'posts_per_page' => $per_page // Number of pages per page
    ];

    $query = new WP_Query($args);
    $pages = [];

    // Loop through the pages and prepare the response data
    foreach ($query->posts as $post) {
        $pages[] = [
            'id'         => $post->ID, // Page ID
            'title'      => get_the_title($post), // Page title
            'excerpt'    => wp_strip_all_tags(get_the_excerpt($post)), // Page excerpt
            'content'    => wp_kses_post(apply_filters('the_content', $post->post_content)), // Sanitized page content
            'slug'       => $post->post_name, // Page slug
            'link'       => get_permalink($post), // Page permalink
            'date'       => get_the_date('', $post), // Page publication date
            'author_name'=> get_the_author_meta('display_name', $post->post_author) // Author's display name
        ];
    }

    // Prepare the response with pagination information
    $response = [
        'page'        => (int)$page,
        'per_page'    => (int)$per_page,
        'total'       => (int)$query->found_posts,
        'total_pages' => (int)$query->max_num_pages,
        'pages'       => $pages
    ];

    return rest_ensure_response($response);
}


/**
 * Código para habilitar el endpoint REST API que devuelve las categorías del blog con imágenes.
 */

add_action('rest_api_init', function () {
    register_rest_route('blog/v1', '/posttaxonomies', [
        'methods'  => 'GET',
        'callback' => 'api_posts_taxonomies',
        'permission_callback' => 'blog_rest_permission'
    ]);
});

function api_posts_taxonomies($request) {
    $categorias = get_terms([
        'taxonomy'   => 'category',
        'hide_empty' => false,
    ]);

    $resultado = [];

    foreach ($categorias as $cat) {
        $imagen_url = get_field('icono_como_url', 'category_' . $cat->term_id); // ACF devuelve URL si está configurado así
        $imagen_png = get_field('icono_como_png', 'category_' . $cat->term_id); // ACF devuelve URL si está configurado así

        $resultado[] = [
            'id'          => $cat->term_id,
            'name'        => $cat->name,
            'slug'        => $cat->slug,
            'description' => $cat->description,
            'count'       => $cat->count,
            'image_url'       => $imagen_url ?: null,
            'image_png'       => $imagen_png ?: null
        ];
    }

    return rest_ensure_response($resultado);
}


