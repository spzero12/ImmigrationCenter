<?php

// Блок "Процессы оформления" на карточке сотрудника (подключается из
// employee_form.php в режиме редактирования). Ожидает $employee.
// Каждый документ открывается на своей странице (process_document.php).

$processType = 'invitation';
$processDefinition = PROCESS_TYPES[$processType];
?>
<div class="card mt-3" id="process">
    <div class="card-header">
        <h3 class="card-title">Процесс: <?php echo e($processDefinition['label']); ?></h3>
    </div>
    <div class="card-body">
        <?php if (!$employee['region_id']): ?>
            <div class="alert alert-warning">У сотрудника не указан регион — адресат документов будет пустым.</div>
        <?php endif; ?>
        <?php if (!$employee['organization_id']): ?>
            <div class="alert alert-warning">У сотрудника не указана организация — реквизиты и бланк подставить не из чего.</div>
        <?php endif; ?>

        <p class="text-secondary">Документ открывается в форме: данные подтягиваются из карточки сотрудника и организации, красные поля образца можно исправить, затем «Сформировать .docx».</p>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($processDefinition['documents'] as $docType => $docDefinition): ?>
                <?php if ($docDefinition['available']): ?>
                    <a class="btn btn-outline-primary" href="process_document.php?employee_id=<?php echo (int) $employee['id']; ?>&amp;document=<?php echo e($docType); ?>">
                        <i class="ti ti-file-text"></i> <?php echo e($docDefinition['label']); ?>
                    </a>
                <?php else: ?>
                    <span class="btn btn-outline-secondary disabled" title="Образец ещё не получен"><?php echo e($docDefinition['label']); ?> — скоро</span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="form-hint mt-2">Опись формируется сразу для нескольких сотрудников — из <a href="employees.php">списка сотрудников</a>.</div>
    </div>
</div>
