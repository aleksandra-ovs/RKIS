<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require 'connect.php';

if ($_POST) {
    $email = $db->real_escape_string($_POST['email']);
    $pass  = sha1($_POST['pass']);

    $user = $db->query("SELECT * FROM users WHERE email='$email' AND password_hash='$pass'")->fetch_assoc();

    if ($user) {
        $_SESSION['user'] = $user;   // вместо $_SESSION['role']

        if ($user['role_id'] == 2) {
            header("Location: index.php");
        } else {
            header("Location: admin.php");
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Городская библиотека города Барнаула</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        ГОРОДСКАЯ БИБЛИОТЕКА ГОРОДА БАРНАУЛА
        <a href="index.php"><img src="images/book.png" alt="book"></a>
    </header>

    <p class="page-name">Войти в аккаунт</p>

    <div class="user-form">
        <form action="" method="post">
            <input type="email" name="email" placeholder="Email">
            <input type="password" name="pass" placeholder="Придумайте пароль">

            <button type="submit" class="user-form-b">Войти в аккаунт</button>
        </form>
        <a href="zareg.php">Зарегистрироваться</a>
    </div>

    <footer>
        <p>ГОРОДСКАЯ БИБЛИОТЕКА ГОРОДА БАРНАУЛА * 2026</p>
    </footer>
</body>
</html>