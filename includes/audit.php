<?php

// Фича "Отчётность и аудит": .agent-os/specs/2026-08-20-reporting-audit/spec.md

function logAudit(string $action, string $entityType, int $entityId, string $label): void
{
    $stmt = getDb()->prepare(
        'INSERT INTO audit_log (user_id, action, entity_type, entity_id, entity_label) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        currentUser()['id'] ?? null,
        $action,
        $entityType,
        $entityId,
        $label,
    ]);
}
