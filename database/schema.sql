-- Plakoto Game Database Schema
-- MySQL Database for Tavli-Plakoto game

-- Create database (run this if you have permission)
-- CREATE DATABASE IF NOT EXISTS plakoto_db;
-- USE plakoto_db;

-- Players table
CREATE TABLE IF NOT EXISTS players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    session_token VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Games table
CREATE TABLE IF NOT EXISTS games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player1_id INT NOT NULL,
    player2_id INT NULL,
    current_turn INT NULL,
    dice1 INT DEFAULT 0,
    dice2 INT DEFAULT 0,
    dice_rolled BOOLEAN DEFAULT FALSE,
    moves_remaining VARCHAR(50) DEFAULT '',
    status ENUM('waiting', 'playing', 'finished') DEFAULT 'waiting',
    winner_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (player1_id) REFERENCES players(id),
    FOREIGN KEY (player2_id) REFERENCES players(id),
    FOREIGN KEY (current_turn) REFERENCES players(id),
    FOREIGN KEY (winner_id) REFERENCES players(id)
);

-- Board state table - stores position of all checkers
-- In Plakoto: 24 points, each player has 15 checkers
-- Positions: 1-24 are board points, 0 is bear-off area
CREATE TABLE IF NOT EXISTS board_state (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    point_number INT NOT NULL,
    player_id INT NOT NULL,
    checker_count INT DEFAULT 0,
    is_pinned BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id),
    UNIQUE KEY unique_position (game_id, point_number, player_id)
);

-- Move history table
CREATE TABLE IF NOT EXISTS move_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    player_id INT NOT NULL,
    from_point INT NOT NULL,
    to_point INT NOT NULL,
    dice_used INT NOT NULL,
    move_number INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id)
);

-- Game messages/log table
CREATE TABLE IF NOT EXISTS game_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    message VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
);
