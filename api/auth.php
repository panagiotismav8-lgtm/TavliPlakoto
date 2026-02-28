<?php
/**
 * Authentication API Endpoint
 * POST /api/auth.php - Login/Register player
 */

require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/Player.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['username'])) {
    errorResponse('Username is required');
}

try {
    $player = new Player();
    $result = $player->login($input['username']);
    
    successResponse([
        'player_id' => $result['id'],
        'username' => $result['username'],
        'token' => $result['session_token']
    ], 'Login successful');
    
} catch (Exception $e) {
    errorResponse($e->getMessage());
}
?>
