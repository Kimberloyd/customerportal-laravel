<?php

$applicationHost = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST);
$trustedHosts = array_values(array_filter(array_map(
    static fn (string $host): string => trim($host),
    explode(',', (string) env('TRUSTED_HOSTS', $applicationHost ?: 'localhost')),
)));

return [
    'trusted_hosts' => $trustedHosts,
    'csp_report_only' => env('CSP_REPORT_ONLY', true),
    'hsts' => env('SECURITY_HSTS', true),
];
