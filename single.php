<?php
get_header();
$id = get_the_ID();
?>
<div class="container">
    <?php
    if (have_posts()):
        while (have_posts()): the_post();
            if (has_post_thumbnail()) {
                echo '<div class="post-featured-image">';
                the_post_thumbnail();
                echo '</div>';
            }
            echo '<nav class="breadcrumbs">';
            echo '<a href="' . esc_url(home_url('/')) . '">Inicio</a> &raquo; ';
            $cats = get_the_category(get_the_ID());
            if (! empty($cats)) {
                $deepest = null;
                $max_depth = -1;
                foreach ($cats as $c) {
                    $depth = count(get_ancestors($c->term_id, 'category'));
                    if ($depth > $max_depth) {
                        $max_depth = $depth;
                        $deepest = $c;
                    }
                }
                if ($deepest) {
                    if ($deepest->parent) {
                        $parent = get_term($deepest->parent, 'category');
                        if ($parent && ! is_wp_error($parent)) {
                            echo '<a href="' . esc_url(get_category_link($parent->term_id)) . '">'
                                . esc_html($parent->name) . '</a> &raquo; ';
                        }
                    }
                    echo '<a href="' . esc_url(get_category_link($deepest->term_id)) . '">'
                        . esc_html($deepest->name) . '</a> &raquo; ';
                }
            }
            echo '<span>' . esc_html(get_the_title()) . '</span>';
            echo '</nav>';
            ?>
    <div class="nota-title"><?php the_title(); ?></div>
    <div class="grid">
        <div class="column">
            <div class="autor">Por: <?php the_author(); ?></div>
            <div class="fecha">Publicado <?php the_date(); ?></div>
        </div>
        <div class="column">
            <div class="duracion">Duración: <b>6 horas</b></div>
            <div class="dificultad">Dificultad: <b>Media</b></div>
        </div>
    </div>
    <div class="nota">
        <div class="contenido">
            <?php
            the_content();
            ?>
        </div>
        <div class="aside">
            <div class="excerpt">
                <?php
                $tags = get_the_terms(get_the_ID(), 'post_tag');

            if (!empty($tags) && !is_wp_error($tags)) :
                // Opcional: limita cuántas etiquetas mostrar
                $max_tags = 12;
                $count = 0;
                ?>
                <div class="tags">
                    <?php foreach ($tags as $tag) :
                        if ($count++ >= $max_tags) {
                            break;
                        }
                        $url = get_term_link($tag);
                        ?>
                    <a class="tag" href="<?php echo esc_url($url); ?>">
                        <?php echo esc_html($tag->name); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php
            endif;
            ?>

                <?php
            // Descripción corta (si la tienes en ACF) o fallback
            $short = get_field('_short_description') ?: '';
            ?>
                <?php if ($short) : ?>
                <div class="descipcion">
                    <?php echo wp_kses_post($short); ?>
                </div>
                <?php endif; ?>
            </div>


            <?php
                    $output = do_shortcode('[post_navigator]');
            if (trim($output) !== ''):
                ?>
            <div class="navegacion">
                <p class="aside-title">
                    Navegación
                </p>
                <?php echo $output; ?>
            </div>
            <?php else:
                echo '<!-- [post_navigator] no devolvió contenido -->';
            endif;
            ?>

            <div class="categorias">
                <p class="aside-title">
                    Categorias relacionadas
                </p>
                <div class="grid">
                    <div class="item">categoria</div>
                    <div class="item">categoria</div>
                    <div class="item">categoria</div>
                    <div class="item">categoria</div>
                </div>
            </div>
        </div>
    </div>
    <div class="carruseles">
        <!-- GUÍAS de VENTA -->
        <?php
        $guias_de_venta = get_field('guias_de_venta');
            if (isset($guias_de_venta['mostrar_gv']) && $guias_de_venta['mostrar_gv'] == 'Si' && !empty($guias_de_venta['related_posts_guias'])):
                ?>
        <section class="rc" data-rc>
            <div class="rc__head">
                <h3 class="rc__title"><span>Guías de </span> <span class="rc__title--accent">Venta</span></h3>
                <div class="rc__controls">
                    <button class="rc__btn" data-rc-prev aria-label="Anterior">‹</button>
                    <button class="rc__btn" data-rc-next aria-label="Siguiente">›</button>
                </div>
            </div>
            <div class="rc__viewport rc__fade">
                <ul class="rc__track">
                    <!-- Repite este <li> por cada post relacionado -->
                    <?php
                            foreach ($guias_de_venta['related_posts_guias'] as $guia):
                                $link = get_permalink($guia);
                                $title = get_the_title($guia);
                                $thumb = get_the_post_thumbnail_url($guia, 'medium_large') ?: get_template_directory_uri().'/img/portada.png';
                                $excerpt = get_the_excerpt($guia) ?: wp_trim_words(wp_strip_all_tags(get_post_field('post_content', $guia)), 10, '…');

                                $tag = 'Guías de Venta';
                                ?>
                    <li class="rc__slide">
                        <article class="rc-card">
                            <a class="rc-card__media" href="<?php echo esc_url($link); ?>">
                                <img class="rc-card__img" src="<?php echo esc_url($thumb); ?>"
                                    alt="<?php echo esc_attr($title); ?>">
                            </a>
                            <div class="rc-card__body">
                                <div class="rc-card__tag"><?php echo esc_html($tag); ?></div>
                                <h4 class="rc-card__title"><a
                                        href="<?php echo esc_url($link); ?>"><?php echo esc_html($title); ?></a></h4>
                                <p class="rc-card__excerpt">
                                    <?php echo wp_trim_words(get_the_excerpt(), 25, '…'); ?></p>
                            </div>
                        </article>
                    </li>

                    <?php
                            endforeach;
                ?>
                </ul>
            </div>
        </section>
        <?php endif; ?>

        <!-- Tutoriales  -->
        <?php
        $guias_de_venta = get_field('tutoriales');
            if (isset($guias_de_venta['mostrar_tt']) && $guias_de_venta['mostrar_tt'] == 'Si' && !empty($guias_de_venta['related_posts_tutoriales'])):
                ?>
        <section class="rc" data-rc>
            <div class="rc__head">
                <h3 class="rc__title"><span>TUTORIALES</span> <span class="rc__title--accent">RELACIONADOS</span></h3>
                <div class="rc__controls">
                    <button class="rc__btn" data-rc-prev aria-label="Anterior">‹</button>
                    <button class="rc__btn" data-rc-next aria-label="Siguiente">›</button>
                </div>
            </div>
            <div class="rc__viewport rc__fade">
                <ul class="rc__track">
                    <!-- Repite este <li> por cada post relacionado -->
                    <?php
                            foreach ($guias_de_venta['related_posts_tutoriales'] as $guia):
                                $link = get_permalink($guia);
                                $title = get_the_title($guia);
                                $thumb = get_the_post_thumbnail_url($guia, 'medium_large') ?: get_template_directory_uri().'/img/portada.png';
                                $excerpt = get_the_excerpt($guia) ?: wp_trim_words(wp_strip_all_tags(get_post_field('post_content', $guia)), 10, '…');

                                $tag = 'TUTORIAL';
                                ?>
                    <li class="rc__slide">
                        <article class="rc-card">
                            <a class="rc-card__media" href="<?php echo esc_url($link); ?>">
                                <img class="rc-card__img" src="<?php echo esc_url($thumb); ?>"
                                    alt="<?php echo esc_attr($title); ?>">
                            </a>
                            <div class="rc-card__body">
                                <div class="rc-card__tag"><?php echo esc_html($tag); ?></div>
                                <h4 class="rc-card__title"><a
                                        href="<?php echo esc_url($link); ?>"><?php echo esc_html($title); ?></a></h4>
                                <p class="rc-card__excerpt">
                                    <?php echo wp_trim_words(get_the_excerpt(), 25, '…'); ?></p>
                            </div>
                        </article>
                    </li>
                    <?php
                            endforeach;
                ?>
                </ul>
            </div>
        </section>
        <?php endif; ?>
    </div>
    <?php
        endwhile;
endif;
?>
</div>
<?php get_footer(); ?>