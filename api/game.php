<?php
/**
 * Game API Endpoint
 * Handles game creation, joining, and listing
 */

require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/Player.php';
require_once __DIR__ . '/../includes/PlakotoGame.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get token from header
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

// Also check for token in query/body
if (!$token) {
    $token = $_GET['token'] ?? null;
}
if (!$token) {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? null;
}

// Validate player
$playerManager = new Player();
$player = $playerManager->validateToken($token);

if (!$player) {
    errorResponse('Unauthorized - Please login first', 401);
}

$playerManager->updateActivity($player['id']);

// Route based on method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet($action, $player);
            break;
        case 'POST':
            handlePost($action, $player);
            break;
        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    errorResponse($e->getMessage());
}

function handleGet($action, $player) {
    switch ($action) {
        case 'list':
            // List available games to join
            $games = PlakotoGame::listAvailableGames();
            successResponse($games, 'Available games');
            break;
            
        case 'my-games':
            // List player's games
            $games = PlakotoGame::listPlayerGames($player['id']);
            successResponse($games, 'Your games');
            break;
            
        case 'state':
            // Get game state
            $gameId = $_GET['game_id'] ?? null;
            if (!$gameId) {
                errorResponse('Game ID required');
            }
            $game = new PlakotoGame($gameId);
            $state = $game->getFullGameState($player['id']);
            successResponse($state, 'Game state');
            break;
            
        case 'valid-moves':
            // Get valid moves for current player
            $gameId = $_GET['game_id'] ?? null;
            if (!$gameId) {
                errorResponse('Game ID required');
            }
            $game = new PlakotoGame($gameId);
            $moves = $game->getValidMoves($player['id']);
            successResponse($moves, 'Valid moves');
            break;
            
        default:
            errorResponse('Invalid action');
    }
}

function handlePost($action, $player) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'create':
            // Create new game
            $game = new PlakotoGame();
            $gameId = $game->createGame($player['id']);
            successResponse(['game_id' => $gameId], 'Game created');
            break;
            
        case 'join':
            // Join existing game
            $gameId = $input['game_id'] ?? null;
            if (!$gameId) {
                errorResponse('Game ID required');
            }
            $game = new PlakotoGame($gameId);
            $game->joinGame($player['id']);
            successResponse(['game_id' => $gameId], 'Joined game');
            break;
            
        case 'roll':
            // Roll dice
            $gameId = $input['game_id'] ?? null;
            if (!$gameId) {
                errorResponse('Game ID required');
            }
            $game = new PlakotoGame($gameId);
            $result = $game->rollDice($player['id']);
            successResponse($result, 'Dice rolled');
            break;
            
        case 'move':
            // Make a move
            $gameId = $input['game_id'] ?? null;
            $from = $input['from'] ?? null;
            $to = $input['to'] ?? null;
            
            if (!$gameId || $from === null || $to === null) {
                errorResponse('Game ID, from point, and to point required');
            }
            
            $game = new PlakotoGame($gameId);
            $result = $game->makeMove($player['id'], (int)$from, (int)$to);
            
            // Get updated state
            $state = $game->getFullGameState($player['id']);
            
            successResponse([
                'move_result' => $result,
                'state' => $state
            ], 'Move made');
            break;
            
        case 'pass':
            // Pass turn (when no valid moves)
            $gameId = $input['game_id'] ?? null;
            if (!$gameId) {
                errorResponse('Game ID required');
            }
            $game = new PlakotoGame($gameId);
            $game->passTurn($player['id']);
            successResponse(null, 'Turn passed');
            break;
            
        default:
            errorResponse('Invalid action');
    }
}
?>
