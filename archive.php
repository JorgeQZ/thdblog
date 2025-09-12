<?php get_header();
// —— Resolver URL del background desde ACF
$default_bg = get_template_directory_uri() . '/img/banner-default.jpg';
$bg_url     = $default_bg;

// Obtener objeto actual de archivo/taxonomía
$term = get_queried_object();

// Validar que sea un término (categoría, etiqueta u otra taxonomía)
if ($term && isset($term->term_id) && isset($term->taxonomy)) {

    // 1) Campo URL directo (ej. imagen_como_url)
    $acf_url = get_field('imagen_como_url', $term->taxonomy . '_' . $term->term_id);
    if (is_string($acf_url)) {
        $acf_url = trim($acf_url);
        if ($acf_url !== '' && filter_var($acf_url, FILTER_VALIDATE_URL)) {
            $bg_url = $acf_url;
        }
    }

    // 2) Campo Imagen/Archivo (ej. imagen)
    if ($bg_url === $default_bg) {
        $acf_img = get_field('imagen', $term->taxonomy . '_' . $term->term_id);

        if (!empty($acf_img)) {
            if (is_array($acf_img) && !empty($acf_img['url'])) {
                $bg_url = $acf_img['url'];
            } elseif (is_numeric($acf_img)) {
                $maybe = wp_get_attachment_image_url((int)$acf_img, 'full');
                if ($maybe) {
                    $bg_url = $maybe;
                }
            } elseif (is_string($acf_img) && filter_var($acf_img, FILTER_VALIDATE_URL)) {
                $bg_url = $acf_img;
            }
        }
    }
}
?>

<div class="banner" style="background-image:url('<?php echo esc_url($bg_url); ?>')">
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
        <?php if (have_posts()) : while (have_posts()) : the_post();

            // — Imagen destacada o fallback
            $size     = 'medium_large'; // ajusta al tamaño que uses en tu tema
            $default  = get_template_directory_uri() . '/img/cover-default.jpg';
            $img_url  = has_post_thumbnail() ? get_the_post_thumbnail_url(get_the_ID(), $size) : $default;
            if (!$img_url) {
                $img_url = $default; // doble seguridad
            }

            // — ALT accesible
            $img_alt = get_the_title();
            if (has_post_thumbnail()) {
                $thumb_id = get_post_thumbnail_id();
                $alt_meta = get_post_meta($thumb_id, '_wp_attachment_image_alt', true);
                if ($alt_meta !== '') {
                    $img_alt = $alt_meta;
                }
            }

            // — srcset (solo si hay destacada)
            $srcset_attr = '';
            if (has_post_thumbnail()) {
                $srcset = wp_get_attachment_image_srcset($thumb_id, $size);
                if ($srcset) {
                    $srcset_attr = ' srcset="' . esc_attr($srcset) . '" sizes="(max-width: 800px) 100vw, 400px"';
                }
            }
        ?>
        <article class="ba-card">
            <a class="ba-card__media" href="<?php the_permalink(); ?>">
                <img class="ba-card__img" src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($img_alt); ?>"
                    loading="lazy" decoding="async" <?php echo $srcset_attr; ?>>
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
        <?php endwhile; else : ?>
        <p class="ba-empty">No se encontraron notas.</p>
        <?php endif; ?>
    </div>
    <!-- Paginación -->
    <nav class="ba-pagination">
        <?php echo paginate_links([ 'prev_text' => '«', 'next_text' => '»' ]);?>
    </nav>

</div>

<?php get_footer(); ?>