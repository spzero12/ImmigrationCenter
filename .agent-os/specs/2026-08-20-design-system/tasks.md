# Задачи: Дизайн-система портала

## Подготовка (этот шаг)

- [x] Спецификация ([spec.md](spec.md)).
- [x] Дизайн-токены `css/tokens.css` (палитра, пока не подключены).

## Реализация

- [ ] Подключить Tabler (CSS, JS, иконки) через CDN в `includes/header.php` /
      `includes/footer.php`, следом — `css/tokens.css` (чтобы переопределить
      `--tblr-*`).
- [ ] `includes/header.php` — layout Tabler: navbar + сайдбар («Сотрудники»,
      «Документы»), сворачиваемый на мобильных.
- [ ] `login.php` — карточка входа в стиле Tabler (`auth`-обёртка).
- [ ] `employees.php` — таблица `table-sm`, поиск как `input-icon`, кнопка
      «Добавить» как Tabler `btn btn-primary`.
- [ ] `employee_form.php` — форма в `card`, поля через Tabler `form-*`
      классы.
- [ ] `documents.php` — таблица со статусами как Tabler `badge` в цветах
      success/warning/danger/info из `css/tokens.css`.
- [ ] `document_form.php` — форма аналогично `employee_form.php`.
- [ ] Проверить, что `js/employees.js` и `js/documents.js` продолжают
      работать (селекторы `#employees-table`, `#documents-table`,
      `#employee-search` и т.д. не переименовывать без правки JS).
- [ ] Проверить адаптивность: мобильный, планшет, десктоп.
- [ ] Обновить `deploy/` после переверстки (см. workflow выкладки).

## Проверка

- [ ] Все существующие функции (логин, CRUD сотрудников, список документов)
      визуально в новом стиле, но без потери функциональности.
- [ ] Плотность: на экране 1366×768 в таблице сотрудников помещается
      заметно больше строк, чем в текущей вёрстке.
