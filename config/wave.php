<?php

/**
 * WAVe-Lite application configuration.
 *
 * SECURITY: The Python scanner must authenticate when posting progress updates.
 * Set SCANNER_TOKEN in .env to a long random secret. Never commit real tokens.
 *
 * Scanning targets without authorization may violate computer misuse laws.
 */
return [

    'scanner_token' => env('SCANNER_TOKEN'),

    /**
     * Docker image for OWASP ZAP baseline (https://www.zaproxy.org/docs/docker/baseline/).
     */
    'zap_docker_image' => env('ZAP_DOCKER_IMAGE', 'zaproxy/zap-stable'),

    /**
     * Local ZAP daemon (ZAP.app or zap.sh -daemon). Used when Docker is unavailable.
     * Start with e.g. zap.sh -daemon -host 127.0.0.1 -port 8080 -config api.disablekey=true
     * (omit api key in .env when disabled) or set ZAP_API_KEY to match ZAP Options → API.
     */
    'zap_local' => [
        'host' => env('ZAP_HOST'),
        'port' => (int) env('ZAP_PORT', 8080),
        'api_key' => env('ZAP_API_KEY'),
    ],

    /**
     * When true, skip Docker and write stub outputs so the UI can be exercised without ZAP.
     */
    'scan_simulate' => env('WAVE_SCAN_SIMULATE', false),

    /**
     * Nikto web server scan (must be installed on the host that runs the queue worker).
     * Uses the same target URL as ZAP; findings are stored with source "nikto".
     */
    'nikto' => [
        'enabled' => env('NIKTO_ENABLED', true),
        'binary' => env('NIKTO_BINARY', 'nikto'),
        /** Per-host cap, e.g. 5m, 10m, 3600s (passed to nikto -maxtime). */
        'maxtime' => env('NIKTO_MAXTIME', '10m'),
    ],

    /**
     * Gemini advisory analysis (summary/prioritization/remediation only; never scanning).
     */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 60),
    ],

    /**
     * Queue connection for RunWaveScanJob (null = default queue).
     */
    'scan_queue' => env('WAVE_SCAN_QUEUE'),

];
