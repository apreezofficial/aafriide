<?php
// /helpers/functions.php - Complete and Corrected for Twilio SMS

// --- CORE UTILITY FUNCTIONS (Unchanged) ---

/**
 * Sends a structured JSON response and terminates script execution.
 * ... (unchanged)
 */
function sendJsonResponse($data, $status = 200) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/**
 * Sends a JSON error response.
 * ... (unchanged)
 */
function sendErrorResponse($message, $status = 400) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

/**
 * Gets the request method.
 * ... (unchanged)
 */
function getRequestMethod() {
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

/**
 * Gets JSON data from the request body.
 * ... (unchanged)
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}


// --- AUTH HELPER FUNCTIONS ---

/**
 * Finds a user by phone number.
 * @param Database $db
 * @param string $phone Phone number (in E.164 format).
 * @return array|false The user data if found, otherwise false.
 */
function findUserByPhone(Database $db, $phone) {
    $db->query("SELECT * FROM users WHERE phone = :phone");
    $db->bind(':phone', $phone);
    return $db->single();
}

/**
 * Sends a 4-digit verification code via Twilio SMS.
 * 🚨 IMPORTANT: This requires cURL or a Twilio library to execute the API call.
 * @param string $toPhoneNumber Recipient phone number (E.164 format).
 * @param int $code The 4-digit code.
 * @return bool True on success, false on failure.
 */
function sendTwilioSMS($toPhoneNumber, $code) {
    $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_SID . "/Messages.json";
    
    // Twilio SMS body
    $data = [
        'To' => $toPhoneNumber,
        'From' => TWILIO_PHONE,
        'Body' => "Your riding app verification code is: $code. This code is reusable for the next 5 minutes."
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

/**
 * Checks if a user is new (hasn't completed onboarding).
 * ... (unchanged)
 */
function isNewUser($user) {
    return empty($user['onboarding_done']);
}

/**
 * Generates and stores a secure database token for the user.
 * ... (unchanged)
 */
function generateAndStoreToken(Database $db, $userId, $type, $origin) {
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
    
    // Clean up the used verification code and expiry time
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
