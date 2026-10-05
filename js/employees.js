$(function () {
    const $tableBody = $('#employees-table tbody');
    const $search = $('#employee-search');
    const $inventoryButton = $('#inventory-button');
    const COLUMN_COUNT = 11;

    // Столбцы со сроками — по эскизу заказчика; тип документа => ключ docs.
    const DATE_COLUMNS = ['passport', 'visa', 'work_permit', 'registration', 'medical_certificate', 'dms'];

    // Названия типов — подпись у жёлтых и красных значений: в длинной таблице
    // иначе не понять, к какому столбцу относится кружок.
    const DOC_LABELS = {
        passport: 'Паспорт',
        visa: 'Виза',
        work_permit: 'РНР',
        registration: 'МУ',
        medical_certificate: 'Мед. справка',
        dms: 'ДМС',
    };

    const selectedIds = new Set();

    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

    function formatDate(isoDate) {
        if (!isoDate) {
            return '';
        }

        const parts = isoDate.split('-');

        return parts[2] + '.' + parts[1] + '.' + parts[0];
    }

    function avatarHtml(employee) {
        if (employee.photo_stored_name) {
            return '<span class="avatar avatar-sm rounded" style="background-image: url(employee_photo.php?employee_id=' + encodeURIComponent(employee.id) + ')"></span>';
        }

        const initials = ((employee.last_name || '').charAt(0) + (employee.first_name || '').charAt(0)).toUpperCase();

        return '<span class="avatar avatar-sm rounded">' + escapeHtml(initials) + '</span>';
    }

    function dateCell(doc, type) {
        if (!doc || !doc.expire_date) {
            return '<td class="text-nowrap"><span class="indicator indicator-none"></span></td>';
        }

        const days = doc.indicator.days;
        const title = DOC_LABELS[type] + ': ' + (days < 0 ? 'просрочено на ' + (-days) + ' дн.' : 'осталось ' + days + ' дн.');
        const flag = doc.indicator.key === 'red' || doc.indicator.key === 'yellow'
            ? '<span class="doc-flag doc-flag-' + doc.indicator.key + '">' + DOC_LABELS[type] + '</span>'
            : '';

        return '<td class="text-nowrap" title="' + title + '">' + flag +
            '<span class="indicator indicator-' + doc.indicator.key + '"></span> ' +
            escapeHtml(formatDate(doc.expire_date)) + '</td>';
    }

    // Строка выделяется рамкой по худшему сроку: есть красный — красная,
    // иначе есть жёлтый — оранжевая.
    function rowAlertClass(docs) {
        const keys = DATE_COLUMNS.map(function (type) {
            return docs[type] && docs[type].expire_date ? docs[type].indicator.key : null;
        });

        if (keys.indexOf('red') !== -1) {
            return 'row-alert-red';
        }

        return keys.indexOf('yellow') !== -1 ? 'row-alert-yellow' : '';
    }

    // Регион ▾ → РНР (1 год) ▾ → Приглашение ▾ → документы процесса.
    // Все уровни раскрыты сразу, любой можно свернуть. Второй уровень пока
    // единственный; остальные пункты появятся позже.
    function wordItem(label, href) {
        return '<li><a href="' + href + '"><i class="ti ti-file-word doc-icon"></i>' + label + '</a>' +
            '<span class="doc-format">word</span></li>';
    }

    function documentsCell(employee) {
        const base = 'process_document.php?employee_id=' + encodeURIComponent(employee.id) + '&document=';

        return '<td class="documents-cell">' +
            '<details class="doc-level" open><summary>' + escapeHtml(employee.region_name || 'Без региона') + '</summary>' +
            '<details class="doc-level" open><summary>РНР (1 год)</summary>' +
            '<details class="doc-level" open><summary>Приглашение</summary>' +
            '<ul class="doc-list">' +
            wordItem('Ходатайство', base + 'petition') +
            wordItem('Гарантийное письмо', base + 'guarantee_letter') +
            '<li class="doc-soon"><i class="ti ti-file-word doc-icon"></i>Доверенность<span class="doc-format">скоро</span></li>' +
            '</ul></details></details></details></td>';
    }

    function updateInventoryButton() {
        $inventoryButton.prop('disabled', selectedIds.size === 0);
        $('#inventory-count').text(selectedIds.size ? '(' + selectedIds.size + ')' : '');
    }

    function renderRows(employees) {
        $tableBody.empty();

        if (employees.length === 0) {
            $tableBody.append('<tr><td colspan="' + COLUMN_COUNT + '">Сотрудники не найдены</td></tr>');
            return;
        }

        let lastOrganization = null;

        employees.forEach(function (employee) {
            const organization = employee.organization || '';

            if (organization !== lastOrganization) {
                lastOrganization = organization;
                $tableBody.append(
                    '<tr class="table-group-header"><td colspan="' + COLUMN_COUNT + '"><strong>' +
                    (organization ? escapeHtml(organization) : 'Без организации') +
                    '</strong></td></tr>'
                );
            }

            const docs = employee.docs || {};
            const passport = docs.passport;
            const deleteBtn = CAN_DELETE
                ? '<button type="button" class="btn btn-danger btn-sm ms-1" data-delete="' + employee.id + '">Удалить</button>'
                : '';

            let cells =
                '<td><input type="checkbox" class="form-check-input select-employee" data-id="' + employee.id + '"' +
                (selectedIds.has(employee.id) ? ' checked' : '') + '></td>' +
                '<td><div class="d-flex align-items-center gap-2">' + avatarHtml(employee) +
                '<a href="employee_form.php?id=' + encodeURIComponent(employee.id) + '">' + escapeHtml(employee.full_name) + '</a></div>' +
                '<div class="text-secondary small">' + escapeHtml(employee.citizenship) + '</div></td>' +
                '<td>' + escapeHtml(passport ? passport.number : '') + '</td>';

            DATE_COLUMNS.forEach(function (type) {
                cells += dateCell(docs[type], type);
            });

            cells += documentsCell(employee) +
                '<td class="row-actions text-end"><a href="employee_form.php?id=' + encodeURIComponent(employee.id) + '">Изменить</a>' +
                deleteBtn + '</td>';

            $tableBody.append($('<tr>').addClass(rowAlertClass(docs)).html(cells));
        });
    }

    function loadEmployees(query) {
        $.getJSON('api/employees.php', { q: query || '' }, renderRows);
    }

    $search.on('input', function () {
        loadEmployees($(this).val());
    });

    $tableBody.on('change', '.select-employee', function () {
        const id = parseInt($(this).data('id'), 10);

        if (this.checked) {
            selectedIds.add(id);
        } else {
            selectedIds.delete(id);
        }

        updateInventoryButton();
    });

    $inventoryButton.on('click', function () {
        window.location.href = 'inventory.php?ids=' + Array.from(selectedIds).join(',');
    });

    $tableBody.on('click', '[data-delete]', function () {
        const id = $(this).data('delete');

        if (!confirm('Удалить сотрудника?')) {
            return;
        }

        $.ajax({
            url: 'api/employees.php',
            method: 'DELETE',
            data: { id: id },
        }).done(function () {
            selectedIds.delete(id);
            updateInventoryButton();
            loadEmployees($search.val());
        });
    });

    loadEmployees('');
});
