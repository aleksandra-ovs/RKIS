// короткая запись для getElementById
const $ = id => document.getElementById(id);

// открытие и закрытие модальных окон
function openModal(id) {
    const modal = $(id);
    if (modal) {
        modal.style.display = 'flex';
    } else {
        console.warn('Окно с id "' + id + '" не найдено на странице');
    }
}

function closeModal(modal) {
    if (modal) {
        modal.style.display = 'none';
    }
}

// переключение вкладок
function showSection(button) {
    document.querySelectorAll('.page-menu button').forEach(b => {
        b.classList.toggle('active', b === button);
    });
    document.querySelectorAll('.section').forEach(section => {
        section.style.display = section.id === button.dataset.section ? 'block' : 'none';
    });
}

// постраничный вывод списков
function paginate(selector, perPage, pagesId) {
    const items = document.querySelectorAll(selector);
    const pages = $(pagesId);
    if (!pages) return;

    const total = Math.ceil(items.length / perPage);

    function show(page) {
        items.forEach((el, i) => {
            el.style.display = Math.ceil((i + 1) / perPage) === page ? '' : 'none';
        });

        pages.innerHTML = '';
        for (let p = 1; p <= total; p++) {
            const b = document.createElement('button');
            b.textContent = p;
            b.disabled = p === page;
            b.onclick = () => show(p);
            pages.append(b);
        }
    }

    show(1);
}

// окно выдачи книги: выбор книги
function selectIssueBook(card) {
    document.querySelectorAll('#issueModal .modal-mini-card').forEach(c => {
        c.classList.toggle('selected', c === card);
    });
    $('issue-book-id').value = card ? card.dataset.book : '';
    $('issue-error').style.display = 'none';
}

// окна продления и приёма: заполнение данных
function fillLoan(prefix, data) {
    $(prefix + '-request-id').value = data.request;
    ['reader', 'title', 'author', 'genre', 'due'].forEach(field => {
        $(prefix + '-' + field).textContent = data[field];
    });

    const photo = $(prefix + '-photo');
    photo.style.backgroundImage = 'url("' + data.photo + '")';
    photo.style.backgroundSize = 'cover';
}

// клики: открытие и закрытие окон
document.addEventListener('click', function (event) {
    const target = event.target;

    const opener = target.closest('[data-modal]');
    if (opener) {
        openModal(opener.dataset.modal);
        return;
    }

    if (target.closest('.photo')) {
        openModal('bookModal');
        return;
    }

    if (target.closest('.book-adm')) {
        openModal('adminBookModal');
        return;
    }

    if (target.closest('.modal-close, .close-modal')) {
        closeModal(target.closest('.modal'));
        return;
    }

    if (target.classList.contains('modal')) {
        closeModal(target);
    }
});

// клики: вкладки, выдача, продление, приём
document.addEventListener('click', function (event) {
    const target = event.target;

    const tab = target.closest('.page-menu button[data-section]');
    if (tab) {
        showSection(tab);
    }

    const issueBtn = target.closest('.issue-btn');
    if (issueBtn) {
        $('issue-user-id').value = issueBtn.dataset.user;
        $('issue-user-name').textContent = issueBtn.dataset.name;
        selectIssueBook(null);
    }

    const card = target.closest('#issueModal .modal-mini-card');
    if (card) {
        selectIssueBook(card);
    }

    const loanBtn = target.closest('[data-request]');
    if (loanBtn) {
        fillLoan(loanBtn.dataset.modal.replace('Modal', ''), loanBtn.dataset);
    }
});

// проверка формы выдачи
document.addEventListener('submit', function (event) {
    if (event.target.matches('#issueModal form') && !$('issue-book-id').value) {
        event.preventDefault();
        $('issue-error').style.display = 'block';
    }
});

// поиск книги в окне выдачи
document.addEventListener('input', function (event) {
    if (event.target.id !== 'issue-search') return;

    const q = event.target.value.toLowerCase();
    document.querySelectorAll('#issueModal .modal-mini-card').forEach(card => {
        card.style.display = card.dataset.title.includes(q) ? '' : 'none';
    });
});

// закрытие окон по Escape
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(closeModal);
    }
});

// запуск при загрузке страницы
paginate('.usersss .user-card', 2, 'readers-pages');
paginate('.books-adm .book-adm', 6, 'books-pages');

// открытие вкладки книг по ?tab=books
const booksTab = document.querySelector('[data-section="books-section"]');
if (booksTab && new URLSearchParams(location.search).get('tab') === 'books') {
    showSection(booksTab);
}