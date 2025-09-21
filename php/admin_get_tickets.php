<?php
/**
 * Admin Get Tickets API
 * Retrieves all support tickets for admin dashboard
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
    
    // Get all support tickets with user information
    $stmt = $pdo->prepare("
        SELECT 
            st.id,
            st.subject,
            st.description,
            st.priority,
            st.status,
            st.category,
            st.assigned_to,
            st.created_at,
            st.updated_at,
            st.resolved_at,
            u.first_name,
            u.last_name,
            u.email,
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            u.email as user_email,
            admin.first_name as assigned_admin_first_name,
            admin.last_name as assigned_admin_last_name,
            CONCAT(admin.first_name, ' ', admin.last_name) as assigned_admin_name
        FROM support_tickets st
        JOIN users u ON st.user_id = u.id
        LEFT JOIN users admin ON st.assigned_to = admin.id
        ORDER BY 
            CASE st.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END,
            st.created_at DESC
    ");
    
    $stmt->execute();
    $tickets = $stmt->fetchAll();
    
    // Format tickets data
    $formattedTickets = array_map(function($ticket) {
        return [
            'id' => (int)$ticket['id'],
            'subject' => $ticket['subject'],
            'description' => $ticket['description'],
            'priority' => $ticket['priority'],
            'status' => $ticket['status'],
            'category' => $ticket['category'],
            'assigned_to' => $ticket['assigned_to'] ? (int)$ticket['assigned_to'] : null,
            'assigned_admin_name' => $ticket['assigned_admin_name'],
            'user_name' => $ticket['user_name'],
            'user_email' => $ticket['user_email'],
            'created_at' => $ticket['created_at'],
            'updated_at' => $ticket['updated_at'],
            'resolved_at' => $ticket['resolved_at']
        ];
    }, $tickets);
    
    sendSuccess('Tickets retrieved successfully', $formattedTickets);

} catch (PDOException $e) {
    error_log("Admin get tickets error: " . $e->getMessage());
    sendError('An error occurred while retrieving tickets', 500);
} catch (Exception $e) {
    error_log("Admin get tickets error: " . $e->getMessage());
    sendError('An unexpected error occurred', 500);
}
?>