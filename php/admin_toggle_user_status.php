<?php
/**
 * Admin Toggle User Status API
 * Allows admin users to activate/deactivate user accounts
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

// Validate JWT token and check admin role
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
if (empty($input['userId'])) {
    sendError('User ID is required');
}

$userId = (int)$input['userId'];

// Prevent admin from deactivating themselves
if ($userId === $payload['user_id']) {
    sendError('You cannot change your own account status');
}

// Get database connection
$pdo = getDBConnection();
if (!$pdo) {
    sendError('Database connection failed', 500);
}

try {
    // Verify user has admin role
    $stmt = $pdo->prepare("
        SELECT r.name 
        FROM roles r 
        JOIN user_roles ur ON r.id = ur.role_id 
        WHERE ur.user_id = ? AND r.name IN ('admin', 'manager')
    ");
    $stmt->execute([$payload['user_id']]);
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($roles)) {
        sendError('Access denied. Admin privileges required.', 403);
    }
    
    // Get current user status
    $stmt = $pdo->prepare("SELECT id, status, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('User not found', 404);
    }
    
    // Check if target user is an admin (prevent deactivating other admins)
    $stmt = $pdo->prepare("
        SELECT r.name 
        FROM roles r 
        JOIN user_roles ur ON r.id = ur.role_id 
        WHERE ur.user_id = ? AND r.name = 'admin'
    ");
    $stmt->execute([$userId]);
    $isAdmin = $stmt->fetch();
    
    if ($isAdmin && !in_array('admin', $roles)) {
        sendError('Only admins can change other admin account status');
    }
    
    // Toggle status
    $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
    
    // Update user status
    $stmt = $pdo->prepare("
        UPDATE users 
        SET status = ?, updated_at = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $newStatus,
        date('Y-m-d H:i:s'),
        $userId
    ]);
    
    // Log activity
    $userName = $user['first_name'] . ' ' . $user['last_name'];
    logActivity($payload['user_id'], 'user_status_changed', "Changed user $userName (#$userId) status to $newStatus");
    
    $responseData = [
        'user_id' => $userId,
        'new_status' => $newStatus,
        'user_name' => $userName
    ];
    
    sendSuccess("User status changed to $newStatus successfully", $responseData);

} catch (PDOException $e) {
    error_log("Admin toggle user status error: " . $e->getMessage());
    sendError('An error occurred while updating user status', 500);
} catch (Exception $e) {
    error_log("Admin toggle user status error: " . $e->getMessage());
    sendError('An unexpected error occurred', 500);
}
?>