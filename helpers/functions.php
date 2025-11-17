<?php
// /helpers/functions.php (Updated with Auth Helpers)

// --- Existing Utility Functions (sendJsonResponse, getRequestMethod, etc.) ---

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


// --- NEW AUTH HELPER FUNCTIONS ---

/**
 * Finds a user by email using the Database class.
 * @param Database $db
 * @param string $email
 * @return array|false The user data if found, otherwise false.
 */
function findUserByEmail(Database $db, $email) {
    $db->query("SELECT * FROM users WHERE email = :email");
    $db->bind(':email', $email);
    return $db->single();
}

/**
 * Checks if a user is new (hasn't completed onboarding).
 * @param array $user
 * @return bool
 */
function isNewUser($user) {
    // Assuming 'onboarding_done' is a column in the users table (0 or 1)
    return empty($user['onboarding_done']);
}

/**
 * Generates and stores a secure database token for the user.
 * @param Database $db
 * @param int $userId
 * @param string $type The token type ('ACCESS', 'REFRESH', etc.)
 * @param string $origin 'web' or 'app'
 * @return string The generated token string.
 */
function generateAndStoreToken(Database $db, $userId, $type, $origin) {
    // Generate a secure, unique token (e.g., 64-character hex string)
    $token = bin2hex(random_bytes(32)); 
    $expiry = time() + (60 * 60 * 24 * 7); // 7 days expiration

    // Store the token and its hash (always store hash in production for security)
    // For simplicity here, we'll store the plain token, but HASHING IS VITAL.
    $db->query("
        INSERT INTO tokens (user_id, token, type, origin, expires_at, created_at)
        VALUES (:user_id, :token, :type, :origin, FROM_UNIXTIME(:expires_at), NOW())
    ");
    $db->bind(':user_id', $userId);
    $db->bind(':token', $token); 
    $db->bind(':type', $type);
    $db->bind(':origin', $origin);
    $db->bind(':expires_at', $expiry);
    $db->execute();

    return $token;
}

/**
 * Handles the successful login flow and returns necessary data.
 * @param Database $db
 * @param array $user The authenticated user data.
 * @param string $origin The device origin ('web' or 'app').
 * @return array Response data including token and user status.
 */
function handleSuccessfulLogin(Database $db, $user, $origin) {
    // 1. Generate and store the primary access token
    $token = generateAndStoreToken($db, $user['id'], 'ACCESS', $origin);
    
    // 2. Clear old verification codes
    $db->query("UPDATE users SET verification_code = NULL, code_expiry = NULL WHERE id = :id");
    $db->bind(':id', $user['id']);
    $db->execute();

    return [
        'success' => true,
        'token' => $token,
        // new_user attribute sent to frontend (as requested in the flow)
        'new_user' => isNewUser($user), 
        'onboarding_done' => (bool)$user['onboarding_done']
    ];
}
