<?php

$host = "192.168.3.92";       // es: localhost
$user = "calessandro";        // es: root
$password ="kWB4gU7bt.Oed2Ce";   // password MySQL
$database = "calessandro";    // nome del database

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Errore di connessione: " . $conn->connect_error);
}
?>