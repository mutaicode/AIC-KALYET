<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "kalyet_church_db";

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);

if ($conn->connect_error) {
    die("Database connection failed. Please check your XAMPP settings.");
}

$conn->set_charset("utf8mb4");