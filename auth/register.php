<?php
// /auth/register.php - Handles New User Registration

require_once '../autoloader.php';

if (getRequestMethod() !== 'POST') {
    sendErrorResponse('Method not allowed.', 405);
}

$input = getJsonInput();
$db = new Database();

$email = $input['email'] ?? null;

if (!$email) {
    sendErrorResponse('Email is required for registration.');
}

if (findUserByEmail($db, $email)) {
    sendErrorResponse('User already exists.', 409); // 409 Conflict
}

try {
    // Insert new user with onboarding_done set to 0 (false)
    $db->query("
        INSERT INTO users (email, created_at, onboarding_done) 
        VALUES (:email, NOW(), 0)
    ");
    $db->bind(':email', $email);
    $db->execute();

    sendJsonResponse([
        'success' => true,
        'message' => 'Registration successful. Proceed to login/verification.'
    ], 201); // 201 Created

} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    sendErrorResponse('Server error during registration.', 500);
}
