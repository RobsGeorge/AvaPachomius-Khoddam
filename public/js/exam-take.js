/**
 * Online exam — synchronized timer, autosave, proctoring
 */
(function () {
    const root = document.getElementById('examTakeRoot');
    if (!root) return;

    const saveUrl = root.dataset.saveUrl;
    const timerUrl = root.dataset.timerUrl;
    const proctorUrl = root.dataset.proctorUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const interval = parseInt(root.dataset.autosaveInterval || '30', 10) * 1000;

    const timerEl = document.getElementById('examFloatingTimer');
    const timerDisplay = document.getElementById('examTimerDisplay');
    const saveStatus = document.getElementById('examSaveStatus');
    const form = document.getElementById('examTakeForm');
    const proctorModalEl = document.getElementById('proctorWarningModal');
    const proctorModalBody = document.getElementById('proctorWarningBody');
    let proctorModal = proctorModalEl ? bootstrap.Modal.getOrCreateInstance(proctorModalEl) : null;

    let endsAtMs = parseInt(root.dataset.endsAtMs || '0', 10);
    let saveTimer = null;
    let clockTimer = null;
    let examLocked = false;
    let proctorReporting = false;

    function collectAnswers() {
        const answers = {};
        form.querySelectorAll('[data-question-id]').forEach((block) => {
            const qid = block.dataset.questionId;
            const type = block.dataset.questionType;
            if (type === 'essay') {
                const ta = block.querySelector('textarea');
                answers[qid] = { text: ta ? ta.value : '' };
            } else {
                const checked = block.querySelector('input[type=radio]:checked');
                answers[qid] = { option_id: checked ? checked.value : null };
            }
        });
        return answers;
    }

    function formatTime(totalSeconds) {
        const s = Math.max(0, totalSeconds);
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = s % 60;
        if (h > 0) {
            return `${h}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
        }
        return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
    }

    function updateClock() {
        const remaining = Math.floor((endsAtMs - Date.now()) / 1000);
        if (timerDisplay) {
            timerDisplay.textContent = formatTime(remaining);
        }
        if (timerEl) {
            timerEl.classList.toggle('exam-timer-warning', remaining <= 300 && remaining > 60);
            timerEl.classList.toggle('exam-timer-critical', remaining <= 60);
        }
        if (remaining <= 0 && !examLocked) {
            clearInterval(clockTimer);
            if (form) form.requestSubmit();
        }
    }

    async function syncTimer() {
        try {
            const res = await fetch(timerUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            if (data.ends_at) {
                endsAtMs = new Date(data.ends_at).getTime();
            }
        } catch (e) { /* ignore */ }
    }

    async function autosave() {
        if (!saveUrl || !form || examLocked) return;
        if (saveStatus) saveStatus.textContent = root.dataset.msgSaving || 'Saving…';

        try {
            const res = await fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ answers: collectAnswers() }),
            });
            const data = await res.json();
            if (data.submitted && data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            if (saveStatus) {
                saveStatus.textContent = data.saved
                    ? (root.dataset.msgSaved || 'Saved')
                    : (root.dataset.msgError || 'Error');
            }
            if (data.timer?.ends_at) {
                endsAtMs = new Date(data.timer.ends_at).getTime();
            }
        } catch (e) {
            if (saveStatus) saveStatus.textContent = root.dataset.msgError || 'Error';
        }
    }

    function scheduleAutosave() {
        if (examLocked) return;
        clearTimeout(saveTimer);
        saveTimer = setTimeout(autosave, 1500);
    }

    function lockExam() {
        examLocked = true;
        form?.querySelectorAll('input, textarea, button').forEach((el) => {
            if (el.type !== 'button' || !el.dataset.bsDismiss) {
                el.disabled = true;
            }
        });
    }

    async function reportProctor(eventType, details) {
        if (!proctorUrl || examLocked || proctorReporting) return;
        proctorReporting = true;

        try {
            const res = await fetch(proctorUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ event_type: eventType, details: details || '' }),
            });
            const data = await res.json();

            if (data.action === 'warn' && data.message) {
                if (proctorModalBody) proctorModalBody.textContent = data.message;
                proctorModal?.show();
            }

            if (data.action === 'terminated') {
                lockExam();
                if (proctorModalBody) {
                    proctorModalBody.textContent = data.message || root.dataset.proctorTerminated;
                }
                proctorModal?.show();
                setTimeout(() => {
                    window.location.href = data.redirect || saveUrl.replace('/save', '/confirmation');
                }, 2500);
            }
        } catch (e) { /* ignore */ }
        finally {
            setTimeout(() => { proctorReporting = false; }, 2000);
        }
    }

    function onVisibilityChange() {
        if (document.hidden) {
            reportProctor('tab_hidden', 'document.visibilityState=hidden');
        }
    }

    function onWindowBlur() {
        if (document.hidden) return;
        reportProctor('window_blur', 'window.blur');
    }

    function onPageHide() {
        reportProctor('page_hide', 'pagehide event');
    }

    form?.addEventListener('change', scheduleAutosave);
    form?.querySelectorAll('textarea').forEach((ta) => {
        ta.addEventListener('input', scheduleAutosave);
    });

    form?.addEventListener('submit', (e) => {
        if (examLocked) {
            e.preventDefault();
            return;
        }
        if (!confirm(root.dataset.confirmSubmit || 'Submit exam?')) {
            e.preventDefault();
        }
    });

    document.querySelectorAll('[data-goto-question]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('question-' + btn.dataset.gotoQuestion)
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    document.addEventListener('visibilitychange', onVisibilityChange);
    window.addEventListener('blur', onWindowBlur);
    window.addEventListener('pagehide', onPageHide);

    updateClock();
    clockTimer = setInterval(updateClock, 1000);
    setInterval(syncTimer, 60000);
    setInterval(autosave, interval);
})();
