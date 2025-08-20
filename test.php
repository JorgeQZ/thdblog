<?php
/**
 * Template Name: Test API
 */
get_header();

$id = 8836;
$get_meta = fn($key) => get_post_meta($id, $key, true);

// echo "<pre>";
// print_r($get_meta('_posttype'));
// echo "</pre>";


$endpoint = 'http://localhost:8888/blog/wp-json/auth/v1/token';

$client_id = 'thd';
$secret_key = '03d7609a9178867d342e390fdd04297f5a79736c9808b2365de42ec09c9d6c4d';
$endpoint = 'http://localhost:8888/blog/wp-json/v1/posts';
// // $endpoint = 'http://localhost:8888/blog/wp-json/v1/posttaxonomies';
// // $endpoint = 'http://localhost:8888/blog/wp-json/v1/tags';

$headers = [
    'X-Client-ID: thd',
    'X-Client-Secret: 03d7609a9178867d342e390fdd04297f5a79736c9808b2365de42ec09c9d6c4d',
    'Content-Type: application/json'
];

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => $headers
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    exit("Error al llamar a la API (HTTP $http_code): $response");
}

$data = json_decode($response, true);

echo "<pre>";
print_r($data);
// echo "</pre>";
get_footer();