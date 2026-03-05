<?php
/**
 * Fix cURL error 77: SSL CA certificate file path on Railway/Docker.
 *
 * WordPress defaults to its bundled ca-bundle.crt, which can cause cURL error 77
 * in some container environments. This redirects to the system CA bundle instead.
 */
add_filter('http_request_args', function($args) {
    $system_ca = '/etc/ssl/certs/ca-certificates.crt';
    if (file_exists($system_ca)) {
        $args['sslcertificates'] = $system_ca;
    }
    return $args;
}, 1, 1);
