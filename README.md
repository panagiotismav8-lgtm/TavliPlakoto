# Παιχνίδι Τάβλι - Πλακωτό

## Περιγραφή Έργου

Το Πλακωτό είναι μια διαδικτυακή, multiplayer υλοποίηση της κλασικής ελληνικής παραλλαγής του backgammon που είναι γνωστή ως «Πλακωτό» (Plakoto), ένα από τα τρία παιχνίδια που παίζονται στο Τάβλι. Η εφαρμογή επιτρέπει σε δύο παίκτες να ανταγωνίζονται σε πραγματικό χρόνο μέσω των web browsers τους.

Το παιχνίδι είναι φτιαγμένο με αρχιτεκτονική client-server: ένα PHP backend που εκθέτει ένα RESTful API και ένα JavaScript frontend που επικοινωνεί με τον server μέσω AJAX αιτημάτων. Όλη η κατάσταση (state) του παιχνιδιού αποθηκεύεται σε βάση MySQL, ώστε οι παίκτες να μπορούν να συνεχίζουν παιχνίδια και να παίζουν και ασύγχρονα.

Βασικά χαρακτηριστικά: αυτόματο ρίξιμο ζαριών, έλεγχος/επικύρωση κινήσεων σύμφωνα με τους επίσημους κανόνες Πλακωτού, μηχανισμός «πλακώματος» (pinning) που είναι μοναδικός σε αυτή την παραλλαγή, και οπτική αναπαράσταση ταμπλό σε μορφή κειμένου που ενημερώνεται σε πραγματικό χρόνο.

---

## Τεκμηρίωση API

Το Plakoto API είναι μια RESTful web υπηρεσία που χειρίζεται όλες τις λειτουργίες του παιχνιδιού. Όλα τα endpoints επιστρέφουν JSON responses και χρησιμοποιούν standard HTTP methods.

### Βασικό URL
```
https://users.iee.ihu.gr/~iee2021087/plakoto-game/index.html
```

### Μορφή Απάντησης (Response Format)

Όλες οι απαντήσεις του API ακολουθούν αυτή τη δομή:
```json
{
    "success": true | false,
    "message": "Description of the result",
    "data": { ... } | null
}
```

### HTTP Status Codes

| Κωδικός | Περιγραφή |
|--------|-----------|
| 200    | Επιτυχία |
| 400    | Bad Request - Μη έγκυρες παράμετροι |
| 401    | Unauthorized - Μη έγκυρο ή απών token |
| 405    | Method Not Allowed - Μη επιτρεπόμενη μέθοδος |

---

### Endpoint Αυθεντικοποίησης

#### POST /api/auth.php

Αυθεντικοποιεί έναν παίκτη ή δημιουργεί νέο λογαριασμό. Το endpoint χρησιμοποιεί ένα απλοποιημένο σύστημα αυθεντικοποίησης όπου απαιτείται μόνο username.

**Request Headers:**
```
Content-Type: application/json
```

**Request Body:**
```json
{
    "username": "string (required, 2-50 characters)"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "player_id": 1,
        "username": "player1",
        "token": "a1b2c3d4e5f6..."
    }
}
```

**Error Response (400):**
```json
{
    "success": false,
    "message": "Username is required",
    "data": null
}
```

**Σημειώσεις:**
- Αν το username υπάρχει, ο παίκτης κάνει login και λαμβάνει νέο session token
- Αν το username δεν υπάρχει, δημιουργείται νέος λογαριασμός παίκτη
- Το token που επιστρέφεται πρέπει να συμπεριλαμβάνεται σε όλα τα επόμενα API requests

---

### Endpoints Παιχνιδιού

Όλα τα endpoints παιχνιδιού απαιτούν αυθεντικοποίηση μέσω του Authorization header:
```
Authorization: Bearer <token>
```

Εναλλακτικά, το token μπορεί να περαστεί στο request body ή ως query parameter.

---

#### GET /api/game.php?action=list

Επιστρέφει λίστα με παιχνίδια που περιμένουν αντίπαλο για να συμμετάσχει.

**Request Headers:**
```
Authorization: Bearer <token>
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Available games",
    "data": [
        {
            "id": 1,
            "created_at": "2026-01-10 14:30:00",
            "creator": "player1"
        },
        {
            "id": 3,
            "created_at": "2026-01-10 15:45:00",
            "creator": "player2"
        }
    ]
}
```

---

#### GET /api/game.php?action=my-games

Επιστρέφει όλα τα ενεργά παιχνίδια για τον αυθεντικοποιημένο παίκτη (και αυτά που περιμένουν και αυτά που είναι σε εξέλιξη).

**Request Headers:**
```
Authorization: Bearer <token>
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Your games",
    "data": [
        {
            "id": 1,
            "player1_id": 1,
            "player2_id": 2,
            "status": "playing",
            "player1_name": "player1",
            "player2_name": "player2"
        },
        {
            "id": 5,
            "player1_id": 1,
            "player2_id": null,
            "status": "waiting",
            "player1_name": "player1",
            "player2_name": null
        }
    ]
}
```

---

#### GET /api/game.php?action=state&game_id={id}

Επιστρέφει την πλήρη κατάσταση ενός συγκεκριμένου παιχνιδιού, συμπεριλαμβανομένης της θέσης στο ταμπλό, των τιμών των ζαριών, των έγκυρων κινήσεων και του game log.

**Request Headers:**
```
Authorization: Bearer <token>
```

**Query Parameters:**
| Παράμετρος | Τύπος | Υποχρεωτικό | Περιγραφή |
|-----------|------|-------------|----------|
| game_id | integer | Ναι | Το ID του παιχνιδιού |

**Success Response (200):**
```json
{
    "success": true,
    "message": "Game state",
    "data": {
        "game": {
            "id": 1,
            "status": "playing",
            "player1_id": 1,
            "player2_id": 2,
            "current_turn": 1,
            "dice1": 4,
            "dice2": 6,
            "dice_rolled": true,
            "moves_remaining": ["4", "6"],
            "winner_id": null,
            "is_my_turn": true
        },
        "board": {
            "0": {"player1": 0, "player2": 0, "pinned1": false, "pinned2": false},
            "1": {"player1": 15, "player2": 0, "pinned1": false, "pinned2": false},
            "...": "..."
        },
        "valid_moves": [
            {"from": 1, "to": 5, "die": 4},
            {"from": 1, "to": 7, "die": 6}
        ],
        "log": [
            {"id": 1, "message": "Game created", "created_at": "2026-01-10 14:30:00"},
            {"id": 2, "message": "Player 2 joined", "created_at": "2026-01-10 14:31:00"}
        ]
    }
}
```

**Σημειώσεις για τις Θέσεις του Ταμπλό (Board Position Notes):**
- Η θέση 0 αντιστοιχεί στα πούλια που έχουν «βγει» (borne-off)
- Οι θέσεις 1-24 αντιστοιχούν στα σημεία του ταμπλό
- Τα `player1` και `player2` δείχνουν τον αριθμό των πουλιών
- Τα `pinned1` και `pinned2` δείχνουν αν υπάρχει «πλακωμένο» πούλι σε αυτή τη θέση

---

#### GET /api/game.php?action=valid-moves&game_id={id}

Επιστρέφει μόνο τις έγκυρες κινήσεις για τη σειρά του τρέχοντος παίκτη.

**Request Headers:**
```
Authorization: Bearer <token>
```

**Query Parameters:**
| Παράμετρος | Τύπος | Υποχρεωτικό | Περιγραφή |
|-----------|------|-------------|----------|
| game_id | integer | Ναι | Το ID του παιχνιδιού |

**Success Response (200):**
```json
{
    "success": true,
    "message": "Valid moves",
    "data": [
        {"from": 1, "to": 5, "die": 4},
        {"from": 1, "to": 7, "die": 6},
        {"from": 5, "to": 9, "die": 4}
    ]
}
```

---

#### POST /api/game.php?action=create

Δημιουργεί νέο παιχνίδι και ορίζει τον αυθεντικοποιημένο παίκτη ως Player 1. Το παιχνίδι θα είναι σε κατάσταση "waiting" μέχρι να μπει άλλος παίκτης.

**Request Headers:**
```
Content-Type: application/json
Authorization: Bearer <token>
```

**Request Body:**
```json
{
    "token": "optional if using Authorization header"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Game created",
    "data": {
        "game_id": 7
    }
}
```

---

#### POST /api/game.php?action=join

Συμμετοχή σε υπάρχον παιχνίδι ως Player 2. Αυτή η ενέργεια ενεργοποιεί το αρχικό ρίξιμο ζαριών για να καθοριστεί ποιος παίκτης ξεκινάει.

**Request Headers:**
```
Content-Type: application/json
Authorization: Bearer <token>
```

**Request Body:**
```json
{
    "game_id": 1
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Joined game",
    "data": {
        "game_id": 1
    }
}
```

**Error Response (400):**
```json
{
    "success": false,
    "message": "Game not found or already started",
    "data": null
}
```

---

#### POST /api/game.php?action=roll

Ρίχνει τα ζάρια για τη σειρά του τρέχοντος παίκτη. Μπορεί να κληθεί μόνο μία φορά ανά γύρο, και μόνο από τον παίκτη που έχει τη σειρά.

**Request Headers:**
```
Content-Type: application/json
Authorization: Bearer <token>
```

**Request Body:**
```json
{
    "game_id": 1
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Dice rolled",
    "data": {
        "dice1": 4,
        "dice2": 6,
        "doubles": false
    }
}
```

**Σημειώσεις:**
- Αν φέρεις διπλές (π.χ. 4-4), ο παίκτης έχει 4 κινήσεις αντί για 2
- Το πεδίο `doubles` δείχνει αν έφεραν διπλές

---

#### POST /api/game.php?action=move

Εκτελεί μια κίνηση στο ταμπλό. Η κίνηση πρέπει να είναι έγκυρη σύμφωνα με τους κανόνες Πλακωτού.

**Request Headers:**
```
Content-Type: application/json
Authorization: Bearer <token>
```

**Request Body:**
```json
{
    "game_id": 1,
    "from": 1,
    "to": 5
}
```

| Πεδίο | Τύπος | Υποχρεωτικό | Περιγραφή |
|------|------|-------------|----------|
| game_id | integer | Ναι | Το ID του παιχνιδιού |
| from | integer | Ναι | Σημείο εκκίνησης (1-24) |
| to | integer | Ναι | Σημείο προορισμού (1-24, ή 0 για «βγάλσιμο»/bearing off) |

**Success Response (200):**
```json
{
    "success": true,
    "message": "Move made",
    "data": {
        "move_result": {
            "moved": true,
            "gameOver": false
        },
        "state": {
            "game": { ... },
            "board": { ... },
            "valid_moves": [ ... ],
            "log": [ ... ]
        }
    }
}
```

**Response όταν τελειώσει το παιχνίδι (Game Over Response):**
```json
{
    "success": true,
    "message": "Move made",
    "data": {
        "move_result": {
            "moved": true,
            "gameOver": true,
            "winner": 1
        },
        "state": { ... }
    }
}
```

**Error Response (400):**
```json
{
    "success": false,
    "message": "Point 5 is blocked by opponent",
    "data": null
}
```

**Συνηθισμένα Error Messages:**
- "Not your turn"
- "Must roll dice first"
- "No checkers at point X"
- "Checker at point X is pinned"
- "Point X is blocked by opponent"
- "Invalid move direction"
- "No valid die for this move"
- "Cannot bear off - not all checkers in home board"

---

#### POST /api/game.php?action=pass

Περνάει τη σειρά όταν δεν υπάρχουν διαθέσιμες έγκυρες κινήσεις. Η σειρά περνάει αυτόματα αν δεν υπάρχουν έγκυρες κινήσεις μετά την εκτέλεση μιας κίνησης.

**Request Headers:**
```
Content-Type: application/json
Authorization: Bearer <token>
```

**Request Body:**
```json
{
    "game_id": 1
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Turn passed",
    "data": null
}
```

**Error Response (400):**
```json
{
    "success": false,
    "message": "You have valid moves available",
    "data": null
}
```

---

## Κανόνες Πλακωτού

1. **Στήσιμο**: Κάθε παίκτης ξεκινά με και τα 15 πούλια στο αρχικό του σημείο:
   - Player 1 (Κόκκινο): Και τα 15 στο σημείο 1
   - Player 2 (Κυανό): Και τα 15 στο σημείο 24

2. **Κίνηση**:
   - Ο Player 1 κινείται από το σημείο 1 προς το σημείο 24 (έδρα/home: 19-24)
   - Ο Player 2 κινείται από το σημείο 24 προς το σημείο 1 (έδρα/home: 1-6)

3. **Πλάκωμα (Pinning)**: Αν προσγειωθείς σε σημείο που έχει ακριβώς ΕΝΑ αντίπαλο πούλι, το «πλακώνεις». Το πλακωμένο πούλι δεν μπορεί να κινηθεί μέχρι να φύγει το πούλι που το πλακώνει.

4. **Μπλοκάρισμα (Blocking)**: Δεν μπορείς να προσγειωθείς σε σημείο που έχει 2 ή περισσότερα αντίπαλα πούλια.

5. **Βγάλσιμο (Bearing Off)**: Όταν όλα σου τα πούλια μπουν στην έδρα σου, μπορείς να τα «βγάλεις».

6. **Νίκη**: Κερδίζει ο πρώτος παίκτης που θα βγάλει και τα 15 πούλια.

7. **Διπλές**: Αν φέρεις διπλές (π.χ. 4-4) έχεις 4 κινήσεις αντί για 2.

## Δοκιμές με cURL/Postman

### Login
```bash
curl -X POST https://users.iee.ihu.gr/~iee2021087/plakoto-game/api/auth.php   -H "Content-Type: application/json"   -d '{"username": "player1"}'
```

### Δημιουργία Παιχνιδιού
```bash
curl -X POST "https://users.iee.ihu.gr/~iee2021087/plakoto-game/api/game.php?action=create"   -H "Content-Type: application/json"   -H "Authorization: Bearer YOUR_TOKEN"
```

### Ανάκτηση Κατάστασης Παιχνιδιού (Game State)
```bash
curl "https://users.iee.ihu.gr/~iee2021087/plakoto-game/api/game.php?action=state&game_id=1"   -H "Authorization: Bearer YOUR_TOKEN"
```

### Ρίξιμο Ζαριών
```bash
curl -X POST "https://users.iee.ihu.gr/~iee2021087/plakoto-game/api/game.php?action=roll"   -H "Content-Type: application/json"   -H "Authorization: Bearer YOUR_TOKEN"   -d '{"game_id": 1}'
```

### Εκτέλεση Κίνησης
```bash
curl -X POST "https://users.iee.ihu.gr/~iee2021087/plakoto-game/api/game.php?action=move"   -H "Content-Type: application/json"   -H "Authorization: Bearer YOUR_TOKEN"   -d '{"game_id": 1, "from": 1, "to": 5}'
```

## Δομή Project

```
plakoto-game/
├── index.html              # Κύριο περιβάλλον παιχνιδιού
├── README.md               # Αυτό το αρχείο
├── api/
│   ├── auth.php            # Endpoint αυθεντικοποίησης
│   └── game.php            # Endpoint λειτουργιών παιχνιδιού
├── config/
│   └── database.php        # Ρυθμίσεις MySQL βάσης δεδομένων
├── database/
│   └── schema.sql          # Schema MySQL βάσης δεδομένων
├── includes/
│   ├── response.php        # Helpers για JSON responses
│   ├── Player.php          # Κλάση διαχείρισης παίκτη
│   └── PlakotoGame.php     # Κλάση λογικής παιχνιδιού
└── js/
    └── plakoto.js          # Frontend JavaScript (jQuery/AJAX)
```

## Αρχιτεκτονική

Το project ακολουθεί αρχιτεκτονική WebAPI:
- **Backend**: PHP με PDO_MySQL για πρόσβαση σε MySQL βάση δεδομένων
- **Frontend**: HTML5/CSS3 με jQuery για AJAX επικοινωνία
- **Μορφή Δεδομένων**: JSON για όλα τα API requests και responses
- **Διαχείριση Κατάστασης**: Όλη η κατάσταση του παιχνιδιού αποθηκεύεται σε MySQL βάση δεδομένων
- **Online Παιχνίδι**: Multiplayer μέσω βάσης δεδομένων για online παιχνίδι

## Πίνακες MySQL Βάσης Δεδομένων

- **players**: Αποθηκεύει πληροφορίες παικτών και session tokens
- **games**: Παρακολουθεί την κατάσταση παιχνιδιού, ποιος έχει τη σειρά, τιμές ζαριών
- **board_state**: Αποθηκεύει τις θέσεις των πουλιών για κάθε παιχνίδι
- **move_history**: Καταγράφει όλες τις κινήσεις σε κάθε παιχνίδι
- **game_log**: Αποθηκεύει μηνύματα/γεγονότα του παιχνιδιού
