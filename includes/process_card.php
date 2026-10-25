<?php

// Блоки "Процессы оформления" на карточке сотрудника (подключается из
// employee_form.php в режиме редактирования): по карточке на процесс
// («Приглашение», «Виза»). Ожидает $employee.
// Каждый документ открывается на своей странице (process_document.php).
?>
<?php foreach (PROCESS_TYPES as $processType => $processDefinition): ?>
<div class="card mt-3" id="<?php echo $processType === 'invitation' ? 'process' : 'process-' . e($processType); ?>">
    <div class="card-header">
        <h3 class="card-title">Процесс: <?php echo e($processDefinition['label']); ?></h3>
    </div>
    <div class="card-body">
        <?php if ($processType === 'invitation'): ?>
            <?php if (!$employee['region_id']): ?>
                <div class="alert alert-warning">У сотрудника не указан регион — адресат документов будет пустым.</div>
            <?php endif; ?>
            <?php if (!$employee['organization_id']): ?>
                <div class="alert alert-warning">У сотрудника не указана организация — реквизиты и бланк подставить не из чего.</div>
            <?php endif; ?>

            <p class="text-secondary">Документ открывается в форме: данные подтягиваются из карточки сотрудника и организации, красные поля образца можно исправить, затем «Сформировать .docx».</p>
        <?php endif; ?>
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
            <?php if (isset(INVENTORY_TYPES[$processType])): ?>
                <a class="btn btn-outline-primary" href="inventory.php?type=<?php echo e($processType); ?>&amp;ids=<?php echo (int) $employee['id']; ?>">
                    <i class="ti ti-list-details"></i> Опись
                </a>
            <?php endif; ?>
        </div>
        <?php if ($processType === 'visa'): ?>
            <div class="form-hint mt-2">Ходатайство на визу и ходатайство на приглашение — разные документы, как и описи. Опись для нескольких сотрудников сразу — из <a href="employees.php">списка сотрудников</a> (кнопка «Сформировать опись»).</div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
