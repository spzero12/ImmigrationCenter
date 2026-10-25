$(function () {
    $('[data-delete-site]').on('click', function () {
        if (!confirm('Удалить объект? У сотрудников объект станет не указан.')) {
            return;
        }

        $.ajax({ url: 'api/work_sites.php', method: 'DELETE', data: { id: $(this).data('delete-site') } })
            .done(function () { location.reload(); });
    });
});
