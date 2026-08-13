@extends('layouts.app')

@section('title', __('exams.grades_dashboard'))

@section('content')
@php
    $totalPoints = (float) $exam->total_points;
    $canEnterOffline = $exam->isOffline() && $totalPoints > 0;
@endphp
<div class="container py-4 animate-in"
     data-exam-total-points="{{ $totalPoints }}">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <a href="{{ route('exams.dashboard') }}" class="text-muted small">{{ __('pages.exams_management') }}</a>
            <h1 class="page-title mb-0">{{ __('exams.grades_dashboard') }}: {{ $exam->exam_name }}</h1>
            <p class="text-muted small mb-0">
                {{ $exam->isOnline() ? __('exams.online_auto_graded') : __('exams.offline_grade_entry') }}
            </p>
            <form method="POST" action="{{ route('exams.grades.total-points', $exam) }}"
                  class="d-flex flex-wrap align-items-end gap-2 mt-2">
                @csrf
                @method('PUT')
                <div>
                    <label class="form-label small mb-1" for="exam-total-points">{{ __('exams.total_points') }}</label>
                    <input type="number"
                           id="exam-total-points"
                           name="total_points"
                           class="form-control form-control-sm"
                           style="width: 8rem;"
                           min="0.01" max="9999.99" step="0.01"
                           value="{{ $totalPoints > 0 ? number_format($totalPoints, 2, '.', '') : '' }}"
                           required>
                </div>
                <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('exams.save_total_points') }}</button>
                @if($exam->isOnline())
                    <span class="small text-muted align-self-center">{{ __('exams.total_points_online_note') }}</span>
                @endif
            </form>
            @if($exam->areResultsAnnounced())
                <p class="small text-success mb-0 mt-1">
                    <i class="bi bi-megaphone"></i>
                    {{ __('exams.results_announced_at', ['when' => $exam->results_announced_at->format('Y-m-d H:i')]) }}
                </p>
            @else
                <p class="small text-muted mb-0 mt-1">{{ __('exams.results_not_announced_yet') }}</p>
            @endif
        </div>
        <div class="d-flex flex-wrap gap-2">
            @unless($exam->areResultsAnnounced())
                <form method="POST" action="{{ route('exams.grades.announce', $exam) }}"
                      onsubmit="return confirm(@json(__('exams.confirm_announce_results')))">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="bi bi-megaphone"></i> {{ __('exams.announce_results') }}
                    </button>
                </form>
            @endunless
            @if($exam->isOnline())
                <a href="{{ route('exams.builder', $exam) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil-square"></i> {{ __('exams.design_exam') }}
                </a>
            @endif
        </div>
    </div>

    @if($exam->isOffline() && $totalPoints <= 0)
        <div class="alert alert-warning">{{ __('exams.total_points_required') }}</div>
    @endif

@foreach($exam->schedules as $schedule)
    @php
        $resultsByUser = $schedule->results->keyBy('user_id');
        $gradedCount = $resultsByUser->filter(fn ($r) => $r->score !== null)->count();
    @endphp
        <div class="app-card card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>{{ $schedule->scheduled_date->format('Y-m-d H:i') }}</span>
                <div class="d-flex align-items-center gap-2">
                    @if($exam->isOffline())
                        <span class="small text-muted">
                            {{ __('pages.corrected_count') }}: {{ $gradedCount }} / {{ $students->count() }}
                        </span>
                    @endif
                    @if($schedule->is_completed)
                        <span class="badge bg-success">{{ __('pages.done') }}</span>
                    @endif
                </div>
            </div>
            <div class="card-body p-0">
                @if($exam->isOffline())
                    {{-- Offline: bulk enrolled roster --}}
                    <form method="POST"
                          action="{{ route('exams.grades.offline.bulk', $exam) }}"
                          class="exam-offline-bulk-form"
                          id="exam-offline-bulk-{{ $schedule->schedule_id }}">
                        @csrf
                        <input type="hidden" name="schedule_id" value="{{ $schedule->schedule_id }}">

                        <div class="table-responsive d-none d-lg-block admin-table-desktop">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:48px;">{{ __('pages.number') }}</th>
                                        <th>{{ __('exams.student') }}</th>
                                        <th style="width:180px;">
                                            {{ __('exams.points_earned') }}
                                            <span class="text-muted small">/ {{ number_format($totalPoints, 1) }}</span>
                                        </th>
                                        <th style="width:100px;">{{ __('exams.percentage_preview') }}</th>
                                        <th>{{ __('pages.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($students as $i => $student)
                                        @php
                                            $result = $resultsByUser->get($student->user_id);
                                            $prefill = $result && $result->score !== null
                                                ? number_format(round(((float) $result->score / 100) * $totalPoints, 2), 2, '.', '')
                                                : '';
                                            $pct = $result && $result->score !== null
                                                ? number_format((float) $result->score, 1).'%'
                                                : '—';
                                        @endphp
                                        <tr class="{{ $result && $result->score !== null ? '' : 'table-warning bg-opacity-25' }}"
                                            data-points-row>
                                            <td class="text-muted small">{{ $i + 1 }}</td>
                                            <td class="fw-semibold">{{ $student->displayName() }}</td>
                                            <td>
                                                <input type="number"
                                                       name="points[{{ $student->user_id }}]"
                                                       class="form-control form-control-sm js-points-input"
                                                       value="{{ $prefill }}"
                                                       min="0" max="{{ $totalPoints }}" step="0.1"
                                                       @disabled(! $canEnterOffline)
                                                       placeholder="—">
                                            </td>
                                            <td class="fw-semibold js-pct-preview">{{ $pct }}</td>
                                            <td>
                                                @if($result && $result->score !== null)
                                                    <span class="badge bg-success">{{ __('exams.status_graded') }}</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ __('pages.not_corrected') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">{{ __('pages.no_students_in_course') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="d-lg-none admin-data-cards student-data-hub p-3">
                            @forelse($students as $i => $student)
                                @php
                                    $result = $resultsByUser->get($student->user_id);
                                    $prefill = $result && $result->score !== null
                                        ? number_format(round(((float) $result->score / 100) * $totalPoints, 2), 2, '.', '')
                                        : '';
                                    $pct = $result && $result->score !== null
                                        ? number_format((float) $result->score, 1).'%'
                                        : '—';
                                @endphp
                                <article class="data-card {{ $result && $result->score !== null ? '' : 'border-warning' }}" data-points-row>
                                    <div class="data-card-title">
                                        <span class="text-muted small me-1">#{{ $i + 1 }}</span>
                                        {{ $student->displayName() }}
                                    </div>
                                    <dl class="data-meta-list mb-0">
                                        <div class="data-meta-row">
                                            <dt>{{ __('exams.points_earned') }}</dt>
                                            <dd>
                                                <input type="number"
                                                       name="points[{{ $student->user_id }}]"
                                                       class="form-control form-control-sm js-points-input"
                                                       value="{{ $prefill }}"
                                                       min="0" max="{{ $totalPoints }}" step="0.1"
                                                       @disabled(! $canEnterOffline)
                                                       placeholder="— / {{ number_format($totalPoints, 1) }}">
                                            </dd>
                                        </div>
                                        <div class="data-meta-row">
                                            <dt>{{ __('exams.percentage_preview') }}</dt>
                                            <dd class="fw-semibold js-pct-preview">{{ $pct }}</dd>
                                        </div>
                                        <div class="data-meta-row">
                                            <dt>{{ __('pages.status') }}</dt>
                                            <dd>
                                                @if($result && $result->score !== null)
                                                    <span class="badge bg-success">{{ __('exams.status_graded') }}</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ __('pages.not_corrected') }}</span>
                                                @endif
                                            </dd>
                                        </div>
                                    </dl>
                                </article>
                            @empty
                                <p class="text-center text-muted py-4 mb-0">{{ __('pages.no_students_in_course') }}</p>
                            @endforelse
                        </div>

                        @if($students->isNotEmpty())
                            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span class="small text-muted d-none d-lg-inline">{{ __('exams.offline_bulk_hint') }}</span>
                                <button type="submit" class="btn btn-success btn-sm" @disabled(! $canEnterOffline)>
                                    <i class="bi bi-save"></i> {{ __('pages.save_all_grades') }}
                                </button>
                            </div>
                        @endif
                    </form>

                    {{-- Quick-add searchable student --}}
                    <div class="card-footer border-top" x-data="examQuickGrade({
                        students: @js($students->map(fn ($s) => [
                            'id' => $s->user_id,
                            'name' => $s->displayName(),
                        ])->values()),
                        totalPoints: {{ $totalPoints }},
                    })">
                        <div class="small fw-semibold mb-2">{{ __('exams.quick_add_grade') }}</div>
                        <form method="POST" action="{{ route('exams.grades.offline', $exam) }}" class="row g-2 align-items-end"
                              x-on:submit="if (!selectedId) { $event.preventDefault(); }">
                            @csrf
                            <input type="hidden" name="schedule_id" value="{{ $schedule->schedule_id }}">
                            <input type="hidden" name="user_id" x-bind:value="selectedId">
                            <div class="col-md-5 position-relative">
                                <label class="form-label small">{{ __('exams.student') }}</label>
                                <input type="text"
                                       class="form-control form-control-sm"
                                       placeholder="{{ __('pages.search_student') }}"
                                       x-model="query"
                                       x-on:focus="open = true"
                                       x-on:click.outside="open = false"
                                       autocomplete="off"
                                       @disabled(! $canEnterOffline)>
                                <div class="list-group position-absolute w-100 shadow-sm"
                                     style="z-index: 20; max-height: 220px; overflow-y: auto;"
                                     x-show="open && filtered.length"
                                     x-cloak>
                                    <template x-for="s in filtered" :key="s.id">
                                        <button type="button"
                                                class="list-group-item list-group-item-action py-2 small"
                                                x-on:click="pick(s)">
                                            <span x-text="s.name"></span>
                                        </button>
                                    </template>
                                </div>
                                <div class="form-text text-success" x-show="selectedId" x-cloak>
                                    <i class="bi bi-check-circle"></i> <span x-text="selectedName"></span>
                                </div>
                            </div>
                            <div class="col-md-3" data-points-row>
                                <label class="form-label small">{{ __('exams.points_earned') }}</label>
                                <div class="input-group input-group-sm">
                                    <input type="number"
                                           name="points"
                                           class="form-control js-points-input"
                                           min="0" max="{{ $totalPoints }}" step="0.1"
                                           required
                                           @disabled(! $canEnterOffline)
                                           x-on:input="previewPct($event.target.value)">
                                    <span class="input-group-text">/ {{ number_format($totalPoints, 1) }}</span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">{{ __('exams.percentage_preview') }}</label>
                                <div class="form-control form-control-sm bg-light fw-semibold" x-text="pctLabel">—</div>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-sm btn-success w-100" type="submit"
                                        @disabled(! $canEnterOffline)
                                        x-bind:disabled="!selectedId">
                                    {{ __('pages.save') }}
                                </button>
                            </div>
                        </form>
                    </div>
                @else
                    {{-- Online: existing results table --}}
                    <div class="table-responsive d-none d-lg-block admin-table-desktop">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('exams.student') }}</th>
                                    <th>{{ __('exams.auto_score') }}</th>
                                    <th>{{ __('exams.manual_score') }}</th>
                                    <th>{{ __('exams.final_score') }}</th>
                                    <th>{{ __('pages.status') }}</th>
                                    <th>{{ __('exams.proctor_flags') }}</th>
                                    <th>{{ __('pages.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($schedule->results as $result)
                                    <tr>
                                        <td>{{ $result->user?->displayName() ?? ($result->user->email ?? '#'.$result->user_id) }}</td>
                                        <td>{{ $result->auto_score !== null ? number_format($result->auto_score, 1) : '—' }}</td>
                                        <td>{{ $result->manual_score !== null ? number_format($result->manual_score, 1) : '—' }}</td>
                                        <td class="fw-semibold">{{ $result->score !== null ? number_format($result->score, 1).'%' : '—' }}</td>
                                        <td>
                                            @if($result->isCheater())
                                                <span class="badge bg-danger">{{ __('exams.status_cheater') }}</span>
                                            @else
                                                <span class="badge bg-secondary">{{ __('exams.status_' . ($result->status ?? 'pending')) }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($result->attempt && $result->attempt->proctorEvents->isNotEmpty())
                                                <span class="badge bg-warning text-dark" title="{{ $result->attempt->proctorEvents->pluck('event_type')->join(', ') }}">
                                                    {{ $result->attempt->proctor_warnings }} {{ __('exams.warnings') }}
                                                </span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if($result->isCheater())
                                                <form method="POST" action="{{ route('exams.grades.clear-cheater', [$exam, $result]) }}" class="d-flex gap-1 flex-wrap">
                                                    @csrf
                                                    <input type="number" name="score" class="form-control form-control-sm" style="width:80px;"
                                                           min="0" max="100" step="0.1" placeholder="%" title="{{ __('exams.override_score') }}">
                                                    <button class="btn btn-sm btn-warning">{{ __('exams.clear_cheater_flag') }}</button>
                                                </form>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-theme"
                                                        data-bs-toggle="collapse" data-bs-target="#result-{{ $result->result_id }}">
                                                    {{ __('exams.review_essay') }}
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                    @if($result->attempt)
                                        <tr class="collapse" id="result-{{ $result->result_id }}">
                                            <td colspan="7" class="bg-light-subtle">
                                                @if($result->attempt->proctorEvents->isNotEmpty())
                                                    <div class="mb-3 p-2 border rounded bg-white">
                                                        <div class="fw-semibold small">{{ __('exams.proctor_log') }}</div>
                                                        <ul class="small mb-0">
                                                            @foreach($result->attempt->proctorEvents as $ev)
                                                                <li>
                                                                    {{ $ev->created_at->format('H:i:s') }} —
                                                                    {{ $ev->event_type }}
                                                                    ({{ __('exams.warning') }} #{{ $ev->warning_number }})
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                                @foreach($result->attempt->answers as $answer)
                                                    @if($answer->question?->question_type === \App\Models\ExamQuestion::TYPE_ESSAY)
                                                        <div class="mb-3 p-3 border rounded bg-white">
                                                            <div class="fw-semibold small">{{ Str::limit($answer->question->prompt, 120) }}</div>
                                                            <p class="small mb-2" style="white-space:pre-line;">{{ $answer->text_answer }}</p>
                                                            @if($answer->ai_feedback)
                                                                <div class="small text-muted"><strong>{{ __('exams.ai_feedback') }}:</strong> {{ $answer->ai_feedback }}</div>
                                                            @endif
                                                            <form method="POST" action="{{ route('exams.grades.update', [$exam, $result]) }}" class="d-flex gap-2 mt-2">
                                                                @csrf @method('PUT')
                                                                <input type="hidden" name="scores[{{ $answer->question_id }}]" value="">
                                                                <label class="small">{{ __('exams.points') }}</label>
                                                                <input type="number" name="scores[{{ $answer->question_id }}]"
                                                                       class="form-control form-control-sm" style="width:90px;"
                                                                       step="0.25" min="0" max="{{ $answer->question->points }}"
                                                                       value="{{ $answer->manual_score ?? $answer->auto_score ?? 0 }}">
                                                                <button class="btn btn-sm btn-primary">{{ __('pages.save') }}</button>
                                                            </form>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">{{ __('pages.no_students') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-lg-none admin-data-cards student-data-hub p-3">
                        @forelse($schedule->results as $result)
                            <article class="data-card">
                                <div class="data-card-title">{{ $result->user?->displayName() ?? ($result->user->email ?? '#'.$result->user_id) }}</div>
                                <dl class="data-meta-list mb-3">
                                    <div class="data-meta-row">
                                        <dt>{{ __('exams.final_score') }}</dt>
                                        <dd class="fw-semibold">{{ $result->score !== null ? number_format($result->score, 1).'%' : '—' }}</dd>
                                    </div>
                                    <div class="data-meta-row">
                                        <dt>{{ __('exams.auto_score') }}</dt>
                                        <dd>{{ $result->auto_score !== null ? number_format($result->auto_score, 1) : '—' }}</dd>
                                    </div>
                                    <div class="data-meta-row">
                                        <dt>{{ __('exams.manual_score') }}</dt>
                                        <dd>{{ $result->manual_score !== null ? number_format($result->manual_score, 1) : '—' }}</dd>
                                    </div>
                                    <div class="data-meta-row">
                                        <dt>{{ __('pages.status') }}</dt>
                                        <dd>
                                            @if($result->isCheater())
                                                <span class="badge bg-danger">{{ __('exams.status_cheater') }}</span>
                                            @else
                                                <span class="badge bg-secondary">{{ __('exams.status_' . ($result->status ?? 'pending')) }}</span>
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                                <div class="data-card-actions">
                                    @if($result->isCheater())
                                        <form method="POST" action="{{ route('exams.grades.clear-cheater', [$exam, $result]) }}" class="d-flex flex-column gap-2">
                                            @csrf
                                            <input type="number" name="score" class="form-control form-control-sm"
                                                   min="0" max="100" step="0.1" placeholder="%" title="{{ __('exams.override_score') }}">
                                            <button class="btn btn-sm btn-warning w-100">{{ __('exams.clear_cheater_flag') }}</button>
                                        </form>
                                    @elseif($result->attempt)
                                        <button type="button" class="btn btn-sm btn-outline-theme w-100"
                                                data-bs-toggle="collapse" data-bs-target="#mobile-result-{{ $result->result_id }}">
                                            {{ __('exams.review_essay') }}
                                        </button>
                                        <div class="collapse mt-2" id="mobile-result-{{ $result->result_id }}">
                                            @foreach($result->attempt->answers as $answer)
                                                @if($answer->question?->question_type === \App\Models\ExamQuestion::TYPE_ESSAY)
                                                    <div class="mb-3 p-2 border rounded bg-light-subtle">
                                                        <div class="fw-semibold small">{{ Str::limit($answer->question->prompt, 120) }}</div>
                                                        <p class="small mb-2" style="white-space:pre-line;">{{ $answer->text_answer }}</p>
                                                        <form method="POST" action="{{ route('exams.grades.update', [$exam, $result]) }}" class="d-flex gap-2 mt-2">
                                                            @csrf @method('PUT')
                                                            <input type="hidden" name="scores[{{ $answer->question_id }}]" value="">
                                                            <input type="number" name="scores[{{ $answer->question_id }}]"
                                                                   class="form-control form-control-sm"
                                                                   step="0.25" min="0" max="{{ $answer->question->points }}"
                                                                   value="{{ $answer->manual_score ?? $answer->auto_score ?? 0 }}">
                                                            <button class="btn btn-sm btn-primary">{{ __('pages.save') }}</button>
                                                        </form>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <p class="text-center text-muted py-4 mb-0">{{ __('pages.no_students') }}</p>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>
@endsection

@push('scripts')
<script>
(function () {
    const root = document.querySelector('[data-exam-total-points]');
    const totalPoints = parseFloat(root?.dataset.examTotalPoints || '0');

    function formatPct(points) {
        if (points === '' || points === null || Number.isNaN(Number(points)) || totalPoints <= 0) {
            return '—';
        }
        const pct = Math.round((Number(points) / totalPoints) * 1000) / 10;
        return pct.toFixed(1) + '%';
    }

    function updateRow(row, value) {
        const preview = row.querySelector('.js-pct-preview');
        if (preview && !preview.hasAttribute('x-text')) {
            preview.textContent = formatPct(value);
        }
    }

    document.addEventListener('input', function (e) {
        if (!e.target.classList.contains('js-points-input')) return;
        const row = e.target.closest('[data-points-row]');
        if (row) updateRow(row, e.target.value);
    });

    document.querySelectorAll('.exam-offline-bulk-form').forEach(function (form) {
        form.addEventListener('submit', function () {
            const useDesktop = window.matchMedia('(min-width: 992px)').matches;
            const disableRoot = useDesktop
                ? form.querySelector('.d-lg-none')
                : form.querySelector('.admin-table-desktop');
            disableRoot?.querySelectorAll('input, select, textarea').forEach(function (el) {
                el.disabled = true;
            });
        });
    });

    window.examQuickGrade = function (config) {
        return {
            students: config.students || [],
            totalPoints: config.totalPoints || 0,
            query: '',
            open: false,
            selectedId: '',
            selectedName: '',
            pctLabel: '—',
            get filtered() {
                const q = (this.query || '').trim().toLowerCase();
                if (!q) return this.students.slice(0, 12);
                return this.students.filter(s => (s.name || '').toLowerCase().includes(q)).slice(0, 12);
            },
            pick(s) {
                this.selectedId = s.id;
                this.selectedName = s.name;
                this.query = s.name;
                this.open = false;
            },
            previewPct(value) {
                if (value === '' || value === null || Number.isNaN(Number(value)) || this.totalPoints <= 0) {
                    this.pctLabel = '—';
                    return;
                }
                const pct = Math.round((Number(value) / this.totalPoints) * 1000) / 10;
                this.pctLabel = pct.toFixed(1) + '%';
            },
        };
    };
})();
</script>
@endpush
