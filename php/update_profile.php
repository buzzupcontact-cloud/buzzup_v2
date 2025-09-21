<?php
/**
 * Update User Profile API
 * Updates profile information for the authenticated user
 */

require_once 'config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// Get authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (empty($authHeader)) {
    sendError('Authorization header missing', 401);
}

// Extract token from "Bearer <token>" format
if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    sendError('Invalid authorization format', 401);
}

$token = $matches[1];

// Validate JWT token
$payload = validateJWT($token);
if (!$payload) {
    sendError('Invalid or expired token', 401);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendError('Invalid JSON data');
}

// Validate required fields
$requiredFields = ['firstName', 'lastName', 'email'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        sendError(ucfirst($field) . ' is required');
    }
}

// Sanitize input data
$firstName = sanitizeInput($input['firstName']);
$lastName = sanitizeInput($input['lastName']);
$email = sanitizeInput($input['email']);
$phone = sanitizeInput($input['phone'] ?? '');
$company = sanitizeInput($input['company'] ?? '');
$jobTitle = sanitizeInput($input['jobTitle'] ?? '');
$bio = sanitizeInput($input['bio'] ?? '');

// Validate email format
if (!validateEmail($email)) {
    sendError('Invalid email format');
}

// Get database connection
$pdo = getDBConnection();
if (!$pdo) {
    sendError('Database connection failed', 500);
}

try {
    // Check if email is already taken by another user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $payload['user_id']]);
    
    if ($stmt->fetch()) {
        sendError('Email address is already in use by another account');
    }
    
    // Update user profile
    $stmt = $pdo->prepare("
        UPDATE users 
        SET first_name = ?, last_name = ?, email = ?, phone = ?, company = ?, job_title = ?, bio = ?, updated_at = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $firstName,
        $lastName,
        $email,
        $phone,
        $company,
        $jobTitle,
        $bio,
        date('Y-m-d H:i:s'),
        $payload['user_id']
    ]);
    
    // Log activity
    logActivity($payload['user_id'], 'profile_updated', 'User profile information updated');
    
    // Prepare response data
    $responseData = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'name' => $firstName . ' ' . $lastName,
        'email' => $email,
        'phone' => $phone,
        'company' => $company,
        'job_title' => $jobTitle,
        'bio' => $bio,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    sendSuccess('Profile updated successfully', $responseData);

} catch (PDOException $e) {
    error_log("Update profile error: " . $e->getMessage());
    sendError('An error occurred while updating profile', 500);
} catch (Exception $e) {
    error_log("Update profile error: " . $e->getMessage());
    sendError('An unexpected error occurred', 500);
}
?>