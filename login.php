<?php
session_start();
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT id, fullname, username, password FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['username'] = $user['username'];
            
            // Redirect to dashboard.php
            header("Location: dashboard.php");
            exit();
        } else {
            // Invalid password
            echo "<script>alert('Invalid password!'); window.location.href='login.html';</script>";
        }
    } else {
        // User not found
        echo "<script>alert('User not found!'); window.location.href='login.html';</script>";
    }
    
    $stmt->close();
    $conn->close();
}
?>