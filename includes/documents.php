<?php

// Фича "Миграционный учёт": .agent-os/specs/2026-08-20-migration-documents/spec.md

const DOCUMENT_EXPIRY_WARNING_DAYS = 30;

const DOCUMENT_TYPE_LABELS = [
    'passport' => 'Паспорт',
    'visa' => 'Виза',
    'work_patent' => 'Патент на работу',
    'work_permit' => 'Разрешение на работу',
    'registration' => 'Регистрация по месту пребывания',
];

function documentStatus(?string $expireDate): array
{
    if (!$expireDate) {
        return ['key' => 'none', 'label' => 'Без срока', 'badgeClass' => 'bg-secondary-lt'];
    }

    $daysLeft = (int) (new DateTimeImmutable('today'))
        ->diff(new DateTimeImmutable($expireDate))
        ->format('%r%a');

    if ($daysLeft < 0) {
        return ['key' => 'expired', 'label' => 'Просрочен', 'badgeClass' => 'bg-danger-lt'];
    }

    if ($daysLeft <= DOCUMENT_EXPIRY_WARNING_DAYS) {
        return ['key' => 'expiring_soon', 'label' => 'Скоро истекает', 'badgeClass' => 'bg-warning-lt'];
    }

    return ['key' => 'valid', 'label' => 'В порядке', 'badgeClass' => 'bg-success-lt'];
}
