<?php

session_start();

$host = "localhost";
$db   = "adminag_clientlogin";
$user = "adminag_mujahid";
$pass = "9cKX@XQ,#(HR";

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch(PDOException $e)
{
    die("Database Connection Failed");
}
?>