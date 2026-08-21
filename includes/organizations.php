<?php

// Фича "Организации и подразделения": справочники для карточки сотрудника.

function getOrganizations(PDO $db): array
{
    return $db->query('SELECT * FROM organizations ORDER BY name')->fetchAll();
}

function getDepartments(PDO $db, ?int $organizationId = null): array
{
    if ($organizationId) {
        $stmt = $db->prepare('SELECT * FROM departments WHERE organization_id = ? ORDER BY name');
        $stmt->execute([$organizationId]);

        return $stmt->fetchAll();
    }

    return $db->query('SELECT * FROM departments ORDER BY organization_id, name')->fetchAll();
}
