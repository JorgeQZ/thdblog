<?php

$BASE = 'https://homedepotmexico-develop.go-vip.net/ideas-y-proyectos/wp-json';
$ID = 'thd';
$SEC = '5da1d0f362fc379e9e6e43ce9785f6c0fc15709f2f16703fdbddd36bbb67dadc';

// Token
$ch = curl_init($BASE.'/auth/v1/token');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json','Accept: application/json'],
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => json_encode(['client_id' => $ID,'secret' => $SEC,'ttl' => 3600])
]);
$tok = json_decode(curl_exec($ch), true)['token'] ?? null;
curl_close($ch);
if (!$tok) {
    die('No token');
}

function get_json($url, $tok)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => ['Accept: application/json',"Authorization: Bearer $tok"]
    ]);
    $out = curl_exec($ch);
    curl_close($ch);
    return $out;
}

echo get_json($BASE.'/v1/posts?per_page=5&page=1', $tok),PHP_EOL;
echo get_json($BASE.'/v1/pages', $tok),PHP_EOL;
echo get_json($BASE.'/v1/posttaxonomies', $tok),PHP_EOL;
echo get_json($BASE.'/v1/tags', $tok),PHP_EOL;