/**
 * Plakoto Game - Frontend JavaScript
 * Uses jQuery and AJAX for API communication
 */

const API_BASE = 'api';

// Game state
let playerData = {
    id: null,
    username: null,
    token: null
};

let currentGameId = null;
let pollInterval = null;

// Initialize
$(document).ready(function() {
    // Check for saved session
    const savedToken = localStorage.getItem('plakoto_token');
    const savedUsername = localStorage.getItem('plakoto_username');
    const savedId = localStorage.getItem('plakoto_id');
    
    if (savedToken && savedUsername && savedId) {
        playerData = {
            id: parseInt(savedId),
            username: savedUsername,
            token: savedToken
        };
        showLobby();
    }
    
    // Event handlers
    $('#login-btn').click(handleLogin);
    $('#username').keypress(function(e) {
        if (e.which === 13) handleLogin();
    });
    
    $('#create-game-btn').click(createGame);
    $('#refresh-games-btn').click(refreshGames);
    
    $('#roll-dice-btn').click(rollDice);
    $('#make-move-btn').click(makeMove);
    $('#pass-turn-btn').click(passTurn);
    
    $('#back-to-lobby-btn').click(backToLobby);
    $('#refresh-game-btn').click(refreshGameState);
});

/**
 * API Helper
 */
function apiCall(endpoint, method, data) {
    const options = {
        url: `${API_BASE}/${endpoint}`,
        method: method,
        contentType: 'application/json',
        headers: {}
    };
    
    if (playerData.token) {
        options.headers['Authorization'] = `Bearer ${playerData.token}`;
    }
    
    if (data) {
        if (playerData.token) {
            data.token = playerData.token;
        }
        options.data = JSON.stringify(data);
    }
    
    return $.ajax(options);
}

/**
 * Show status message
 */
function showStatus(elementId, message, type) {
    const $el = $(`#${elementId}`);
    $el.html(`<div class="status ${type}">${message}</div>`);
    
    if (type !== 'error') {
        setTimeout(() => $el.empty(), 3000);
    }
}

/**
 * Handle login
 */
function handleLogin() {
    const username = $('#username').val().trim();
    
    if (!username) {
        showStatus('login-status', 'Παρακαλώ εισάγετε όνομα χρήστη', 'error');
        return;
    }
    
    apiCall('auth.php', 'POST', { username: username })
        .done(function(response) {
            if (response.success) {
                playerData = {
                    id: response.data.player_id,
                    username: response.data.username,
                    token: response.data.token
                };
                
                // Save to localStorage
                localStorage.setItem('plakoto_token', playerData.token);
                localStorage.setItem('plakoto_username', playerData.username);
                localStorage.setItem('plakoto_id', playerData.id);
                
                showLobby();
            } else {
                showStatus('login-status', response.message, 'error');
            }
        })
        .fail(function(xhr) {
            const response = xhr.responseJSON || { message: 'Connection failed' };
            showStatus('login-status', response.message, 'error');
        });
}

/**
 * Show lobby
 */
function showLobby() {
    $('#login-panel').addClass('hidden');
    $('#game-panel').addClass('hidden');
    $('#lobby-panel').removeClass('hidden');
    $('#player-name').text(playerData.username);
    
    refreshGames();
    refreshMyGames();
    
    // Stop game polling if active
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
}

/**
 * Refresh available games
 */
function refreshGames() {
    apiCall('game.php?action=list', 'GET')
        .done(function(response) {
            if (response.success) {
                displayAvailableGames(response.data);
            }
        });
}

/**
 * Display available games
 */
function displayAvailableGames(games) {
    const $container = $('#available-games');
    $container.empty();
    
    if (games.length === 0) {
        $container.html('<p>Δεν υπάρχουν διαθέσιμα παιχνίδια. Δημιούργησε ένα!</p>');
        return;
    }
    
    games.forEach(function(game) {
        const $item = $(`
            <div class="game-item">
                <span>Παιχνίδι #${game.id} από ${game.creator}</span>
                <button class="join-btn" data-id="${game.id}">Συμμετοχή</button>
            </div>
        `);
        $container.append($item);
    });
    
    // Add join handlers
    $('.join-btn').click(function() {
        const gameId = $(this).data('id');
        joinGame(gameId);
    });
}

/**
 * Refresh player's games
 */
function refreshMyGames() {
    apiCall('game.php?action=my-games', 'GET')
        .done(function(response) {
            if (response.success) {
                displayMyGames(response.data);
            }
        });
}

/**
 * Display player's games
 */
function displayMyGames(games) {
    const $container = $('#my-games');
    $container.empty();
    
    if (games.length === 0) {
        $container.html('<p>Δεν υπάρχουν ενεργά παιχνίδια.</p>');
        return;
    }
    
    games.forEach(function(game) {
        const status = game.status === 'waiting' ? 'Αναμονή αντιπάλου' : 'Σε εξέλιξη';
        const opponent = game.player1_id == playerData.id ? game.player2_name : game.player1_name;
        
        const $item = $(`
            <div class="game-item">
                <span>Παιχνίδι #${game.id} - ${status}${opponent ? ' εναντίον ' + opponent : ''}</span>
                <button class="resume-btn" data-id="${game.id}">Συνέχεια</button>
            </div>
        `);
        $container.append($item);
    });
    
    // Add resume handlers
    $('.resume-btn').click(function() {
        const gameId = $(this).data('id');
        enterGame(gameId);
    });
}

/**
 * Create new game
 */
function createGame() {
    apiCall('game.php?action=create', 'POST', {})
        .done(function(response) {
            if (response.success) {
                enterGame(response.data.game_id);
            } else {
                alert('Αποτυχία δημιουργίας παιχνιδιού: ' + response.message);
            }
        })
        .fail(function() {
            alert('Αποτυχία δημιουργίας παιχνιδιού');
        });
}

/**
 * Join a game
 */
function joinGame(gameId) {
    apiCall('game.php?action=join', 'POST', { game_id: gameId })
        .done(function(response) {
            if (response.success) {
                enterGame(gameId);
            } else {
                alert('Αποτυχία συμμετοχής στο παιχνίδι: ' + response.message);
            }
        })
        .fail(function(xhr) {
            const response = xhr.responseJSON || { message: 'Αποτυχία συμμετοχής στο παιχνίδι' };
            alert(response.message);
        });
}

/**
 * Enter game view
 */
function enterGame(gameId) {
    currentGameId = gameId;
    
    $('#lobby-panel').addClass('hidden');
    $('#game-panel').removeClass('hidden');
    $('#game-id-display').text(gameId);
    
    refreshGameState();
    
    // Start polling for updates
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(refreshGameState, 3000);
}

/**
 * Back to lobby
 */
function backToLobby() {
    currentGameId = null;
    showLobby();
}

/**
 * Refresh game state
 */
function refreshGameState() {
    if (!currentGameId) return;
    
    apiCall(`game.php?action=state&game_id=${currentGameId}`, 'GET')
        .done(function(response) {
            if (response.success) {
                updateGameDisplay(response.data);
            }
        });
}

/**
 * Update game display
 */
function updateGameDisplay(data) {
    const game = data.game;
    const board = data.board;
    const log = data.log;
    const validMoves = data.valid_moves;
    
    // Update status
    $('#game-status').text(game.status);
    
    // Determine player role
    const isPlayer1 = (game.player1_id == playerData.id);
    $('#player-role').text(isPlayer1 ? 'Παίκτης 1 (Κόκκινος ●)' : 'Παίκτης 2 (Πράσινος ○)');
    
    // Update turn info
    if (game.status === 'waiting') {
        $('#current-turn').text('Αναμονή για αντίπαλο...');
        $('#turn-controls').addClass('hidden');
        $('#move-controls').addClass('hidden');
    } else if (game.status === 'finished') {
        const won = game.winner_id == playerData.id;
        $('#current-turn').text(won ? '🎉 ΚΕΡΔΙΣΕΣ! 🎉' : 'Έχασες.');
        $('#turn-controls').addClass('hidden');
        $('#move-controls').addClass('hidden');
        
        // Stop polling on game end
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    } else {
        const isMyTurn = game.is_my_turn;
        $('#current-turn').text(isMyTurn ? "Η ΣΕΙΡΑ ΣΟΥ" : "Σειρά του αντιπάλου");
        
        if (isMyTurn) {
            $('#turn-controls').removeClass('hidden');
            
            if (!game.dice_rolled) {
                $('#roll-dice-btn').prop('disabled', false).show();
                $('#move-controls').addClass('hidden');
            } else {
                $('#roll-dice-btn').prop('disabled', true).hide();
                $('#move-controls').removeClass('hidden');
                
                // Update pass button
                $('#pass-turn-btn').prop('disabled', validMoves.length > 0);
            }
        } else {
            $('#turn-controls').addClass('hidden');
            $('#move-controls').addClass('hidden');
        }
    }
    
    // Update dice display
    if (game.dice_rolled && game.dice1 > 0) {
        $('#dice-display').html(`
            <span class="dice">${game.dice1}</span>
            <span class="dice">${game.dice2}</span>
        `);
        $('#moves-remaining').text(game.moves_remaining.join(', ') || 'Καμία');
    } else {
        $('#dice-display').text('Ρίξε τα ζάρια!');
        $('#moves-remaining').text('-');
    }
    
    // Update board
    drawBoard(board, game.player1_id, game.player2_id);
    
    // Update valid moves
    displayValidMoves(validMoves);
    
    // Update log
    displayLog(log);
}

/**
 * Draw the board in text format
 * Board is drawn from the perspective of the current player (they see their home at bottom-right)
 */
function drawBoard(board, player1Id, player2Id) {
    let output = '';
    
    // Determine if current player is Player 1 or Player 2
    const isPlayer1 = (player1Id == playerData.id);
    
    if (isPlayer1) {
        // Player 1 perspective: Points 13-24 on top, 12-1 on bottom, P1 home (1-6) at bottom-right
        output += '╔══════════════════════════╦══════════════════════════╦══════════╗\n';
        output += '║  13  14  15  16  17  18  ║  19  20  21  22  23  24  ║ ΑΝΤΙΠΑΛΟΣ║\n';
        output += '╠══════════════════════════╬══════════════════════════╬══════════╣\n';
        
        // Top half (points 13-24)
        for (let row = 0; row < 5; row++) {
            output += '║  ';
            for (let point = 13; point <= 18; point++) {
                output += formatPoint(board[point], row, true, true, point) + ' ';
            }
            output += '║  ';
            for (let point = 19; point <= 24; point++) {
                output += formatPoint(board[point], row, true, true, point) + ' ';
            }
            output += '║          ║\n';
        }
        
        // Middle bar
        output += '╠══════════════════════════╩══════════════════════════╬══════════╣\n';
        output += '║                                                     ║          ║\n';
        output += '╠══════════════════════════╦══════════════════════════╬══════════╣\n';
        
        // Bottom half (points 12-1)
        for (let row = 0; row < 5; row++) {
            output += '║  ';
            for (let point = 12; point >= 7; point--) {
                output += formatPoint(board[point], 4 - row, false, true, point) + ' ';
            }
            output += '║  ';
            for (let point = 6; point >= 1; point--) {
                output += formatPoint(board[point], 4 - row, false, true, point) + ' ';
            }
            output += '║          ║\n';
        }
        
        output += '╠══════════════════════════╬══════════════════════════╬══════════╣\n';
        output += '║  12  11  10   9   8   7  ║   6   5   4   3   2   1  ║    ΕΣΥ   ║\n';
        output += '╚══════════════════════════╩══════════════════════════╩══════════╝\n';
    } else {
        // Player 2 perspective: Points 12-1 on top, 13-24 on bottom, P2 home (19-24) at bottom-right
        output += '╔══════════════════════════╦══════════════════════════╦══════════╗\n';
        output += '║  12  11  10   9   8   7  ║   6   5   4   3   2   1  ║ ΑΝΤΙΠΑΛΟΣ║\n';
        output += '╠══════════════════════════╬══════════════════════════╬══════════╣\n';
        
        // Top half (points 12-1) - shown from Player 2's perspective
        for (let row = 0; row < 5; row++) {
            output += '║  ';
            for (let point = 12; point >= 7; point--) {
                output += formatPoint(board[point], row, true, false, point) + ' ';
            }
            output += '║  ';
            for (let point = 6; point >= 1; point--) {
                output += formatPoint(board[point], row, true, false, point) + ' ';
            }
            output += '║          ║\n';
        }
        
        // Middle bar
        output += '╠══════════════════════════╩══════════════════════════╬══════════╣\n';
        output += '║                                                     ║          ║\n';
        output += '╠══════════════════════════╦══════════════════════════╬══════════╣\n';
        
        // Bottom half (points 13-24) - shown from Player 2's perspective
        for (let row = 0; row < 5; row++) {
            output += '║  ';
            for (let point = 13; point <= 18; point++) {
                output += formatPoint(board[point], 4 - row, false, false, point) + ' ';
            }
            output += '║  ';
            for (let point = 19; point <= 24; point++) {
                output += formatPoint(board[point], 4 - row, false, false, point) + ' ';
            }
            output += '║          ║\n';
        }
        
        output += '╠══════════════════════════╬══════════════════════════╬══════════╣\n';
        output += '║  13  14  15  16  17  18  ║  19  20  21  22  23  24  ║    ΕΣΥ   ║\n';
        output += '╚══════════════════════════╩══════════════════════════╩══════════╝\n';
    }
    
    // Borne off display - show current player first
    output += '\n';
    if (isPlayer1) {
        output += `Εσύ (●) μαζεμένα: ${board[0]?.player1 || 0}/15\n`;
        output += `Αντίπαλος (○) μαζεμένα: ${board[0]?.player2 || 0}/15\n`;
    } else {
        output += `Εσύ (○) μαζεμένα: ${board[0]?.player2 || 0}/15\n`;
        output += `Αντίπαλος (●) μαζεμένα: ${board[0]?.player1 || 0}/15\n`;
    }
    
    $('#board-display').html(colorizeBoard(output, board));
}

/**
 * Format a single point for display
 * When a piece is pinned, the pinned piece stays at the "base" of the board section,
 * and the pinning piece appears "on top" (further into the board).
 * 
 * Rules based on point number:
 * - Red (Player 1) pinned at points 1-12: Red at bottom, enemy on top
 * - Red (Player 1) pinned at points 13-24: Red at top, enemy on bottom
 * - Green (Player 2) pinned at points 13-24: Green at bottom, enemy on top  
 * - Green (Player 2) pinned at points 1-12: Green at top, enemy on bottom
 * 
 * @param {object} point - The point data with player1, player2, pinned1, pinned2
 * @param {number} row - The current row being rendered (0-4)
 * @param {boolean} isTop - Whether this is the top half of the board visually
 * @param {boolean} isPlayer1View - Whether the viewer is Player 1 (red)
 * @param {number} pointNumber - The actual point number (1-24)
 */
function formatPoint(point, row, isTop, isPlayer1View, pointNumber) {
    const p1 = point?.player1 || 0;
    const p2 = point?.player2 || 0;
    const pinned1 = point?.pinned1 || false;
    const pinned2 = point?.pinned2 || false;
    
    // Determine what to show at this row
    let display = '   ';
    
    // Determine display order based on pinning
    // The pinned piece stays at the "base", pinner goes "on top" (toward center of board)
    let firstPlayer, secondPlayer, firstCount, secondCount, firstPinned, secondPinned;
    
    if (pinned1 && p2 > 0) {
        // Player1 (red) is pinned by Player2 (green)
        // Point 1-12: Red's base is at bottom, so red first (bottom), green second (top)
        // Point 13-24: Red's base is at top, so red first (top), green second (bottom)
        if (pointNumber <= 12) {
            // Red pinned in bottom area - red at base (first), green on top (second)
            firstPlayer = '●'; secondPlayer = '○';
            firstCount = p1; secondCount = p2;
            firstPinned = true; secondPinned = false;
        } else {
            // Red pinned in top area - red at base (first), green below (second)
            firstPlayer = '●'; secondPlayer = '○';
            firstCount = p1; secondCount = p2;
            firstPinned = true; secondPinned = false;
        }
    } else if (pinned2 && p1 > 0) {
        // Player2 (green) is pinned by Player1 (red)
        // Point 13-24: Green's base is at bottom, so green first (bottom), red second (top)
        // Point 1-12: Green's base is at top, so green first (top), red second (bottom)
        if (pointNumber >= 13) {
            // Green pinned in their bottom area (13-24) - green at base (first), red on top (second)
            firstPlayer = '○'; secondPlayer = '●';
            firstCount = p2; secondCount = p1;
            firstPinned = true; secondPinned = false;
        } else {
            // Green pinned in their top area (1-12) - green at base (first), red below (second)
            firstPlayer = '○'; secondPlayer = '●';
            firstCount = p2; secondCount = p1;
            firstPinned = true; secondPinned = false;
        }
    } else {
        // No pinning situation - default order (player1 first, then player2)
        firstPlayer = '●'; secondPlayer = '○';
        firstCount = p1; secondCount = p2;
        firstPinned = pinned1; secondPinned = pinned2;
    }
    
    // Now render based on visual position (isTop)
    // isTop=true: pieces grow downward from top edge (row 0 is at top)
    // isTop=false: pieces grow upward from bottom edge (row 0 is at bottom)
    // "first" piece should be at the base of the visual area
    
    if (row < firstCount) {
        display = firstPinned ? `[${firstPlayer}]` : ` ${firstPlayer} `;
    } else if (row < firstCount + secondCount) {
        display = secondPinned ? `[${secondPlayer}]` : ` ${secondPlayer} `;
    }
    
    // Show count if more than 5
    if (row === 4) {
        const total = p1 + p2;
        if (total > 5) {
            // When showing count, we need to show the dominant player's count
            // In a pinning situation, show the pinner (secondPlayer) count since they have more
            if (secondCount > 0 && firstCount <= 1) {
                // Pinning situation - show the pinner's count (secondPlayer)
                display = secondCount > 9 ? `${secondCount}${secondPlayer}` : ` ${secondCount}${secondPlayer}`;
            } else if (firstCount > secondCount) {
                display = firstCount > 9 ? `${firstCount}${firstPlayer}` : ` ${firstCount}${firstPlayer}`;
            } else {
                display = secondCount > 9 ? `${secondCount}${secondPlayer}` : ` ${secondCount}${secondPlayer}`;
            }
        }
    }
    
    return display;
}

/**
 * Colorize board output
 */
function colorizeBoard(output, board) {
    // Replace checker symbols with colored spans
    output = output.replace(/●/g, '<span class="player1-checker">●</span>');
    output = output.replace(/○/g, '<span class="player2-checker">○</span>');
    output = output.replace(/\[<span class="player1-checker">●<\/span>\]/g, '<span class="player1-checker pinned">[●]</span>');
    output = output.replace(/\[<span class="player2-checker">○<\/span>\]/g, '<span class="player2-checker pinned">[○]</span>');
    
    return output;
}

/**
 * Display valid moves
 */
function displayValidMoves(moves) {
    const $container = $('#valid-moves-display');
    $container.empty();
    
    if (moves.length === 0) {
        $container.html('<span style="color: #888;">Δεν υπάρχουν έγκυρες κινήσεις</span>');
        return;
    }
    
    moves.forEach(function(move) {
        const toText = move.to === 0 ? 'Μάζεψε' : move.to;
        const $move = $(`<span class="valid-move" data-from="${move.from}" data-to="${move.to}">
            ${move.from} → ${toText} (${move.die})
        </span>`);
        $container.append($move);
    });
    
    // Click handler for valid moves
    $('.valid-move').click(function() {
        const from = $(this).data('from');
        const to = $(this).data('to');
        $('#move-from').val(from);
        $('#move-to').val(to);
    });
}

/**
 * Display game log
 */
function displayLog(log) {
    const $container = $('#game-log');
    $container.empty();
    
    log.forEach(function(entry) {
        const time = new Date(entry.created_at).toLocaleTimeString();
        $container.append(`<div class="log-entry">[${time}] ${entry.message}</div>`);
    });
    
    // Scroll to bottom
    $container.scrollTop($container[0].scrollHeight);
}

/**
 * Roll dice
 */
function rollDice() {
    apiCall('game.php?action=roll', 'POST', { game_id: currentGameId })
        .done(function(response) {
            if (response.success) {
                refreshGameState();
            } else {
                alert('Αποτυχία ρίψης ζαριών: ' + response.message);
            }
        })
        .fail(function(xhr) {
            const response = xhr.responseJSON || { message: 'Αποτυχία ρίψης ζαριών' };
            alert(response.message);
        });
}

/**
 * Make a move
 */
function makeMove() {
    const from = parseInt($('#move-from').val());
    const to = parseInt($('#move-to').val());
    
    if (isNaN(from) || isNaN(to)) {
        alert('Παρακαλώ εισάγετε έγκυρους αριθμούς θέσεων');
        return;
    }
    
    apiCall('game.php?action=move', 'POST', {
        game_id: currentGameId,
        from: from,
        to: to
    })
        .done(function(response) {
            if (response.success) {
                $('#move-from').val('');
                $('#move-to').val('');
                updateGameDisplay(response.data.state);
            } else {
                alert('Μη έγκυρη κίνηση: ' + response.message);
            }
        })
        .fail(function(xhr) {
            const response = xhr.responseJSON || { message: 'Αποτυχία εκτέλεσης κίνησης' };
            alert(response.message);
        });
}

/**
 * Pass turn
 */
function passTurn() {
    apiCall('game.php?action=pass', 'POST', { game_id: currentGameId })
        .done(function(response) {
            if (response.success) {
                refreshGameState();
            } else {
                alert('Δεν μπορείς να πάσεις: ' + response.message);
            }
        })
        .fail(function(xhr) {
            const response = xhr.responseJSON || { message: 'Αποτυχία πάσου' };
            alert(response.message);
        });
}
