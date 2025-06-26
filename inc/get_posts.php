<?php

add_action('rest_api_init', function () {
    register_rest_route('blog/v1', '/posts', [
        'methods'  => 'GET',
        'callback' => 'blog_get_posts',
        //'permission_callback' => 'blog_rest_permission'
        'permission_callback' => '__return_true'
    ]);
});

function blog_get_posts($request) {
    $page     = max(1, (int) $request->get_param('page'));
    $per_page = max(100, min(100, (int) $request->get_param('per_page')));

    $args = [
        'post_type'      => 'post',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'post_status'    => 'publish'
    ];

    $query = new WP_Query($args);
    $posts = [];

    foreach ($query->posts as $post) {
        $id = $post->ID;
        $author_id = $post->post_author;
        $get_meta = fn($key) => get_post_meta($id, $key, true);

        $posts[] = [
            'id'                => $id,
            'date'              => $post->post_date,
            'modified'          => $post->post_modified,
            'status'            => $post->post_status,
            'link'              => get_permalink($post),
            'postType'          => $get_meta('_posttype') ?: 'Tutorial',
            'title'             => get_the_title($post),
            'author'            => get_the_author_meta('display_name', $author_id),
            'authorDescription' => get_the_author_meta('description', $author_id) ?: 'null',
            'steps'             => get_post_meta($id, 'how-to__steps', true) ?: 'null',
            'difficulty'        => $get_meta('_difficulty') ?: 'null',
            'duration'          => estimate_post_duration($id),
            'thumbnail'         => get_the_post_thumbnail_url($id, 'medium') ?: 'null',
            'mainImage'         => get_the_post_thumbnail_url($id, 'full')?: 'null',
            'shortDescription'  => $get_meta('_short_description')?: 'null',
            'video'             => $get_meta('_video_url')?: 'null',
            'categories'        => array_map(function($cat_id) {return get_cat_name($cat_id); }, wp_get_post_categories($id)),
            'tags'              => array_map(function($tag) {return $tag->name; }, wp_get_post_tags($id)),
            'navigator'         => generate_navigator_from_steps($id) ?: 'null',
            'relatedPosts'      => get_related_posts($id) ?: 'null',
            'attributes'        => json_decode($get_meta('_attributes')) ?: 'null',
            'content'           => wp_kses_post(apply_filters('the_content', $post->post_content)) . render_steps_as_html($id),

        ];
    }

    return [
        'page'         => $page,
        'per_page'     => $per_page,
        'total'        => (int) $query->found_posts,
        'total_pages'  => (int) $query->max_num_pages,
        'posts'        => $posts
    ];
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
 *
 */

/**
 * Código para habilitar el endpoint REST API que devuelve las páginas del blog.
 */

 // Add a REST API endpoint to get clean pages
add_action('rest_api_init', function () {
    register_rest_route('blog/v1', '/pages', [
        'methods'  => 'GET',
        'callback' => 'blog_get_clean_pages',
        //'permission_callback' => 'blog_rest_permission'
        'permission_callback' => '__return_true'
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
    return [
        'page'        => (int)$page,
        'per_page'    => (int)$per_page,
        'total'       => (int)$query->found_posts,
        'total_pages' => (int)$query->max_num_pages,
        'pages'       => $pages
    ];
}

