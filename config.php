<?php

$host = "mysql-db"; // service name from docker-compose

$user = "root";

$pass = "root";

$db  = "fintracker";



// Create connection

$conn = new mysqli($host, $user, $pass, $db);



// Check connection

if ($conn->connect_error) {

 die("Connection failed: " . $conn->connect_error);

}

?>