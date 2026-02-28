<?php
/**
 * Plakoto Game Logic Class
 * Implements all game rules for Tavli-Plakoto
 */

require_once __DIR__ . '/../config/database.php';

class PlakotoGame {
    private $pdo;
    private $gameId;
    
    // Plakoto specific: All 15 checkers start at point 1 for player 1, point 24 for player 2
    // Players move in opposite directions
    // Player 1 moves 1->24 (home is 19-24), Player 2 moves 24->1 (home is 1-6)
    
    public function __construct($gameId = null) {
        $this->pdo = getDBConnection();
        $this->gameId = $gameId;
    }
    
    /**
     * Create a new game
     */
    public function createGame($playerId) {
        // Create game record
        $stmt = $this->pdo->prepare("INSERT INTO games (player1_id, status) VALUES (?, 'waiting')");
        $stmt->execute([$playerId]);
        $this->gameId = $this->pdo->lastInsertId();
        
        // Initialize board - Player 1: all 15 checkers on point 1
        $stmt = $this->pdo->prepare("INSERT INTO board_state (game_id, point_number, player_id, checker_count, is_pinned) VALUES (?, 1, ?, 15, FALSE)");
        $stmt->execute([$this->gameId, $playerId]);
        
        // Log game creation
        $this->logMessage("Το παιχνίδι δημιουργήθηκε. Αναμονή αντιπάλου...");
        
        return $this->gameId;
    }
    
    /**
     * Join an existing game
     */
    public function joinGame($playerId) {
        // Get game info
        $stmt = $this->pdo->prepare("SELECT * FROM games WHERE id = ? AND status = 'waiting'");
        $stmt->execute([$this->gameId]);
        $game = $stmt->fetch();
        
        if (!$game) {
            throw new Exception("Game not found or already started");
        }
        
        if ($game['player1_id'] == $playerId) {
            throw new Exception("Cannot join your own game");
        }
        
        // Update game with player 2
        $stmt = $this->pdo->prepare("UPDATE games SET player2_id = ?, status = 'playing' WHERE id = ?");
        $stmt->execute([$playerId, $this->gameId]);
        
        // Initialize board - Player 2: all 15 checkers on point 24
        $stmt = $this->pdo->prepare("INSERT INTO board_state (game_id, point_number, player_id, checker_count, is_pinned) VALUES (?, 24, ?, 15, FALSE)");
        $stmt->execute([$this->gameId, $playerId]);
        
        // Determine who goes first (higher die roll)
        $this->determineFirstPlayer();
        
        $this->logMessage("Ο Παίκτης 2 μπήκε. Το παιχνίδι ξεκίνησε!");
        
        return true;
    }
    
    /**
     * Determine first player by rolling dice
     */
    private function determineFirstPlayer() {
        $game = $this->getGame();
        
        // Roll for each player until different
        do {
            $roll1 = rand(1, 6);
            $roll2 = rand(1, 6);
        } while ($roll1 == $roll2);
        
        $firstPlayer = ($roll1 > $roll2) ? $game['player1_id'] : $game['player2_id'];
        
        // Set initial dice for first player's turn
        $stmt = $this->pdo->prepare("UPDATE games SET current_turn = ?, dice1 = 0, dice2 = 0, dice_rolled = FALSE WHERE id = ?");
        $stmt->execute([$firstPlayer, $this->gameId]);
        
        $this->logMessage("Αρχική ρίψη: Παίκτης 1 έριξε $roll1, Παίκτης 2 έριξε $roll2. " . 
                         ($roll1 > $roll2 ? "Ο Παίκτης 1" : "Ο Παίκτης 2") . " παίζει πρώτος!");
    }
    
    /**
     * Roll dice for current player
     */
    public function rollDice($playerId) {
        $game = $this->getGame();
        
        if ($game['status'] !== 'playing') {
            throw new Exception("Game is not in progress");
        }
        
        if ($game['current_turn'] != $playerId) {
            throw new Exception("Not your turn");
        }
        
        if ($game['dice_rolled']) {
            throw new Exception("Dice already rolled this turn");
        }
        
        $dice1 = rand(1, 6);
        $dice2 = rand(1, 6);
        
        // Build moves remaining string (doubles = 4 moves)
        if ($dice1 == $dice2) {
            $movesRemaining = "$dice1,$dice1,$dice1,$dice1";
        } else {
            $movesRemaining = "$dice1,$dice2";
        }
        
        $stmt = $this->pdo->prepare("UPDATE games SET dice1 = ?, dice2 = ?, dice_rolled = TRUE, moves_remaining = ? WHERE id = ?");
        $stmt->execute([$dice1, $dice2, $movesRemaining, $this->gameId]);
        
        $this->logMessage("Έριξε: $dice1 και $dice2" . ($dice1 == $dice2 ? " (διπλές!)" : ""));
        
        return ['dice1' => $dice1, 'dice2' => $dice2, 'doubles' => ($dice1 == $dice2)];
    }
    
    /**
     * Make a move
     */
    public function makeMove($playerId, $fromPoint, $toPoint) {
        $game = $this->getGame();
        
        // Validate basic conditions
        if ($game['status'] !== 'playing') {
            throw new Exception("Game is not in progress");
        }
        
        if ($game['current_turn'] != $playerId) {
            throw new Exception("Not your turn");
        }
        
        if (!$game['dice_rolled']) {
            throw new Exception("Must roll dice first");
        }
        
        $movesRemaining = $this->getMovesRemaining();
        if (empty($movesRemaining)) {
            throw new Exception("No moves remaining");
        }
        
        // Validate the move
        $diceUsed = $this->validateMove($playerId, $fromPoint, $toPoint, $movesRemaining);
        
        // Execute the move
        $this->executeMove($playerId, $fromPoint, $toPoint);
        
        // Remove used die from moves remaining
        $this->removeDieFromMoves($diceUsed);
        
        // Record move in history
        $this->recordMove($playerId, $fromPoint, $toPoint, $diceUsed);
        
        // Check for win
        if ($this->checkWin($playerId)) {
            $stmt = $this->pdo->prepare("UPDATE games SET status = 'finished', winner_id = ? WHERE id = ?");
            $stmt->execute([$playerId, $this->gameId]);
            $this->logMessage("Τέλος Παιχνιδιού! Αναδείχθηκε νικητής!");
            return ['moved' => true, 'gameOver' => true, 'winner' => $playerId];
        }
        
        // Check if turn should end
        $movesRemaining = $this->getMovesRemaining();
        if (empty($movesRemaining) || !$this->hasValidMoves($playerId)) {
            $this->endTurn();
        }
        
        return ['moved' => true, 'gameOver' => false];
    }
    
    /**
     * Validate a move according to Plakoto rules
     */
    private function validateMove($playerId, $fromPoint, $toPoint, $movesRemaining) {
        $game = $this->getGame();
        $isPlayer1 = ($playerId == $game['player1_id']);
        
        // Get board state
        $board = $this->getBoardState();
        
        // Check if player has checkers at fromPoint
        $playerCheckers = $this->getCheckersAt($playerId, $fromPoint);
        if ($playerCheckers <= 0) {
            throw new Exception("No checkers at point $fromPoint");
        }
        
        // Check if checkers at fromPoint are pinned
        if ($this->isPointPinned($playerId, $fromPoint)) {
            throw new Exception("Checker at point $fromPoint is pinned");
        }
        
        // Calculate move distance
        if ($toPoint == 0) {
            // Bearing off
            if ($isPlayer1) {
                $distance = 25 - $fromPoint; // Player 1 bears off from points 19-24
            } else {
                $distance = $fromPoint; // Player 2 bears off from points 1-6
            }
        } else {
            if ($isPlayer1) {
                $distance = $toPoint - $fromPoint; // Player 1 moves forward (1->24)
            } else {
                $distance = $fromPoint - $toPoint; // Player 2 moves forward (24->1)
            }
        }
        
        if ($distance <= 0) {
            throw new Exception("Invalid move direction");
        }
        
        // Check if dice value matches
        $diceUsed = null;
        foreach ($movesRemaining as $die) {
            if ($die == $distance) {
                $diceUsed = $die;
                break;
            }
        }
        
        // For bearing off, can use higher die if no exact match and all checkers in home
        if ($diceUsed === null && $toPoint == 0) {
            if ($this->allCheckersInHome($playerId)) {
                // Can use higher die if this is the furthest checker
                foreach ($movesRemaining as $die) {
                    if ($die >= $distance) {
                        if ($this->isFurthestChecker($playerId, $fromPoint)) {
                            $diceUsed = $die;
                            break;
                        }
                    }
                }
            }
        }
        
        if ($diceUsed === null) {
            throw new Exception("No valid die for this move (distance: $distance)");
        }
        
        // Check destination rules
        if ($toPoint != 0) {
            // Check if destination is valid (not blocked by 2+ opponent checkers in Plakoto)
            // In Plakoto, you CAN land on a single opponent checker to PIN it
            $opponentId = $isPlayer1 ? $game['player2_id'] : $game['player1_id'];
            $opponentCheckers = $this->getCheckersAt($opponentId, $toPoint);
            
            // In Plakoto: cannot land where opponent has 2+ checkers
            if ($opponentCheckers >= 2) {
                throw new Exception("Point $toPoint is blocked by opponent");
            }
            
            // In Plakoto: if your checker is pinned at this point, you cannot add more checkers there
            $myCheckers = $this->getCheckersAt($playerId, $toPoint);
            if ($myCheckers > 0 && $this->isPointPinned($playerId, $toPoint)) {
                throw new Exception("Cannot add more checkers to point $toPoint - your checker is pinned there");
            }
        } else {
            // Bearing off - must have all checkers in home board
            if (!$this->allCheckersInHome($playerId)) {
                throw new Exception("Cannot bear off - not all checkers in home board");
            }
        }
        
        return $diceUsed;
    }
    
    /**
     * Execute a validated move
     */
    private function executeMove($playerId, $fromPoint, $toPoint) {
        $game = $this->getGame();
        $isPlayer1 = ($playerId == $game['player1_id']);
        $opponentId = $isPlayer1 ? $game['player2_id'] : $game['player1_id'];
        
        // Remove checker from source
        $this->updateCheckerCount($playerId, $fromPoint, -1);
        
        // If source now has 0 checkers, check if we need to unpin opponent
        if ($this->getCheckersAt($playerId, $fromPoint) == 0) {
            // Unpin any opponent checker at this point
            $stmt = $this->pdo->prepare("UPDATE board_state SET is_pinned = FALSE WHERE game_id = ? AND point_number = ? AND player_id = ?");
            $stmt->execute([$this->gameId, $fromPoint, $opponentId]);
        }
        
        if ($toPoint != 0) {
            // Add checker to destination
            $this->updateCheckerCount($playerId, $toPoint, 1);
            
            // Check if we're pinning an opponent checker
            $opponentCheckers = $this->getCheckersAt($opponentId, $toPoint);
            if ($opponentCheckers == 1) {
                // Pin the opponent's checker
                $stmt = $this->pdo->prepare("UPDATE board_state SET is_pinned = TRUE WHERE game_id = ? AND point_number = ? AND player_id = ?");
                $stmt->execute([$this->gameId, $toPoint, $opponentId]);
                $this->logMessage("Πλάκωσε πούλι αντιπάλου στη θέση $toPoint!");
            }
        } else {
            // Bearing off - add to borne off pile
            $this->updateCheckerCount($playerId, 0, 1);
        }
        
        $this->logMessage("Κίνηση από $fromPoint προς " . ($toPoint == 0 ? "έξω" : $toPoint));
    }
    
    /**
     * Update checker count at a point
     */
    private function updateCheckerCount($playerId, $point, $delta) {
        // Check if entry exists
        $stmt = $this->pdo->prepare("SELECT checker_count FROM board_state WHERE game_id = ? AND point_number = ? AND player_id = ?");
        $stmt->execute([$this->gameId, $point, $playerId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $newCount = $existing['checker_count'] + $delta;
            if ($newCount <= 0) {
                $stmt = $this->pdo->prepare("DELETE FROM board_state WHERE game_id = ? AND point_number = ? AND player_id = ?");
                $stmt->execute([$this->gameId, $point, $playerId]);
            } else {
                $stmt = $this->pdo->prepare("UPDATE board_state SET checker_count = ? WHERE game_id = ? AND point_number = ? AND player_id = ?");
                $stmt->execute([$newCount, $this->gameId, $point, $playerId]);
            }
        } else if ($delta > 0) {
            $stmt = $this->pdo->prepare("INSERT INTO board_state (game_id, point_number, player_id, checker_count, is_pinned) VALUES (?, ?, ?, ?, FALSE)");
            $stmt->execute([$this->gameId, $point, $playerId, $delta]);
        }
    }
    
    /**
     * Get checkers at a point for a player
     */
    private function getCheckersAt($playerId, $point) {
        $stmt = $this->pdo->prepare("SELECT checker_count FROM board_state WHERE game_id = ? AND point_number = ? AND player_id = ?");
        $stmt->execute([$this->gameId, $point, $playerId]);
        $result = $stmt->fetch();
        return $result ? $result['checker_count'] : 0;
    }
    
    /**
     * Check if a point is pinned for a player
     */
    private function isPointPinned($playerId, $point) {
        $stmt = $this->pdo->prepare("SELECT is_pinned FROM board_state WHERE game_id = ? AND point_number = ? AND player_id = ?");
        $stmt->execute([$this->gameId, $point, $playerId]);
        $result = $stmt->fetch();
        return $result ? $result['is_pinned'] : false;
    }
    
    /**
     * Check if all checkers are in home board
     */
    private function allCheckersInHome($playerId) {
        $game = $this->getGame();
        $isPlayer1 = ($playerId == $game['player1_id']);
        
        // Player 1 home: 19-24, Player 2 home: 1-6
        if ($isPlayer1) {
            $stmt = $this->pdo->prepare("SELECT SUM(checker_count) as count FROM board_state WHERE game_id = ? AND player_id = ? AND point_number NOT IN (0, 19, 20, 21, 22, 23, 24)");
        } else {
            $stmt = $this->pdo->prepare("SELECT SUM(checker_count) as count FROM board_state WHERE game_id = ? AND player_id = ? AND point_number NOT IN (0, 1, 2, 3, 4, 5, 6)");
        }
        $stmt->execute([$this->gameId, $playerId]);
        $result = $stmt->fetch();
        
        return ($result['count'] == 0 || $result['count'] === null);
    }
    
    /**
     * Check if this is the furthest checker from bearing off
     */
    private function isFurthestChecker($playerId, $point) {
        $game = $this->getGame();
        $isPlayer1 = ($playerId == $game['player1_id']);
        
        if ($isPlayer1) {
            // For player 1, furthest is lowest point number in home (19-24)
            $stmt = $this->pdo->prepare("SELECT MIN(point_number) as furthest FROM board_state WHERE game_id = ? AND player_id = ? AND point_number BETWEEN 19 AND 24 AND checker_count > 0");
        } else {
            // For player 2, furthest is highest point number in home (1-6)
            $stmt = $this->pdo->prepare("SELECT MAX(point_number) as furthest FROM board_state WHERE game_id = ? AND player_id = ? AND point_number BETWEEN 1 AND 6 AND checker_count > 0");
        }
        $stmt->execute([$this->gameId, $playerId]);
        $result = $stmt->fetch();
        
        return $result['furthest'] == $point;
    }
    
    /**
     * Check if player has won
     */
    private function checkWin($playerId) {
        $borneOff = $this->getCheckersAt($playerId, 0);
        return $borneOff >= 15;
    }
    
    /**
     * Get moves remaining array
     */
    private function getMovesRemaining() {
        $game = $this->getGame();
        if (empty($game['moves_remaining'])) {
            return [];
        }
        return array_map('intval', explode(',', $game['moves_remaining']));
    }
    
    /**
     * Remove a die from moves remaining
     */
    private function removeDieFromMoves($die) {
        $moves = $this->getMovesRemaining();
        $index = array_search($die, $moves);
        if ($index !== false) {
            unset($moves[$index]);
        }
        $movesStr = implode(',', array_values($moves));
        
        $stmt = $this->pdo->prepare("UPDATE games SET moves_remaining = ? WHERE id = ?");
        $stmt->execute([$movesStr, $this->gameId]);
    }
    
    /**
     * Check if player has any valid moves
     */
    public function hasValidMoves($playerId) {
        $movesRemaining = $this->getMovesRemaining();
        if (empty($movesRemaining)) {
            return false;
        }
        
        $game = $this->getGame();
        $isPlayer1 = ($playerId == $game['player1_id']);
        $opponentId = $isPlayer1 ? $game['player2_id'] : $game['player1_id'];
        
        // Get all points where player has checkers
        $stmt = $this->pdo->prepare("SELECT point_number, checker_count, is_pinned FROM board_state WHERE game_id = ? AND player_id = ? AND point_number > 0 AND checker_count > 0");
        $stmt->execute([$this->gameId, $playerId]);
        $positions = $stmt->fetchAll();
        
        foreach ($positions as $pos) {
            if ($pos['is_pinned']) continue; // Skip pinned checkers
            
            foreach ($movesRemaining as $die) {
                $fromPoint = $pos['point_number'];
                
                if ($isPlayer1) {
                    $toPoint = $fromPoint + $die;
                } else {
                    $toPoint = $fromPoint - $die;
                }
                
                // Check bearing off
                if (($isPlayer1 && $toPoint > 24) || (!$isPlayer1 && $toPoint < 1)) {
                    if ($this->allCheckersInHome($playerId)) {
                        // Check if exact or can use higher die
                        if (($isPlayer1 && $toPoint == 25) || (!$isPlayer1 && $toPoint == 0)) {
                            return true; // Exact bear off
                        }
                        if ($this->isFurthestChecker($playerId, $fromPoint)) {
                            return true; // Can use higher die
                        }
                    }
                    continue;
                }
                
                // Check if destination is valid
                if ($toPoint >= 1 && $toPoint <= 24) {
                    $opponentCheckers = $this->getCheckersAt($opponentId, $toPoint);
                    if ($opponentCheckers < 2) {
                        // Check if our own checker is pinned at this point - we cannot add more there
                        $myCheckers = $this->getCheckersAt($playerId, $toPoint);
                        if ($myCheckers > 0 && $this->isPointPinned($playerId, $toPoint)) {
                            continue; // Cannot add more checkers where our checker is pinned
                        }
                        return true; // Valid move found
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get all valid moves for current player
     */
    public function getValidMoves($playerId) {
        $movesRemaining = $this->getMovesRemaining();
        if (empty($movesRemaining)) {
            return [];
        }
        
        $game = $this->getGame();
        $isPlayer1 = ($playerId == $game['player1_id']);
        $opponentId = $isPlayer1 ? $game['player2_id'] : $game['player1_id'];
        
        $validMoves = [];
        
        // Get all points where player has checkers
        $stmt = $this->pdo->prepare("SELECT point_number, checker_count, is_pinned FROM board_state WHERE game_id = ? AND player_id = ? AND point_number > 0 AND checker_count > 0");
        $stmt->execute([$this->gameId, $playerId]);
        $positions = $stmt->fetchAll();
        
        foreach ($positions as $pos) {
            if ($pos['is_pinned']) continue;
            
            foreach ($movesRemaining as $die) {
                $fromPoint = $pos['point_number'];
                
                if ($isPlayer1) {
                    $toPoint = $fromPoint + $die;
                } else {
                    $toPoint = $fromPoint - $die;
                }
                
                // Check bearing off
                if (($isPlayer1 && $toPoint > 24) || (!$isPlayer1 && $toPoint < 1)) {
                    if ($this->allCheckersInHome($playerId)) {
                        if (($isPlayer1 && $toPoint == 25) || (!$isPlayer1 && $toPoint == 0)) {
                            $validMoves[] = ['from' => $fromPoint, 'to' => 0, 'die' => $die];
                        } else if ($this->isFurthestChecker($playerId, $fromPoint)) {
                            $validMoves[] = ['from' => $fromPoint, 'to' => 0, 'die' => $die];
                        }
                    }
                    continue;
                }
                
                if ($toPoint >= 1 && $toPoint <= 24) {
                    $opponentCheckers = $this->getCheckersAt($opponentId, $toPoint);
                    if ($opponentCheckers < 2) {
                        // Check if our own checker is pinned at this point - we cannot add more there
                        $myCheckers = $this->getCheckersAt($playerId, $toPoint);
                        if ($myCheckers > 0 && $this->isPointPinned($playerId, $toPoint)) {
                            continue; // Cannot add more checkers where our checker is pinned
                        }
                        $validMoves[] = ['from' => $fromPoint, 'to' => $toPoint, 'die' => $die];
                    }
                }
            }
        }
        
        return $validMoves;
    }
    
    /**
     * End current turn
     */
    public function endTurn() {
        $game = $this->getGame();
        $nextPlayer = ($game['current_turn'] == $game['player1_id']) ? $game['player2_id'] : $game['player1_id'];
        
        $stmt = $this->pdo->prepare("UPDATE games SET current_turn = ?, dice1 = 0, dice2 = 0, dice_rolled = FALSE, moves_remaining = '' WHERE id = ?");
        $stmt->execute([$nextPlayer, $this->gameId]);
        
        $this->logMessage("Η σειρά τελείωσε. Σειρά επόμενου παίκτη.");
    }
    
    /**
     * Force end turn (for when no moves available)
     */
    public function passTurn($playerId) {
        $game = $this->getGame();
        
        if ($game['current_turn'] != $playerId) {
            throw new Exception("Not your turn");
        }
        
        if (!$game['dice_rolled']) {
            throw new Exception("Must roll dice first");
        }
        
        if ($this->hasValidMoves($playerId)) {
            throw new Exception("You have valid moves available");
        }
        
        $this->logMessage("Δεν υπάρχουν έγκυρες κινήσεις. Πέρασε η σειρά.");
        $this->endTurn();
        
        return true;
    }
    
    /**
     * Record move in history
     */
    private function recordMove($playerId, $from, $to, $die) {
        $stmt = $this->pdo->prepare("SELECT COALESCE(MAX(move_number), 0) + 1 as next FROM move_history WHERE game_id = ?");
        $stmt->execute([$this->gameId]);
        $next = $stmt->fetch()['next'];
        
        $stmt = $this->pdo->prepare("INSERT INTO move_history (game_id, player_id, from_point, to_point, dice_used, move_number) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$this->gameId, $playerId, $from, $to, $die, $next]);
    }
    
    /**
     * Log a game message
     */
    private function logMessage($message) {
        $stmt = $this->pdo->prepare("INSERT INTO game_log (game_id, message) VALUES (?, ?)");
        $stmt->execute([$this->gameId, $message]);
    }
    
    /**
     * Get game info
     */
    public function getGame() {
        $stmt = $this->pdo->prepare("SELECT * FROM games WHERE id = ?");
        $stmt->execute([$this->gameId]);
        return $stmt->fetch();
    }
    
    /**
     * Get full board state
     */
    public function getBoardState() {
        $stmt = $this->pdo->prepare("
            SELECT bs.*, p.username 
            FROM board_state bs 
            JOIN players p ON bs.player_id = p.id 
            WHERE bs.game_id = ?
            ORDER BY bs.point_number
        ");
        $stmt->execute([$this->gameId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get game log
     */
    public function getGameLog($limit = 20) {
        $stmt = $this->pdo->prepare("SELECT * FROM game_log WHERE game_id = ? ORDER BY id DESC LIMIT ?");
        $stmt->execute([$this->gameId, $limit]);
        return array_reverse($stmt->fetchAll());
    }
    
    /**
     * Get full game state for API
     */
    public function getFullGameState($playerId) {
        $game = $this->getGame();
        
        if (!$game) {
            throw new Exception("Game not found");
        }
        
        $board = $this->getBoardState();
        $log = $this->getGameLog();
        
        // Format board for display
        $formattedBoard = [];
        for ($i = 0; $i <= 24; $i++) {
            $formattedBoard[$i] = ['player1' => 0, 'player2' => 0, 'pinned1' => false, 'pinned2' => false];
        }
        
        foreach ($board as $pos) {
            $playerKey = ($pos['player_id'] == $game['player1_id']) ? 'player1' : 'player2';
            $pinnedKey = ($pos['player_id'] == $game['player1_id']) ? 'pinned1' : 'pinned2';
            $formattedBoard[$pos['point_number']][$playerKey] = $pos['checker_count'];
            $formattedBoard[$pos['point_number']][$pinnedKey] = (bool)$pos['is_pinned'];
        }
        
        // Get valid moves if it's this player's turn
        $validMoves = [];
        if ($game['current_turn'] == $playerId && $game['dice_rolled']) {
            $validMoves = $this->getValidMoves($playerId);
        }
        
        return [
            'game' => [
                'id' => $game['id'],
                'status' => $game['status'],
                'player1_id' => $game['player1_id'],
                'player2_id' => $game['player2_id'],
                'current_turn' => $game['current_turn'],
                'dice1' => $game['dice1'],
                'dice2' => $game['dice2'],
                'dice_rolled' => (bool)$game['dice_rolled'],
                'moves_remaining' => $game['moves_remaining'] ? explode(',', $game['moves_remaining']) : [],
                'winner_id' => $game['winner_id'],
                'is_my_turn' => ($game['current_turn'] == $playerId)
            ],
            'board' => $formattedBoard,
            'valid_moves' => $validMoves,
            'log' => $log
        ];
    }
    
    /**
     * List available games to join
     */
    public static function listAvailableGames() {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT g.id, g.created_at, p.username as creator 
            FROM games g 
            JOIN players p ON g.player1_id = p.id 
            WHERE g.status = 'waiting'
            ORDER BY g.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * List player's active games
     */
    public static function listPlayerGames($playerId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT g.*, 
                   p1.username as player1_name,
                   p2.username as player2_name
            FROM games g 
            JOIN players p1 ON g.player1_id = p1.id 
            LEFT JOIN players p2 ON g.player2_id = p2.id 
            WHERE (g.player1_id = ? OR g.player2_id = ?) AND g.status != 'finished'
            ORDER BY g.updated_at DESC
        ");
        $stmt->execute([$playerId, $playerId]);
        return $stmt->fetchAll();
    }
}
?>
