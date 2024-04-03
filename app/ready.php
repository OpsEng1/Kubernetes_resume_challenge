<?php
// Check if your application is ready to serve traffic
$isReady = true;

// Set the HTTP response code based on the readiness status
http_response_code($isReady ? 200 : 503);

// Set the response headers
header("Content-Type: text/plain");

// Output the readiness status message
echo $isReady ? "Ready" : "Not Ready";
?>
