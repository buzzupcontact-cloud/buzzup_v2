<?php
/**
 * Database Configuration
 * BuzzUp Website Backend Configuration
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'buzzup_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// JWT Configuration
define('JWT_SECRET', 'your_super_secret_jwt_key_here_change_in_production');
define('JWT_EXPIRY', 3600 * 24); // 24 hours

// Application configuration
define('APP_NAME', 'BuzzUp');
define('APP_URL', 'http://localhost:3000');
define('APP_VERSION', '1.0.0');

// Security settings
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes

// Email configuration (for future use)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your_email@gmail.com');
define('SMTP_PASSWORD', 'your_app_password');
define('SMTP_FROM', 'noreply@buzzup.com');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Database connection function
 */
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Response helper functions
 */
function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

function sendError($message, $httpCode = 400, $data = null) {
    sendResponse(false, $message, $data, $httpCode);
}

function sendSuccess($message, $data = null, $httpCode = 200) {
    sendResponse(true, $message, $data, $httpCode);
}

/**
 * Input validation and sanitization
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    // At least 8 characters, one uppercase, one lowercase, one number, one special char
    return strlen($password) >= PASSWORD_MIN_LENGTH && 
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[a-z]/', $password) &&
           preg_match('/\d/', $password) &&
           preg_match('/[^A-Za-z0-9]/', $password);
}

/**
 * JWT Helper functions
 */
function generateJWT($payload) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    
    $payload['exp'] = time() + JWT_EXPIRY;
    $payload['iat'] = time();
    $payload = json_encode($payload);
    
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

function validateJWT($jwt) {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return false;
    }
    
    list($header, $payload, $signature) = $parts;
    
    $validSignature = str_replace(['+', '/', '='], ['-', '_', ''], 
        base64_encode(hash_hmac('sha256', $header . "." . $payload, JWT_SECRET, true)));
    
    if ($signature !== $validSignature) {
        return false;
    }
    
    $decodedPayload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
    
    if ($decodedPayload['exp'] < time()) {
        return false; // Token expired
    }
    
    return $decodedPayload;
}

/**
 * Security helper functions
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Rate limiting functions
 */
function checkRateLimit($identifier, $action) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        // Clean old attempts
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < ?");
        $stmt->execute([date('Y-m-d H:i:s', time() - LOCKOUT_TIME)]);
        
        // Check current attempts
        $stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE identifier = ? AND action = ?");
        $stmt->execute([$identifier, $action]);
        $result = $stmt->fetch();
        
        return $result['attempts'] < MAX_LOGIN_ATTEMPTS;
    } catch (PDOException $e) {
        error_log("Rate limit check failed: " . $e->getMessage());
        return true; // Fail open for availability
    }
}

function recordAttempt($identifier, $action, $success = false) {
    $pdo = getDBConnection();
    if (!$pdo) return;
    
    try {
        if ($success) {
            // Clear failed attempts on success
            $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE identifier = ? AND action = ?");
            $stmt->execute([$identifier, $action]);
        } else {
            // Record failed attempt
            $stmt = $pdo->prepare("INSERT INTO login_attempts (identifier, action, attempt_time) VALUES (?, ?, ?)");
            $stmt->execute([$identifier, $action, date('Y-m-d H:i:s')]);
        }
    } catch (PDOException $e) {
        error_log("Failed to record attempt: " . $e->getMessage());
    }
}

/**
 * Logging function
 */
function logActivity($userId, $action, $details = null) {
    $pdo = getDBConnection();
    if (!$pdo) return;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            date('Y-m-d H:i:s')
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}
?>