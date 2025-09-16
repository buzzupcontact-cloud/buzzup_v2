<?php
/**
 * JWT Token Validation API
 * Validates JWT tokens and returns user information
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

// Get database connection
$pdo = getDBConnection();
if (!$pdo) {
    sendError('Database connection failed', 500);
}

try {
    // Verify user still exists and is active
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, email_verified, status, last_login FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$payload['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('User not found or inactive', 401);
    }
    
    // Get user roles
    $stmt = $pdo->prepare("
        SELECT r.name, r.permissions 
        FROM roles r 
        JOIN user_roles ur ON r.id = ur.role_id 
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $roles = $stmt->fetchAll();
    
    // Prepare user data
    $userData = [
        'id' => $user['id'],
        'name' => $user['first_name'] . ' ' . $user['last_name'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
        'email_verified' => (bool)$user['email_verified'],
        'status' => $user['status'],
        'last_login' => $user['last_login'],
        'roles' => array_column($roles, 'name'),
        'permissions' => []
    ];
    
    // Compile permissions from all roles
    foreach ($roles as $role) {
        $rolePermissions = json_decode($role['permissions'], true);
        if (is_array($rolePermissions)) {
            $userData['permissions'] = array_merge($userData['permissions'], $rolePermissions);
        }
    }
    
    // Remove duplicates
    $userData['permissions'] = array_unique($userData['permissions']);
    
    // Log token validation (optional)
    logActivity($user['id'], 'token_validation', 'Token validated successfully');
    
    sendSuccess('Token is valid', [
        'user' => $userData,
        'token_expires_at' => date('Y-m-d H:i:s', $payload['exp'])
    ]);

} catch (PDOException $e) {
    error_log("Token validation error: " . $e->getMessage());
    sendError('Database error occurred', 500);
} catch (Exception $e) {
    error_log("Token validation error: " . $e->getMessage());
    sendError('An unexpected error occurred', 500);
}
?>