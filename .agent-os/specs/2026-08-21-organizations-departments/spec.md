# Спецификация: Справочники организаций и подразделений

Не привязана к конкретному этапу roadmap.

## Решения

- Подразделение всегда принадлежит организации (FK `departments.organization_id`,
  `ON DELETE CASCADE`) — дропдаун подразделений на карточке сотрудника
  каскадно фильтруется по выбранной организации (JS, без перезагрузки).
- CRUD организаций/подразделений доступен всем аутентифицированным ролям
  (без ограничения по роли, в отличие от `users.php`).
- `employees.organization`/`department` (свободный текст) заменены на
  `organization_id`/`department_id` (FK, `ON DELETE SET NULL`). Старые
  текстовые колонки **удалены** после автомиграции — существующие
  значения превращаются в записи `organizations`/`departments` по
  совпадению текста, сотрудники связываются с ними.
- Все места, читавшие `employee['organization']`/`['department']` как
  текст (`api/employees.php`, `document_generate.php`, `reports.php`,
  `report_export.php`), не переписаны на работу с id — вместо этого их
  SQL-запросы получили `LEFT JOIN` на справочники с алиасом `AS
  organization`/`AS department`, чтобы имя поля в результате осталось
  прежним и остальной код (`js/employees.js` и т.д.) не потребовал
  правок.

## Новые файлы

`db/migrations/009_organizations_and_departments.sql`,
`includes/organizations.php` (`getOrganizations()`, `getDepartments()`),
`organizations.php`, `organization_form.php` (+ встроенное управление
подразделениями организации), `department_form.php`,
`api/organizations.php`, `api/departments.php` (обе — только `DELETE`).

## Проверено локально

Автомиграция превратила 3 текстовых организации и 3 подразделения в
записи справочников, сотрудники связались верно (кроме одного без
организации — остался `NULL`, как и было). Создание организации/
подразделения, каскадное удаление (организация → подразделения удаляются,
у сотрудников id обнуляется), генерация .docx с `${organization}`/
`${department}` через join — всё отработало на PHP 8.4 + MariaDB 12.3.2.
