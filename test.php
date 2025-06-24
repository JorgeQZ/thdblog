<?php
/**
 * Template Name: Test API
 */

 global $wpdb;
$row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}api_clients WHERE client_id = 'thd'");
print_r($row);

$endpoint = 'http://localhost:8888/blog/wp-json/blog/v1/token';

$client_id = 'thd';
$secret_key = 'd277294678da6708c9e3be79ca42809948afb1d6980f963ab1d1de9adf763f8d';

$data = [
    'client_id' => $client_id,
    'secret'    => $secret_key,
];

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    exit("Error al obtener token (HTTP $http_code): $response");
}

$body = json_decode($response, true);
$token = $body['token'] ?? null;

if (!$token) {
    exit("Token inválido.");
}

$api_endpoint = 'http://localhost:8888/blog/wp-json/blog/v1/posts';

$ch = curl_init($api_endpoint);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>Token:</strong> $token</p>";
echo "<p><strong>Decoded payload:</strong><br>";
print_r(json_decode(base64_decode(strtr(explode('.', $token)[0], '-_', '+/')), true));
echo "</p>";

if ($http_code !== 200) {
    exit("Error al llamar a la API (HTTP $http_code): $response");
}

$data = json_decode($response, true);

echo "<pre>";
print_r($data);
echo "</pre>";
