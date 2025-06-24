<?php

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
            'count'       => $cat->count,
            'image_url'       => $imagen_url ?: null,
            'image_png'       => $imagen_png ?: null
        ];
    }

    return rest_ensure_response($resultado);
}