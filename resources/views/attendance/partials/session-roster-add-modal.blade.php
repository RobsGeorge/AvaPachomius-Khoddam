<div class="modal fade" id="add-session-attendance-modal" tabindex="-1" aria-labelledby="add-session-attendance-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('sessions.attendance.store', $session->session_id) }}" id="add-session-attendance-form">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h5" id="add-session-attendance-label">{{ __('pages.add_student_attendance') }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="student-search-input" class="form-label">{{ __('pages.search_student') }}</label>
                        <input type="text" id="student-search-input" class="form-control" autocomplete="off"
                               placeholder="{{ __('pages.search_student_placeholder') }}">
                        <div id="student-search-results" class="list-group mt-2"></div>
                    </div>

                    <input type="hidden" name="user_id" id="selected-student-id" required>

                    <div class="mb-3">
                        <label for="add-attendance-status" class="form-label">{{ __('pages.status') }}</label>
                        <select name="status" id="add-attendance-status" class="form-select" required>
                            <option value="Present">{{ __('pages.present') }}</option>
                            <option value="Absent">{{ __('pages.absent') }}</option>
                            <option value="Late">{{ __('pages.late') }}</option>
                            <option value="Permission">{{ __('pages.permission') }}</option>
                        </select>
                    </div>

                    <div class="mb-3" id="add-permission-reason-wrap" style="display: none;">
                        <label for="add-permission-reason" class="form-label">{{ __('pages.permission_reason') }}</label>
                        <input type="text" name="permission_reason" id="add-permission-reason" class="form-control" maxlength="255">
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="allow_non_enrolled" value="1" id="allow-non-enrolled">
                        <label class="form-check-label" for="allow-non-enrolled">
                            {{ __('pages.allow_non_enrolled_student') }}
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('pages.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('pages.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const searchInput = document.getElementById('student-search-input');
    const resultsEl = document.getElementById('student-search-results');
    const selectedIdInput = document.getElementById('selected-student-id');
    const statusSelect = document.getElementById('add-attendance-status');
    const permissionWrap = document.getElementById('add-permission-reason-wrap');
    const allowNonEnrolled = document.getElementById('allow-non-enrolled');
    const searchUrl = @json(route('sessions.attendance.search', $session->session_id));
    let searchTimer = null;

    if (statusSelect && permissionWrap) {
        statusSelect.addEventListener('change', function () {
            permissionWrap.style.display = statusSelect.value === 'Permission' ? '' : 'none';
        });
    }

    function renderResults(items) {
        if (!resultsEl) return;
        resultsEl.innerHTML = '';
        if (!items.length) {
            resultsEl.innerHTML = '<div class="list-group-item text-muted-theme small">' + @json(__('pages.no_students_found')) + '</div>';
            return;
        }
        items.forEach(function (item) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action';
            btn.textContent = item.label + (item.mobile_number ? ' — ' + item.mobile_number : '');
            btn.addEventListener('click', function () {
                selectedIdInput.value = item.user_id;
                if (searchInput) {
                    searchInput.value = item.label;
                }
                resultsEl.innerHTML = '';
            });
            resultsEl.appendChild(btn);
        });
    }

    function runSearch() {
        if (!searchInput || searchInput.value.trim().length < 2) {
            if (resultsEl) resultsEl.innerHTML = '';
            return;
        }

        const params = new URLSearchParams({
            q: searchInput.value.trim(),
            include_non_enrolled: allowNonEnrolled && allowNonEnrolled.checked ? '1' : '0',
        });

        fetch(searchUrl + '?' + params.toString(), {
            headers: { 'Accept': 'application/json' },
        })
            .then(function (response) { return response.json(); })
            .then(function (data) { renderResults(data.results || []); })
            .catch(function () { renderResults([]); });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            selectedIdInput.value = '';
            clearTimeout(searchTimer);
            searchTimer = setTimeout(runSearch, 300);
        });
    }

    if (allowNonEnrolled) {
        allowNonEnrolled.addEventListener('change', runSearch);
    }
})();
</script>
@endpush
