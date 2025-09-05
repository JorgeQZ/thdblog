<?php
/**
 * Auth helpers y endpoints (JWT HS256 + Client headers)
 * Nota: el archivo se llama jtw_token.php, pero lo correcto sería jwt_token.php
 */

if (!defined('ABSPATH')) exit;

/** =========================
 *  Utilidades Base64URL/JWT
 *  ========================= */
if (!function_exists('thd_b64url_encode')) {
    function thd_b64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
if (!function_exists('thd_b64url_decode')) {
    function thd_b64url_decode($data) {
        $data = strtr($data, '-_', '+/');
        $pad  = strlen($data) % 4;
        if ($pad) $data .= str_repeat('=', 4 - $pad);
        return base64_decode($data);
    }
}

/** Devuelve fila del cliente por client_id */
if (!function_exists('thd_get_client_row')) {
    function thd_get_client_row($client_id) {
        global $wpdb;
        if (!$client_id) return null;
        $table = $wpdb->prefix . 'api_clients';
        return $wpdb->get_row(
            $wpdb->prepare("SELECT client_id, secret_key FROM {$table} WHERE client_id = %s", $client_id)
        );
    }
}

/** Construye un JWT HS256 */
if (!function_exists('thd_jwt_sign')) {
    function thd_jwt_sign(array $payload, $secret) {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $h = thd_b64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $p = thd_b64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $sig = hash_hmac('sha256', "{$h}.{$p}", (string)$secret, true);
        $s = thd_b64url_encode($sig);
        return "{$h}.{$p}.{$s}";
    }
}

/** Verifica un JWT HS256 y devuelve el payload (array) o WP_Error */
if (!function_exists('thd_jwt_verify')) {
    function thd_jwt_verify($token) {
        if (!is_string($token) || substr_count($token, '.') !== 2) {
            return new WP_Error('jwt_malformed', 'Token mal formado');
        }
        list($h, $p, $s) = explode('.', $token, 3);
        $header  = json_decode(thd_b64url_decode($h), true);
        $payload = json_decode(thd_b64url_decode($p), true);
        $sig     = thd_b64url_decode($s);

        if (!is_array($header) || !is_array($payload)) {
            return new WP_Error('jwt_decode', 'No se pudo decodificar el token');
        }
        if (!isset($header['alg']) || strtoupper($header['alg']) !== 'HS256') {
            return new WP_Error('jwt_alg', 'Algoritmo no soportado');
        }
        if (empty($payload['client_id'])) {
            return new WP_Error('jwt_payload', 'Falta client_id en payload');
        }
        // Busca el secreto del cliente
        $row = thd_get_client_row(sanitize_text_field($payload['client_id']));
        if (!$row) {
            return new WP_Error('jwt_client', 'Cliente no encontrado');
        }
        // Recalcula firma
        $calc = hash_hmac('sha256', "{$h}.{$p}", (string)$row->secret_key, true);
        if (!hash_equals($calc, (string)$sig)) {
            return new WP_Error('jwt_signature', 'Firma inválida');
        }
        // Expiración
        if (!empty($payload['exp']) && time() >= (int)$payload['exp']) {
            return new WP_Error('jwt_expired', 'Token expirado');
        }
        return $payload;
    }
}

/** Valida por headers X-Client-ID / X-Client-Secret */
if (!function_exists('validate_api_client')) {
    function validate_api_client($request) {
        $client_id = $request->get_header('X-Client-ID');
        $secret    = $request->get_header('X-Client-Secret');
        if (!$client_id || !$secret) return false;

        $row = thd_get_client_row(sanitize_text_field($client_id));
        if (!$row) return false;

        // Si guardas el secret en texto plano:
        return hash_equals((string)$row->secret_key, (string)$secret);

        // Recomendado (si guardas hash bcrypt/argon2):
        // return password_verify((string)$secret, (string)$row->secret_key);
    }
}

/**
 * Permission callback "ESTRICTO" para endpoints protegidos.
 * Acepta:
 *  - Authorization: Bearer <jwt>
 *  - o bien headers X-Client-ID + X-Client-Secret
 */
if (!function_exists('blog_rest_permission_strict')) {
    function blog_rest_permission_strict($request) {
        // 1) Bearer JWT
        $auth = $request->get_header('Authorization');
        if (is_string($auth) && stripos($auth, 'Bearer ') === 0) {
            $token = trim(substr($auth, 7));
            $ok = thd_jwt_verify($token);
            if (!is_wp_error($ok)) return true;
        }
        // 2) Client headers
        if (validate_api_client($request)) {
            return true;
        }
        return false;
    }
}

/**
 * Compatibilidad: si NO existe ya blog_rest_permission,
 * define uno que usa la versión estricta. (Evita colisiones.)
 */
if (!function_exists('blog_rest_permission')) {
    function blog_rest_permission($request) {
        return blog_rest_permission_strict($request);
    }
}

/** =========================
 *  Endpoint: emitir JWT
 *  ========================= */
add_action('rest_api_init', function () {
    register_rest_route('auth/v1', '/token', [
        'methods'             => 'POST',
        'callback'            => 'blog_generate_token',
        'permission_callback' => '__return_true', // validamos dentro
    ]);
});

/**
 * Body aceptado: JSON o x-www-form-urlencoded con:
 *  - client_id
 *  - secret
 * Opcional:
 *  - ttl (segundos; default 3600, máx 86400)
 */
function blog_generate_token($request)
{
    $client_id = sanitize_text_field((string)$request->get_param('client_id'));
    $secret    = (string)$request->get_param('secret');

    if (!$client_id || !$secret) {
        return new WP_REST_Response(['error' => 'Faltan credenciales'], 400);
    }

    $row = thd_get_client_row($client_id);
    if (!$row) {
        return new WP_REST_Response(['error' => 'Credenciales inválidas'], 403);
    }

    // Si guardas hash: password_verify($secret, $row->secret_key)
    if (!hash_equals((string)$row->secret_key, (string)$secret)) {
        return new WP_REST_Response(['error' => 'Credenciales inválidas'], 403);
    }

    $now = time();
    $ttl = (int)$request->get_param('ttl');
    if ($ttl <= 0) $ttl = 3600;
    $ttl = min($ttl, 86400); // máx 24h

    $payload = [
        'iat'       => $now,
        'exp'       => $now + $ttl,
        'client_id' => $client_id,
    ];

    $token = thd_jwt_sign($payload, (string)$row->secret_key);

    return new WP_REST_Response([
        'token'      => $token,
        'expires_in' => $ttl,
        'token_type' => 'Bearer',
    ], 200);
}
