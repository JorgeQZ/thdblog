<?php
get_header();
$id = get_the_ID(  );
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

          // Breadcrumbs debajo de la imagen destacada
echo '<nav class="breadcrumbs">';
echo '<a href="' . esc_url(home_url('/')) . '">Inicio</a> &raquo; ';

// Obtén todas las categorías del post
$cats = get_the_category(get_the_ID());

if ( ! empty($cats) ) {
    // Elegimos la categoría más profunda (mayor nivel de anidación)
    $deepest = null;
    $max_depth = -1;

    foreach ($cats as $c) {
        $depth = count( get_ancestors( $c->term_id, 'category' ) );
        if ( $depth > $max_depth ) {
            $max_depth = $depth;
            $deepest = $c;
        }
    }

        if ( $deepest ) {
        // Si tiene padre, imprimimos solo el padre inmediato y luego el hijo
        if ( $deepest->parent ) {
            $parent = get_term( $deepest->parent, 'category' );
            if ( $parent && ! is_wp_error($parent) ) {
                echo '<a href="' . esc_url( get_category_link($parent->term_id) ) . '">'
                    . esc_html( $parent->name ) . '</a> &raquo; ';
            }
        }
        // La subcategoría (o la categoría si no tiene padre)
        echo '<a href="' . esc_url( get_category_link($deepest->term_id) ) . '">'
            . esc_html( $deepest->name ) . '</a> &raquo; ';
            }
        }

        // Título del post
        echo '<span>' . esc_html( get_the_title() ) . '</span>';
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
          the_content( );
           ?>
        </div>

        <div class="aside">
            <div class="excerpt">
                <div class="tags">
                    <div class="tag">etiqueta</div>
                    <div class="tag">etiqueta</div>
                    <div class="tag">etiqueta</div>
                    <div class="tag">etiqueta</div>
                </div>
                <div class="descipcion">
                    Lorem ipsum dolor sit amet consectetur adipisicing elit. Voluptas, beatae non quasi iusto expedita
                    laudantium ducimus. Officiis, iure. Recusandae cum perferendis illo accusantium veniam in esse
                    deleniti temporibus rerum adipisci.
                </div>
            </div>


            <?php
            $nav = generate_navigator_from_steps($id);
            print_r($nav) ?: null;

            if($nav != null):
            ?>
            <div class="navegacion">
                <p class="aside-title">
                    Navegación
                </p>
                <nav>
                    <ol>
                        <?php
                        foreach($nav as $link):
                            echo '<li>'.$link.'</li>';
                        endforeach;
                        ?>
                    </ol>
                </nav>
            </div>
            <?php endif; ?>


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
    <?php
        endwhile;
    endif;
    ?>
</div>
<?php get_footer(); ?>