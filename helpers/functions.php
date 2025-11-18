<?php
// /helpers/functions.php - Complete Set of Helper Functions

// --- 1. CORE UTILITY FUNCTIONS ---

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
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
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


// --- 2. AUTHENTICATION & DATABASE HELPER FUNCTIONS ---

/**
 * Finds a user by email OR phone number.
 * @param Database $db
 * @param string $credential Email or phone number.
 * @return array|false The user data if found, otherwise false.
 */
function findUserByCredential(Database $db, $credential) {
    // Looks for a match in either email or phone column
    $db->query("SELECT * FROM users WHERE email = :credential OR phone = :credential");
    $db->bind(':credential', $credential);
    return $db->single();
}

/**
 * Checks if a user is new (hasn't completed onboarding).
 * @param array $user
 * @return bool
 */
function isNewUser($user) {
    // A user is new if the 'onboarding_done' flag is not set or is false (0).
    return empty($user['onboarding_done']);
}

/**
 * Hashes a plaintext password securely.
 * @param string $password
 * @return string The hashed password.
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verifies a password against a hash.
 * @param string $password
 * @param string $hash
 * @return bool
 */
function verifyPassword($password, $hash) {
    // Returns true if the password matches the hash
    return password_verify($password, $hash);
}

/**
 * Generates and stores a secure database token for the user.
 * @param Database $db
 * @param int $userId
 * @param string $type The token type ('ACCESS').
 * @param string $origin 'web' or 'app'
 * @return string The generated token string.
 */
function generateAndStoreToken(Database $db, $userId, $type, $origin) {
    // Generate a secure, unique 64-character token
    $token = bin2hex(random_bytes(32)); 
    $expiry = time() + (60 * 60 * 24 * 7); // 7 days expiration

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
 * Handles the successful login flow: generates token and formats response.
 * @param Database $db
 * @param array $user The authenticated user data.
 * @param string $origin The device origin ('web' or 'app').
 * @return array Response data including token and user status.
 */
function handleSuccessfulLogin(Database $db, $user, $origin) {
    $token = generateAndStoreToken($db, $user['id'], 'ACCESS', $origin);
    
    // Clear the verification code after a successful token-based login
    $db->query("UPDATE users SET verification_code = NULL, code_expiry = NULL WHERE id = :id");
    $db->bind(':id', $user['id']);
    $db->execute();
    
    return [
        'success' => true,
        'token' => $token,
        'new_user' => isNewUser($user), 
        'onboarding_done' => (bool)$user['onboarding_done']
    ];
}

/**
 * Verifies the Bearer token in the request header and fetches the active user.
 * @param Database $db
 * @return array|false The user data if token is valid and active, otherwise false.
 */
function verifyTokenAndGetUser(Database $db) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        return false; // No token found
    }

    $token = $matches[1];

    // Look up the token in the database, checking for expiry
    $db->query("
        SELECT u.*, t.expires_at 
        FROM users u 
        JOIN tokens t ON u.id = t.user_id 
        WHERE t.token = :token AND t.type = 'ACCESS' 
        LIMIT 1
    ");
    $db->bind(':token', $token);
    $result = $db->single();

    // Check if token exists and is not expired
    if ($result && strtotime($result['expires_at']) > time()) {
        // Return user data (excluding token expiry detail)
        unset($result['expires_at']);
        return $result;
    }

    return false; // Token invalid or expired
}


// --- 3. TWILIO INTEGRATION FUNCTION ---

/**
 * Sends a 4-digit verification code via Twilio SMS.
 * 🚨 IMPORTANT: Requires TWILIO_SID, TWILIO_TOKEN, and TWILIO_PHONE defined in /config/secrets.php.
 * @param string $toPhoneNumber Recipient phone number (E.164 format).
 * @param int $code The 4-digit code.
 * @return bool True on success, false on failure.
 */
function sendTwilioSMS($toPhoneNumber, $code) {
    if (!defined('TWILIO_SID') || !defined('TWILIO_TOKEN') || !defined('TWILIO_PHONE')) {
        error_log("Twilio credentials not defined in secrets.php.");
        return false;
    }
    
    $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_SID . "/Messages.json";
    
    // Twilio SMS body
    $data = [
        'To' => $toPhoneNumber,
        'From' => TWILIO_PHONE,
        'Body' => "Your riding app verification code is: $code. It is reusable for the next 5 minutes."
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Basic Auth for Twilio API
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_SID . ":" . TWILIO_TOKEN);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 201) {
        error_log("Twilio SMS Error: HTTP $http_code. Response: " . $response);
        return false;
    }
    
    return true;
}
