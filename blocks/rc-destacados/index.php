<?php

/**
 * Plugin Name: THD Carrusel Destacados (RC)
 * Description: Bloque dinámico para carrusel de posts destacados por taxonomía (Guías de venta / Tutoriales).
 * Version:     1.0.1
 * Author:      Tu Nombre / Equipo
 * License:     GPL-2.0+
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('THD_RC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('THD_RC_BLOCK_DIR', THD_RC_PLUGIN_DIR . 'blocks/rc-destacados');

/** Taxonomías requeridas */
function thd_rc_register_taxonomies()
{
    if (!taxonomy_exists('guias_de_venta_destacadas')) {
        register_taxonomy(
            'guias_de_venta_destacadas',
            ['post'],
            [
                'labels' => [
                    'name'          => __('Guías de venta destacadas', 'thd'),
                    'singular_name' => __('Guía de venta destacada', 'thd'),
                ],
                'public'            => true,
                'show_ui'           => true,
                'show_in_rest'      => true,
                'hierarchical'      => false,
                'show_admin_column' => true,
                'rewrite'           => ['slug' => 'guias-venta-destacadas'],
            ]
        );
    }

    if (!taxonomy_exists('tutoriales_destacados')) {
        register_taxonomy(
            'tutoriales_destacados',
            ['post'],
            [
                'labels' => [
                    'name'          => __('Tutoriales destacados', 'thd'),
                    'singular_name' => __('Tutorial destacado', 'thd'),
                ],
                'public'            => true,
                'show_ui'           => true,
                'show_in_rest'      => true,
                'hierarchical'      => false,
                'show_admin_column' => true,
                'rewrite'           => ['slug' => 'tutoriales-destacados'],
            ]
        );
    }
}
add_action('init', 'thd_rc_register_taxonomies');

/** Registrar bloque con render_callback explícito */
function thd_rc_register_block()
{
    $block_dir = THD_RC_BLOCK_DIR;
    if (!file_exists($block_dir . '/block.json')) {
        return;
    }

    // Incluir la función de render
    require_once $block_dir . '/render.php';

    // Registrar bloque desde metadata + callback explícito
    register_block_type_from_metadata($block_dir, [
        'render_callback' => 'thd_render_rc_destacados_block',
    ]);
}
add_action('init', 'thd_rc_register_block');

/** Activación/Desactivación */
function thd_rc_activate()
{
    thd_rc_register_taxonomies();
    flush_rewrite_rules(false);
}
register_activation_hook(__FILE__, 'thd_rc_activate');

function thd_rc_deactivate()
{
    flush_rewrite_rules(false);
}
register_deactivation_hook(__FILE__, 'thd_rc_deactivate');
