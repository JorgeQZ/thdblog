<?php

add_action('rest_api_init', function () {
    register_rest_route('v1', '/tags', [
        'methods'  => 'GET',
        'callback' => 'api_post_tags',
        'permission_callback' => 'blog_rest_permission',
    ]);
});

function api_post_tags($request)
{
    // Filtros opcionales
    $hide_empty = (bool)$request->get_param('hide_empty'); // 0/1
    $search     = sanitize_text_field((string)$request->get_param('s'));

    $args = [
        'taxonomy'   => 'post_tag',
        'hide_empty' => $hide_empty,
    ];
    if ($search !== '') {
        $args['search'] = $search;
    }

    $terms = get_terms($args);
    if (is_wp_error($terms)) {
        return rest_ensure_response(['error' => $terms->get_error_message()], 400);
    }

    $out = [];
    foreach ($terms as $t) {
        $assets = thd_build_term_assets('post_tag', $t->term_id);

        $out[] = [
            'id'          => (int)$t->term_id,
            'taxonomy'    => 'post_tag',
            'name'        => $t->name,
            'slug'        => $t->slug,
            'description' => wp_kses_post($t->description),
            'count'       => (int)$t->count,
            // Assets “mejor opción”
            'icon'        => $assets['icon'],
            'image'       => $assets['image'],
            // Campos detallados
            'assets'      => [
                'iconoComoUrl'  => $assets['iconoComoUrl'],
                'iconoComoPng'  => $assets['iconoComoPng'],
                'imagenComoUrl' => $assets['imagenComoUrl'],
                'imagenPng'     => $assets['imagenPng'],
            ],
        ];
    }
    return rest_ensure_response($out);
}
