<?php
/**
 * Template Name: Test API
 */



$endpoint = 'http://localhost:8888/blog/wp-json/blog/v1/token';

$client_id = 'thd';
$secret_key = 'd277294678da6708c9e3be79ca42809948afb1d6980f963ab1d1de9adf763f8d';
$endpoint = 'http://localhost:8888/blog/wp-json/blog/v1/posts?page=1&per_page=10';

$headers = [
    'X-Client-ID: thd',
    'X-Client-Secret: d277294678da6708c9e3be79ca42809948afb1d6980f963ab1d1de9adf763f8d',
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
echo "</pre>";