<?php

/**
 * Template Name: Test API (token + posts, pages, taxonomies, tags)
 */

$BASE = 'http://localhost:8888/blog/wp-json';

$CLIENT_ID  = 'thd';
$SECRET_KEY = '03d7609a9178867d342e390fdd04297f5a79736c9808b2365de42ec09c9d6c4d';

// ======================
// Helpers cURL
// ======================
function curl_json($url, $method = 'GET', array $headers = [], $body = null, $timeout = 10)
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [$code, $resp, $err];
}

function h_json(array $extra = [])
{
    // Mezcla Content-Type/Accept JSON con headers extra
    return array_values(array_unique(array_merge([
        'Content-Type: application/json',
        'Accept: application/json',
    ], $extra)));
}

// ======================
// 1) Obtener token (POST /auth/v1/token)
// ======================
list($code, $resp, $err) = curl_json(
    $BASE . '/auth/v1/token',
    'POST',
    h_json(),
    json_encode([
        'client_id' => $CLIENT_ID,
        'secret'    => $SECRET_KEY,
        'ttl'       => 3600, // opcional
    ], JSON_UNESCAPED_SLASHES)
);

if ($code !== 200) {
    exit("Error token (HTTP $code): $resp\ncURL: $err");
}
$data  = json_decode($resp, true);
$token = $data['token'] ?? null;
if (!$token) {
    exit("No se recibió token válido");
}

// Header Bearer listo
$authBearer = ['Authorization: Bearer ' . $token];

// (Alternativa) Auth por headers de cliente:
$authClient = [
    'X-Client-ID: ' . $CLIENT_ID,
    'X-Client-Secret: ' . $SECRET_KEY,
];

// ======================
// 2) GET /v1/posts con filtros
//    Ejemplo: per_page, page, s, cat, tag
// ======================
$query = http_build_query([
    'per_page' => 5,
    'page'     => 1,
    's'        => '',       // busca por término (opcional)
    'cat'      => '',       // id de categoría (opcional)
    'tag'      => '',       // slug de tag (opcional)
]);
list($c1, $r1, $e1) = curl_json(
    $BASE . '/v1/posts' . ($query ? ('?' . $query) : ''),
    'GET',
    h_json($authBearer) // o h_json($authClient)
);
if ($c1 !== 200) {
    exit("Error posts (HTTP $c1): $r1\ncURL: $e1");
}
$posts = json_decode($r1, true);

// ======================
// 3) GET Pages
//    Si tienes endpoint custom: /v1/pages
//    Si no, usa core: /wp/v2/pages (sin auth estricta normalmente)
// ======================
$pages_endpoint = $BASE . '/v1/pages'; // <-- usa este si lo registraste tú
list($c2, $r2, $e2) = curl_json($pages_endpoint, 'GET', h_json($authBearer));
if ($c2 !== 200) {
    // Fallback al core si tu endpoint custom aún no existe:
    list($c2b, $r2b, $e2b) = curl_json($BASE . '/wp/v2/pages?per_page=5&page=1', 'GET', h_json());
    if ($c2b !== 200) {
        exit("Error pages (custom:$c2 / core:$c2b): $r2 | $r2b");
    }
    $pages = json_decode($r2b, true);
} else {
    $pages = json_decode($r2, true);
}

// ======================
// 4) GET Taxonomies (custom)
//    Por tus includes parece /blog/v1/posttaxonomies
//    Ajusta si tu namespace cambió
// ======================
list($c3, $r3, $e3) = curl_json(
    $BASE . '/v1/posttaxonomies',
    'GET',
    h_json($authBearer) // o h_json($authClient)
);
if ($c3 !== 200) {
    exit("Error taxonomies (HTTP $c3): $r3\ncURL: $e3");
}
$taxonomies = json_decode($r3, true);

// ======================
// 5) GET Tags (custom)
//    Por tus includes parece /blog/v1/tags
//    (Alternativa core: /wp/v2/tags)
// ======================
list($c4, $r4, $e4) = curl_json(
    $BASE . '/v1/tags',
    'GET',
    h_json($authBearer) // o h_json($authClient)
);
if ($c4 !== 200) {
    // Fallback al core si lo necesitas:
    list($c4b, $r4b, $e4b) = curl_json($BASE . '/wp/v2/tags?per_page=20', 'GET', h_json());
    if ($c4b !== 200) {
        exit("Error tags (custom:$c4 / core:$c4b): $r4 | $r4b");
    }
    $tags = json_decode($r4b, true);
} else {
    $tags = json_decode($r4, true);
}

// ======================
// Output rápido
// ======================
echo "<pre>";
echo "=== POSTS ===\n";
print_r($posts);

echo "\n=== PAGES ===\n";
print_r($pages);

echo "\n=== TAXONOMIES ===\n";
print_r($taxonomies);

echo "\n=== TAGS ===\n";
print_r($tags);
echo "</pre>";
