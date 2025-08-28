<?php
/**
 * Plantilla de formulario de búsqueda
 */
$unique_id = uniqid('search-');
?>
<form role="search" method="get" class="search-form thd-search__form" action="<?php echo esc_url(home_url('/')); ?>">
    <label class="screen-reader-text" for="<?php echo esc_attr($unique_id); ?>">
        <?php _e('Buscar:', 'thd'); ?>
    </label>
    <input type="search" id="<?php echo esc_attr($unique_id); ?>" class="search-field thd-search__input"
        placeholder="<?php esc_attr_e('Buscar…', 'thd'); ?>" value="<?php echo get_search_query(); ?>" name="s"
        autocomplete="off" aria-autocomplete="list" aria-controls="thd-search-suggestions" />
    <button type="submit" class="search-submit thd-search__submit">
        <span class="screen-reader-text"><?php _e('Buscar', 'thd'); ?></span>
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path
                d="M15.5 14h-.79l-.28-.27a6 6 0 10-.71.71l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0a4 4 0 110-8 4 4 0 010 8z" />
        </svg>
    </button>
</form>