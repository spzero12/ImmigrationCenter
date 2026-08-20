# Задачи: Дизайн-система портала

## Подготовка

- [x] Спецификация ([spec.md](spec.md)).
- [x] Дизайн-токены `css/tokens.css` (палитра).

## Реализация

- [x] Подключён Tabler 1.4.0 (CSS, JS) и `@tabler/icons-webfont` 3.46.0 через
      CDN (jsDelivr, версии проверены на доступность) в `includes/header.php`
      / `includes/footer.php`, следом — `css/tokens.css` (переопределяет
      `--tblr-primary`, `--tblr-body-bg`, `--tblr-font-size-base`).
- [x] `includes/header.php` — layout Tabler: `navbar-vertical` сайдбар
      («Сотрудники», «Документы», пользователь, выход), сворачивается в
      бургер-меню на мобильных/планшетах (`navbar-expand-lg`).
- [x] `login.php` — карточка входа (`container-tight` + `card`).
- [x] `employees.php` — `card` с таблицей `table-sm`, поиск как
      `input-icon`, кнопка «Добавить» как `btn btn-primary`.
- [x] `employee_form.php` — форма в `card`, поля через Tabler `form-*`.
- [x] `documents.php` — обёрнута в `card`/`table-sm`; примеры классов
      бейджей статусов (`bg-danger-lt`/`bg-warning-lt`/`bg-success-lt`/
      `bg-secondary-lt`) оставлены комментарием — сам рендеринг статусов
      всё ещё TODO вместе с `api/documents.php` (см. spec фичи
      migration-documents).
- [x] `document_form.php` — форма аналогично `employee_form.php`.
- [x] `js/employees.js`/`js/documents.js` не менялись — используемые ими
      селекторы (`#employees-table`, `#documents-table`,
      `#employee-search`) и классы кнопок (`btn btn-danger btn-sm`)
      сохранены в новой разметке, это стандартные классы Bootstrap/Tabler.
- [x] Старые конфликтующие правила (`.btn`, `.table`, `.form`,
      `.site-header` и др.) убраны из `css/style.css`, чтобы не
      переопределять стили Tabler, подключённые раньше по каскаду.
- [x] `deploy/` пересобран под новую вёрстку.

## Проверка (требует ручной проверки — PHP не установлен в этой среде)

- [ ] Визуально открыть логин, список/форму сотрудников, список/форму
      документов в браузере и убедиться, что вся функциональность жива
      (поиск, удаление сотрудника, сохранение карточки).
- [ ] Проверить адаптивность: сворачивание сайдбара на мобильном/планшете.
- [ ] Плотность: на экране 1366×768 в таблице сотрудников заметно больше
      строк, чем в старой вёрстке.
