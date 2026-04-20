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
        /** Limit spider breadth for faster scans. */
        'max_children' => (int) env('ZAP_MAX_CHILDREN', 12),
        /** Max seconds to wait for spider completion. */
        'spider_timeout' => (int) env('ZAP_SPIDER_TIMEOUT', 240),
        /** Max seconds to wait for passive scan queue to drain. */
        'pscan_wait' => (int) env('ZAP_PSCAN_WAIT', 45),
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
        /** Per-host cap, e.g. 3m, 5m, 600s (passed to nikto -maxtime). */
        'maxtime' => env('NIKTO_MAXTIME', '4m'),
        /** Hard process timeout in seconds (prevents hung Nikto runs). */
        'process_timeout' => (int) env('NIKTO_PROCESS_TIMEOUT', 300),
    ],

    /**
     * Nuclei template-based scan (optional).
     */
    'nuclei' => [
        'enabled' => env('NUCLEI_ENABLED', false),
        'binary' => env('NUCLEI_BINARY', 'nuclei'),
        'templates' => env('NUCLEI_TEMPLATES'),
        'severity' => env('NUCLEI_SEVERITY', 'critical,high,medium'),
        'timeout' => (int) env('NUCLEI_TIMEOUT', 240),
    ],

    /**
     * Gemini advisory analysis (summary/prioritization/remediation only; never scanning).
     */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 60),
    ],

    /**
     * Groq advisory analysis fallback (OpenAI-compatible chat API).
     */
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'timeout' => (int) env('GROQ_TIMEOUT', 60),
    ],

    /**
     * Queue connection for RunWaveScanJob (null = default queue).
     */
    'scan_queue' => env('WAVE_SCAN_QUEUE'),

];
