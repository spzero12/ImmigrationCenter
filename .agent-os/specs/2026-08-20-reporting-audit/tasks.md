# Задачи: Отчётность и аудит (Этап 4)

## Подготовка

- [x] Спецификация ([spec.md](spec.md)).

## Реализация

- [x] `db/migrations/005_audit_log.sql` — таблица `audit_log`.
- [x] `includes/audit.php` — `logAudit(action, entityType, entityId, label)`.
- [x] Вызовы `logAudit()`: `employee_form.php` (create/update),
      `api/employees.php` (delete, до самого удаления),
      `document_form.php` (create/update), `api/documents.php` (delete, до
      удаления, с JOIN на `employees` за именем).
- [x] `audit_log.php` — таблица последних 200 записей, фильтр по типу
      сущности и действию, роль `admin`.
- [x] `includes/documents.php`: `documentStatus()` получил необязательный
      `$referenceDate` вторым параметром (по умолчанию сегодня) —
      существующие вызовы без него не тронуты.
- [x] `reports.php` — три блока: сводка по сотрудникам, статусы на выбранную
      дату, история добавлений за период. Доступ — все роли.
- [x] `composer require phpoffice/phpspreadsheet:^1.29` — установлена
      версия 1.30.6.
- [x] `report_export.php` — экспорт сводки по сотрудникам в `.xlsx`
      (временный файл, удаляется сразу после отдачи).
- [x] Пункты навигации «Отчёты» (все роли) и «Аудит» (только `admin`) в
      `includes/header.php`.
- [x] Обновить `deploy/` (включая новый `vendor/` с PhpSpreadsheet).

## Проверка

Выполнено локально (PHP 8.4 + MariaDB 11.8.8) сквозными HTTP-запросами:

- [x] Изменение сотрудника → запись `update`/`employee` в `audit_log` с
      верным `entity_label` ("Петров Иван Иванович").
- [x] Создание документа → запись `create`/`employee_document` с меткой
      "Регистрация по месту пребывания REG-555 — Петров Иван Иванович".
- [x] Удаление того же документа → запись `delete` с той же меткой,
      несмотря на то, что запись документа уже удалена (снимок `entity_label`
      берётся до `DELETE`, а не после).
- [x] Роль `hr` получает 403 на `audit_log.php`; `admin` видит записи.
- [x] `reports.php` доступен роли `hr` (не только `admin`) — сводка,
      счётчики статусов на дату отображаются.
- [x] `report_export.php` отдаёт валидный `.xlsx` (200,
      `Content-Type: .../spreadsheetml.sheet`); содержимое перепроверено
      обратным чтением через `PhpSpreadsheet\IOFactory::load()` — строки и
      значения совпадают с тем, что в БД.

Не проверялось: работа функции на реальных объёмах данных хостинга (там
пока пусто) — логика идентична тому, что уже проверено на тестовых данных.

После проверки обновить чек-бокс Этапа 4 в [roadmap.md](../../product/roadmap.md).
