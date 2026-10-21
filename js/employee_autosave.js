// Автосохранение карточки сотрудника и отмена изменений по Ctrl+Z.
//
// Автосохранение: при уходе из изменённого поля (событие change), периодически
// (раз в 20 секунд, если есть несохранённое) и при закрытии/сворачивании вкладки.
// Сканы документов не отправляются — они сохраняются только кнопкой «Сохранить».
// Новая карточка создаётся первым успешным автосохранением (когда указано имя).
//
// Ctrl+Z / Ctrl+Shift+Z (или Ctrl+Y): отмена и возврат изменений по полям.
// Пока в поле есть несохранённый набор текста, Ctrl+Z работает как обычно
// (внутри поля); когда в нём отменять нечего — отменяется предыдущее поле.
(function () {
    const form = document.getElementById('employee-form');

    if (!form) {
        return;
    }

    const SAVE_DELAY_MS = 400;
    const INTERVAL_MS = 20000;
    const HISTORY_LIMIT = 200;

    let employeeId = parseInt(form.dataset.employeeId, 10) || 0;
    let dirty = false;
    let inFlight = null;
    let delayTimer = null;
    let submitting = false;

    // ---------- Статус ----------

    const statusEl = document.createElement('div');
    statusEl.className = 'autosave-status';
    statusEl.setAttribute('role', 'status');
    statusEl.setAttribute('aria-live', 'polite');
    document.body.appendChild(statusEl);

    let statusTimer = null;

    function setStatus(state, text, autoClearMs) {
        statusEl.className = 'autosave-status autosave-' + state + ' autosave-visible';
        statusEl.textContent = text;
        clearTimeout(statusTimer);

        if (autoClearMs) {
            statusTimer = setTimeout(function () {
                statusEl.classList.remove('autosave-visible');
            }, autoClearMs);
        }
    }

    // ---------- Поля формы ----------

    function isTracked(el) {
        return el && form.contains(el) && el.name && ['file', 'submit', 'button', 'hidden'].indexOf(el.type) === -1;
    }

    function fields() {
        return Array.prototype.filter.call(form.elements, isTracked);
    }

    function isTextLike(el) {
        return el.tagName === 'TEXTAREA' || (el.tagName === 'INPUT' && ['checkbox', 'radio'].indexOf(el.type) === -1);
    }

    function valueOf(el) {
        return el.type === 'checkbox' || el.type === 'radio' ? el.checked : el.value;
    }

    function setValue(el, value) {
        if (el.type === 'checkbox' || el.type === 'radio') {
            el.checked = value;
        } else {
            el.value = value;
        }
    }

    function labelOf(el) {
        const label = el.closest('.mb-3, .col-md-3, .col-md-4, .col-md-6') && el.closest('.mb-3, .col-md-3, .col-md-4, .col-md-6').querySelector('label');

        return label ? label.textContent.trim() : (el.getAttribute('placeholder') || el.name);
    }

    // ---------- Автосохранение ----------

    function markDirty() {
        if (submitting) {
            return;
        }

        dirty = true;

        if (!inFlight) {
            setStatus('unsaved', 'Есть несохранённые изменения…');
        }
    }

    function scheduleSave(delay) {
        clearTimeout(delayTimer);
        delayTimer = setTimeout(save, delay);
    }

    function snapshot() {
        const data = new FormData(form);

        Array.from(data.keys()).forEach(function (key) {
            if (key.indexOf('scan_') === 0) {
                data.delete(key);
            }
        });

        data.append('_autosave', '1');

        return data;
    }

    function endpoint() {
        return employeeId ? 'employee_form.php?id=' + employeeId : 'employee_form.php';
    }

    function save() {
        if (submitting || !dirty) {
            return inFlight || Promise.resolve();
        }

        if (inFlight) {
            // Изменения во время отправки: допишем сразу после неё.
            return inFlight.then(function () { return save(); });
        }

        dirty = false;
        setStatus('saving', 'Сохранение…');

        inFlight = fetch(endpoint(), {
            method: 'POST',
            body: snapshot(),
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        })
            .then(function (response) { return response.json(); })
            .then(function (result) {
                if (result.ok) {
                    if (result.created) {
                        employeeId = result.id;
                        form.dataset.employeeId = String(employeeId);
                        form.action = 'employee_form.php?id=' + employeeId;
                        history.replaceState(null, '', 'employee_form.php?id=' + employeeId);
                        setStatus('saved', 'Сотрудник создан и сохранён в ' + result.saved_at + '. Дальше изменения сохраняются автоматически.', 6000);
                    } else {
                        setStatus('saved', 'Автосохранено в ' + result.saved_at, 4000);
                    }
                } else if (result.skipped) {
                    setStatus('unsaved', 'Укажите имя — тогда карточка начнёт сохраняться автоматически');
                } else {
                    setStatus('error', 'Не сохранено: ' + (result.errors || ['ошибка']).join('; '));
                }
            })
            .catch(function () {
                dirty = true;
                setStatus('error', 'Не удалось сохранить (нет связи или нужно войти заново). Повторю автоматически.');
            })
            .then(function () {
                inFlight = null;

                if (dirty && !submitting) {
                    scheduleSave(SAVE_DELAY_MS);
                }
            });

        return inFlight;
    }

    // Уход из поля / выбор в списке.
    form.addEventListener('input', function (event) {
        if (isTracked(event.target)) {
            markDirty();
        }
    });

    form.addEventListener('change', function (event) {
        if (isTracked(event.target)) {
            markDirty();
            scheduleSave(SAVE_DELAY_MS);
        }
    });

    // Периодически — на случай, если пользователь долго печатает в одном поле.
    setInterval(function () {
        if (dirty && !inFlight) {
            save();
        }
    }, INTERVAL_MS);

    // Закрытие или сворачивание вкладки: последняя попытка сохранить.
    function flushOnLeave() {
        if (dirty && !inFlight && !submitting && navigator.sendBeacon) {
            dirty = !navigator.sendBeacon(endpoint(), snapshot());
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            flushOnLeave();
        }
    });
    window.addEventListener('pagehide', flushOnLeave);

    // Кнопка «Сохранить»: дожидаемся идущего автосохранения (иначе новая
    // карточка могла бы создаться дважды), затем отправляем форму как обычно.
    form.addEventListener('submit', function (event) {
        if (submitting) {
            return;
        }

        if (inFlight) {
            event.preventDefault();
            const pending = inFlight;

            pending.then(function () {
                submitting = true;
                form.submit();
            });

            return;
        }

        submitting = true;
        dirty = false;
        clearTimeout(delayTimer);
    });

    // ---------- Отмена / возврат (Ctrl+Z) ----------

    const lastValue = new WeakMap();    // последнее зафиксированное значение поля
    const sessionStart = new WeakMap(); // значение в момент входа в поле
    const undoStack = [];
    const redoStack = [];
    let applying = false;

    fields().forEach(function (el) {
        lastValue.set(el, valueOf(el));
    });

    form.addEventListener('focusin', function (event) {
        if (isTracked(event.target)) {
            sessionStart.set(event.target, valueOf(event.target));
        }
    });

    form.addEventListener('change', function (event) {
        const el = event.target;

        if (!isTracked(el) || applying) {
            return;
        }

        const from = lastValue.get(el);
        const to = valueOf(el);

        if (from !== to) {
            undoStack.push({ el: el, from: from, to: to });
            redoStack.length = 0;

            if (undoStack.length > HISTORY_LIMIT) {
                undoStack.shift();
            }
        }

        lastValue.set(el, to);

        // Смена организации пересобирает список подразделений — синхронизируем.
        if (el.id === 'organization-select') {
            setTimeout(function () {
                const department = document.getElementById('department-select');

                if (department) {
                    lastValue.set(department, department.value);
                }
            }, 0);
        }
    });

    function apply(entry, value, verb) {
        applying = true;
        setValue(entry.el, value);
        lastValue.set(entry.el, value);
        entry.el.dispatchEvent(new Event('input', { bubbles: true }));
        entry.el.dispatchEvent(new Event('change', { bubbles: true }));
        applying = false;

        sessionStart.set(entry.el, valueOf(entry.el));

        if (typeof entry.el.focus === 'function') {
            entry.el.focus();
        }

        if (typeof entry.el.scrollIntoView === 'function') {
            entry.el.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }

        setStatus('unsaved', verb + ': ' + labelOf(entry.el), 3000);
    }

    function undo() {
        const entry = undoStack.pop();

        if (!entry) {
            setStatus('unsaved', 'Нечего отменять', 2000);
            return;
        }

        redoStack.push(entry);
        apply(entry, entry.from, 'Отменено');
    }

    function redo() {
        const entry = redoStack.pop();

        if (!entry) {
            setStatus('unsaved', 'Нечего возвращать', 2000);
            return;
        }

        undoStack.push(entry);
        apply(entry, entry.to, 'Возвращено');
    }

    // Ctrl+Z работает и на русской раскладке — смотрим физическую клавишу.
    document.addEventListener('keydown', function (event) {
        if (!(event.ctrlKey || event.metaKey) || event.altKey) {
            return;
        }

        const isUndo = event.code === 'KeyZ' && !event.shiftKey;
        const isRedo = (event.code === 'KeyZ' && event.shiftKey) || event.code === 'KeyY';

        if (!isUndo && !isRedo) {
            return;
        }

        const active = document.activeElement;

        // Фокус вне формы (например, в поиске) — не вмешиваемся.
        if (active && active !== document.body && !form.contains(active)) {
            return;
        }

        // Пока в текстовом поле есть неотменённый набор — стандартная отмена внутри поля.
        if (isUndo && isTracked(active) && isTextLike(active) && valueOf(active) !== sessionStart.get(active)) {
            return;
        }

        event.preventDefault();

        if (isUndo) {
            undo();
        } else {
            redo();
        }
    });
})();
