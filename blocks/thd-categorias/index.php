<?php

/**
 * Registro del bloque (editor + estilos)
 */
add_action('init', function () {
    $dir = get_stylesheet_directory() . '/blocks/thd-categorias';
    $uri = get_stylesheet_directory_uri() . '/blocks/thd-categorias';

    // Editor JS
    wp_register_script(
        'thd-categorias-editor',
        $uri . '/editor.js',
        [ 'wp-blocks','wp-i18n','wp-element','wp-components','wp-block-editor','wp-data' ],
        filemtime($dir . '/editor.js')
    );

    // Estilos front
    wp_register_style(
        'thd-categorias-style',
        $uri . '/css/style.css',
        [],
        filemtime($dir . '/css/style.css')
    );

    register_block_type($dir);
});
