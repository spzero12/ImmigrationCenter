$(function () {
    const $tableBody = $('#employees-table tbody');
    const $search = $('#employee-search');
    const $organization = $('#organization-filter');
    const $workSite = $('#work-site-filter');
    const $columnFilters = $('.column-filter');
    const $inventoryButton = $('#inventory-button');
    const COLUMN_COUNT = 13;

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
            return '<td class="text-nowrap" data-label="' + DOC_LABELS[type] + ' до"><span class="indicator indicator-none"></span></td>';
        }

        const days = doc.indicator.days;
        const title = DOC_LABELS[type] + ' до ' + formatDate(doc.expire_date) + ': ' + (days < 0 ? 'просрочено на ' + (-days) + ' дн.' : 'осталось ' + days + ' дн.');
        const flag = doc.indicator.key === 'red' || doc.indicator.key === 'yellow'
            ? '<span class="doc-flag doc-flag-' + doc.indicator.key + '">' + DOC_LABELS[type] + '</span>'
            : '';

        return '<td class="text-nowrap" data-label="' + DOC_LABELS[type] + ' до" title="' + title + '">' + flag +
            '<span class="indicator indicator-' + doc.indicator.key + '"></span>' +
            '<span class="doc-date"> ' + escapeHtml(formatDate(doc.expire_date)) + '</span></td>';
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

        return '<td class="documents-cell" data-label="Документы">' +
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

    let allEmployees = [];

    function norm(text) {
        return String(text || '').toLowerCase();
    }

    // Текст каждого столбца таблицы (ключи совпадают с номерами th) — по нему
    // работают фильтры под заголовками.
    function columnTexts(employee) {
        const docs = employee.docs || {};
        const texts = {
            1: employee.full_name + ' ' + (employee.citizenship || ''),
            2: docs.passport ? docs.passport.number : '',
            3: employee.irs_number || '',
        };

        return texts;
    }

    function matchesFilters(employee) {
        const org = $organization.val();

        const site = $workSite.val();

        if (org !== '' && (employee.organization || '') !== (org === '__none__' ? '' : org)) {
            return false;
        }

        if (site !== '' && (employee.work_site || '') !== (site === '__none__' ? '' : site)) {
            return false;
        }

        const query = norm($search.val()).trim();

        if (query !== '') {
            const docs = employee.docs || {};
            const haystack = norm([
                employee.full_name, employee.citizenship, employee.position, employee.department, employee.irs_number, employee.work_site,
                docs.passport ? docs.passport.number : '',
            ].join(' '));

            if (haystack.indexOf(query) === -1) {
                return false;
            }
        }

        const texts = columnTexts(employee);
        let ok = true;

        $columnFilters.each(function () {
            const value = norm($(this).val()).trim();

            if (value !== '' && norm(texts[$(this).data('col')]).indexOf(value) === -1) {
                ok = false;
                return false;
            }
        });

        return ok;
    }

    function renderRows() {
        const employees = allEmployees.filter(matchesFilters);

        $tableBody.empty();

        if (employees.length === 0) {
            $tableBody.append('<tr><td colspan="' + COLUMN_COUNT + '">Сотрудники не найдены</td></tr>');
            return;
        }

        employees.forEach(function (employee) {
            const docs = employee.docs || {};
            const passport = docs.passport;
            const deleteBtn = CAN_DELETE
                ? '<button type="button" class="btn btn-danger btn-sm ms-1" data-delete="' + employee.id + '">Удалить</button>'
                : '';

            let cells =
                '<td class="cell-select"><input type="checkbox" class="form-check-input select-employee" data-id="' + employee.id + '"' +
                (selectedIds.has(employee.id) ? ' checked' : '') + '></td>' +
                '<td class="cell-name"><div class="d-flex align-items-center gap-2">' + avatarHtml(employee) +
                '<a href="employee_form.php?id=' + encodeURIComponent(employee.id) + '">' + escapeHtml(employee.full_name) + '</a></div>' +
                '<div class="text-secondary small">' + escapeHtml(employee.citizenship) + '</div></td>' +
                '<td data-label="№ паспорта">' + escapeHtml(passport ? passport.number : '') + '</td>' +
                '<td data-label="№ ИРС">' + escapeHtml(employee.irs_number) + '</td>' +
                '<td data-label="Объект" class="cell-site">' + escapeHtml(employee.work_site) + '</td>';

            DATE_COLUMNS.forEach(function (type) {
                cells += dateCell(docs[type], type);
            });

            cells += documentsCell(employee) +
                '<td class="row-actions text-end"><a href="employee_form.php?id=' + encodeURIComponent(employee.id) + '">Изменить</a>' +
                deleteBtn + '</td>';

            $tableBody.append($('<tr>').addClass(rowAlertClass(docs)).html(cells));
        });
    }

    // Списки организаций и объектов строятся из загруженных сотрудников;
    // выбор сохраняется при перезагрузке (например, после удаления).
    function fillSelect($select, field, allLabel, noneLabel) {
        const current = $select.val();
        const names = [];
        let hasNone = false;

        allEmployees.forEach(function (employee) {
            if (!employee[field]) {
                hasNone = true;
            } else if (names.indexOf(employee[field]) === -1) {
                names.push(employee[field]);
            }
        });

        names.sort(function (a, b) { return a.localeCompare(b, 'ru'); });

        $select.empty().append($('<option>').val('').text(allLabel));
        names.forEach(function (name) {
            $select.append($('<option>').val(name).text(name));
        });

        if (hasNone) {
            $select.append($('<option>').val('__none__').text(noneLabel));
        }

        $select.val(current);

        if ($select.val() === null) {
            $select.val('');
        }
    }

    function loadEmployees() {
        $.getJSON('api/employees.php', { q: '' }, function (employees) {
            allEmployees = employees;
            fillSelect($organization, 'organization', 'Все организации', 'Без организации');
            fillSelect($workSite, 'work_site', 'Все объекты', 'Без объекта');
            renderRows();
        });
    }

    $search.on('input', renderRows);
    $organization.on('change', renderRows);
    $workSite.on('change', renderRows);
    $columnFilters.on('input', renderRows);

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
            loadEmployees();
        });
    });


    // ПК: высота блока таблицы подгоняется под окно, чтобы горизонтальная
    // полоса прокрутки была у нижнего края экрана, а не под всеми строками.
    const $wrap = $('.employees-wrap');

    function fitTableHeight() {
        if (window.innerWidth < 992) {
            $wrap.css('max-height', '');
            return;
        }

        const top = $wrap.offset().top;

        $wrap.css('max-height', Math.max(320, window.innerHeight - top - 24) + 'px');
    }

    $(window).on('resize', fitTableHeight);
    fitTableHeight();
    loadEmployees();
});
