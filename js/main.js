$(function () {
    // Раскрывающееся боковое меню (на ПК); состояние запоминается.
    $('#sidebar-toggle').on('click', function () {
        const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');

        try {
            localStorage.setItem('sidebarOpen', collapsed ? '0' : '1');
        } catch (e) {}

        // Таблицы подгоняют высоту/ширину под размер окна.
        $(window).trigger('resize');
    });
});
