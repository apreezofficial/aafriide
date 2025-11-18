<?php
// /auth/register.php - Collects data and initiates SMS verification

require_once '../autoloader.php'; 

if (getRequestMethod() !== 'POST') {
    sendErrorResponse('Method not allowed.', 405);
}

$input = getJsonInput();
$db = new Database();

$phone = $input['phone'] ?? null;
$email = $input['email'] ?? null;
// Password fields are removed as per new flow

if (!$phone || !$email) {
    sendErrorResponse('Missing required fields (phone or email).', 400);
}

// Check if user (by phone) already exists
if (findUserByPhone($db, $phone)) {
    sendErrorResponse('Account already exists with this phone number.', 409); 
}

// 1. Generate 4-digit code and store expiry (5 minutes)
$verification_code = random_int(1000, 9999); 
$code_expiry = time() + (60 * 5); // 5 minutes from now

try {
    // Insert new user data, waiting for verification
    $db->query("
        INSERT INTO users (phone, email, verification_code, code_expiry, created_at, onboarding_done) 
        VALUES (:phone, :email, :code, FROM_UNIXTIME(:expiry), NOW(), 0)
    ");
    $db->bind(':phone', $phone);
    $db->bind(':email', $email);
    $db->bind(':code', $verification_code);
    $db->bind(':expiry', $code_expiry);
    $db->execute();

    // 2. Send the verification code via Twilio
    if (!sendTwilioSMS($phone, $verification_code)) {
        // If SMS fails, clean up the registration and report error
        $db->query("DELETE FROM users WHERE phone = :phone");
        $db->bind(':phone', $phone);
        $db->execute();
        sendErrorResponse('Registration failed. Could not send SMS verification.', 500);
    }
    
    sendJsonResponse([
        'success' => true,
        'message' => 'Registration successful. 4-digit code sent to your phone for verification.'
    ], 201); 

} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    sendErrorResponse('Server error during registration.', 500);
}
