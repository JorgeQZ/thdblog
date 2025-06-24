<?php

add_action('after_setup_theme', function () {
    global $wpdb;
    $table_name = $wpdb->prefix . 'api_clients';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id VARCHAR(100) NOT NULL UNIQUE,
            secret_key VARCHAR(255) NOT NULL,
            nombre VARCHAR(100),
            creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
});


add_action('admin_menu', function () {
    if (!is_network_admin()) {
        add_menu_page(
            'Clientes API',
            'Clientes API',
            'manage_options',
            'api-client-manager',
            'mostrar_api_client_manager',
            'dashicons-lock',
            80
        );
    }
});

function mostrar_api_client_manager() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'api_clients';

    if (isset($_POST['nuevo_cliente'])) {
        $nombre = sanitize_text_field($_POST['nombre']);
        $client_id = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nombre));
        $secret_key = bin2hex(random_bytes(32));

        $wpdb->insert($tabla, [
            'client_id'  => $client_id,
            'secret_key' => $secret_key,
            'nombre'     => $nombre
        ]);
        echo '<div class="notice notice-success"><p>Cliente creado.</p></div>';
    }

    if (isset($_GET['eliminar'])) {
        $wpdb->delete($tabla, ['id' => intval($_GET['eliminar'])]);
        echo '<div class="notice notice-warning"><p>Cliente eliminado.</p></div>';
    }

    $clientes = $wpdb->get_results("SELECT * FROM $tabla ORDER BY creado_en DESC");
    ?>
    <div class="wrap">
        <h1>Clientes API</h1>

        <form method="post">
            <input type="text" name="nombre" placeholder="Nombre del cliente" required />
            <input type="submit" name="nuevo_cliente" class="button button-primary" value="Crear Cliente" />
        </form>

        <hr>

        <table class="widefat">
            <thead><tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Client ID</th>
                <th>Secret</th>
                <th>Creado</th>
                <th>Acciones</th>
            </tr></thead>
            <tbody>
                <?php foreach ($clientes as $c): ?>
                    <tr>
                        <td><?= esc_html($c->id) ?></td>
                        <td><?= esc_html($c->nombre) ?></td>
                        <td><code><?= esc_html($c->client_id) ?></code></td>
                        <td style="font-size:0.85em;"><code><?= esc_html($c->secret_key) ?></code></td>
                        <td><?= esc_html($c->creado_en) ?></td>
                        <td>
                            <a href="<?= admin_url('admin.php?page=api-client-manager&eliminar=' . $c->id) ?>" onclick="return confirm('¿Eliminar?')" class="button">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($clientes)) echo '<tr><td colspan="6">No hay clientes aún.</td></tr>'; ?>
            </tbody>
        </table>
    </div>
    <?php
}
