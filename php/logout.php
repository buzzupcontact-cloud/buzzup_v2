<?php
/**
 * User Logout API
 * Handles user logout and session cleanup
 */

require_once 'config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// Get authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
    $payload = validateJWT($token);
    
    if ($payload) {
        // Get database connection
        $pdo = getDBConnection();
        
        if ($pdo) {
            try {
                // Remove user sessions
                $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
                $stmt->execute([$payload['user_id']]);
                
                // Log logout activity
                logActivity($payload['user_id'], 'logout', 'User logged out');
                
                sendSuccess('Logged out successfully');
            } catch (PDOException $e) {
                error_log("Logout error: " . $e->getMessage());
                sendSuccess('Logged out successfully'); // Still return success even if DB operation fails
            }
        }
    }
}

// Always return success for logout (even if token is invalid)
sendSuccess('Logged out successfully');
?>