<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_theme_support('post-thumbnails'); // Enable post thumbnails support for the theme


// Enable CORS for REST API requests
// This code allows cross-origin requests to the REST API, enabling access from different domains.
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

/**
 * Código para habilitar el endpoint REST API que devuelve los posts del blog.
 * Este código permite acceder a los posts del blog a través de la REST API de WordPress.
 * Los posts se devuelven en formato JSON con información básica como título, contenido, categorías, etiquetas y autor.
 */
// Add a REST API endpoint to get blog posts
// This endpoint retrieves all published posts with their details.
// It includes title, excerpt, content, slug, link, date, categories, tags,
add_action('rest_api_init', function () {
    register_rest_route('blog/v1', '/posts', [
        'methods'  => 'GET',
        'callback' => 'blog_get_posts',
        'permission_callback' => '__return_true'
    ]);
});

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
            'content' => apply_filters('the_content', $post->post_content) . render_steps_as_html($post->ID),
            'author'            => get_the_author_meta('display_name', $author_id),
            'authorDescription' => get_the_author_meta('description', $author_id),
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

    return $posts;
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
        'permission_callback' => '__return_true'
    ]);
});

function blog_get_clean_pages($request) {// Function to get clean pages
    // This function retrieves a paginated list of published pages.
    // It accepts 'page' and 'per_page' parameters to control pagination.
    $page = $request->get_param('page') ?: 1;
    $per_page = $request->get_param('per_page') ?: 10;


    // Validate the 'page' and 'per_page' parameters
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
            'content'    => apply_filters('the_content', $post->post_content), // Page content with filters applied
            'slug'       => $post->post_name, // Page slug
            'link'       => get_permalink($post), // Page permalink
            'date'       => get_the_date('', $post), // Page publication date
            'author_name'=> get_the_author_meta('display_name', $post->post_author) // Author's display name
        ];
    }

    // Prepare the response with pagination information
    return [
        'page'        => (int)$page,
        'per_page'    => (int)$per_page,
        'total'       => (int)$query->found_posts,
        'total_pages' => (int)$query->max_num_pages,
        'pages'       => $pages
    ];
}


/**
 * Código para habilitar el endpoint REST API que devuelve las categorías del blog con imágenes.
 */

add_action('rest_api_init', function () {
    register_rest_route('blog/v1', '/posttaxonomies', [
        'methods'  => 'GET',
        'callback' => 'api_posts_taxonomies',
        'permission_callback' => '__return_true'
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
?>