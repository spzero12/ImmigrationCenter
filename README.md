# Миграционный портал

Корпоративный портал для учёта иностранных сотрудников, миграционных карт и
сопутствующих документов. Продуктовая спецификация (контекст для ИИ-агента):
[.agent-os/product/mission.md](.agent-os/product/mission.md),
[.agent-os/product/tech-stack.md](.agent-os/product/tech-stack.md),
[.agent-os/product/roadmap.md](.agent-os/product/roadmap.md).

## Запуск локально

**Важно:** [config/config.php](config/config.php) в git содержит боевые
данные подключения к БД хостинга. Для локальной разработки задайте
переменные окружения `PORTAL_DB_HOST`, `PORTAL_DB_PORT`, `PORTAL_DB_NAME`,
`PORTAL_DB_USER`, `PORTAL_DB_PASS` (они имеют приоритет) — файл конфигурации
править не нужно. Либо временно подставьте локальные значения в файл и
**не коммитьте** это изменение.

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

Документы процесса «Приглашение» (Ходатайство, Гарантийное письмо) и Опись
формируются по шаблонам `templates/processes/*.docx`, сделанным из образцов
заказчика (`templates/samples/`, пересборка — `php templates/build_templates.php`):
шаблон повторяется полностью, подставляются значения `${ключ}`
(`includes/docx_template.php`, `includes/processes.php`). Если у организации
загружен бланк (.docx с колонтитулами), его колонтитулы переносятся в
Гарантийное письмо и Опись; Ходатайство — форма МВД, выдаётся без бланка.
Страница редактирования Ходатайства и Гарантийного письма показывает «лист
Word» (`templates/processes/*.html`, `includes/document_sheet.php`) с полями
прямо в тексте. Эти HTML делаются из шаблонов .docx через Word:
`powershell -ExecutionPolicy Bypass -File templates\build_html.ps1` (после
`php templates/build_templates.php`; при изменении шаблона пересобрать оба). Подробности —
[.agent-os/specs/2026-10-04-employee-card-processes/](.agent-os/specs/2026-10-04-employee-card-processes/).

## Роли пользователей

`admin`, `hr`, `lawyer`, `manager` — хранятся в таблице `users.role`. Удаление
карточки сотрудника доступно ролям `admin` и `hr`.
