<?php
/**
 * Change Password API
 * Allows authenticated users to change their password
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
if (empty($input['currentPassword']) || empty($input['newPassword'])) {
    sendError('Current password and new password are required');
}

$currentPassword = $input['currentPassword'];
$newPassword = $input['newPassword'];

// Validate new password strength
if (!validatePassword($newPassword)) {
    sendError('New password must be at least 8 characters long and contain uppercase, lowercase, numbers, and special characters');
}

// Get database connection
$pdo = getDBConnection();
if (!$pdo) {
    sendError('Database connection failed', 500);
}

try {
    // Get current user data
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$payload['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('User not found', 404);
    }
    
    // Verify current password
    if (!verifyPassword($currentPassword, $user['password_hash'])) {
        sendError('Current password is incorrect');
    }
    
    // Hash new password
    $newPasswordHash = hashPassword($newPassword);
    
    // Update password
    $stmt = $pdo->prepare("
        UPDATE users 
        SET password_hash = ?, updated_at = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $newPasswordHash,
        date('Y-m-d H:i:s'),
        $payload['user_id']
    ]);
    
    // Log activity
    logActivity($payload['user_id'], 'password_changed', 'User password changed successfully');
    
    sendSuccess('Password changed successfully');

} catch (PDOException $e) {
    error_log("Change password error: " . $e->getMessage());
    sendError('An error occurred while changing password', 500);
} catch (Exception $e) {
    error_log("Change password error: " . $e->getMessage());
    sendError('An unexpected error occurred', 500);
}
?>