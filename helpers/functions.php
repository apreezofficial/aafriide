<?php
// /helpers/functions.php

/**
 * Sends a structured JSON response and terminates script execution.
 * @param array $data The data to return in the response.
 * @param int $status The HTTP status code (default: 200).
 */
function sendJsonResponse($data, $status = 200) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/**
 * Sends a JSON error response.
 * @param string $message The error message.
 * @param int $status The HTTP status code for the error (default: 400).
 */
function sendErrorResponse($message, $status = 400) {
    sendJsonResponse([
        'success' => false,
        'error' => $message
    ], $status);
}

/**
 * Gets the request method.
 * @return string The request method (e.g., 'GET', 'POST').
 */
function getRequestMethod() {
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

/**
 * Gets JSON data from the request body.
 * @return mixed Decoded JSON data or null on failure.
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}
