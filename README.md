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
2. Примените схему и миграции по порядку (номер — порядок применения):
   ```bash
   mysql -u root migration_portal < db/schema.sql
   for f in db/migrations/*.sql; do mysql -u root migration_portal < "$f"; done
   ```
3. Установите зависимости (нужны для генерации .docx/.xlsx):
   ```bash
   composer install
   ```
4. Создайте первого администратора:
   ```bash
   php db/seed.php
   ```
   Учётные данные по умолчанию: `admin` / `admin123`.
5. (Опционально) наполните базу вымышленными данными для демонстрации —
   сотрудники, документы разных статусов, демо-пользователи всех ролей:
   ```bash
   php db/demo_seed.php
   ```
   Демо-пользователи: `hr_demo`/`lawyer_demo`/`manager_demo`, пароль у
   всех `Demo12345`. **Не запускайте на реальной проде с настоящими
   сотрудниками.**
6. Запустите встроенный сервер PHP:
   ```bash
   php -S localhost:8000
   ```
7. Откройте `http://localhost:8000` и войдите под созданным администратором.

`templates/` уже содержит заглушечные .docx-шаблоны для всех пяти типов
документов (не от заказчика — собственные, см.
[templates/README.md](templates/README.md)) — генерация документов
(`document_generate.php`) работает из коробки. Конвенция плейсхолдеров —
в [.agent-os/specs/2026-08-20-document-generation/](.agent-os/specs/2026-08-20-document-generation/).

## Роли пользователей

`admin`, `hr`, `lawyer`, `manager` — хранятся в таблице `users.role`. Удаление
карточки сотрудника доступно ролям `admin` и `hr`.
