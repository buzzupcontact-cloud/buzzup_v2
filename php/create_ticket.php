<?php
/**
 * Create Support Ticket API
 * Handles creation of new support tickets from authenticated users
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
$requiredFields = ['subject', 'priority', 'title', 'message'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        sendError(ucfirst($field) . ' is required');
    }
}

// Sanitize input data
$subject = sanitizeInput($input['subject']);
$priority = sanitizeInput($input['priority']);
$title = sanitizeInput($input['title']);
$message = sanitizeInput($input['message']);

// Validate priority
$validPriorities = ['low', 'medium', 'high', 'urgent'];
if (!in_array($priority, $validPriorities)) {
    sendError('Invalid priority level');
}

// Validate subject category
$validSubjects = ['technical', 'billing', 'hosting', 'marketing', 'general'];
if (!in_array($subject, $validSubjects)) {
    sendError('Invalid subject category');
}

// Get database connection
$pdo = getDBConnection();
if (!$pdo) {
    sendError('Database connection failed', 500);
}

try {
    // Create support ticket
    $stmt = $pdo->prepare("
        INSERT INTO support_tickets (user_id, subject, description, priority, category, created_at) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $payload['user_id'],
        $title,
        $message,
        $priority,
        $subject,
        date('Y-m-d H:i:s')
    ]);
    
    $ticketId = $pdo->lastInsertId();
    
    // Log activity
    logActivity($payload['user_id'], 'ticket_created', "Support ticket #$ticketId created");
    
    // Prepare response data
    $responseData = [
        'ticket_id' => $ticketId,
        'subject' => $title,
        'priority' => $priority,
        'status' => 'open',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    sendSuccess('Support ticket created successfully', $responseData, 201);

} catch (PDOException $e) {
    error_log("Create ticket error: " . $e->getMessage());
    sendError('An error occurred while creating the ticket', 500);
} catch (Exception $e) {
    error_log("Create ticket error: " . $e->getMessage());
    sendError('An unexpected error occurred', 500);
}
?>