<?php
// /auth/logout.php - Handles Token Invalidation (Unchanged)

require_once '../autoloader.php';

if (getRequestMethod() !== 'POST') {
    sendErrorResponse('Method not allowed.', 405);
}

$db = new Database();

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    sendErrorResponse('Token missing.', 401);
}

$token = $matches[1];

$db->query("DELETE FROM tokens WHERE token = :token");
$db->bind(':token', $token);
$db->execute();

sendJsonResponse([
    'success' => true,
    'message' => 'Logout successful.'
], 200);
