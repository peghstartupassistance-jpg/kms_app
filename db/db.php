<?php
// db/db.php

$DB_HOST = 'localhost';
$DB_NAME = 'kms_gestion';
$DB_USER = 'root';
$DB_PASS = ''; // à adapter à ton hébergement

$DB_DSN = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($DB_DSN, $DB_USER, $DB_PASS, $options);
    // Normaliser l'encodage côté connexion - FORCER UTF-8MB4
    $pdo->exec('SET NAMES utf8mb4');
    $pdo->exec('SET CHARACTER SET utf8mb4');
    $pdo->exec('SET character_set_connection=utf8mb4');
    $pdo->exec('SET character_set_results=utf8mb4');
    $pdo->exec('SET character_set_client=utf8mb4');
} catch (PDOException $e) {
    // Ne pas exposer le message complet en prod
    die('Erreur de connexion à la base de données.');
}
