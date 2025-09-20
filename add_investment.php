<?php
session_start();
include 'config.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_investment'])) {
    $user_id = $_SESSION['user_id'];
    $investment_name = $_POST['investment_name'];
    $amount = $_POST['amount'];
    $investment_date = $_POST['investment_date'];

    $sql = "INSERT INTO investments (user_id, investment_name, amount, investment_date) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isds", $user_id, $investment_name, $amount, $investment_date);

    if ($stmt->execute()) {
        // Success
        header("Location: profile.php?status=investment_added");
    } else {
        // Error
        header("Location: profile.php?status=investment_error");
    }
    $stmt->close();
    $conn->close();
    exit();
} else {
    // Redirect back if accessed directly
    header("Location: profile.php");
    exit();
}
?>