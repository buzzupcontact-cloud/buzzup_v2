<?php
/**
 * Get User Tickets API
 * Retrieves support tickets for the authenticated user
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
    // Get user's support tickets
    $stmt = $pdo->prepare("
        SELECT 
            id,
            subject,
            description,
            priority,
            status,
            category,
            created_at,
            updated_at,
            resolved_at
        FROM support_tickets 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    
    $stmt->execute([$payload['user_id']]);
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
            'created_at' => $ticket['created_at'],
            'updated_at' => $ticket['updated_at'],
            'resolved_at' => $ticket['resolved_at']
        ];
    }, $tickets);
    
    sendSuccess('Tickets retrieved successfully', $formattedTickets);

} catch (PDOException $e) {
    error_log("Get user tickets error: " . $e->getMessage());
    sendError('An error occurred while retrieving tickets', 500);
} catch (Exception $e) {
    error_log("Get user tickets error: " . $e->getMessage());
    sendError('An unexpected error occurred', 500);
}
?>