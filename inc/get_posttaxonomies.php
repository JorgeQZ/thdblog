<?php

/**
 * Código para habilitar el endpoint REST API que devuelve las categorías del blog con imágenes.
 */

add_action('rest_api_init', function () {
    register_rest_route('v1', '/posttaxonomies', [
        'methods'  => 'GET',
        'callback' => 'api_posts_taxonomies',
         //'permission_callback' => 'blog_rest_permission'
        'permission_callback' => '__return_true'
    ]);
});

function api_posts_taxonomies($request) {
    $categorias = get_terms([
        'taxonomy'   => 'category',
        'hide_empty' => false,
    ]);

    $resultado = [];

    $categorias = get_terms([
        'taxonomy'   => 'category',
        'hide_empty' => false,
    ]);

    $resultado = [];


    foreach ($categorias as $cat) {
        $parent_id = $cat->parent;
        $parent_name = $parent_id ? get_cat_name($parent_id) : null;

        $resultado[] = [
            'id'           => $cat->term_id,
            'name'         => $cat->name,
            'slug'         => $cat->slug,
            'description'  => wp_kses_post($cat->description),
            'count'        => $cat->count,
            'parent_id'    => $parent_id,
            'parent_name'  => $parent_name,
            'image_url'    => get_field('icono_como_url', 'category_' . $cat->term_id) ?: null,
            'image_png'    => get_field('icono_como_png', 'category_' . $cat->term_id) ?: null
        ];
    }

    return rest_ensure_response($resultado);
}