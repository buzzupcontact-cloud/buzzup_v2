<?php
/**
 * Admin Get Users API
 * Retrieves all users for admin dashboard
 */

require_once 'config.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
    
    // Get all users with their roles and ticket counts
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.company,
            u.status,
            u.email_verified,
            u.last_login,
            u.created_at,
            GROUP_CONCAT(r.name) as roles,
            COUNT(st.id) as ticket_count
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        LEFT JOIN support_tickets st ON u.id = st.user_id
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    // Format users data
    $formattedUsers = array_map(function($user) {
        return [
            'id' => (int)$user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'company' => $user['company'],
            'status' => $user['status'],
            'email_verified' => (bool)$user['email_verified'],
            'last_login' => $user['last_login'],
            'created_at' => $user['created_at'],
            'roles' => $user['roles'] ? explode(',', $user['roles']) : [],
            'ticket_count' => (int)$user['ticket_count']
        ];
    }, $users);
    
    sendSuccess('Users retrieved successfully', $formattedUsers);

} catch (PDOException $e) {
    error_log("Admin get users error: " . $e->getMessage());
    sendError('An error occurred while retrieving users', 500);
} catch (Exception $e) {
    error_log("Admin get users error: " . $e->getMessage());
    sendError('An unexpected error occurred', 500);
}
?>