# Миграционный портал

Корпоративный портал для учёта иностранных сотрудников, миграционных карт и
сопутствующих документов. Продуктовая спецификация (контекст для ИИ-агента):
[.agent-os/product/mission.md](.agent-os/product/mission.md),
[.agent-os/product/tech-stack.md](.agent-os/product/tech-stack.md),
[.agent-os/product/roadmap.md](.agent-os/product/roadmap.md).

## Запуск локально

1. Создайте базу данных MySQL/MariaDB `migration_portal` и при необходимости
   поправьте доступы в [config/config.php](config/config.php).
2. Примените схему:
   ```bash
   mysql -u root migration_portal < db/schema.sql
   ```
3. Создайте первого администратора:
   ```bash
   php db/seed.php
   ```
   Учётные данные по умолчанию: `admin` / `admin123`.
4. Запустите встроенный сервер PHP:
   ```bash
   php -S localhost:8000
   ```
5. Откройте `http://localhost:8000` и войдите под созданным администратором.

## Роли пользователей

`admin`, `hr`, `lawyer`, `manager` — хранятся в таблице `users.role`. Удаление
карточки сотрудника доступно ролям `admin` и `hr`.
