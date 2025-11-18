<?php
// /auth/google.php - Handles Google OAuth Login

require_once '../autoloader.php';

if (getRequestMethod() !== 'POST') {
    sendErrorResponse('Method not allowed.', 405);
}

$input = getJsonInput();
$db = new Database();

$googleToken = $input['google_id_token'] ?? null;
$origin = $input['origin'] ?? null; 

if (!$googleToken || !$origin) {
    sendErrorResponse('Missing required fields (google_id_token or origin).');
}

// 1. --- VERIFY GOOGLE TOKEN (REAL API CALL) ---
$google_user_info = verifyGoogleToken($googleToken);

if (!$google_user_info) {
    sendErrorResponse('Invalid or expired Google token.', 401);
}

$email = $google_user_info['email'];
$user = findUserByEmail($db, $email);

// 2. --- REGISTER NEW USER IF NOT FOUND ---
if (!$user) {
    $db->query("
        INSERT INTO users (email, first_name, last_name, created_at, onboarding_done) 
        VALUES (:email, :first_name, :last_name, NOW(), 0)
    ");
    $db->bind(':email', $email);
    $db->bind(':first_name', $google_user_info['given_name'] ?? '');
    $db->bind(':last_name', $google_user_info['family_name'] ?? '');
    $db->execute();
    
    // Re-fetch the new user data
    $user = findUserByEmail($db, $email); 
}

// 3. --- SUCCESSFUL LOGIN ---
$response = handleSuccessfulLogin($db, $user, $origin);
sendJsonResponse($response, 200);
