-- ============================================================================
-- Полное пересоздание схемы БД "Миграционный портал" — для продакшена.
--
-- ВНИМАНИЕ: этот скрипт УДАЛЯЕТ существующие таблицы проекта (если они
-- есть) и создаёт их заново ПУСТЫМИ. Все данные в этих таблицах будут
-- безвозвратно потеряны. Сделайте резервную копию перед запуском, если
-- в базе уже есть реальные данные.
--
-- Использование:
--   mysql -u <user> -p <database> < db/production_rebuild.sql
--
-- Это консолидированная альтернатива последовательному применению
-- db/schema.sql + db/migrations/002..010_*.sql на пустой базе — отражает
-- финальное состояние схемы одним файлом, без промежуточных шагов
-- (например, без когда-то существовавших и затем удалённых текстовых
-- колонок employees.organization/department). Список миграций в
-- db/migrations/ по-прежнему нужен для баз, где схема уже частично
-- применена и накатывается по номерам, а не пересоздаётся с нуля.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS employee_documents;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS organizations;
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- users — учётные записи системы (сотрудники портала, не мигранты).
-- ----------------------------------------------------------------------------
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    role ENUM('admin', 'hr', 'lawyer', 'manager') NOT NULL DEFAULT 'hr',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- organizations / departments — справочники юрлиц-работодателей и их
-- подразделений (подразделение всегда принадлежит организации).
-- ----------------------------------------------------------------------------
CREATE TABLE organizations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_organizations_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_departments_org_name (organization_id, name),
    CONSTRAINT fk_departments_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- employees — единая карточка иностранного сотрудника (организационные и
-- личные данные; ФИО раздельно, паспортные данные и фото — не здесь, см.
-- employee_documents/фото ниже).
-- ----------------------------------------------------------------------------
CREATE TABLE employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    last_name VARCHAR(100) NOT NULL DEFAULT '',
    first_name VARCHAR(100) NOT NULL DEFAULT '',
    middle_name VARCHAR(100) NULL,
    citizenship VARCHAR(100) NULL,
    birth_date DATE NULL,
    position VARCHAR(150) NULL,
    department_id INT UNSIGNED NULL,
    organization_id INT UNSIGNED NULL,
    phone VARCHAR(50) NULL,
    email VARCHAR(150) NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    photo_stored_name VARCHAR(255) NULL,
    photo_original_name VARCHAR(255) NULL,
    photo_mime_type VARCHAR(100) NULL,
    photo_size INT UNSIGNED NULL,
    CONSTRAINT fk_employees_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_employees_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    CONSTRAINT fk_employees_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- employee_documents — разрешительные документы (паспорт/виза/патент/
-- разрешение на работу/регистрация). Паспорт — "единая карточка": прочие
-- типы ссылаются на конкретную запись паспорта через passport_document_id
-- (не пересчитывается заново при выдаче нового паспорта). field_overrides
-- и extra_fields — JSON, без отдельных колонок под каждое поле.
-- ----------------------------------------------------------------------------
CREATE TABLE employee_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    passport_document_id INT UNSIGNED NULL,
    type ENUM('passport', 'visa', 'work_patent', 'work_permit', 'registration') NOT NULL,
    number VARCHAR(50) NULL,
    series VARCHAR(20) NULL,
    issue_date DATE NULL,
    expire_date DATE NULL,
    issued_by VARCHAR(255) NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    scan_stored_name VARCHAR(255) NULL,
    scan_original_name VARCHAR(255) NULL,
    scan_mime_type VARCHAR(100) NULL,
    scan_size INT UNSIGNED NULL,
    field_overrides JSON NULL,
    extra_fields JSON NULL,
    CONSTRAINT fk_employee_documents_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_employee_documents_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_employee_documents_expire_date ON employee_documents (expire_date);

-- Self-referencing FK отдельным ALTER (не внутри CREATE TABLE) — так
-- надёжнее переносится между версиями MySQL/MariaDB, некоторые версии
-- капризничают с самоссылающимися FK, объявленными сразу в CREATE TABLE.
ALTER TABLE employee_documents
    ADD CONSTRAINT fk_employee_documents_passport FOREIGN KEY (passport_document_id) REFERENCES employee_documents(id) ON DELETE SET NULL;

-- ----------------------------------------------------------------------------
-- audit_log — кто/когда/что сделал с сотрудниками, документами,
-- пользователями. Без диффа старых/новых значений полей (сознательно
-- упрощено), entity_label — человекочитаемый снимок на момент действия,
-- остаётся понятным даже после удаления самой сущности.
-- ----------------------------------------------------------------------------
CREATE TABLE audit_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action ENUM('create', 'update', 'delete') NOT NULL,
    entity_type ENUM('employee', 'employee_document', 'user') NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    entity_label VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_audit_log_created_at ON audit_log (created_at);

-- ----------------------------------------------------------------------------
-- Первый администратор для входа в систему. Захардкожен здесь (а не через
-- `php db/seed.php`), т.к. на боевом хостинге нет SSH/CLI-доступа — этот
-- скрипт должен полностью поднимать рабочую систему одним импортом через
-- phpMyAdmin. Хеш ниже — bcrypt для пароля "admin123" (password_hash с
-- PASSWORD_DEFAULT). Смените пароль после первого входа.
-- ----------------------------------------------------------------------------
INSERT INTO users (username, password_hash, full_name, role)
VALUES ('admin', '$2y$12$hLvhyTdREddUdR.u41grte2deNaIx6te4xXjidjRPS0gRKbpTWmTC', 'Администратор', 'admin');

-- ============================================================================
-- Готово: admin / admin123 — смените пароль после первого входа.
--
-- (Опционально, НЕ для реальной проды с настоящими сотрудниками)
-- наполнить вымышленными данными: php db/demo_seed.php — требует CLI,
-- на этом хостинге недоступно.
-- ============================================================================
