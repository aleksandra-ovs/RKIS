<?php
session_start();
require 'connect.php';

// простые функции

// перейти на другую страницу и остановить скрипт
function go($url) {
    header('Location: ' . $url);
    exit;
}

// защита текста от ломающих символов (кавычек) перед вставкой в SQL
function clean($text) {
    global $db;
    return mysqli_real_escape_string($db, $text);
}

// выполнить запрос
function query($sql) {
    global $db;
    return mysqli_query($db, $sql);
}

// выполнить SELECT и вернуть все строки обычным массивом
function get_all($sql) {
    $rows = [];
    $result = query($sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

// доступ только для администратора 
if (!isset($_SESSION['user']) || $_SESSION['user']['role_id'] == 2) {
    go('auth.php');
}

// выдача книги
if (isset($_POST['issue'])) {
    $user_id = (int)$_POST['user_id'];
    $book_id = (int)$_POST['book_id'];
    $due = clean($_POST['due_date']);
    $ok = false;

    if ($user_id && $book_id && $due >= date('Y-m-d')) {
        query("UPDATE books SET status = 'Выдана' WHERE book_id = $book_id AND status = 'В библиотеке'");

        // если книга реально поменяла статус — записываем выдачу
        if (mysqli_affected_rows($db) == 1) {
            query("INSERT INTO requests (user_id, book_id, status, status_srok, created_at, issued_at, due_date)
                   VALUES ($user_id, $book_id, 'Выдана', 'не просрочено', NOW(), NOW(), '$due')");
            $ok = true;
        }
    }

    go($ok ? 'admin.php' : 'admin.php?err=1');
}

// продление 
if (isset($_POST['extend'])) {
    $request_id = (int)$_POST['request_id'];
    $days = (int)$_POST['days'];

    if ($request_id && $days >= 1) {
        query("UPDATE requests SET due_date = DATE_ADD(due_date, INTERVAL $days DAY)
               WHERE request_id = $request_id AND returned_at IS NULL");
    }

    go('admin.php');
}

// приём книги 
if (isset($_POST['accept'])) {
    $request_id = (int)$_POST['request_id'];
    $loan = mysqli_fetch_assoc(query("SELECT book_id FROM requests
                                      WHERE request_id = $request_id AND returned_at IS NULL"));

    if ($loan) {
        query("UPDATE requests SET returned_at = NOW(), status = 'Возвращена' WHERE request_id = $request_id");
        query("UPDATE books SET status = 'В библиотеке' WHERE book_id = " . (int)$loan['book_id']);
    }

    go('admin.php');
}

// добавление книги
if (isset($_POST['add_book'])) {
    $title = clean($_POST['title']);
    $author = clean($_POST['author']);
    $genre = clean($_POST['genre']);
    $isbn = clean($_POST['isbn']);
    $description = clean($_POST['description']);
    $photo = clean($_POST['photo']);

    query("INSERT INTO books (title, author, genre, isbn, photo_file, description, status)
           VALUES ('$title', '$author', '$genre', '$isbn', '$photo', '$description', 'В библиотеке')");

    go('admin.php?tab=books');
}

// проверка просрочки
$today = date('Y-m-d');
query("UPDATE requests
       SET status_srok = IF(DATE(due_date) < '$today', 'просрочено', 'не просрочено')
       WHERE returned_at IS NULL");

// Получаем список просроченных книг с именами читателей для плашки администратора
$admin_overdue = get_all("SELECT r.*, b.title, u.name as reader_name 
                          FROM requests r 
                          JOIN books b ON b.book_id = r.book_id 
                          JOIN users u ON u.user_id = r.user_id 
                          WHERE r.returned_at IS NULL AND r.status_srok = 'просрочено'");

// данные для страницы
$s = clean($_GET['s'] ?? '');   // поиск читателя
$b = clean($_GET['b'] ?? '');   // поиск книги

$users = get_all("SELECT * FROM users WHERE role_id = 2 AND name LIKE '%$s%'");
$books = get_all("SELECT * FROM books WHERE title LIKE '%$b%' OR author LIKE '%$b%' OR genre LIKE '%$b%'");
$all_books = get_all("SELECT * FROM books");
$free_books = get_all("SELECT * FROM books WHERE status = 'В библиотеке'");
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

    <!-- УВЕДОМЛЕНИЕ О ПРОСРОЧЕННЫХ КНИГАХ ДЛЯ АДМИНА -->
    <?php if (!empty($admin_overdue)) { ?>
        <div class="overdue-banner">
            Внимание! Есть просроченные книги: 
            <?php 
                $overdue_items = [];
                foreach ($admin_overdue as $item) {
                    $overdue_items[] = '"' . htmlspecialchars($item['title']) . '" (читатель: ' . htmlspecialchars($item['reader_name']) . ')';
                }
                echo implode(', ', $overdue_items);
            ?>
        </div>
    <?php } ?>

    <!-- пользователь и выход -->
    <div class="full-user">
        <div class="user-block">
            <img src="images/user.png" alt="user">
            <a href="admin.php"><?= $_SESSION['user']['name'] ?></a>
        </div>
        <form action="logout.php" method="post">
            <button class="logout">Выход</button>
        </form>
    </div>

    <!-- меню вкладок -->
    <div class="page-menu">
        <button class="active" data-section="readers-section">УПРАВЛЕНИЕ ЧИТАТЕЛЯМИ</button>
        <button data-section="books-section">УПРАВЛЕНИЕ КНИГАМИ</button>
    </div>

    <!-- вкладка: читатели -->
    <div class="section" id="readers-section" style="display: block;">
        <?php if (isset($_GET['err'])) { ?>
            <p style="color:#c0392b;">Не удалось выдать книгу: выберите книгу и дату возврата (не раньше сегодняшней), либо книга уже выдана.</p>
        <?php } ?>

        <form method="get">
            <input type="search" name="s" value="<?= htmlspecialchars($_GET['s'] ?? '') ?>" placeholder="Поиск читателя по имени....">
        </form>

        <div class="usersss">
            <?php if (count($users) == 0) { ?>
                <p>Читатели не найдены</p>
            <?php } ?>

            <?php foreach ($users as $u) {
                // книги, которые сейчас на руках у этого читателя
                $loans = get_all("SELECT r.*, b.title, b.author, b.genre, b.photo_file
                                  FROM requests r JOIN books b ON b.book_id = r.book_id
                                  WHERE r.user_id = " . (int)$u['user_id'] . "
                                    AND r.issued_at IS NOT NULL
                                    AND r.returned_at IS NULL");
            ?>
                <div class="user-card">
                    <div class="personal-data">
                        <img src="images/user.png" alt="user">
                        <div class="name-bilet">
                            <p><?= $u['name'] ?></p>
                            <p class="bilet">Билет <?= $u['user_id'] ?></p>
                        </div>
                        <button class="open-modal-btn issue-btn" data-modal="issueModal"
                                data-user="<?= $u['user_id'] ?>"
                                data-name="<?= $u['name'] ?>">Выдать -></button>
                    </div>

                    <?php if (count($loans) > 0) { ?>
                        <div class="person-brons">
                            <?php foreach ($loans as $loan) {
                                // данные для окон «Продлить» и «Принять»
                                $data = 'data-request="' . $loan['request_id'] . '"'
                                      . ' data-reader="' . $u['name'] . '"'
                                      . ' data-title="' . $loan['title'] . '"'
                                      . ' data-author="' . $loan['author'] . '"'
                                      . ' data-genre="' . $loan['genre'] . '"'
                                      . ' data-photo="' . $loan['photo_file'] . '"'
                                      . ' data-due="' . date('d.m.y', strtotime($loan['due_date'])) . '"';
                            ?>
                                <div class="books-info">
                                    <div class="one-book-info">
                                        <div class="photo-adm"><img src="<?= $loan['photo_file'] ?>" alt="обложка"></div>
                                        <div class="name-author">
                                            <p><?= $loan['title'] ?></p>
                                            <p><?= $loan['author'] ?></p>
                                        </div>
                                    </div>

                                    <div class="other-infos">
                                        <div class="time-info">
                                            <p>Выдана: <?= date('d.m.y', strtotime($loan['issued_at'])) ?></p>
                                            <p>До: <?= date('d.m.y', strtotime($loan['due_date'])) ?></p>
                                            <p>Статус: <?= $loan['status_srok'] ?></p>
                                        </div>
                                        <div class="actions">
                                            <button class="open-modal-btn" data-modal="extendModal" <?= $data ?>>Продлить -></button>
                                            <button class="open-modal-btn" data-modal="acceptModal" <?= $data ?>>Принять -></button>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <div class="pagination" id="readers-pages"></div>
    </div>

    <!-- вкладка: книги -->
    <div class="section" id="books-section" style="display: none;">
        <form method="get">
            <input type="hidden" name="tab" value="books">
            <input type="search" name="b" value="<?= htmlspecialchars($_GET['b'] ?? '') ?>" placeholder="Поиск по названию, автору, жанру....">
        </form>

        <?php if (count($books) == 0) { ?>
            <p>Книги не найдены</p>
        <?php } ?>

        <div class="books-adm">
            <?php foreach ($books as $book) {
                $class = ($book['status'] == 'В библиотеке') ? 'positive' : 'negative';
            ?>
                <div class="book-adm open-modal-btn" data-modal="bookModal<?= $book['book_id'] ?>">
                    <div class="pht">
                        <img src="<?= $book['photo_file'] ?>" alt="обложка">
                    </div>

                    <div class="inf">
                        <div class="top-info">
                            <p class="bookname-adm"><?= $book['title'] ?></p>
                            <p class="status <?= $class ?>"><?= $book['status'] ?></p>
                        </div>
                        <p class="other-adm"><?= $book['author'] ?></p>
                        <p class="other-adm"><?= $book['genre'] ?></p>
                    </div>
                </div>
            <?php } ?>
        </div>

        <div class="pagination" id="books-pages"></div>

        <!-- форма добавления книги -->
        <p class="results">Добавить книгу</p>

        <form class="add-book" action="" method="post">
            <div class="main-data">
                <input type="text" name="title" placeholder="НАЗВАНИЕ КНИГИ">
                <input type="text" name="author" placeholder="АВТОР КНИГИ">
                <input type="text" name="genre" placeholder="ЖАНР КНИГИ">
            </div>

            <div class="descrip-photo">
                <textarea name="description" placeholder="ОПИСАНИЕ"></textarea>
                <div>
                    <input type="text" name="photo" class="photo-link" placeholder="ССЫЛКА НА ИЗОБРАЖЕНИЕ">
                    <input type="text" name="isbn" class="photo-link" style="margin-top: 10px;" placeholder="isbn">
                </div>
            </div>

            <button type="submit" name="add_book">Добавить</button>
        </form>
    </div>

    <!-- подвал -->
    <footer>
        <p>ГОРОДСКАЯ БИБЛИОТЕКА ГОРОДА БАРНАУЛА * 2026</p>
    </footer>

<!-- окно: выдача книги -->
<div class="modal" id="issueModal">
    <div class="admin-modal-content">
        <span class="modal-close">&times;</span>
        <h2>Выберите книгу для выдачи</h2>
        <p class="modal-subtitle">Читатель: <span id="issue-user-name"></span></p>

        <input type="text" class="modal-search" id="issue-search" placeholder="Поиск книги по названию....">

        <form method="post">
            <input type="hidden" name="user_id" id="issue-user-id">
            <input type="hidden" name="book_id" id="issue-book-id">

            <div class="modal-books-grid">
                <?php if (count($free_books) == 0) { ?>
                    <p>Свободных книг нет</p>
                <?php } ?>

                <?php foreach ($free_books as $fb) { ?>
                    <div class="modal-mini-card" data-book="<?= $fb['book_id'] ?>"
                         data-title="<?= mb_strtolower($fb['title']) ?>">
                        <div class="modal-mini-inf">
                            <p class="m-title"><?= $fb['title'] ?></p>
                            <p class="m-sub"><?= $fb['author'] ?></p>
                            <p class="m-sub"><?= $fb['genre'] ?></p>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <p id="issue-error" style="color:#c0392b; display:none;">Выберите книгу из списка</p>

            <input type="date" name="due_date" class="modal-input" required min="<?= date('Y-m-d') ?>">
            <button type="submit" name="issue" class="modal-main-btn">Записать на читателя</button>
        </form>
    </div>
</div>

<!-- окно: продление брони -->
<div class="modal" id="extendModal">
    <div class="admin-modal-content small-modal">
        <span class="modal-close">&times;</span>
        <h2>Продление брони</h2>
        <p class="modal-subtitle">Продление для <span id="extend-reader"></span></p>

        <form method="post">
            <input type="hidden" name="request_id" id="extend-request-id">

            <div class="modal-single-book">
                <div class="modal-mini-pht" id="extend-photo"></div>
                <div class="modal-mini-inf">
                    <p class="m-title" id="extend-title"></p>
                    <p class="m-sub" id="extend-author"></p>
                    <p class="m-sub" id="extend-genre"></p>
                </div>
                <p class="modal-date-right">До: <span id="extend-due"></span></p>
            </div>

            <input type="number" name="days" min="1" required class="modal-input center-input" placeholder="Продлить на: ...... дней (число)">
            <button type="submit" name="extend" class="modal-main-btn">Продлить</button>
        </form>
    </div>
</div>

<!-- окно: приём книги -->
<div class="modal" id="acceptModal">
    <div class="admin-modal-content small-modal">
        <span class="modal-close">&times;</span>
        <h2>Принять книгу</h2>
        <p class="modal-subtitle">Подтвердите возврат книги для <span id="accept-reader"></span></p>

        <form method="post">
            <input type="hidden" name="request_id" id="accept-request-id">

            <div class="modal-single-book">
                <div class="modal-mini-pht" id="accept-photo"></div>
                <div class="modal-mini-inf">
                    <p class="m-title" id="accept-title"></p>
                    <p class="m-sub" id="accept-author"></p>
                    <p class="m-sub" id="accept-genre"></p>
                </div>
                <p class="modal-date-right">До: <span id="accept-due"></span></p>
            </div>

            <button type="submit" name="accept" class="modal-main-btn">Принять</button>
            <button type="button" class="modal-close-text close-modal">Отмена</button>
        </form>
    </div>
</div>

<!-- окна: информация о книгах -->
<?php foreach ($all_books as $book) { ?>
<div class="modal" id="bookModal<?= $book['book_id'] ?>">
    <div class="admin-modal-content book-modal">
        <span class="modal-close">&times;</span>

        <div class="book-modal-top">
            <div class="book-modal-photo"><img src="<?= $book['photo_file'] ?>" alt="обложка"></div>
            <div class="book-modal-info">
                <p class="book-modal-title"><?= $book['title'] ?></p>
                <p class="book-modal-sub"><?= $book['author'] ?></p>
                <p class="book-modal-sub"><?= $book['genre'] ?></p>
            </div>
        </div>

        <p class="book-modal-details">ISBN: <?= $book['isbn'] ?></p>
        <p class="book-modal-details book-modal-desc"><?= $book['description'] ?></p>
    </div>
</div>
<?php } ?>

<!-- скрипты -->
<script src="script.js"></script>
</body>
</html>