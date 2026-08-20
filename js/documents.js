$(function () {
    let allDocuments = [];

    const $tableBody = $('#documents-table tbody');
    const $search = $('#document-search');
    const $statusFilter = $('#document-status-filter');
    const $statExpired = $('#stat-expired');
    const $statExpiring = $('#stat-expiring');

    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

    function updateSummary(documents) {
        const expired = documents.filter(function (doc) { return doc.status.key === 'expired'; }).length;
        const expiringSoon = documents.filter(function (doc) { return doc.status.key === 'expiring_soon'; }).length;

        $statExpired.text(expired);
        $statExpiring.text(expiringSoon);
    }

    function renderRows(documents) {
        $tableBody.empty();

        if (documents.length === 0) {
            $tableBody.append('<tr><td colspan="6">Документы не найдены</td></tr>');
            return;
        }

        documents.forEach(function (doc) {
            const deleteBtn = CAN_DELETE
                ? '<button type="button" class="btn btn-danger btn-sm" data-delete="' + doc.id + '">Удалить</button>'
                : '';

            const $row = $('<tr>').html(
                '<td><a href="employee_form.php?id=' + encodeURIComponent(doc.employee_id) + '">' + escapeHtml(doc.employee_name) + '</a></td>' +
                '<td>' + escapeHtml(doc.type_label) + '</td>' +
                '<td>' + escapeHtml(doc.number) + '</td>' +
                '<td>' + escapeHtml(doc.expire_date || '—') + '</td>' +
                '<td><span class="badge ' + doc.status.badgeClass + '">' + escapeHtml(doc.status.label) + '</span></td>' +
                '<td class="text-end">' +
                '<a href="document_form.php?id=' + encodeURIComponent(doc.id) + '&employee_id=' + encodeURIComponent(doc.employee_id) + '">Изменить</a> ' +
                '<a href="document_generate.php?document_id=' + encodeURIComponent(doc.id) + '">Скачать .docx</a> ' +
                deleteBtn +
                '</td>'
            );

            $tableBody.append($row);
        });
    }

    function applyFilterAndRender() {
        const statusValue = $statusFilter.val();
        const filtered = statusValue
            ? allDocuments.filter(function (doc) { return doc.status.key === statusValue; })
            : allDocuments;

        renderRows(filtered);
    }

    function loadDocuments(query) {
        $.getJSON('api/documents.php', { q: query || '' }, function (documents) {
            allDocuments = documents;
            updateSummary(documents);
            applyFilterAndRender();
        });
    }

    $search.on('input', function () {
        loadDocuments($(this).val());
    });

    $statusFilter.on('change', applyFilterAndRender);

    $tableBody.on('click', '[data-delete]', function () {
        const id = $(this).data('delete');

        if (!confirm('Удалить документ?')) {
            return;
        }

        $.ajax({
            url: 'api/documents.php',
            method: 'DELETE',
            data: { id: id },
        }).done(function () {
            loadDocuments($search.val());
        });
    });

    loadDocuments('');
});
