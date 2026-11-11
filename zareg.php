<?php
require 'connect.php';

if ($_POST) {
    $name  = $db->real_escape_string($_POST['name']);
    $email = $db->real_escape_string($_POST['email']);
    $phone = $db->real_escape_string($_POST['phone']);
    $pass  = sha1($_POST['pass']);

    $db->query("INSERT INTO users (name, email, phone, password_hash, role_id, registered_at)
                VALUES ('$name', '$email', '$phone', '$pass', 2, NOW())");

    header('Location: auth.php');
    exit;
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

    <p class="page-name">Зарегристрироваться</p>

    <div class="user-form">
        <form method="post">
            <input type="text" name="name" placeholder="Полное имя">
            <input type="email" name="email" placeholder="Email">
            <input type="text" name="phone" placeholder="Телефон">
            <input type="password" name="pass" placeholder="Придумайте пароль">
            <button class="user-form-b" type="submit">Зарегистрироваться</button>
        </form>
        <a href="auth.php">Войти в аккаунт</a>
    </div>

    <footer>
        <p>ГОРОДСКАЯ БИБЛИОТЕКА ГОРОДА БАРНАУЛА * 2026</p>
    </footer>
</body>
</html>