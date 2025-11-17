<?php
// /auth/logout.php - Handles Token Invalidation

require_once '../autoloader.php';

if (getRequestMethod() !== 'POST') {
    sendErrorResponse('Method not allowed.', 405);
}

$db = new Database();

// Get the token from the Authorization header
$headers = getallheaders();
// Check common header casings
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    sendErrorResponse('Token missing.', 401);
}

$token = $matches[1];

// Invalidate the token by deleting it from the database
$db->query("DELETE FROM tokens WHERE token = :token");
$db->bind(':token', $token);
$db->execute();

// We return success regardless of row count to prevent leaking token existence info
sendJsonResponse([
    'success' => true,
    'message' => 'Logout successful.'
], 200);
