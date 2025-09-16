<?php
/**
 * User Login API
 * Handles user authentication and JWT token generation
 */

require_once 'config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendError('Invalid JSON data');
}

// Validate required fields
if (empty($input['email']) || empty($input['password'])) {
    sendError('Email and password are required');
}

$email = sanitizeInput($input['email']);
$password = $input['password'];

// Validate email format
if (!validateEmail($email)) {
    sendError('Invalid email format');
}

// Check rate limiting
if (!checkRateLimit($email, 'login')) {
    sendError('Too many login attempts. Please try again later.', 429);
}

// Get database connection
$pdo = getDBConnection();
if (!$pdo) {
    sendError('Database connection failed', 500);
}

try {
    // Get user by email
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, password_hash, email_verified, status, last_login FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        recordAttempt($email, 'login', false);
        sendError('Invalid email or password');
    }
    
    // Verify password
    if (!verifyPassword($password, $user['password_hash'])) {
        recordAttempt($email, 'login', false);
        sendError('Invalid email or password');
    }
    
    // Check if email is verified (optional - remove if you want to allow unverified users)
    if (!$user['email_verified']) {
        sendError('Please verify your email before logging in', 403);
    }
    
    // Generate JWT token
    $tokenPayload = [
        'user_id' => $user['id'],
        'email' => $user['email'],
        'name' => $user['first_name'] . ' ' . $user['last_name'],
        'role' => 'customer' // Default role, you can enhance this by joining with roles table
    ];
    
    $token = generateJWT($tokenPayload);
    
    // Generate session token for additional security
    $sessionToken = generateSecureToken();
    
    // Store session in database
    $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_token, expires_at, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $expiresAt = date('Y-m-d H:i:s', time() + JWT_EXPIRY);
    $stmt->execute([
        $user['id'],
        $sessionToken,
        $expiresAt,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = ? WHERE id = ?");
    $stmt->execute([date('Y-m-d H:i:s'), $user['id']]);
    
    // Log successful login
    logActivity($user['id'], 'login', 'User logged in successfully');
    recordAttempt($email, 'login', true);
    
    // Prepare user data for response (exclude sensitive information)
    $userData = [
        'id' => $user['id'],
        'name' => $user['first_name'] . ' ' . $user['last_name'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
        'email_verified' => (bool)$user['email_verified'],
        'last_login' => $user['last_login']
    ];
    
    // Send success response
    sendSuccess('Login successful', [
        'token' => $token,
        'session_token' => $sessionToken,
        'expires_at' => $expiresAt,
        'user' => $userData,
        'redirect' => 'index.html' // You can change this based on user role
    ]);

} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    sendError('An error occurred during login', 500);
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    sendError('An unexpected error occurred', 500);
}
?>