<?php
/**
 * Código para habilitar el endpoint REST API que valida un cliente API.
 */
function validate_api_client($request) {
    global $wpdb;
    $client_id = $request->get_header('X-Client-ID');
    $secret    = $request->get_header('X-Client-Secret');

    if (!$client_id || !$secret) return false;

    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT secret_key FROM {$wpdb->prefix}api_clients WHERE client_id = %s", $client_id)
    );

    return $row && hash_equals($row->secret_key, $secret);
}

/**
 * Código para habilitar el endpoint REST API que devuelve las publicaciones del blog.
 */
function blog_rest_permission($request) {
    global $wpdb;

    $client_id = $request->get_header('X-Client-ID');
    $secret    = $request->get_header('X-Client-Secret');

    if (!$client_id || !$secret) return false;

    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT secret_key FROM {$wpdb->prefix}api_clients WHERE client_id = %s", $client_id)
    );

    return $row && hash_equals($row->secret_key, $secret);
}
/**
 * Código para habilitar el endpoint REST API que genera un token JWT.
 * Este token se usará para autenticar solicitudes a otros endpoints.
 */

add_action('rest_api_init', function () {
    register_rest_route('blog/v1', '/token', [
        'methods' => 'POST',
        'callback' => 'blog_generate_token',
        'permission_callback' => '__return_true',
    ]);
});

function blog_generate_token($request) {
    $client_id = $request->get_param('client_id');
    $secret    = $request->get_param('secret');

    global $wpdb;
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT secret_key FROM {$wpdb->prefix}api_clients WHERE client_id = %s", $client_id)
    );

    if (!$row || !hash_equals($row->secret_key, $secret)) {
        return new WP_REST_Response(['error' => 'Credenciales inválidas'], 403);
    }

    $issued_at = time();
    $expires_at = $issued_at + 3600;

    $payload = [
        'iat' => $issued_at,
        'exp' => $expires_at,
        'client_id' => $client_id,
    ];

    $payload_b64 = base64_encode(json_encode($payload));
    $signature = hash_hmac('sha256', $base64_payload, $row->secret_key);
    $token = $base64_payload . '.' . $signature;

    return ['token' => $token, 'expires_in' => 3600];
}
