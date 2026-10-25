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
    const RETRY_DELAY_MS = 15000;   // первая пауза после сбоя; дальше удваивается
    const MAX_FAILURES = 4;         // после стольких сбоев подряд автосохранение останавливается

    let employeeId = parseInt(form.dataset.employeeId, 10) || 0;
    let dirty = false;
    let inFlight = null;
    let delayTimer = null;
    let submitting = false;
    let failed = false;
    let failures = 0;   // сбоев подряд
    let halted = false; // автосохранение остановлено до перезагрузки страницы

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

    // Обычная urlencoded-форма (не multipart): так запрос ближе всего к обычной
    // отправке формы и не отсекается защитными фильтрами хостинга. Файлы не шлём.
    function snapshot() {
        const data = new URLSearchParams();

        new FormData(form).forEach(function (value, key) {
            if (typeof value === 'string') {
                data.append(key, value);
            }
        });

        data.append('autosave', '1');

        return data;
    }

    function endpoint() {
        return employeeId ? 'employee_form.php?id=' + employeeId : 'employee_form.php';
    }

    function retryDelay() {
        return RETRY_DELAY_MS * Math.pow(2, Math.max(0, failures - 1));
    }

    // Начало ответа сервера без разметки — чтобы в сообщении была видна причина.
    function snippet(text) {
        const plain = String(text).replace(/<style[\s\S]*?<\/style>|<script[\s\S]*?<\/script>|<[^>]*>/gi, ' ').replace(/\s+/g, ' ').trim();

        if (plain === '') {
            return '';
        }

        console.warn('Автосохранение: ответ сервера:', text);

        return ': «' + plain.slice(0, 120) + '»';
    }

    function save() {
        if (halted || submitting || !dirty) {
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
        })
            .then(function (response) {
                return response.text().then(function (text) {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        const reason = response.redirected && /login\.php/.test(response.url)
                            ? 'сессия истекла — войдите заново в соседней вкладке и вернитесь сюда'
                            : 'сервер вернул ошибку (HTTP ' + response.status + ')' + snippet(text);

                        const failure = new Error(reason);

                        failure.status = response.status;
                        throw failure;
                    }
                });
            })
            .then(function (result) {
                failures = 0;

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
            .catch(function (error) {
                dirty = true;
                failed = true;
                failures += 1;

                const reason = error instanceof TypeError ? 'нет связи с сервером' : error.message;
                // 403/429 — сервер сам блокирует запросы: настаивать нельзя.
                const blocked = error.status === 403 || error.status === 429;

                if (blocked || failures >= MAX_FAILURES) {
                    halted = true;
                    clearTimeout(delayTimer);
                    setStatus('error', 'Автосохранение остановлено: ' + reason + '. Нажмите «Сохранить» внизу страницы, чтобы не потерять изменения.');
                } else {
                    setStatus('error', 'Не удалось сохранить: ' + reason + '. Повторю через ' + retryDelay() / 1000 + ' с.');
                }
            })
            .then(function () {
                inFlight = null;

                if (dirty && !submitting && !halted) {
                    // После сбоя не частим: пауза растёт 15 → 30 → 60 с, иначе — быстро.
                    scheduleSave(failed ? retryDelay() : SAVE_DELAY_MS);
                }

                failed = false;
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
        if (dirty && !inFlight && !submitting && !halted && navigator.sendBeacon) {
            dirty = !navigator.sendBeacon(endpoint(), snapshot());
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            flushOnLeave();
        }
    });
    window.addEventListener('pagehide', flushOnLeave);

    // Хостинг может отклонять multipart/form-data (403), а форма отправляется так
    // из-за полей для сканов. Если файлы не выбраны, шлём обычную urlencoded-форму —
    // сервер читает ту же $_POST. Если выбран скан, multipart нужен.
    function chooseEncoding() {
        const hasFiles = Array.prototype.some.call(form.querySelectorAll('input[type="file"]'), function (input) {
            return input.files && input.files.length > 0;
        });

        form.enctype = hasFiles ? 'multipart/form-data' : 'application/x-www-form-urlencoded';
    }

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
                chooseEncoding();
                form.submit();
            });

            return;
        }

        submitting = true;
        dirty = false;
        clearTimeout(delayTimer);
        chooseEncoding();
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
