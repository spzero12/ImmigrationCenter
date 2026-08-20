# Миграционный портал

Корпоративный портал для учёта иностранных сотрудников, миграционных карт и
сопутствующих документов. Продуктовая спецификация (контекст для ИИ-агента):
[.agent-os/product/mission.md](.agent-os/product/mission.md),
[.agent-os/product/tech-stack.md](.agent-os/product/tech-stack.md),
[.agent-os/product/roadmap.md](.agent-os/product/roadmap.md).

## Запуск локально

**Важно:** [config/config.php](config/config.php) в git содержит боевые
данные подключения к БД хостинга. Для локальной разработки временно
подставьте свои (локальные) значения и **не коммитьте** это изменение —
после сессии верните файл командой `git checkout -- config/config.php`.

1. Создайте базу данных MySQL/MariaDB `migration_portal`.
2. Примените схему и миграции по порядку:
   ```bash
   mysql -u root migration_portal < db/schema.sql
   mysql -u root migration_portal < db/migrations/002_employee_documents.sql
   mysql -u root migration_portal < db/migrations/003_employee_name_parts.sql
   mysql -u root migration_portal < db/migrations/004_document_scans_and_overrides.sql
   ```
3. Установите зависимости (нужны для генерации .docx):
   ```bash
   composer install
   ```
4. Создайте первого администратора:
   ```bash
   php db/seed.php
   ```
   Учётные данные по умолчанию: `admin` / `admin123`.
5. Запустите встроенный сервер PHP:
   ```bash
   php -S localhost:8000
   ```
6. Откройте `http://localhost:8000` и войдите под созданным администратором.

Заполнение .docx-шаблонов по спецификации из
[.agent-os/specs/2026-08-20-document-generation/](.agent-os/specs/2026-08-20-document-generation/)
дополнительно требует положить реальный шаблон в `templates/` (см.
[templates/README.md](templates/README.md)) — без него
`document_generate.php` покажет понятную ошибку вместо файла.

## Роли пользователей

`admin`, `hr`, `lawyer`, `manager` — хранятся в таблице `users.role`. Удаление
карточки сотрудника доступно ролям `admin` и `hr`.
