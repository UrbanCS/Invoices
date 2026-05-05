<?php

$sharedHostingPublicPath = realpath(base_path('../public_html'));
$localPublicPath = realpath(base_path('public'));

return [
    'public_path' => env('DOMPDF_PUBLIC_PATH', $sharedHostingPublicPath ?: $localPublicPath ?: base_path('public')),
];
