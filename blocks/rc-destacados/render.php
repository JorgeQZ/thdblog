<?php
/**
 * Render callback para el bloque thd/rc-destacados
 */
if (!function_exists('thd_render_rc_destacados_block')) {
    function thd_render_rc_destacados_block($attributes = [], $content = '', $block = null)
    {
        // ——— Back-compat: soportar bloques antiguos con "tipo"
        // tipo: 'guia' | 'tutorial' -> map a slugs actuales
        $legacy_to_slug = [
            'guia'     => 'guia-de-venta-destacada',
            'tutorial' => 'tutorial-destacado',
        ];

        // Nuevo atributo 'termino' (slug) o fallback al viejo 'tipo'
        if (!empty($attributes['termino'])) {
            $termino = (string) $attributes['termino'];
        } elseif (!empty($attributes['tipo']) && isset($legacy_to_slug[$attributes['tipo']])) {
            $termino = $legacy_to_slug[$attributes['tipo']];
        } else {
            $termino = 'guia-de-venta-destacada'; // default
        }

        $postsPerPage = isset($attributes['postsPerPage']) ? (int)$attributes['postsPerPage'] : 8;

        if (!taxonomy_exists('destacado')) {
            return '';
        }
        $term_obj = get_term_by('slug', $termino, 'destacado');
        if (!$term_obj || is_wp_error($term_obj)) {
            return '';
        }

        $q = new WP_Query([
            'post_type'           => 'post',
            'posts_per_page'      => $postsPerPage,
            'post_status'         => 'publish',
            'ignore_sticky_posts' => true,
            'tax_query'           => [[
                'taxonomy' => 'destacado',
                'field'    => 'slug',
                'terms'    => [$termino],
            ]],
        ]);
        if (!$q->have_posts()) {
            return '';
        }

        // Etiquetas para títulos
        $is_tutorial = ($termino === 'tutorial-destacado');
        $titleMain   = $is_tutorial ? 'Tutoriales ' : 'Guías de ';
        $titleAccent = $is_tutorial ? 'Destacados' : 'Venta';
        $tagLabel    = $is_tutorial ? 'Tutorial Destacado' : 'Guía de Venta Destacada';

        ob_start(); ?>
<section class="rc" data-rc>
    <div class="rc__head">
        <h3 class="rc__title">
            <span><?php echo esc_html($titleMain); ?></span>
            <span class="rc__title--accent"><?php echo esc_html($titleAccent); ?></span>
        </h3>
        <div class="rc__controls">
            <button class="rc__btn" data-rc-prev aria-label="<?php echo esc_attr__('Anterior', 'thd'); ?>">‹</button>
            <button class="rc__btn" data-rc-next aria-label="<?php echo esc_attr__('Siguiente', 'thd'); ?>">›</button>
        </div>
    </div>
    <div class="rc__viewport rc__fade">
        <ul class="rc__track">
            <?php while ($q->have_posts()) : $q->the_post();
                $post_id = get_the_ID();
                $link    = get_permalink($post_id);
                $title   = get_the_title($post_id);
                $thumb   = get_the_post_thumbnail_url($post_id, 'medium_large') ?: get_template_directory_uri().'/img/portada.png';
                $excerpt = get_the_excerpt($post_id) ?: wp_trim_words(wp_strip_all_tags(get_post_field('post_content', $post_id)), 25, '…');
                ?>
            <li class="rc__slide">
                <article class="rc-card">
                    <a class="rc-card__media" href="<?php echo esc_url($link); ?>">
                        <img class="rc-card__img" src="<?php echo esc_url($thumb); ?>"
                            alt="<?php echo esc_attr($title); ?>">
                    </a>
                    <div class="rc-card__body">
                        <div class="rc-card__tag"><?php echo esc_html($tagLabel); ?></div>
                        <h4 class="rc-card__title"><a
                                href="<?php echo esc_url($link); ?>"><?php echo esc_html($title); ?></a></h4>
                        <p class="rc-card__excerpt"><?php echo esc_html($excerpt); ?></p>
                    </div>
                </article>
            </li>
            <?php endwhile;
        wp_reset_postdata(); ?>
        </ul>
    </div>
</section>
<?php
        return ob_get_clean();
    }
}
