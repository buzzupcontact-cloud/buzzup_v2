<?php
/**
 * Admin Reply to Ticket API
 * Allows admin users to reply to support tickets and update status
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
if (empty($input['ticketId']) || empty($input['message'])) {
    sendError('Ticket ID and message are required');
}

$ticketId = (int)$input['ticketId'];
$message = sanitizeInput($input['message']);
$status = sanitizeInput($input['status'] ?? 'in_progress');

// Validate status
$validStatuses = ['open', 'in_progress', 'resolved', 'closed'];
if (!in_array($status, $validStatuses)) {
    sendError('Invalid status');
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
    
    // Verify ticket exists
    $stmt = $pdo->prepare("SELECT id, user_id, status FROM support_tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        sendError('Ticket not found', 404);
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Add reply message
        $stmt = $pdo->prepare("
            INSERT INTO support_ticket_messages (ticket_id, user_id, message, is_internal, created_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $ticketId,
            $payload['user_id'],
            $message,
            0, // Not internal - visible to customer
            date('Y-m-d H:i:s')
        ]);
        
        // Update ticket status and assign to current admin if not already assigned
        $updateFields = ['status = ?', 'updated_at = ?'];
        $updateValues = [$status, date('Y-m-d H:i:s')];
        
        // If ticket is not assigned, assign it to current admin
        if (!$ticket['assigned_to']) {
            $updateFields[] = 'assigned_to = ?';
            $updateValues[] = $payload['user_id'];
        }
        
        // If status is resolved or closed, set resolved_at
        if (in_array($status, ['resolved', 'closed'])) {
            $updateFields[] = 'resolved_at = ?';
            $updateValues[] = date('Y-m-d H:i:s');
        }
        
        $updateValues[] = $ticketId; // For WHERE clause
        
        $stmt = $pdo->prepare("
            UPDATE support_tickets 
            SET " . implode(', ', $updateFields) . "
            WHERE id = ?
        ");
        
        $stmt->execute($updateValues);
        
        // Log activity
        logActivity($payload['user_id'], 'ticket_replied', "Replied to ticket #$ticketId and updated status to $status");
        
        // Commit transaction
        $pdo->commit();
        
        sendSuccess('Reply sent and ticket updated successfully');
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Admin reply ticket error: " . $e->getMessage());
    sendError('An error occurred while sending reply', 500);
} catch (Exception $e) {
    error_log("Admin reply ticket error: " . $e->getMessage());
    sendError('An unexpected error occurred', 500);
}
?>