<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        file_put_contents("users.txt", "\n$email,$password,user\n", FILE_APPEND);
        echo "<script>alert('Registration Successful!'); window.location.href='index.html';</script>";
    } else {
        echo "<script>alert('Please fill in all fields!'); window.history.back();</script>";
    }
}
?>