<?php
session_start();
include("config.php");

/* Valori iniziali */
$libri = 0;
$utenti = 0;
$scambi = 0;

/* Conteggio libri */
$q1 = $conn->query("SELECT COUNT(*) as totale FROM libro");
if($q1){
    $libri = $q1->fetch_assoc()['totale'];
}

/* Conteggio utenti */
$q2 = $conn->query("SELECT COUNT(*) as totale FROM utente");
if($q2){
    $utenti = $q2->fetch_assoc()['totale'];
}

/* Conteggio scambi completati */
$q3 = $conn->query("SELECT COUNT(*) as totale FROM annuncio WHERE stato = 'VENDUTO'");
if($q3){
    $scambi = $q3->fetch_assoc()['totale'];
}

// Recupera annunci recenti dal database
$annunci_recenti = [];
$query_annunci = $conn->query("
    SELECT a.id, l.titolo, l.autore, a.prezzo, a.statoConservazione 
    FROM annuncio a
    JOIN libro l ON a.libroId = l.id
    WHERE a.stato = 'ATTIVO'
    ORDER BY a.dataCreazione DESC
    LIMIT 6
");

if($query_annunci) {
    while($row = $query_annunci->fetch_assoc()) {
        $annunci_recenti[] = $row;
    }
}

if(isset($_POST['crea_annuncio'])) {

    $titolo = $_POST['titolo'];
    $autore = $_POST['autore'];
    $materia = $_POST['materia'];
    $prezzo = $_POST['prezzo'];
    $descrizione = $_POST['descrizione'];
    $stato = $_POST['stato'];

    /* 1️⃣ Inserisco prima il libro */
    $stmtLibro = $conn->prepare("INSERT INTO libro (titolo, autore, materia) VALUES (?, ?, ?)");
    $stmtLibro->bind_param("sss", $titolo, $autore, $materia);
    $stmtLibro->execute();
    $libroId = $stmtLibro->insert_id;

    /* 2️⃣ Inserisco annuncio */
    $utenteId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Usa l'ID dalla sessione o 1 come fallback

    $stmtAnnuncio = $conn->prepare("
        INSERT INTO annuncio 
        (utenteId, libroId, descrizione, prezzo, statoConservazione, stato)
        VALUES (?, ?, ?, ?, ?, 'ATTIVO')
    ");

    $stmtAnnuncio->bind_param("iisss", 
        $utenteId, 
        $libroId, 
        $descrizione, 
        $prezzo, 
        $stato
    );

    if($stmtAnnuncio->execute()) {
        $messaggio_successo = "Annuncio creato con successo!";
        // Reindirizza per evitare reinvio del form
        header("Location: index.php?success=1");
        exit();
    }
}

if(isset($_POST['login'])) {

    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM utente WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows === 1) {

        $user = $result->fetch_assoc();

        if($password === $user['password']) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nome'] = $user['nome'];
            $_SESSION['ruolo'] = $user['ruolo'];
            $_SESSION['email'] = $user['email'];

            header("Location: index.php");
            exit();

        } else {
            $erroreLogin = "Password errata";
        }

    } else {
        $erroreLogin = "Utente non trovato";
    }
}

// Gestione logout
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookSwap - Scambio Libri Online</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reset e variabili (mantieni tutto il CSS che avevi) */
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header e Navbar */
        header {
            background-color: white;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            cursor: pointer;
        }

        .logo i {
            color: var(--secondary-color);
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 25px;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--dark-color);
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 4px;
            transition: var(--transition);
            position: relative;
            cursor: pointer;
        }

        .nav-links a:hover {
            background-color: var(--light-color);
            color: var(--secondary-color);
        }

        .nav-links a.active {
            color: var(--secondary-color);
        }

        .nav-links a.active::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 12px;
            right: 12px;
            height: 3px;
            background-color: var(--secondary-color);
            border-radius: 2px;
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .notification, .cart {
            position: relative;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: var(--transition);
        }

        .notification:hover, .cart:hover {
            background-color: var(--light-color);
        }

        .badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background-color: var(--accent-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 20px;
            transition: var(--transition);
        }

        .user-profile:hover {
            background-color: var(--light-color);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-name {
            font-weight: 600;
        }

        .user-role {
            font-size: 0.8rem;
            color: #7f8c8d;
        }

        /* Login Overlay */
        .login-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            transition: opacity 0.5s ease;
        }

        .login-container {
            background-color: white;
            width: 90%;
            max-width: 500px;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h2 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .login-tabs {
            display: flex;
            margin-bottom: 25px;
            border-bottom: 1px solid #ddd;
        }

        .login-tab {
            flex: 1;
            text-align: center;
            padding: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }

        .login-tab.active {
            color: var(--secondary-color);
            border-bottom: 3px solid var(--secondary-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: var(--secondary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            width: 100%;
        }

        .btn:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        .btn-warning {
            background-color: var(--warning-color);
        }

        .btn-warning:hover {
            background-color: #e67e22;
        }

        .btn-danger {
            background-color: var(--accent-color);
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        /* Contenuto principale */
        .main-content {
            padding: 30px 0;
            min-height: calc(100vh - 120px);
        }

        .page {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .page.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 2rem;
            color: var(--primary-color);
        }

        /* Homepage */
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 40px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .welcome-banner h2 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 2.5rem;
            color: var(--secondary-color);
            margin-bottom: 15px;
        }

        .stat-card h3 {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .recent-listings {
            margin-top: 40px;
        }

        .listing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .listing-card {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .listing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .listing-img {
            height: 180px;
            width: 100%;
            background-color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #777;
            font-size: 3rem;
        }

        .listing-info {
            padding: 20px;
        }

        .listing-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--primary-color);
        }

        .listing-author {
            color: #7f8c8d;
            margin-bottom: 10px;
        }

        .listing-price {
            color: var(--success-color);
            font-weight: 700;
            font-size: 1.3rem;
        }

        /* Cerca */
        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }

        .search-bar input {
            flex: 1;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .filters {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .filter-group {
            margin-bottom: 15px;
        }

        .filter-title {
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
        }

        /* Profilo */
        .profile-header {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            overflow: hidden;
        }

        .profile-details h2 {
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .profile-details p {
            color: #7f8c8d;
            margin-bottom: 5px;
        }

        .profile-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }

        .profile-tab {
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 600;
            border-radius: 5px;
            transition: var(--transition);
        }

        .profile-tab.active {
            background-color: var(--secondary-color);
            color: white;
        }

        /* Messaggistica */
        .chat-container {
            display: flex;
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            overflow: hidden;
            height: 600px;
        }

        .chat-list {
            width: 300px;
            border-right: 1px solid #eee;
            overflow-y: auto;
        }

        .chat-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: var(--transition);
        }

        .chat-item:hover, .chat-item.active {
            background-color: #f9f9f9;
        }

        .chat-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .message {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
        }

        .message.sent {
            align-self: flex-end;
            background-color: var(--secondary-color);
            color: white;
        }

        .message.received {
            align-self: flex-start;
            background-color: #f1f1f1;
            color: #333;
        }

        .chat-input {
            padding: 20px;
            border-top: 1px solid #eee;
        }

        /* Carrello */
        .cart-items {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .cart-item {
            display: flex;
            padding: 20px;
            border-bottom: 1px solid #eee;
            align-items: center;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-img {
            width: 80px;
            height: 100px;
            background-color: #ddd;
            border-radius: 5px;
            margin-right: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #777;
        }

        .cart-item-details {
            flex: 1;
        }

        .cart-item-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .cart-item-price {
            color: var(--success-color);
            font-weight: 700;
        }

        .cart-summary {
            background-color: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-top: 30px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .summary-total {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 3000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background-color: white;
            width: 90%;
            max-width: 600px;
            border-radius: 10px;
            overflow: hidden;
            animation: modalAppear 0.3s ease;
        }

        @keyframes modalAppear {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 30px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Messaggi di errore/successo */
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .user-actions {
                margin-top: 10px;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .chat-container {
                flex-direction: column;
                height: 80vh;
            }

            .chat-list {
                width: 100%;
                max-height: 200px;
            }

            .search-bar {
                flex-direction: column;
            }

            .listing-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
    </style>
</head>
<body>
    <!-- Overlay di login -->
    <?php if(!isset($_SESSION['user_id'])): ?>
    <div id="loginOverlay" class="login-overlay" style="display:flex;">
    <?php else: ?>
    <div id="loginOverlay" class="login-overlay" style="display:none;">
    <?php endif; ?>
        <div class="login-container">
            <div class="login-header">
                <h2>BookSwap</h2>
                <p>Accedi al tuo account per continuare</p>
            </div>
            
            <?php if(isset($erroreLogin)): ?>
            <div class="alert alert-danger"><?php echo $erroreLogin; ?></div>
            <?php endif; ?>
            
            <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success">Annuncio creato con successo!</div>
            <?php endif; ?>
            
            <div class="login-tabs">
                <div class="login-tab active" data-tab="user">Utente</div>
                <div class="login-tab" data-tab="admin">Amministratore</div>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" name="login" class="btn">Accedi</button>
            </form>
            
            <div style="text-align: center; margin-top: 20px;">
                <p>Non hai un account? <a href="#" style="color: var(--secondary-color);">Registrati</a></p>
            </div>
        </div>
    </div>

    <!-- Header e Navbar -->
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="#" class="logo" id="logo-home">
                    <i class="fas fa-book"></i>
                    <span>BookSwap</span>
                </a>
                
                <ul class="nav-links">
                    <li><a class="nav-link active" data-page="home">Home</a></li>
                    <li><a class="nav-link" data-page="cerca">Cerca</a></li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                    <li><a class="nav-link" data-page="annunci">I miei annunci</a></li>
                    <li><a class="nav-link" data-page="messaggi">Messaggi</a></li>
                    <li><a class="nav-link" data-page="profilo">Profilo</a></li>
                    <?php endif; ?>
                </ul>
                
                <div class="user-actions">
                    <?php if(isset($_SESSION['user_id'])): ?>
                    <div class="notification" id="notification-icon">
                        <i class="fas fa-bell"></i>
                        <span class="badge">3</span>
                    </div>
                    
                    <div class="cart" id="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="badge" id="cartCount">0</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="user-profile" id="userProfile">
                        <div class="user-avatar">
                            <span id="avatarInitial">
                                <?php echo isset($_SESSION['nome']) ? strtoupper(substr($_SESSION['nome'], 0, 1)) : "U"; ?>
                            </span>
                        </div>
                        <div>
                            <div class="user-name" id="userName">
                                <?php echo isset($_SESSION['nome']) ? $_SESSION['nome'] : "Ospite"; ?>  
                            </div>
                            <div class="user-role" id="userRole">
                                <?php 
                                if(isset($_SESSION['ruolo'])) {
                                    echo $_SESSION['ruolo'] == 'admin' ? 'Amministratore' : 'Utente';
                                } else {
                                    echo "Non autenticato";
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="?logout=1" class="btn" style="width: auto; padding: 8px 16px;">Logout</a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>

    <!-- Contenuto principale -->
    <main class="main-content">
        <div class="container">
            <!-- Homepage -->
            <section id="home" class="page active">
                <div class="welcome-banner">
                    <h2>Benvenuto su BookSwap</h2>
                    <p>Scambia, compra e vendi libri con altri appassionati di lettura</p>
                </div>
                
                <div class="stats">
                    <div class="stat-card">
                        <i class="fas fa-book-open"></i>
                        <h3><?php echo $libri; ?></h3>
                        <p>Libri disponibili</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-users"></i>
                        <h3><?php echo $utenti; ?></h3>
                        <p>Utenti attivi</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-exchange-alt"></i>
                        <h3><?php echo $scambi; ?></h3>
                        <p>Scambi completati</p>
                    </div>
                </div>
                
                <div class="recent-listings">
                    <h2 class="page-title">Annunci recenti</h2>
                    <div class="listing-grid" id="recentListings">
                        <?php if(empty($annunci_recenti)): ?>
                            <p style="text-align: center; grid-column: 1/-1; padding: 40px;">Nessun annuncio disponibile</p>
                        <?php else: ?>
                            <?php foreach($annunci_recenti as $annuncio): ?>
                            <div class="listing-card">
                                <div class="listing-img">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div class="listing-info">
                                    <h3 class="listing-title"><?php echo htmlspecialchars($annuncio['titolo']); ?></h3>
                                    <p class="listing-author"><?php echo htmlspecialchars($annuncio['autore']); ?></p>
                                    <p>Condizione: <?php echo htmlspecialchars($annuncio['statoConservazione']); ?></p>
                                    <div class="listing-price">€<?php echo number_format($annuncio['prezzo'], 2); ?></div>
                                    <?php if(isset($_SESSION['user_id']) && $_SESSION['ruolo'] != 'admin'): ?>
                                    <button class="btn" onclick="addToCart(<?php echo $annuncio['id']; ?>)">Aggiungi al carrello</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            
            <!-- Cerca -->
            <section id="cerca" class="page">
                <div class="page-header">
                    <h2 class="page-title">Cerca libri</h2>
                </div>
                
                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="Cerca per titolo, autore o genere...">
                    <button class="btn" id="searchButton">Cerca</button>
                </div>
                
                <div class="filters">
                    <h3>Filtri di ricerca</h3>
                    <div class="filter-group">
                        <span class="filter-title">Condizione:</span>
                        <select id="conditionFilter" class="form-control">
                            <option value="">Qualsiasi condizione</option>
                            <option value="NUOVO">Nuovo</option>
                            <option value="OTTIMO">Ottimo</option>
                            <option value="BUONO">Buono</option>
                            <option value="DISCRETO">Discreto</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <span class="filter-title">Prezzo massimo:</span>
                        <input type="range" id="priceRange" min="0" max="50" value="50" class="form-control">
                        <span id="priceValue">€50</span>
                    </div>
                </div>
                
                <div class="listing-grid" id="searchResults">
                    <!-- I risultati verranno inseriti qui da JavaScript -->
                </div>
            </section>
            
            <?php if(isset($_SESSION['user_id'])): ?>
            <!-- Annunci -->
            <section id="annunci" class="page">
                <div class="page-header">
                    <h2 class="page-title">I miei annunci</h2>
                    <button class="btn" id="createListingBtn">Crea nuovo annuncio</button>
                </div>
                
                <div class="listing-grid" id="myListings">
                    <!-- Annunci dell'utente verranno caricati qui -->
                </div>
            </section>
            
            <!-- Messaggi -->
            <section id="messaggi" class="page">
                <div class="page-header">
                    <h2 class="page-title">Messaggi</h2>
                </div>
                
                <div class="chat-container">
                    <div class="chat-list" id="chatList">
                        <!-- Lista conversazioni verrà inserita qui -->
                    </div>
                    
                    <div class="chat-content">
                        <div class="chat-header">
                            <h3 id="currentChat">Seleziona una conversazione</h3>
                        </div>
                        
                        <div class="chat-messages" id="chatMessages">
                            <!-- Messaggi verranno inseriti qui -->
                        </div>
                        
                        <div class="chat-input">
                            <div class="search-bar">
                                <input type="text" id="messageInput" placeholder="Scrivi un messaggio..." disabled>
                                <button class="btn" id="sendMessageBtn" disabled>Invia</button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Profilo -->
            <section id="profilo" class="page">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <span id="profileAvatarInitial"><?php echo isset($_SESSION['nome']) ? strtoupper(substr($_SESSION['nome'], 0, 1)) : "U"; ?></span>
                    </div>
                    
                    <div class="profile-details">
                        <h2 id="profileName"><?php echo isset($_SESSION['nome']) ? $_SESSION['nome'] : "Nome Utente"; ?></h2>
                        <p id="profileEmail"><?php echo isset($_SESSION['email']) ? $_SESSION['email'] : "utente@example.com"; ?></p>
                        <p id="profileMemberSince">Membro dal: Gennaio 2025</p>
                    </div>
                </div>
                
                <div class="profile-tabs">
                    <div class="profile-tab active" data-tab="info">Informazioni</div>
                    <div class="profile-tab" data-tab="listings">Annunci attivi</div>
                    <div class="profile-tab" data-tab="history">Cronologia</div>
                    <div class="profile-tab" data-tab="settings">Impostazioni</div>
                </div>
                
                <div id="profileInfoTab" class="profile-tab-content">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div class="stat-card">
                            <i class="fas fa-book"></i>
                            <h3 id="profileBooks">0</h3>
                            <p>Libri venduti</p>
                        </div>
                        
                        <div class="stat-card">
                            <i class="fas fa-star"></i>
                            <h3>5.0</h3>
                            <p>Valutazione media</p>
                        </div>
                    </div>
                </div>
                
                <div id="profileSettingsTab" class="profile-tab-content" style="display: none;">
                    <div class="filters">
                        <h3>Impostazioni account</h3>
                        <form method="POST" id="profileForm">
                            <div class="form-group">
                                <label for="profileNameInput">Nome</label>
                                <input type="text" id="profileNameInput" class="form-control" value="<?php echo isset($_SESSION['nome']) ? $_SESSION['nome'] : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="profileEmailInput">Email</label>
                                <input type="email" id="profileEmailInput" class="form-control" value="<?php echo isset($_SESSION['email']) ? $_SESSION['email'] : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="profilePasswordInput">Nuova password</label>
                                <input type="password" id="profilePasswordInput" class="form-control" placeholder="Lascia vuoto per non cambiare">
                            </div>
                            
                            <button type="button" class="btn" id="saveProfileBtn">Salva modifiche</button>
                        </form>
                    </div>
                </div>
            </section>
            
            <!-- Carrello -->
            <section id="cart" class="page">
                <div class="page-header">
                    <h2 class="page-title">Il tuo carrello</h2>
                </div>
                
                <div class="cart-items" id="cartItems">
                    <!-- Elementi del carrello verranno inseriti qui -->
                </div>
                
                <div class="cart-summary">
                    <div class="summary-row">
                        <span>Subtotale</span>
                        <span id="cartSubtotal">€0.00</span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Spedizione</span>
                        <span id="cartShipping">€5.00</span>
                    </div>
                    
                    <div class="summary-row summary-total">
                        <span>Totale</span>
                        <span id="cartTotal">€5.00</span>
                    </div>
                    
                    <button class="btn" id="checkoutBtn">Procedi al checkout</button>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal per creazione annuncio -->
    <div class="modal" id="createListingModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Crea nuovo annuncio</h3>
                <button class="close-modal" id="closeListingModal">&times;</button>
            </div>
            
            <div class="modal-body">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="listingTitle">Titolo libro</label>
                        <input type="text" name="titolo" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="listingAuthor">Autore</label>
                        <input type="text" name="autore" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="listingGenre">Materia</label>
                        <select name="materia" class="form-control" required>
                            <option value="Matematica">Matematica</option>
                            <option value="Italiano">Italiano</option>
                            <option value="Storia">Storia</option>
                            <option value="Scienze">Scienze</option>
                            <option value="Inglese">Inglese</option>
                            <option value="Filosofia">Filosofia</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="listingCondition">Condizione</label>
                        <select name="stato" class="form-control" required>
                            <option value="NUOVO">Nuovo</option>
                            <option value="OTTIMO">Ottimo</option>
                            <option value="BUONO">Buono</option>
                            <option value="DISCRETO">Discreto</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="listingPrice">Prezzo (€)</label>
                        <input type="number" name="prezzo" step="0.01" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="listingDescription">Descrizione</label>
                        <textarea name="descrizione" class="form-control" rows="4"></textarea>
                    </div>
                    
                    <button type="submit" name="crea_annuncio" class="btn">Pubblica annuncio</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Stato dell'applicazione
        let currentPage = 'home';
        let cartItems = [];
        let currentChatId = null;
        
        // Dati di esempio per ricerca (prendi gli annunci dal database PHP)
        const sampleListings = [
            <?php foreach($annunci_recenti as $annuncio): ?>
            {
                id: <?php echo $annuncio['id']; ?>,
                title: "<?php echo addslashes($annuncio['titolo']); ?>",
                author: "<?php echo addslashes($annuncio['autore']); ?>",
                price: <?php echo $annuncio['prezzo']; ?>,
                condition: "<?php echo $annuncio['statoConservazione']; ?>",
                user: "Utente"
            },
            <?php endforeach; ?>
        ];

        // Inizializzazione
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM caricato');
            
            // Nascondi login overlay se utente loggato
            <?php if(isset($_SESSION['user_id'])): ?>
            document.getElementById('loginOverlay').style.display = 'none';
            <?php endif; ?>
            
            // Setup navigazione
            setupNavigation();
            
            // Setup altri eventi
            setupSearch();
            setupModals();
            setupProfile();
            setupMessaging();
            
            // Aggiorna carrello
            updateCartCount();
        });

        // Setup navigazione
        function setupNavigation() {
            const navLinks = document.querySelectorAll('.nav-link');
            const logo = document.getElementById('logo-home');
            const cartIcon = document.getElementById('cart-icon');
            const profileBtn = document.getElementById('userProfile');
            
            // Link navbar
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const pageId = this.dataset.page;
                    console.log('Navigazione verso:', pageId);
                    
                    switchPage(pageId);
                });
            });
            
            // Logo torna alla home
            if(logo) {
                logo.addEventListener('click', function(e) {
                    e.preventDefault();
                    switchPage('home');
                });
            }
            
            // Icona carrello
            if(cartIcon) {
                cartIcon.addEventListener('click', function() {
                    <?php if(isset($_SESSION['user_id'])): ?>
                    switchPage('cart');
                    <?php else: ?>
                    showNotification('Devi prima effettuare il login', 'warning');
                    <?php endif; ?>
                });
            }
            
            // Profilo utente
            if(profileBtn) {
                profileBtn.addEventListener('click', function() {
                    <?php if(isset($_SESSION['user_id'])): ?>
                    switchPage('profilo');
                    <?php else: ?>
                    document.getElementById('loginOverlay').style.display = 'flex';
                    <?php endif; ?>
                });
            }
        }

        // Cambia pagina
        function switchPage(pageId) {
            // Nascondi tutte le pagine
            document.querySelectorAll('.page').forEach(page => {
                page.classList.remove('active');
            });
            
            // Mostra pagina selezionata
            const targetPage = document.getElementById(pageId);
            if(targetPage) {
                targetPage.classList.add('active');
                currentPage = pageId;
                
                // Aggiorna link attivo
                document.querySelectorAll('.nav-link').forEach(link => {
                    link.classList.remove('active');
                    if(link.dataset.page === pageId) {
                        link.classList.add('active');
                    }
                });
                
                console.log('Pagina attiva:', pageId);
                
                // Carica contenuto specifico
                if(pageId === 'cart') {
                    updateCartDisplay();
                } else if(pageId === 'cerca') {
                    performSearch();
                } else if(pageId === 'annunci') {
                    loadMyListings();
                }
            } else {
                console.error('Pagina non trovata:', pageId);
            }
        }

        // Setup ricerca
        function setupSearch() {
            const searchBtn = document.getElementById('searchButton');
            const searchInput = document.getElementById('searchInput');
            const priceRange = document.getElementById('priceRange');
            const priceValue = document.getElementById('priceValue');
            
            if(searchBtn) {
                searchBtn.addEventListener('click', performSearch);
            }
            
            if(searchInput) {
                searchInput.addEventListener('keyup', function(e) {
                    if(e.key === 'Enter') performSearch();
                });
            }
            
            if(priceRange && priceValue) {
                priceRange.addEventListener('input', function() {
                    priceValue.textContent = `€${this.value}`;
                    performSearch();
                });
            }
            
            const conditionFilter = document.getElementById('conditionFilter');
            if(conditionFilter) {
                conditionFilter.addEventListener('change', performSearch);
            }
        }

        // Esegui ricerca
        function performSearch() {
            const searchTerm = document.getElementById('searchInput')?.value.toLowerCase() || '';
            const condition = document.getElementById('conditionFilter')?.value || '';
            const maxPrice = parseInt(document.getElementById('priceRange')?.value || 50);
            
            const results = sampleListings.filter(listing => {
                const matchesText = !searchTerm || 
                    listing.title.toLowerCase().includes(searchTerm) ||
                    listing.author.toLowerCase().includes(searchTerm);
                
                const matchesCondition = !condition || listing.condition === condition;
                const matchesPrice = listing.price <= maxPrice;
                
                return matchesText && matchesCondition && matchesPrice;
            });
            
            const container = document.getElementById('searchResults');
            if(!container) return;
            
            container.innerHTML = '';
            
            if(results.length === 0) {
                container.innerHTML = '<p style="text-align: center; grid-column: 1/-1; padding: 40px;">Nessun risultato trovato</p>';
            } else {
                results.forEach(listing => {
                    const card = createListingCard(listing);
                    container.appendChild(card);
                });
            }
        }

        // Crea card annuncio
        function createListingCard(listing) {
            const card = document.createElement('div');
            card.className = 'listing-card';
            card.dataset.id = listing.id;
            
            card.innerHTML = `
                <div class="listing-img">
                    <i class="fas fa-book"></i>
                </div>
                <div class="listing-info">
                    <h3 class="listing-title">${listing.title}</h3>
                    <p class="listing-author">${listing.author}</p>
                    <p>Condizione: ${listing.condition}</p>
                    <div class="listing-price">€${listing.price.toFixed(2)}</div>
                    <?php if(isset($_SESSION['user_id']) && (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] != 'admin')): ?>
                    <button class="btn" onclick="addToCart(${listing.id})">Aggiungi al carrello</button>
                    <?php endif; ?>
                </div>
            `;
            
            return card;
        }

        // Carica i miei annunci
        function loadMyListings() {
            const container = document.getElementById('myListings');
            if(!container) return;
            
            // Qui dovresti fare una chiamata AJAX per caricare gli annunci dell'utente
            container.innerHTML = '<p style="text-align: center; grid-column: 1/-1; padding: 40px;">Funzionalità in sviluppo</p>';
        }

        // Aggiungi al carrello
        function addToCart(listingId) {
            <?php if(!isset($_SESSION['user_id'])): ?>
            showNotification('Devi prima effettuare il login', 'warning');
            document.getElementById('loginOverlay').style.display = 'flex';
            return;
            <?php endif; ?>
            
            <?php if(isset($_SESSION['ruolo']) && $_SESSION['ruolo'] == 'admin'): ?>
            showNotification('Gli amministratori non possono acquistare', 'warning');
            return;
            <?php endif; ?>
            
            const listing = sampleListings.find(l => l.id === listingId);
            if(!listing) return;
            
            const existingItem = cartItems.find(item => item.id === listingId);
            if(existingItem) {
                existingItem.quantity++;
            } else {
                cartItems.push({
                    id: listing.id,
                    title: listing.title,
                    author: listing.author,
                    price: listing.price,
                    quantity: 1
                });
            }
            
            updateCartCount();
            showNotification(`"${listing.title}" aggiunto al carrello`, 'success');
        }

        // Aggiorna conteggio carrello
        function updateCartCount() {
            const cartCount = document.getElementById('cartCount');
            if(cartCount) {
                const total = cartItems.reduce((sum, item) => sum + item.quantity, 0);
                cartCount.textContent = total;
            }
        }

        // Aggiorna visualizzazione carrello
        function updateCartDisplay() {
            const container = document.getElementById('cartItems');
            if(!container) return;
            
            container.innerHTML = '';
            
            let subtotal = 0;
            
            cartItems.forEach(item => {
                subtotal += item.price * item.quantity;
                
                const cartItem = document.createElement('div');
                cartItem.className = 'cart-item';
                cartItem.innerHTML = `
                    <div class="cart-item-img">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="cart-item-details">
                        <h4 class="cart-item-title">${item.title}</h4>
                        <p>${item.author}</p>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                            <div>
                                <button onclick="updateQuantity(${item.id}, -1)" style="padding: 5px 10px; margin-right: 5px;">-</button>
                                <span>${item.quantity}</span>
                                <button onclick="updateQuantity(${item.id}, 1)" style="padding: 5px 10px; margin-left: 5px;">+</button>
                            </div>
                            <div class="cart-item-price">€${(item.price * item.quantity).toFixed(2)}</div>
                        </div>
                    </div>
                `;
                container.appendChild(cartItem);
            });
            
            const shipping = 5.00;
            const total = subtotal + shipping;
            
            document.getElementById('cartSubtotal').textContent = `€${subtotal.toFixed(2)}`;
            document.getElementById('cartShipping').textContent = `€${shipping.toFixed(2)}`;
            document.getElementById('cartTotal').textContent = `€${total.toFixed(2)}`;
        }

        // Aggiorna quantità nel carrello
        window.updateQuantity = function(id, change) {
            const item = cartItems.find(item => item.id === id);
            if(!item) return;
            
            item.quantity += change;
            
            if(item.quantity <= 0) {
                cartItems = cartItems.filter(item => item.id !== id);
            }
            
            updateCartCount();
            updateCartDisplay();
        };

        // Setup profilo
        function setupProfile() {
            const profileTabs = document.querySelectorAll('.profile-tab');
            const saveBtn = document.getElementById('saveProfileBtn');
            
            profileTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    profileTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    const tabId = this.dataset.tab;
                    
                    document.querySelectorAll('.profile-tab-content').forEach(content => {
                        content.style.display = 'none';
                    });
                    
                    if(tabId === 'info') {
                        document.getElementById('profileInfoTab').style.display = 'block';
                    } else if(tabId === 'settings') {
                        document.getElementById('profileSettingsTab').style.display = 'block';
                    }
                });
            });
            
            if(saveBtn) {
                saveBtn.addEventListener('click', function() {
                    showNotification('Profilo aggiornato con successo!', 'success');
                });
            }
        }

        // Setup messaggistica
        function setupMessaging() {
            const chatList = document.getElementById('chatList');
            const messageInput = document.getElementById('messageInput');
            const sendBtn = document.getElementById('sendMessageBtn');
            
            if(chatList) {
                const sampleChats = [
                    {id: 1, name: "Mario Rossi", lastMessage: "L'annuncio è ancora disponibile?", time: "10:30", unread: 2},
                    {id: 2, name: "Laura Bianchi", lastMessage: "Accetto il prezzo, quando possiamo incontrarci?", time: "Ieri", unread: 0}
                ];
                
                chatList.innerHTML = '';
                sampleChats.forEach(chat => {
                    const chatItem = document.createElement('div');
                    chatItem.className = 'chat-item';
                    chatItem.dataset.id = chat.id;
                    
                    chatItem.innerHTML = `
                        <div style="display: flex; justify-content: space-between;">
                            <strong>${chat.name}</strong>
                            <span style="font-size: 0.8rem; color: #7f8c8d;">${chat.time}</span>
                        </div>
                        <p style="margin-top: 5px; font-size: 0.9rem;">${chat.lastMessage}</p>
                        ${chat.unread > 0 ? `<span class="badge" style="position: static; margin-top: 5px;">${chat.unread}</span>` : ''}
                    `;
                    
                    chatItem.addEventListener('click', function() {
                        document.querySelectorAll('.chat-item').forEach(item => {
                            item.classList.remove('active');
                        });
                        this.classList.add('active');
                        
                        currentChatId = chat.id;
                        document.getElementById('currentChat').textContent = chat.name;
                        
                        if(messageInput) messageInput.disabled = false;
                        if(sendBtn) sendBtn.disabled = false;
                        
                        loadChatMessages(chat.id);
                    });
                    
                    chatList.appendChild(chatItem);
                });
            }
            
            if(sendBtn) {
                sendBtn.addEventListener('click', sendMessage);
            }
            
            if(messageInput) {
                messageInput.addEventListener('keyup', function(e) {
                    if(e.key === 'Enter') sendMessage();
                });
            }
        }

        function loadChatMessages(chatId) {
            const messagesContainer = document.getElementById('chatMessages');
            if(!messagesContainer) return;
            
            const sampleMessages = [
                {id: 1, sender: "other", text: "Ciao, l'annuncio è ancora disponibile?", time: "10:30"},
                {id: 2, sender: "me", text: "Sì, è ancora disponibile! È in ottime condizioni.", time: "10:32"}
            ];
            
            messagesContainer.innerHTML = '';
            
            sampleMessages.forEach(message => {
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${message.sender === 'me' ? 'sent' : 'received'}`;
                messageDiv.innerHTML = `
                    <div>${message.text}</div>
                    <div style="font-size: 0.7rem; text-align: ${message.sender === 'me' ? 'right' : 'left'}; margin-top: 5px;">${message.time}</div>
                `;
                messagesContainer.appendChild(messageDiv);
            });
            
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function sendMessage() {
            const input = document.getElementById('messageInput');
            const messageText = input?.value.trim();
            
            if(!messageText || !currentChatId) return;
            
            const messagesContainer = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message sent';
            
            const now = new Date();
            const timeString = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;
            
            messageDiv.innerHTML = `
                <div>${messageText}</div>
                <div style="font-size: 0.7rem; text-align: right; margin-top: 5px;">${timeString}</div>
            `;
            
            messagesContainer.appendChild(messageDiv);
            input.value = '';
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Setup modal
        function setupModals() {
            const createBtn = document.getElementById('createListingBtn');
            const modal = document.getElementById('createListingModal');
            const closeBtn = document.getElementById('closeListingModal');
            
            if(createBtn) {
                createBtn.addEventListener('click', function() {
                    <?php if(!isset($_SESSION['user_id'])): ?>
                    showNotification('Devi prima effettuare il login', 'warning');
                    document.getElementById('loginOverlay').style.display = 'flex';
                    <?php else: ?>
                    modal.classList.add('active');
                    <?php endif; ?>
                });
            }
            
            if(closeBtn) {
                closeBtn.addEventListener('click', function() {
                    modal.classList.remove('active');
                });
            }
            
            window.addEventListener('click', function(e) {
                if(e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        }

        // Mostra notifica
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification-toast ${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background-color: ${type === 'success' ? '#27ae60' : type === 'warning' ? '#f39c12' : '#3498db'};
                color: white;
                padding: 15px 20px;
                border-radius: 5px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                z-index: 4000;
                animation: slideInRight 0.3s ease;
                max-width: 300px;
            `;
            
            notification.innerHTML = `
                <div style="display: flex; align-items: center;">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'}" style="margin-right: 10px;"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Aggiungi animazioni CSS se non esistono
        if(!document.querySelector('#notification-animations')) {
            const style = document.createElement('style');
            style.id = 'notification-animations';
            style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOutRight {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }
    </script>
</body>
</html>