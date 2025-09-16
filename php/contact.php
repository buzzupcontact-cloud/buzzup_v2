<?php
/**
 * Contact Form API
 * Handles contact inquiries and support requests
 */

require_once 'config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendError('Invalid JSON data');
}

// Validate required fields
$requiredFields = ['name', 'email', 'subject', 'message'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        sendError(ucfirst($field) . ' is required');
    }
}

// Sanitize input data
$name = sanitizeInput($input['name']);
$email = sanitizeInput($input['email']);
$subject = sanitizeInput($input['subject']);
$message = sanitizeInput($input['message']);
$serviceType = sanitizeInput($input['service_type'] ?? 'general');

// Validate input
if (!validateEmail($email)) {
    sendError('Invalid email format');
}

// Validate service type
$validServiceTypes = ['sponsoring', 'hosting', 'general'];
if (!in_array($serviceType, $validServiceTypes)) {
    $serviceType = 'general';
}

// Check rate limiting (prevent spam)
if (!checkRateLimit($_SERVER['REMOTE_ADDR'], 'contact')) {
    sendError('Too many contact submissions. Please try again later.', 429);
}

// Get database connection
$pdo = getDBConnection();
if (!$pdo) {
    sendError('Database connection failed', 500);
}

try {
    // Insert contact inquiry
    $stmt = $pdo->prepare("
        INSERT INTO contact_inquiries (name, email, subject, message, service_type, created_at) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $name,
        $email,
        $subject,
        $message,
        $serviceType,
        date('Y-m-d H:i:s')
    ]);
    
    $inquiryId = $pdo->lastInsertId();
    
    // Record successful attempt
    recordAttempt($_SERVER['REMOTE_ADDR'], 'contact', true);
    
    // Here you would typically send email notifications
    // to the admin team about the new inquiry
    
    // Prepare response data
    $responseData = [
        'inquiry_id' => $inquiryId,
        'message' => 'Thank you for contacting us! We will get back to you within 24 hours.',
        'expected_response_time' => '24 hours'
    ];
    
    sendSuccess('Contact form submitted successfully', $responseData, 201);

} catch (PDOException $e) {
    recordAttempt($_SERVER['REMOTE_ADDR'], 'contact', false);
    error_log("Contact form error: " . $e->getMessage());
    sendError('An error occurred while submitting your message', 500);
} catch (Exception $e) {
    recordAttempt($_SERVER['REMOTE_ADDR'], 'contact', false);
    error_log("Contact form error: " . $e->getMessage());
    sendError('An unexpected error occurred', 500);
}
?>