<?php
/**
 * Configuration helper for MuTraPro Web
 * Detects Kong Gateway host based on environment
 */

/**
 * Get Kong Gateway host
 * Returns 'kong' if running in Docker container, 'localhost' if running on host
 */
function getKongHost() {
    $kong_host = getenv('KONG_HOST');
    if (!empty($kong_host)) {
        return $kong_host;
    }
    
    // Try to resolve 'kong' hostname
    // If it resolves to itself, we're on host (can't resolve Docker service name)
    $resolved = @gethostbyname('kong');
    if ($resolved === 'kong' || empty($resolved)) {
        // Can't resolve, running on host
        return 'localhost';
    }
    
    // Resolved successfully, running in container
    return 'kong';
}

/**
 * Get Kong Gateway port
 */
function getKongPort() {
    return getenv('KONG_PORT') ?: '8000';
}

/**
 * Get Kong Gateway base URL
 */
function getKongBaseUrl() {
    $host = getKongHost();
    $port = getKongPort();
    return "http://{$host}:{$port}";
}

/**
 * Get API base URL for a specific service
 */
function getApiBaseUrl($service = 'Admin') {
    $base = getKongBaseUrl();
    return "{$base}/api/{$service}";
}

