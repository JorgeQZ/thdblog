<?php
add_action('rest_api_init', function () {
    register_rest_route('blog/v1', '/tags', [
        'methods'  => 'GET',
        'callback' => 'api_post_tags',
         //'permission_callback' => 'blog_rest_permission'
        'permission_callback' => '__return_true'
    ]);
});

function api_post_tags($request) {
    $tags = get_terms([
        'taxonomy'   => 'post_tag',
        'hide_empty' => false,
    ]);

    $resultado = [];

    foreach ($tags as $tag) {
        $resultado[] = [
            'id'          => $tag->term_id,
            'name'        => $tag->name,
            'slug'        => $tag->slug,
            'description' => wp_kses_post($tag->description),
            'count'       => $tag->count
        ];
    }

    return rest_ensure_response($resultado);
}
