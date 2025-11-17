<?php
// /auth/login.php - Handles Credentials Auth (Initiate or Verify Code)

require_once '../autoloader.php';

if (getRequestMethod() !== 'POST') {
    sendErrorResponse('Method not allowed.', 405);
}

$input = getJsonInput();
$db = new Database();

$email = $input['email'] ?? null;
$code = $input['code'] ?? null;
$origin = $input['origin'] ?? null; 

if (!$email || !$origin) {
    sendErrorResponse('Missing required fields (email or origin).');
}

$user = findUserByEmail($db, $email);

if (!$user) {
    // Return generic error for security
    sendErrorResponse('Invalid credentials.', 401); 
}

// --- Step 1: Initiate Login (Send Code) ---
if (!$code) {
    $verification_code = random_int(100000, 999999); 
    
    // Store the code and its expiration time (10 minutes)
    $db->query("
        UPDATE users SET 
        verification_code = :code, 
        code_expiry = DATE_ADD(NOW(), INTERVAL 10 MINUTE) 
        WHERE email = :email
    ");
    $db->bind(':code', $verification_code);
    $db->bind(':email', $email);
    $db->execute();
    
    if (!sendVerificationCodeEmail($email, $verification_code)) {
        sendErrorResponse('Failed to send verification code. Please check your email address.', 500);
    }
    
    sendJsonResponse([
        'success' => true,
        'message' => 'Verification code sent successfully.'
    ], 200);

} 
// --- Step 2: Complete Login (Verify Code) ---
else {
    $code_expiry_timestamp = strtotime($user['code_expiry'] ?? 0);

    // Check code validity and expiration
    if ($user['verification_code'] != $code || $code_expiry_timestamp < time()) {
        // Clear the invalid code immediately to prevent brute-force
        $db->query("UPDATE users SET verification_code = NULL, code_expiry = NULL WHERE email = :email");
        $db->bind(':email', $email);
        $db->execute();
        
        sendErrorResponse('Invalid or expired verification code.', 401);
    }
    
    // Code verified: Process successful login
    $response = handleSuccessfulLogin($db, $user, $origin);
    sendJsonResponse($response, 200);
}
