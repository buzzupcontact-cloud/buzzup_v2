<?php
/**
 * Admin Get Ticket Details API
 * Retrieves detailed information about a specific ticket including conversation
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

// Get ticket ID from query parameter
$ticketId = $_GET['id'] ?? null;
if (!$ticketId || !is_numeric($ticketId)) {
    sendError('Valid ticket ID is required');
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
    
    // Get ticket details with user information
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
            u.phone,
            u.company,
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            admin.first_name as assigned_admin_first_name,
            admin.last_name as assigned_admin_last_name,
            CONCAT(admin.first_name, ' ', admin.last_name) as assigned_admin_name
        FROM support_tickets st
        JOIN users u ON st.user_id = u.id
        LEFT JOIN users admin ON st.assigned_to = admin.id
        WHERE st.id = ?
    ");
    
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        sendError('Ticket not found', 404);
    }
    
    // Get ticket messages/conversation
    $stmt = $pdo->prepare("
        SELECT 
            stm.id,
            stm.message,
            stm.is_internal,
            stm.created_at,
            u.first_name,
            u.last_name,
            CONCAT(u.first_name, ' ', u.last_name) as sender_name,
            CASE 
                WHEN ur.role_id IN (SELECT id FROM roles WHERE name IN ('admin', 'manager', 'support')) 
                THEN 1 
                ELSE 0 
            END as is_admin
        FROM support_ticket_messages stm
        JOIN users u ON stm.user_id = u.id
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        WHERE stm.ticket_id = ?
        ORDER BY stm.created_at ASC
    ");
    
    $stmt->execute([$ticketId]);
    $messages = $stmt->fetchAll();
    
    // Format ticket data
    $ticketData = [
        'id' => (int)$ticket['id'],
        'subject' => $ticket['subject'],
        'description' => $ticket['description'],
        'priority' => $ticket['priority'],
        'status' => $ticket['status'],
        'category' => $ticket['category'],
        'assigned_to' => $ticket['assigned_to'] ? (int)$ticket['assigned_to'] : null,
        'assigned_admin_name' => $ticket['assigned_admin_name'],
        'user_name' => $ticket['user_name'],
        'user_email' => $ticket['email'],
        'user_phone' => $ticket['phone'],
        'user_company' => $ticket['company'],
        'created_at' => $ticket['created_at'],
        'updated_at' => $ticket['updated_at'],
        'resolved_at' => $ticket['resolved_at'],
        'messages' => array_map(function($message) {
            return [
                'id' => (int)$message['id'],
                'message' => $message['message'],
                'is_internal' => (bool)$message['is_internal'],
                'is_admin' => (bool)$message['is_admin'],
                'sender_name' => $message['sender_name'],
                'created_at' => $message['created_at']
            ];
        }, $messages)
    ];
    
    sendSuccess('Ticket details retrieved successfully', $ticketData);

} catch (PDOException $e) {
    error_log("Admin get ticket details error: " . $e->getMessage());
    sendError('An error occurred while retrieving ticket details', 500);
} catch (Exception $e) {
    error_log("Admin get ticket details error: " . $e->getMessage());
    sendError('An unexpected error occurred', 500);
}
?>