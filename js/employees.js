$(function () {
    const $tableBody = $('#employees-table tbody');
    const $search = $('#employee-search');

    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

    function renderRows(employees) {
        $tableBody.empty();

        if (employees.length === 0) {
            $tableBody.append('<tr><td colspan="6">Сотрудники не найдены</td></tr>');
            return;
        }

        employees.forEach(function (employee) {
            const deleteBtn = CAN_DELETE
                ? '<button type="button" class="btn btn-danger btn-sm" data-delete="' + employee.id + '">Удалить</button>'
                : '';

            const $row = $('<tr>').html(
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
