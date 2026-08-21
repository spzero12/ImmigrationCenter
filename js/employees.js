$(function () {
    const $tableBody = $('#employees-table tbody');
    const $search = $('#employee-search');

    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

    function avatarHtml(employee) {
        if (employee.photo_stored_name) {
            return '<span class="avatar avatar-sm rounded" style="background-image: url(employee_photo.php?employee_id=' + encodeURIComponent(employee.id) + ')"></span>';
        }

        const initials = ((employee.last_name || '').charAt(0) + (employee.first_name || '').charAt(0)).toUpperCase();

        return '<span class="avatar avatar-sm rounded">' + escapeHtml(initials) + '</span>';
    }

    function renderRows(employees) {
        $tableBody.empty();

        if (employees.length === 0) {
            $tableBody.append('<tr><td colspan="7">Сотрудники не найдены</td></tr>');
            return;
        }

        let lastOrganization = null;

        employees.forEach(function (employee) {
            const organization = employee.organization || '';

            if (organization !== lastOrganization) {
                lastOrganization = organization;
                $tableBody.append(
                    '<tr class="table-group-header"><td colspan="7"><strong>' +
                    (organization ? escapeHtml(organization) : 'Без организации') +
                    '</strong></td></tr>'
                );
            }

            const deleteBtn = CAN_DELETE
                ? '<button type="button" class="btn btn-danger btn-sm" data-delete="' + employee.id + '">Удалить</button>'
                : '';

            const $row = $('<tr>').html(
                '<td>' + avatarHtml(employee) + '</td>' +
                '<td>' + escapeHtml(employee.full_name) + '</td>' +
                '<td>' + escapeHtml(employee.citizenship) + '</td>' +
                '<td>' + escapeHtml(employee.position) + '</td>' +
                '<td>' + escapeHtml(employee.department) + '</td>' +
                '<td>' + escapeHtml(employee.phone) + '</td>' +
                '<td class="row-actions">' +
                '<a href="employee_form.php?id=' + encodeURIComponent(employee.id) + '">Изменить</a> ' +
                deleteBtn +
                '</td>'
            );

            $tableBody.append($row);
        });
    }

    function loadEmployees(query) {
        $.getJSON('api/employees.php', { q: query || '' }, renderRows);
    }

    $search.on('input', function () {
        loadEmployees($(this).val());
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
            loadEmployees($search.val());
        });
    });

    loadEmployees('');
});
