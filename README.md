# SecureRide API: Robust User Authentication & Management System

## Overview
This project provides a robust PHP backend API for secure user authentication and management. It implements comprehensive registration, login, and password reset flows, leveraging Twilio for SMS-based verification and Google OAuth for seamless social login integration. The system uses a custom architecture with PDO for secure database interactions and token-based session management.

## Features
- **PHP**: Core language for all backend API logic.
- **PDO**: Provides a secure and efficient layer for database abstraction and interaction.
- **Twilio SMS Integration**: Enables two-factor authentication via SMS for registration, login, and password reset.
- **Google OAuth 2.0**: Supports direct user registration and login through Google accounts.
- **Token-Based Authentication**: Manages user sessions securely using Bearer tokens with defined expiration.
- **Password Hashing**: Stores user passwords securely using `password_hash()` with `PASSWORD_DEFAULT` algorithm.
- **Rate Limiting**: Implements basic rate limiting for SMS code requests to prevent abuse.
- **Structured API Endpoints**: Clearly defined endpoints for various authentication operations.

## Getting Started

### Environment Variables
To run this project, you will need to configure the following environment variables. It is recommended to create a `.env` file or directly configure them in `config/database.php` and `config/secrets.php` for development purposes. For production, use secure environment variable management.

*   `DB_HOST`: `localhost` (e.g., `localhost` or your database host)
*   `DB_USER`: `your_db_user` (e.g., `root`)
*   `DB_PASS`: `your_db_password` (e.g., `secretpassword`)
*   `DB_NAME`: `your_database_name` (e.g., `secureride_db`)
*   `DB_CHARSET`: `utf8mb4` (Database character set)
*   `TWILIO_SID`: `ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX` (Your Twilio Account SID)
*   `TWILIO_TOKEN`: `your_twilio_auth_token` (Your Twilio Auth Token)
*   `TWILIO_PHONE`: `+15017122661` (Your Twilio phone number in E.164 format)
*   `GOOGLE_CLIENT_ID`: `YOUR-GOOGLE-OAUTH-CLIENT-ID.apps.googleusercontent.com` (Your Google OAuth Client ID)

## API Documentation

### Base URL
All endpoints are relative to the server root where the `auth` directory is hosted. For example, if your server serves the project from `http://localhost/`, then `/auth/register.php` would be `http://localhost/auth/register.php`.

### Endpoints

#### POST /auth/register.php?action=create
Registers a new user account with email, phone, and password.

**Request**:
```json
{
  "phone": "08012345678",
  "email": "user@example.com",
  "password": "StrongPassword123",
  "confirm_password": "StrongPassword123"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Registration successful. 4-digit code sent to your phone for confirmation."
}
```

**Errors**:
- `400 Bad Request`: Missing required fields or passwords do not match.
- `409 Conflict`: Account already exists with the provided email or phone number.
- `500 Internal Server Error`: Server error during registration.

#### POST /auth/register.php?action=confirm
Confirms a newly registered account using a verification code sent to the user's phone.

**Request**:
```json
{
  "phone": "08012345678",
  "code": "1234"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Account successfully confirmed. Proceed to login."
}
```

**Errors**:
- `400 Bad Request`: Phone number or confirmation code is missing.
- `404 Not Found`: Account not found for the provided phone number.
- `401 Unauthorized`: Invalid or expired confirmation code.

#### POST /auth/register.php?action=resend
Requests a new confirmation code to be sent to the user's phone for an unconfirmed account.

**Request**:
```json
{
  "phone": "08012345678"
}
```

**Response**:
```json
{
  "success": true,
  "message": "New confirmation code sent."
}
```

**Errors**:
- `400 Bad Request`: Phone number is missing.
- `404 Not Found`: Account not found for the provided phone number.
- `429 Too Many Requests`: Please wait a minute before requesting another code.
- `500 Internal Server Error`: Failed to send SMS verification code.

#### POST /auth/register.php?action=reset_code
Initiates the password reset process by sending a verification code to the user's phone.

**Request**:
```json
{
  "phone": "08012345678"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Password reset code sent to your phone."
}
```

**Errors**:
- `400 Bad Request`: Phone number is missing.
- `404 Not Found`: Account not found for the provided phone number.
- `429 Too Many Requests`: Please wait a minute before requesting another code.
- `500 Internal Server Error`: Failed to send password reset code.

#### POST /auth/register.php?action=reset_finalize
Finalizes the password reset process by verifying the code and setting a new password.

**Request**:
```json
{
  "phone": "08012345678",
  "code": "5678",
  "new_password": "NewStrongPassword123",
  "confirm_password": "NewStrongPassword123"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Password successfully reset."
}
```

**Errors**:
- `400 Bad Request`: Missing required fields or new passwords do not match.
- `404 Not Found`: Account not found for the provided phone number.
- `401 Unauthorized`: Invalid or expired reset code.

#### POST /auth/login.php
Handles SMS code-based login. This endpoint can either initiate the login process by sending a code or complete it by verifying a provided code.

**Request**:
*To initiate/resend code:*
```json
{
  "phone": "08012345678",
  "origin": "web"
}
```
*To verify code and complete login:*
```json
{
  "phone": "08012345678",
  "code": "9101",
  "origin": "web"
}
```

**Response**:
*After initiating/resending code:*
```json
{
  "success": true,
  "message": "Verification code sent successfully."
}
```
*After verifying code and successful login:*
```json
{
  "success": true,
  "token": "a1b2c3d4e5f6...",
  "new_user": false,
  "onboarding_done": true
}
```

**Errors**:
- `400 Bad Request`: Missing required fields (`phone` or `origin`).
- `404 Not Found`: Account not found for the provided phone number.
- `429 Too Many Requests`: Please wait a minute before requesting another code (when initiating).
- `500 Internal Server Error`: Failed to send SMS verification code (when initiating).
- `401 Unauthorized`: Invalid or expired verification code (when verifying).

#### POST /auth/google.php
Authenticates or registers a user via Google OAuth 2.0.

**Request**:
```json
{
  "google_id_token": "YOUR_GOOGLE_ID_TOKEN",
  "origin": "web"
}
```

**Response**:
```json
{
  "success": true,
  "token": "a1b2c3d4e5f6...",
  "new_user": true,
  "onboarding_done": false
}
```

**Errors**:
- `405 Method Not Allowed`: Only POST requests are supported.
- `400 Bad Request`: Missing `google_id_token` or `origin`.
- `401 Unauthorized`: Invalid or expired Google token.

#### POST /auth/logout.php
Invalidates the current session token, effectively logging out the user.

**Request**:
Headers:
`Authorization: Bearer YOUR_AUTH_TOKEN`

**Response**:
```json
{
  "success": true,
  "message": "Logout successful."
}
```

**Errors**:
- `405 Method Not Allowed`: Only POST requests are supported.
- `401 Unauthorized`: Token missing from Authorization header.

---

## Usage
To interact with the SecureRide API, you can use any HTTP client like `curl`, Postman, or integrate directly from your client-side application. Below are examples using `curl`.

**1. Register a New User**

```bash
curl -X POST \
  http://localhost/auth/register.php?action=create \
  -H 'Content-Type: application/json' \
  -d '{
        "phone": "08012345678",
        "email": "jane.doe@example.com",
        "password": "MySuperSecretPassword123",
        "confirm_password": "MySuperSecretPassword123"
      }'
```

**2. Confirm Account with SMS Code**

```bash
curl -X POST \
  http://localhost/auth/register.php?action=confirm \
  -H 'Content-Type: application/json' \
  -d '{
        "phone": "08012345678",
        "code": "1234" 
      }'
```

**3. Initiate SMS Login**

```bash
curl -X POST \
  http://localhost/auth/login.php \
  -H 'Content-Type: application/json' \
  -d '{
        "phone": "08012345678",
        "origin": "web"
      }'
```

**4. Complete SMS Login with Code**

```bash
curl -X POST \
  http://localhost/auth/login.php \
  -H 'Content-Type: application/json' \
  -d '{
        "phone": "08012345678",
        "code": "9101", 
        "origin": "web"
      }'
```

**5. Login with Google OAuth**

```bash
curl -X POST \
  http://localhost/auth/google.php \
  -H 'Content-Type: application/json' \
  -d '{
        "google_id_token": "YOUR_ACTUAL_GOOGLE_ID_TOKEN",
        "origin": "web"
      }'
```

**6. Logout (Invalidate Token)**

```bash
curl -X POST \
  http://localhost/auth/logout.php \
  -H 'Authorization: Bearer YOUR_AUTHENTICATION_TOKEN'
```

## Technologies Used

| Technology         | Description                                        | Link                                                    |
| :----------------- | :------------------------------------------------- | :------------------------------------------------------ |
| PHP                | Server-side scripting language                     | [php.net](https://www.php.net/)                         |
| MySQL / PostgreSQL | Relational Database Management System (via PDO)    | [mysql.com](https://www.mysql.com/) / [postgresql.org](https://www.postgresql.org/) |
| PDO                | PHP Data Objects - Database access abstraction     | [php.net/manual/en/book.pdo.php](https://www.php.net/manual/en/book.pdo.php) |
| Twilio             | Cloud communications platform for SMS messaging    | [twilio.com](https://www.twilio.com/)                   |
| Google OAuth       | Secure authorization framework for Google services | [developers.google.com/identity/protocols/oauth2](https://developers.google.com/identity/protocols/oauth2) |
| cURL               | Command-line tool and library for transferring data with URLs | [curl.se](https://curl.se/)                             |


## License
This project is licensed under the Apache License 2.0. See the [LICENSE](LICENSE) file for details.

## Author Info
**Your Name Here**
*   LinkedIn: [Your LinkedIn Profile](https://linkedin.com/in/yourprofile)
*   Twitter: [@yourtwitterhandle](https://twitter.com/yourtwitterhandle)

---

[![PHP](https://img.shields.io/badge/Language-PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL/PostgreSQL-00758F?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)
[![Readme was generated by Dokugen](https://img.shields.io/badge/Readme%20was%20generated%20by-Dokugen-brightgreen)](https://www.npmjs.com/package/dokugen)
