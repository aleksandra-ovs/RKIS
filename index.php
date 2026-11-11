<?php
session_start();
require 'connect.php';

// функции
function get_all($sql)
{
    global $db;
    $rows = [];
    $result = mysqli_query($db, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function d($date)
{
    return $date ? date('d.m.Y', strtotime($date)) : '—';
}

function loan_status($loan)
{
    if ($loan['returned_at']) {
        return 'возвращена';
    }
    if ($loan['due_date'] && substr($loan['due_date'], 0, 10) < date('Y-m-d')) {
        return 'просрочено';
    }
    return 'не просрочено';
}

// данные для страницы
$q = $_GET['q'] ?? '';
$books = get_all("SELECT * FROM books
    WHERE title LIKE '%$q%' OR author LIKE '%$q%' OR genre LIKE '%$q%'");
$all_books = get_all("SELECT * FROM books");

// мои книги и поиск просроченных
$loans = [];
$overdue_books = []; // массив для просроченных книг
if (isset($_SESSION['user'])) {
    $user_id = (int)$_SESSION['user']['user_id'];
    $loans = get_all("SELECT r.*, b.title, b.author, b.genre, b.photo_file
                      FROM requests r JOIN books b ON b.book_id = r.book_id
                      WHERE r.user_id = $user_id
                      ORDER BY r.returned_at IS NULL DESC, r.issued_at DESC");

    // Ищем среди броней те, у которых статус "просрочено"
    foreach ($loans as $loan) {
        if (loan_status($loan) === 'просрочено') {
            $overdue_books[] = $loan['title'];
        }
    }
}

// расширенный поиск
$searched = isset($_GET['search']);
$search_result = [];
if ($searched) {
    $title = $_GET['title'];
    $author = $_GET['author'];
    $genre = $_GET['genre'];
    $isbn = $_GET['isbn'];

    $search_result = get_all("SELECT * FROM books
        WHERE title LIKE '%$title%' AND author LIKE '%$author%'
        AND genre LIKE '%$genre%' AND isbn LIKE '%$isbn%'");
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Городская библиотека города Барнаула</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- шапка -->
    <header>
        ГОРОДСКАЯ БИБЛИОТЕКА ГОРОДА БАРНАУЛА
        <img src="images/book.png" alt="book">
    </header>

    <!-- УВЕДОМЛЕНИЕ О ПРОСРОЧЕННЫХ КНИГАХ -->
    <?php if (!empty($overdue_books)) { ?>
        <div class="overdue-banner">
            У вас есть просроченные книги: <?= htmlspecialchars(implode(', ', $overdue_books)) ?>. Пожалуйста, сдайте их в библиотеку!
        </div>
    <?php } ?>

    <!-- пользователь -->
    <?php if (isset($_SESSION['user'])) { ?>
        <div class="full-user">
            <div class="user-block">
                <img src="images/user.png" alt="user">
                <a href="index.php"><?= $_SESSION['user']['name'] ?></a>
            </div>
            <form action="logout.php" method="post">
                <button class="logout">Выход</button>
            </form>
        </div>
    <?php } else { ?>
        <div class="user-block">
            <img src="images/user.png" alt="user">
            <a href="auth.php">Авторизация</a>
        </div>
    <?php } ?>

    <!-- поиск -->
    <form method="get">
        <input type="search" name="q" value="<?= $q ?>" placeholder="Поиск по названию, жанру и др....">
    </form>

    <!-- меню вкладок -->
    <div class="page-menu">
        <button class="active" data-section="catalog-section">Каталог</button>
        <button data-section="search-section">Расширенный поиск</button>
        <?php if (isset($_SESSION['user'])) { ?>
            <button data-section="mybooks-section">Мои книги</button>
        <?php } ?>
    </div>

    <!-- вкладка: каталог -->
    <div class="section" id="catalog-section" style="display: block;">
        <?php if (count($books) == 0) { ?>
            <p>Ничего не найдено</p>
        <?php } ?>

        <div class="catalog">
            <?php foreach ($books as $book) { ?>
                <div class="book open-modal-btn" data-modal="bookModal<?= $book['book_id'] ?>">
                    <div class="photo">
                        <img src="<?= $book['photo_file'] ?>" alt="обложка">
                    </div>
                    <div class="info">
                        <p class="bookname"><?= $book['title'] ?></p>
                        <p class="other"><?= $book['author'] ?></p>
                        <p class="other"><?= $book['genre'] ?></p>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- вкладка: расширенный поиск -->
    <div class="section" id="search-section" style="display: none;">
        <form class="search-form" method="get">
            <input type="text" name="title" class="search-details" placeholder="Название книги....">
            <input type="text" name="author" class="search-details" placeholder="Автор книги....">
            <input type="text" name="genre" class="search-details" placeholder="Жанр книги....">
            <input type="text" name="isbn" class="search-details" placeholder="ISBN.....">
            <button type="submit" name="search" class="search-button">Поиск</button>
        </form>

        <p class="results">Результаты поиска:</p>

        <?php if ($searched && count($search_result) == 0) { ?>
            <p>Ничего не найдено</p>
        <?php } ?>

        <?php foreach ($search_result as $book) { ?>
            <div class="results-of-search">
                <main class="main-results">
                    <div class="res-photo">
                        <img src="<?= $book['photo_file'] ?>" alt="обложка">
                    </div>
                    <div class="res-info">
                        <p class="res-name"><?= $book['title'] ?></p>
                        <p class="res-other"><?= $book['author'] ?></p>
                        <p class="res-other"><?= $book['genre'] ?></p>
                    </div>
                </main>
                <p class="res-last">ISBN: <?= $book['isbn'] ?></p>
                <p class="res-last"><?= $book['description'] ?></p>
            </div>
        <?php } ?>
    </div>

    <!-- вкладка: мои книги -->
    <?php if (isset($_SESSION['user'])) { ?>
    <div class="section" id="mybooks-section" style="display: none;">
        <p class="results">Текущие бронирования</p>

        <?php if (count($loans) == 0) { ?>
            <p>У вас пока нет бронирований</p>
        <?php } ?>

        <?php foreach ($loans as $loan) { ?>
            <div class="bronirovanie">
                <div class="book-info">
                    <div class="main-results">
                        <div class="res-photo">
                            <img src="<?= $loan['photo_file'] ?>" alt="обложка">
                        </div>
                        <div class="res-info">
                            <p class="res-name"><?= $loan['title'] ?></p>
                            <p class="res-other"><?= $loan['author'] ?></p>
                            <p class="res-other"><?= $loan['genre'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="bron-info">
                    <p>Статус: <?= loan_status($loan) ?></p>
                    <p>Выдана: <?= d($loan['issued_at']) ?></p>
                    <p>Бронь до: <?= d($loan['due_date']) ?></p>
                    <p>Возвращена: <?= d($loan['returned_at']) ?></p>
                </div>
            </div>
        <?php } ?>
    </div>
    <?php } ?>

    <!-- подвал -->
    <footer>
        <p>ГОРОДСКАЯ БИБЛИОТЕКА ГОРОДА БАРНАУЛА * 2026</p>
    </footer>

<!-- окна: информация о книгах -->
<?php foreach ($all_books as $book) { ?>
<div class="modal" id="bookModal<?= $book['book_id'] ?>">
    <div class="modal-content">
        <span class="modal-close">&times;</span>

        <div class="modal-top">
            <div class="modal-photo">
                <img src="<?= $book['photo_file'] ?>" alt="обложка">
            </div>
            <div class="modal-info">
                <p class="bookname"><?= $book['title'] ?></p>
                <p class="other"><?= $book['author'] ?></p>
                <p class="other"><?= $book['genre'] ?></p>
            </div>
        </div>

        <p class="modal-details">ISBN: <?= $book['isbn'] ?></p>
        <p class="modal-details"><?= $book['description'] ?></p>
    </div>
</div>
<?php } ?>

<!-- скрипты -->
<script src="script.js"></script>
<?php if ($searched) { ?>
<script>
    showSection(document.querySelector('[data-section="search-section"]'));
</script>
<?php } ?>
</body>
</html>