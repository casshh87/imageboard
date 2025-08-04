<?php
// Подключение к MariaDB
try {
    $db = new PDO("mysql:host=localhost;dbname=imageboard;charset=utf8mb4", "root", "root");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}