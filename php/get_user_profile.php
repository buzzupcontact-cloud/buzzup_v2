<?php
/**
 * Get User Profile API
 * Retrieves detailed profile information for the authenticated user
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
    // Get user profile information
    $stmt = $pdo->prepare("
        SELECT 
            id,
            first_name,
            last_name,
            email,
            phone,
            company,
            job_title,
            bio,
            profile_image,
            email_verified,
            status,
            last_login,
            created_at
        FROM users 
        WHERE id = ? AND status = 'active'
    ");
    
    $stmt->execute([$payload['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('User not found', 404);
    }
    
    // Get user statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_tickets,
            SUM(CASE WHEN status IN ('open', 'in_progress') THEN 1 ELSE 0 END) as active_tickets,
            SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved_tickets
        FROM support_tickets 
        WHERE user_id = ?
    ");
    
    $stmt->execute([$payload['user_id']]);
    $stats = $stmt->fetch();
    
    // Prepare user data
    $userData = [
        'id' => (int)$user['id'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'name' => $user['first_name'] . ' ' . $user['last_name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'company' => $user['company'],
        'job_title' => $user['job_title'],
        'bio' => $user['bio'],
        'profile_image' => $user['profile_image'],
        'email_verified' => (bool)$user['email_verified'],
        'status' => $user['status'],
        'last_login' => $user['last_login'],
        'created_at' => $user['created_at'],
        'stats' => [
            'total_tickets' => (int)$stats['total_tickets'],
            'active_tickets' => (int)$stats['active_tickets'],
            'resolved_tickets' => (int)$stats['resolved_tickets']
        ]
    ];
    
    sendSuccess('Profile retrieved successfully', $userData);

} catch (PDOException $e) {
    error_log("Get user profile error: " . $e->getMessage());
    sendError('An error occurred while retrieving profile', 500);
} catch (Exception $e) {
    error_log("Get user profile error: " . $e->getMessage());
    sendError('An unexpected error occurred', 500);
}
?>