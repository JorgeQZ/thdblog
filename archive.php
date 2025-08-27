<?php get_header(); ?>
<div class="banner"
    style="background-image: url(<?php echo get_template_directory_uri().'/img/banner-default.jpg'; ?>)">
    <div class="title">
        <?php the_archive_title(); ?>
    </div>
</div>

<div class="container">
    <div class="filters-cont">
        <form class="ba-controls" method="get" action="">
            <label class="ba-search">
                <input type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>"
                    placeholder="Buscar nota" aria-label="Buscar nota">
                <button class="ba-search__btn" aria-label="Buscar">🔍</button>
            </label>

            <label class="ba-select">
                <select name="sort_title" id="sort_title" aria-label="Ordenar por título">
                    <option value="">Ordenar A-Z, Z-A</option>
                    <option value="asc" <?php selected($_GET['sort_title'] ?? '', 'asc');  ?>>A-Z</option>
                    <option value="desc" <?php selected($_GET['sort_title'] ?? '', 'desc'); ?>>Z-A</option>
                </select>
            </label>

            <label class="ba-select">
                <select name="sort_date" id="sort_date" aria-label="Ordenar por fecha">
                    <option value="">Ordenar por fecha de publicación</option>
                    <option value="desc" <?php selected($_GET['sort_date'] ?? '', 'desc'); ?>>Más recientes</option>
                    <option value="asc" <?php selected($_GET['sort_date'] ?? '', 'asc');  ?>>Más antiguas</option>
                </select>
            </label>
        </form>
    </div>

    <!-- Controles -->


    <!-- Grid de tarjetas -->
    <div class="ba-grid">
        <?php
        if (have_posts()):
            while (have_posts()):
                the_post();
                ?>
        <article class="ba-card">
            <a class="ba-card__media" href="<?php the_permalink(); ?>">
                <img class="ba-card__img" src="<?php echo esc_url(get_template_directory_uri().'/img/portada.png'); ?>"
                    alt="<?php the_title_attribute(); ?>">
            </a>

            <div class="ba-card__body">
                <h3 class="ba-card__title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h3>
                <time class="ba-card__date" datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                    <?php echo esc_html(get_the_date('d/m/Y')); ?>
                </time>
                <p class="ba-card__excerpt">
                    <?php echo esc_html(wp_trim_words(get_the_excerpt() ?: wp_strip_all_tags(get_the_content()), 24, '…')); ?>
                </p>
                <a class="ba-card__cta" href="<?php the_permalink(); ?>">VER NOTA</a>
            </div>
        </article>

        <?php
            endwhile;
        else:
            ?>
        <p class="ba-empty">No se encontraron notas.</p>
        <?php endif; ?>
    </div>

    <!-- Paginación -->
    <nav class="ba-pagination">
        <?php echo paginate_links([ 'prev_text' => '«', 'next_text' => '»' ]);?>
    </nav>

</div>

<?php get_footer(); ?>