<?php
/**
 * Player Management Class
 */

require_once __DIR__ . '/../config/database.php';

class Player {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    /**
     * Login or register a player (simple authentication without password)
     */
    public function login($username) {
        $username = trim($username);
        
        if (empty($username)) {
            throw new Exception("Username is required");
        }
        
        if (strlen($username) < 2 || strlen($username) > 50) {
            throw new Exception("Username must be between 2 and 50 characters");
        }
        
        // Check if player exists
        $stmt = $this->pdo->prepare("SELECT * FROM players WHERE username = ?");
        $stmt->execute([$username]);
        $player = $stmt->fetch();
        
        if ($player) {
            // Update session token
            $token = $this->generateToken();
            $stmt = $this->pdo->prepare("UPDATE players SET session_token = ? WHERE id = ?");
            $stmt->execute([$token, $player['id']]);
            $player['session_token'] = $token;
        } else {
            // Create new player
            $token = $this->generateToken();
            $stmt = $this->pdo->prepare("INSERT INTO players (username, session_token) VALUES (?, ?)");
            $stmt->execute([$username, $token]);
            
            $player = [
                'id' => $this->pdo->lastInsertId(),
                'username' => $username,
                'session_token' => $token
            ];
        }
        
        return $player;
    }
    
    /**
     * Validate session token
     */
    public function validateToken($token) {
        if (empty($token)) {
            return null;
        }
        
        $stmt = $this->pdo->prepare("SELECT * FROM players WHERE session_token = ?");
        $stmt->execute([$token]);
        return $stmt->fetch();
    }
    
    /**
     * Get player by ID
     */
    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM players WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Generate random token
     */
    private function generateToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Update last active timestamp
     */
    public function updateActivity($playerId) {
        $stmt = $this->pdo->prepare("UPDATE players SET last_active = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$playerId]);
    }
}
?>
