<?php
// /auth/login.php - Handles SMS Code Login (Initiate or Verify Code)

require_once '../autoloader.php';

if (getRequestMethod() !== 'POST') {
    sendErrorResponse('Method not allowed.', 405);
}

$input = getJsonInput();
$db = new Database();

$phone = $input['phone'] ?? null;
$code = $input['code'] ?? null;
$origin = $input['origin'] ?? null; 

if (!$phone || !$origin) {
    sendErrorResponse('Missing required fields (phone or origin).');
}

$user = findUserByPhone($db, $phone);

if (!$user) {
    sendErrorResponse('Account not found.', 404); 
}

// --- Scenario 1: Initiate Login / Resend Code ---
if (!$code) {
    // Rate Limiting Logic (check if code was sent recently, e.g., last 60 seconds)
    $last_code_sent = strtotime($user['code_sent_at'] ?? 0);
    if (time() - $last_code_sent < 60) {
        sendErrorResponse('Please wait a minute before requesting another code.', 429); // 429 Too Many Requests
    }
    
    // Generate new code, update DB, and send SMS
    $verification_code = random_int(1000, 9999); 
    $code_expiry = time() + (60 * 5); // 5 minutes from now

    // Update the DB with the new code, expiry, and timestamp for rate limiting
    $db->query("
        UPDATE users SET 
        verification_code = :code, 
        code_expiry = FROM_UNIXTIME(:expiry),
        code_sent_at = NOW()
        WHERE phone = :phone
    ");
    $db->bind(':code', $verification_code);
    $db->bind(':expiry', $code_expiry);
    $db->bind(':phone', $phone);
    $db->execute();
    
    if (!sendTwilioSMS($phone, $verification_code)) {
        sendErrorResponse('Failed to send SMS verification code.', 500);
    }
    
    sendJsonResponse([
        'success' => true,
        'message' => 'Verification code sent successfully.'
    ], 200);
} 
// --- Scenario 2: Complete Login (Verify Code) ---
else {
    $code_expiry_timestamp = strtotime($user['code_expiry'] ?? 0);

    // Check code validity and expiration
    if ($user['verification_code'] != $code) {
        // Do NOT clear the code, as it's reusable (but increment a failed attempt counter if needed)
        sendErrorResponse('Invalid verification code.', 401);
    }
    
    if ($code_expiry_timestamp < time()) {
        sendErrorResponse('Expired verification code.', 401);
    }
    
    // Code verified: Process successful login
    $response = handleSuccessfulLogin($db, $user, $origin);
    sendJsonResponse($response, 200);
}
