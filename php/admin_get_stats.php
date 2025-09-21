<?php
/**
 * Admin Get Statistics API
 * Retrieves dashboard statistics for admin panel
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
        WHERE ur.user_id = ? AND r.name IN ('admin', 'manager', 'support')
    ");
    $stmt->execute([$payload['user_id']]);
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($roles)) {
        sendError('Access denied. Admin privileges required.', 403);
    }
    
    // Get ticket statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_tickets,
            SUM(CASE WHEN status IN ('open', 'in_progress') THEN 1 ELSE 0 END) as pending_tickets,
            SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved_tickets,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tickets,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
            SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_tickets,
            SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_tickets,
            SUM(CASE WHEN priority = 'medium' THEN 1 ELSE 0 END) as medium_tickets,
            SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END) as low_tickets
        FROM support_tickets
    ");
    
    $stmt->execute();
    $ticketStats = $stmt->fetch();
    
    // Get user statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_users,
            SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verified_users,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30_days
        FROM users
    ");
    
    $stmt->execute();
    $userStats = $stmt->fetch();
    
    // Get recent activity
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_activities,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as activities_24h,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as activities_7d
        FROM activity_logs
    ");
    
    $stmt->execute();
    $activityStats = $stmt->fetch();
    
    // Get ticket trends (last 7 days)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count
        FROM support_tickets 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    
    $stmt->execute();
    $ticketTrends = $stmt->fetchAll();
    
    // Prepare statistics data
    $stats = [
        'tickets' => [
            'total' => (int)$ticketStats['total_tickets'],
            'pending' => (int)$ticketStats['pending_tickets'],
            'resolved' => (int)$ticketStats['resolved_tickets'],
            'open' => (int)$ticketStats['open_tickets'],
            'in_progress' => (int)$ticketStats['in_progress_tickets'],
            'by_priority' => [
                'urgent' => (int)$ticketStats['urgent_tickets'],
                'high' => (int)$ticketStats['high_tickets'],
                'medium' => (int)$ticketStats['medium_tickets'],
                'low' => (int)$ticketStats['low_tickets']
            ]
        ],
        'users' => [
            'total' => (int)$userStats['total_users'],
            'active' => (int)$userStats['active_users'],
            'inactive' => (int)$userStats['inactive_users'],
            'verified' => (int)$userStats['verified_users'],
            'new_30_days' => (int)$userStats['new_users_30_days']
        ],
        'activity' => [
            'total' => (int)$activityStats['total_activities'],
            'last_24h' => (int)$activityStats['activities_24h'],
            'last_7d' => (int)$activityStats['activities_7d']
        ],
        'trends' => [
            'tickets_7d' => array_map(function($trend) {
                return [
                    'date' => $trend['date'],
                    'count' => (int)$trend['count']
                ];
            }, $ticketTrends)
        ],
        // Legacy format for backward compatibility
        'totalTickets' => (int)$ticketStats['total_tickets'],
        'pendingTickets' => (int)$ticketStats['pending_tickets'],
        'resolvedTickets' => (int)$ticketStats['resolved_tickets'],
        'totalUsers' => (int)$userStats['total_users']
    ];
    
    sendSuccess('Statistics retrieved successfully', $stats);

} catch (PDOException $e) {
    error_log("Admin get stats error: " . $e->getMessage());
    sendError('An error occurred while retrieving statistics', 500);
} catch (Exception $e) {
    error_log("Admin get stats error: " . $e->getMessage());
    sendError('An unexpected error occurred', 500);
}
?>