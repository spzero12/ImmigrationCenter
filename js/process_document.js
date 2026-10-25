// Страница документа процесса: «лист Word» в iframe (includes/document_sheet.php).
// Размер фрейма по высоте листа, перенос значений полей в форму при отправке,
// выбор подписанта.
$(function () {
    const frame = document.getElementById('sheet');
    const form = document.getElementById('document-form');

    function resize() {
        const doc = frame.contentDocument;

        if (doc && doc.documentElement) {
            frame.style.height = doc.documentElement.scrollHeight + 'px';
        }
    }

    window.sheetResized = resize;
    frame.addEventListener('load', function () {
        resize();
        setTimeout(resize, 300);
    });
    resize();
    setTimeout(resize, 300);

    form.addEventListener('submit', function (event) {
        const sheet = frame.contentWindow;

        if (sheet.hasInvalidFields && sheet.hasInvalidFields()) {
            event.preventDefault();
            alert('Есть поля с неверной датой (подсвечены красной рамкой). Формат: ДД.ММ.ГГГГ или ДД/ММ/ГГГГ.');
            return;
        }

        const values = sheet.getSheetValues();

        Object.keys(values).forEach(function (key) {
            const input = form.elements['f[' + key + ']'];

            if (input) {
                input.value = values[key];
            }
        });
    });

    $('#signer-select').on('change', function () {
        const $option = $(this).find('option:selected');

        if (!$option.val()) {
            return;
        }

        const sheet = frame.contentWindow;
        sheet.setSheetField('signer_position', $option.data('position'));
        sheet.setSheetField('signer_name', $option.data('name'));
        sheet.setSheetField('signer_short', $option.data('short'));
    });
});
