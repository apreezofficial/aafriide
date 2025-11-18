# **Riding App Authentication API**

<p align="center">
  <a href="https://php.net" target="_blank">
    <img src="https://img.shields.io/badge/PHP-7.4%2B-blue.svg?style=for-the-badge&logo=php" alt="PHP Version">
  </a>
  <a href="https://dev.mysql.com/doc/" target="_blank">
    <img src="https://img.shields.io/badge/MySQL-DB-orange.svg?style=for-the-badge&logo=mysql" alt="MySQL Database">
  </a>
  <a href="https://www.twilio.com/" target="_blank">
    <img src="https://img.shields.io/badge/Twilio-SMS-red.svg?style=for-the-badge&logo=twilio" alt="Twilio SMS">
  </a>
  <a href="https://developers.google.com/identity/one-tap/web/overview" target="_blank">
    <img src="https://img.shields.io/badge/Google-OAuth-green.svg?style=for-the-badge&logo=google" alt="Google OAuth">
  </a>
</p>

This project provides a robust and secure authentication API backend, meticulously engineered to handle user registration, login, and session management for a modern riding application. It leverages PHP for backend logic, integrates with Google OAuth for social logins, and utilizes Twilio for reliable SMS-based verification, ensuring a seamless and secure user experience.

## Overview
This PHP-based backend implements core authentication functionalities, including user registration and login via SMS verification and Google OAuth. It manages user sessions through token-based authentication and integrates with a MySQL database for data persistence.

## Features
- **SMS Code Authentication**: Users can register and log in using a secure 4-digit verification code sent to their phone via Twilio.
- **Google OAuth Integration**: Supports seamless registration and login through Google accounts, simplifying the onboarding process.
- **Token-based Session Management**: Securely handles user sessions with dynamically generated access tokens, ensuring authenticated API access.
- **User Account Management**: Manages basic user profiles, including phone, email, and onboarding status.
- **Rate Limiting**: Implements a basic rate-limiting mechanism for SMS code requests to prevent abuse.
- **Database Persistence**: Stores user and token data securely in a MySQL database.

## Getting Started
### Installation
1.  **Clone the repository:**
    ```bash
    git clone https://github.com/your-username/riding-app-auth.git
    cd riding-app-auth
    ```
2.  **Set up your PHP environment:** Ensure you have PHP 7.4+ installed and a web server (Apache, Nginx, or PHP's built-in server for development).
3.  **Configure Database:**
    *   Create a MySQL database.
    *   You will need to implement a `Database` class (e.g., using PDO) in `db/Database.php` that handles connections and queries, consistent with how it's called in `autoloader.php` and `helpers/functions.php`.
    *   Create a `users` table and a `tokens` table with appropriate schemas. A basic schema might look like this:
        ```sql
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20) UNIQUE,
            email VARCHAR(255) UNIQUE NOT NULL,
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            verification_code VARCHAR(10),
            code_expiry DATETIME,
            code_sent_at DATETIME,
            onboarding_done TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );

        CREATE TABLE tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(255) UNIQUE NOT NULL,
            type VARCHAR(50) NOT NULL,
            origin VARCHAR(50),
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        ```
4.  **Place the project files:**
    *   Place the `auth`, `config`, `helpers` directories and `autoloader.php` at the root of your web server's document `www` directory, or configure your web server to point to this project as its root.
    *   For example, if you configure Nginx/Apache to serve requests for `/auth/*` from the `auth/` directory, the endpoints will be accessible.

### Environment Variables
Edit `config/database.php` and `config/secrets.php` with your credentials:

*   **`config/database.php`**:
    ```php
    define('DB_HOST', 'localhost'); // Your database host
    define('DB_USER', 'your_db_user'); // Your database username
    define('DB_PASS', 'your_db_password'); // Your database password
    define('DB_NAME', 'your_database_name'); // Your database name
    define('DB_CHARSET', 'utf8mb4');
    ```

*   **`config/secrets.php`**:
    ```php
    define('TWILIO_SID', 'ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'); // Your Twilio Account SID
    define('TWILIO_TOKEN', 'your_twilio_auth_token'); // Your Twilio Auth Token
    define('TWILIO_PHONE', '+15017122661'); // Your Twilio phone number (E.164 format)
    define('GOOGLE_CLIENT_ID', 'YOUR-GOOGLE-OAUTH-CLIENT-ID.apps.googleusercontent.com'); // Your Google OAuth Client ID
    ```

## API Documentation
### Base URL
Assuming the project files are served under a base path, the authentication endpoints would typically be accessed via: `https://your-api-domain.com/auth/`

### Endpoints
#### `POST /auth/register.php`
Registers a new user account by phone and email, then initiates SMS verification.

**Request**:
```json
{
  "phone": "+12345678900",
  "email": "user@example.com"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Registration successful. 4-digit code sent to your phone for verification."
}
```

**Errors**:
- `400 Bad Request`: Missing required fields (e.g., `phone` or `email`).
- `405 Method Not Allowed`: Request method is not POST.
- `409 Conflict`: An account already exists with the provided phone number.
- `500 Internal Server Error`: Server error during registration or failure to send SMS verification.

#### `POST /auth/login.php`
Handles both initiating SMS code login and verifying the code to complete login.

**Request (Initiate/Resend Code)**:
```json
{
  "phone": "+12345678900",
  "origin": "web"
}
```

**Response (Initiate/Resend Code)**:
```json
{
  "success": true,
  "message": "Verification code sent successfully."
}
```

**Request (Verify Code)**:
```json
{
  "phone": "+12345678900",
  "code": "1234",
  "origin": "app"
}
```

**Response (Verify Code)**:
```json
{
  "success": true,
  "token": "a1b2c3d4e5f6...",
  "new_user": false,
  "onboarding_done": true
}
```

**Errors**:
- `400 Bad Request`: Missing required fields (e.g., `phone` or `origin`).
- `401 Unauthorized`: Invalid or expired verification code.
- `404 Not Found`: Account not found for the provided phone number.
- `405 Method Not Allowed`: Request method is not POST.
- `429 Too Many Requests`: Please wait before requesting another verification code.
- `500 Internal Server Error`: Failed to send SMS verification code.

#### `POST /auth/google.php`
Allows users to log in or register using their Google account via an ID token.

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
  "token": "f6e5d4c3b2a1...",
  "new_user": true,
  "onboarding_done": false
}
```

**Errors**:
- `400 Bad Request`: Missing required fields (e.g., `google_id_token` or `origin`).
- `401 Unauthorized`: Invalid or expired Google token.
- `405 Method Not Allowed`: Request method is not POST.

#### `POST /auth/logout.php`
Invalidates the provided access token, effectively logging the user out.

**Request**:
```
Header: Authorization: Bearer YOUR_ACCESS_TOKEN
```
*(No request body for this endpoint)*

**Response**:
```json
{
  "success": true,
  "message": "Logout successful."
}
```

**Errors**:
- `401 Unauthorized`: Token missing or invalid Authorization header format.
- `405 Method Not Allowed`: Request method is not POST.

## Technologies Used

| Technology | Category       | Description                                              | Link                                            |
| :--------- | :------------- | :------------------------------------------------------- | :---------------------------------------------- |
| PHP        | Backend Language | Core scripting language for server-side logic.           | [php.net](https://php.net)                      |
| MySQL      | Database       | Relational database for storing user and token data.     | [mysql.com](https://www.mysql.com/)             |
| Twilio     | SMS Service    | API for sending SMS verification codes.                  | [twilio.com](https://www.twilio.com/)           |
| Google OAuth | Authentication | Secure user authentication and authorization service.    | [developers.google.com/identity](https://developers.google.com/identity) |
| cURL       | HTTP Client    | Used for making external HTTP requests (e.g., to Twilio). | [php.net/manual/en/book.curl.php](https://www.php.net/manual/en/book.curl.php) |

## License
This project is licensed under the Apache License 2.0. See the [LICENSE](LICENSE) file for details.

## Author Info

Connect with the developer behind this project:

*   **Portfolio**: [your-portfolio.com](https://your-portfolio.com)
*   **LinkedIn**: [linkedin.com/in/your-linkedin-username](https://linkedin.com/in/your-linkedin-username)
*   **Twitter**: [@your_twitter_handle](https://twitter.com/your_twitter_handle)

---

[![Readme was generated by Dokugen](https://img.shields.io/badge/Readme%20was%20generated%20by-Dokugen-brightgreen)](https://www.npmjs.com/package/dokugen)