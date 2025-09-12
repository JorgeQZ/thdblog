<?php

/**
 * Código para habilitar el endpoint REST API que devuelve las categorías del blog con imágenes.
 */
add_action('rest_api_init', function () {
    register_rest_route('v1', '/posttaxonomies', [
        'methods'  => 'GET',
        'callback' => 'api_posts_taxonomies',
        'permission_callback' => 'blog_rest_permission',
    ]);
});

function api_posts_taxonomies($request)
{
    // Filtros opcionales
    $hide_empty = (bool)$request->get_param('hide_empty'); // 0/1
    $search     = sanitize_text_field((string)$request->get_param('s'));
    $parent     = $request->get_param('parent');
    $parent     = is_numeric($parent) ? (int)$parent : '';

    $args = [
        'taxonomy'   => 'category',
        'hide_empty' => $hide_empty,
    ];
    if ($search !== '') {
        $args['search'] = $search;
    }
    if ($parent !== '') {
        $args['parent'] = $parent;
    }

    $terms = get_terms($args);
    if (is_wp_error($terms)) {
        return rest_ensure_response(['error' => $terms->get_error_message()], 400);
    }

    $out = [];
    foreach ($terms as $t) {
        $parent_id   = (int)$t->parent;
        $parent_name = $parent_id ? get_cat_name($parent_id) : null;

        $assets = thd_build_term_assets('category', $t->term_id);

        $out[] = [
            'id'           => (int)$t->term_id,
            'taxonomy'     => 'category',
            'name'         => $t->name,
            'slug'         => $t->slug,
            'description'  => wp_kses_post($t->description),
            'count'        => (int)$t->count,
            'parent_id'    => $parent_id,
            'parent_name'  => $parent_name,
            // Assets “mejor opción”
            'icon'         => $assets['icon'],
            'image'        => $assets['image'],
            // Campos detallados
            'assets'       => [
                'iconoComoUrl'  => $assets['iconoComoUrl'],
                'iconoComoPng'  => $assets['iconoComoPng'],
                'imagenComoUrl' => $assets['imagenComoUrl'],
                'imagenPng'     => $assets['imagenPng'],
            ],
        ];
    }
    return rest_ensure_response($out);
}
