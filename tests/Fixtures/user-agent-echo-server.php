<?php

// Router for PHP's built-in web server: answers every request with the
// User-Agent header it received, so tests can check what went over the wire.
header('Content-Type: application/json');

echo json_encode(['userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null]);
