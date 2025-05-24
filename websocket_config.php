<?php
// websocket_config.php
// Configuration for WebSocket connection with Cloudflare Tunnel

// Your tunnel URLs
define('APP_URL', 'https://ghost-however-privacy-volumes.trycloudflare.com');
define('WEBSOCKET_TUNNEL_URL', 'https://since-accepts-rachel-shut.trycloudflare.com');

// Determine if we're running locally or through tunnel
$is_localhost = isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1']);
$is_tunnel = isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'trycloudflare.com') !== false;

// Set WebSocket URL based on environment
if ($is_localhost && !$is_tunnel) {
    // Local development
    define('WEBSOCKET_URL', 'ws://localhost:8081');
} else {
    // Through Cloudflare tunnel - use wss:// for secure WebSocket
    define('WEBSOCKET_URL', 'https://since-accepts-rachel-shut.trycloudflare.com/');
}

// Function to get WebSocket URL for JavaScript
function getWebSocketUrl() {
    return WEBSOCKET_URL;
}

// Debug function to check environment
function debugEnvironment() {
    echo "<!-- Debug Info:\n";
    echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'not set') . "\n";
    echo "Is Localhost: " . ($GLOBALS['is_localhost'] ? 'true' : 'false') . "\n";
    echo "Is Tunnel: " . ($GLOBALS['is_tunnel'] ? 'true' : 'false') . "\n";
    echo "WebSocket URL: " . WEBSOCKET_URL . "\n";
    echo "-->\n";
}
?>