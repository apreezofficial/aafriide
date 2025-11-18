<?php
// /auth/register.php - Handles Create Account, Confirmation, and Password Reset Code

require_once '../autoloader.php'; 

if (getRequestMethod() !== 'POST') {
    sendErrorResponse('Method not allowed.', 405);
}

$input = getJsonInput();
$db = new Database();

// Get the requested action from the URL query string
$action = $_GET['action'] ?? 'create'; // Default action is 'create'

// --- Input Fields Common to Multiple Actions ---
$phone = $input['phone'] ?? null;
$email = $input['email'] ?? null;
$password = $input['password'] ?? null;
$confirm_password = $input['confirm_password'] ?? null;
$code = $input['code'] ?? null;
$new_password = $input['new_password'] ?? null;

// --- ACTION 1: Account Creation (?action=create) ---
if ($action === 'create') {
    if (!$phone || !$email || !$password || !$confirm_password) {
        sendErrorResponse('Missing required fields: phone, email, password, and confirmation.', 400);
    }
    if ($password !== $confirm_password) {
        sendErrorResponse('Password and confirmation password do not match.', 400);
    }
    
    // Check if user (by email or phone) already exists
    if (findUserByCredential($db, $email) || findUserByCredential($db, $phone)) {
        sendErrorResponse('Account already exists with this email or phone number.', 409); 
    }

    $password_hash = hashPassword($password);
    
    // Generate code for immediate confirmation
    $verification_code = random_int(1000, 9999); 
    $code_expiry = time() + (60 * 5); // 5 minutes

    try {
        $db->query("
            INSERT INTO users (phone, email, password, verification_code, code_expiry, created_at, onboarding_done) 
            VALUES (:phone, :email, :password, :code, FROM_UNIXTIME(:expiry), NOW(), 0)
        ");
        $db->bind(':phone', $phone);
        $db->bind(':email', $email);
        $db->bind(':password', $password_hash);
        $db->bind(':code', $verification_code);
        $db->bind(':expiry', $code_expiry);
        $db->execute();

        // Send the verification code via Twilio
        if (!sendTwilioSMS($phone, $verification_code)) {
            // Log error but proceed, tell user to use resend on the login page
            error_log("Failed to send SMS after registration for user: $phone");
        }
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Registration successful. 4-digit code sent to your phone for confirmation.'
        ], 201); 

    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        sendErrorResponse('Server error during registration.', 500);
    }
} 
// --- ACTION 2: Confirmation/Resend (?action=confirm or ?action=resend) ---
elseif ($action === 'confirm' || $action === 'resend') {
    if (!$phone) {
        sendErrorResponse('Phone number is required for confirmation/resend.', 400);
    }
    
    $user = findUserByCredential($db, $phone);
    if (!$user) {
        sendErrorResponse('Account not found.', 404);
    }

    if ($action === 'resend') {
        // Rate Limiting Logic (check if code was sent recently, e.g., last 60 seconds)
        $last_code_sent = strtotime($user['code_sent_at'] ?? 0);
        if (time() - $last_code_sent < 60) {
            sendErrorResponse('Please wait a minute before requesting another code.', 429);
        }

        $new_code = random_int(1000, 9999); 
        $new_expiry = time() + (60 * 5); 

        $db->query("
            UPDATE users SET 
            verification_code = :code, 
            code_expiry = FROM_UNIXTIME(:expiry),
            code_sent_at = NOW()
            WHERE phone = :phone
        ");
        $db->bind(':code', $new_code);
        $db->bind(':expiry', $new_expiry);
        $db->bind(':phone', $phone);
        $db->execute();
        
        if (!sendTwilioSMS($phone, $new_code)) {
            sendErrorResponse('Failed to send SMS verification code.', 500);
        }
        
        sendJsonResponse(['success' => true, 'message' => 'New confirmation code sent.'], 200);

    } elseif ($action === 'confirm') {
        if (!$code) {
            sendErrorResponse('Confirmation code is required.', 400);
        }
        $code_expiry_timestamp = strtotime($user['code_expiry'] ?? 0);

        if ($user['verification_code'] != $code) {
            sendErrorResponse('Invalid confirmation code.', 401);
        }
        
        if ($code_expiry_timestamp < time()) {
            sendErrorResponse('Expired confirmation code.', 401);
        }
        
        // Success: Mark account as confirmed (or activate a flag if you had one)
        // Since we don't have an 'activated' flag, we just clear the code for security.
        $db->query("UPDATE users SET verification_code = NULL, code_expiry = NULL WHERE phone = :phone");
        $db->bind(':phone', $phone);
        $db->execute();
        
        sendJsonResponse(['success' => true, 'message' => 'Account successfully confirmed. Proceed to login.'], 200);
    }
}
// --- ACTION 3: Password Reset Code (?action=reset) ---
elseif ($action === 'reset_code') {
    if (!$phone) {
        sendErrorResponse('Phone number is required for password reset.', 400);
    }
    $user = findUserByCredential($db, $phone);
    if (!$user) {
        sendErrorResponse('Account not found.', 404);
    }

    // Rate Limiting Check (reuse logic from resend)
    $last_code_sent = strtotime($user['code_sent_at'] ?? 0);
    if (time() - $last_code_sent < 60) {
        sendErrorResponse('Please wait a minute before requesting another code.', 429);
    }
    
    $reset_code = random_int(1000, 9999); 
    $reset_expiry = time() + (60 * 5); 

    $db->query("
        UPDATE users SET 
        verification_code = :code, 
        code_expiry = FROM_UNIXTIME(:expiry),
        code_sent_at = NOW()
        WHERE phone = :phone
    ");
    $db->bind(':code', $reset_code);
    $db->bind(':expiry', $reset_expiry);
    $db->bind(':phone', $phone);
    $db->execute();
    
    if (!sendTwilioSMS($phone, $reset_code)) {
        sendErrorResponse('Failed to send password reset code.', 500);
    }
    
    sendJsonResponse(['success' => true, 'message' => 'Password reset code sent to your phone.'], 200);
}
// --- ACTION 4: Password Reset Finalize (?action=reset_finalize) ---
elseif ($action === 'reset_finalize') {
    if (!$phone || !$code || !$new_password || $new_password !== $confirm_password) {
        sendErrorResponse('Missing required fields or passwords do not match.', 400);
    }

    $user = findUserByCredential($db, $phone);
    if (!$user) {
        sendErrorResponse('Account not found.', 404);
    }
    
    $code_expiry_timestamp = strtotime($user['code_expiry'] ?? 0);

    if ($user['verification_code'] != $code || $code_expiry_timestamp < time()) {
        sendErrorResponse('Invalid or expired reset code.', 401);
    }

    // Success: Update password and clear code
    $new_password_hash = hashPassword($new_password);

    $db->query("
        UPDATE users SET 
        password = :password,
        verification_code = NULL, 
        code_expiry = NULL 
        WHERE phone = :phone
    ");
    $db->bind(':password', $new_password_hash);
    $db->bind(':phone', $phone);
    $db->execute();

    sendJsonResponse(['success' => true, 'message' => 'Password successfully reset.'], 200);

} else {
    sendErrorResponse('Invalid action specified.', 400);
}
