-- Подписанты организации; выбираются при оформлении документов.
CREATE TABLE IF NOT EXISTS organization_signers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    position VARCHAR(150) NOT NULL COMMENT 'Например: Генеральный директор',
    full_name VARCHAR(150) NOT NULL COMMENT 'Например: Бочков Вадим Владимирович',
    short_name VARCHAR(100) NOT NULL COMMENT 'Например: Бочков В.В.',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
