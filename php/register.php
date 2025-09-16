<?php
/**
 * User Registration API
 * Handles new user registration with validation and security
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
$requiredFields = ['firstName', 'lastName', 'email', 'password', 'confirmPassword'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        sendError(ucfirst($field) . ' is required');
    }
}

// Sanitize input data
$firstName = sanitizeInput($input['firstName']);
$lastName = sanitizeInput($input['lastName']);
$email = sanitizeInput($input['email']);
$password = $input['password'];
$confirmPassword = $input['confirmPassword'];

// Validate input
if (!validateEmail($email)) {
    sendError('Invalid email format');
}

if ($password !== $confirmPassword) {
    sendError('Passwords do not match');
}

if (!validatePassword($password)) {
    sendError('Password must be at least 8 characters long and contain uppercase, lowercase, numbers, and special characters');
}

// Check rate limiting
if (!checkRateLimit($_SERVER['REMOTE_ADDR'], 'register')) {
    sendError('Too many registration attempts. Please try again later.', 429);
}

// Get database connection
$pdo = getDBConnection();
if (!$pdo) {
    sendError('Database connection failed', 500);
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        recordAttempt($_SERVER['REMOTE_ADDR'], 'register', false);
        sendError('Email address is already registered');
    }
    
    // Hash password
    $passwordHash = hashPassword($password);
    
    // Generate email verification token
    $emailVerificationToken = generateSecureToken();
    
    // Insert new user
    $stmt = $pdo->prepare("
        INSERT INTO users (first_name, last_name, email, password_hash, email_verification_token, created_at) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $firstName,
        $lastName,
        $email,
        $passwordHash,
        $emailVerificationToken,
        date('Y-m-d H:i:s')
    ]);
    
    $userId = $pdo->lastInsertId();
    
    // Assign default customer role
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'customer'");
    $stmt->execute();
    $customerRole = $stmt->fetch();
    
    if ($customerRole) {
        $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        $stmt->execute([$userId, $customerRole['id']]);
    }
    
    // Log registration activity
    logActivity($userId, 'register', 'User account created');
    recordAttempt($_SERVER['REMOTE_ADDR'], 'register', true);
    
    // Here you would typically send an email verification email
    // For demo purposes, we'll just mark the email as verified
    // In production, implement proper email verification
    
    // For demo: Auto-verify email
    $stmt = $pdo->prepare("UPDATE users SET email_verified = 1, email_verification_token = NULL WHERE id = ?");
    $stmt->execute([$userId]);
    
    // Prepare response data
    $responseData = [
        'user_id' => $userId,
        'email' => $email,
        'name' => $firstName . ' ' . $lastName,
        'email_verified' => true, // Since we auto-verified above
        'verification_email_sent' => false // Set to true when you implement email sending
    ];
    
    // Send success response
    sendSuccess('Registration successful! You can now log in.', $responseData, 201);

} catch (PDOException $e) {
    // Check for duplicate entry error
    if ($e->getCode() == '23000') {
        sendError('Email address is already registered');
    }
    
    error_log("Registration error: " . $e->getMessage());
    sendError('An error occurred during registration', 500);
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    sendError('An unexpected error occurred', 500);
}
?>