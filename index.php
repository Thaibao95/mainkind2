<?php

declare(strict_types=1);

const MKD_RELEASE = '300a10f0c0f6559e6abdfa920a1defa3f407cf02ec556cfea5cf65b6140a52a5';
$root = __DIR__;
$marker = $root . '/.mankind-release';
$installed = is_file($root . '/public/index.php') && is_file($marker) && trim((string) @file_get_contents($marker)) === MKD_RELEASE;

if (!$installed) {
    $parts = glob($root . '/.mankind-bundle-*.txt') ?: [];
    sort($parts, SORT_NATURAL);
    if (!$parts) {
        http_response_code(503);
        exit('Deployment bundle is missing.');
    }

    $encoded = '';
    foreach ($parts as $part) {
        $encoded .= trim((string) file_get_contents($part));
    }

    $compressed = base64_decode($encoded, true);
    $json = $compressed !== false && function_exists('gzdecode') ? gzdecode($compressed) : false;
    $files = $json !== false ? json_decode($json, true) : null;
    if (!is_array($files)) {
        http_response_code(503);
        exit('Unable to unpack deployment bundle.');
    }

    foreach ($files as $relative => $payload) {
        if (!is_string($relative) || str_contains($relative, '..') || str_starts_with($relative, '/')) {
            continue;
        }
        $target = $root . '/' . $relative;
        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            http_response_code(503);
            exit('Unable to create deployment directory.');
        }
        $bytes = base64_decode((string) $payload, true);
        if ($bytes === false || file_put_contents($target, $bytes, LOCK_EX) === false) {
            http_response_code(503);
            exit('Unable to write deployment file.');
        }
    }

    @mkdir($root . '/storage/data', 0775, true);
    @mkdir($root . '/storage/logs', 0775, true);
    file_put_contents($marker, MKD_RELEASE . "\n", LOCK_EX);
}

require $root . '/public/index.php';
