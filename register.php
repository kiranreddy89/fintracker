<?php
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $_POST['fullname'];
    $email    = $_POST['email'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // Insert into users table
    $sql = "INSERT INTO users (fullname, email, username, password) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $fullname, $email, $username, $password);

    if ($stmt->execute()) {
        echo "<script>alert('Registration successful! Please login.'); window.location.href='login.html';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
}
?>
