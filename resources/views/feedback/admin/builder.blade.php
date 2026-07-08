@extends('layouts.app')

@section('title', __('pages.feedback_builder_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:1100px;">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h1 class="page-title mb-1">{{ $survey->title }}</h1>
            <small class="text-muted-theme">{{ $survey->course?->title }} — {{ $survey->module?->title }}</small>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('feedback.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('pages.back') }}</a>
            @if($survey->submissions_count ?? $survey->submissions()->count() > 0)
                <a href="{{ route('feedback.surveys.report', $survey) }}" class="btn btn-outline-primary btn-sm">{{ __('pages.view_report') }}</a>
            @endif
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="app-card card mb-4">
                <div class="card-header fw-semibold">{{ __('pages.survey_settings') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('feedback.surveys.update', $survey) }}">
                        @csrf @method('PUT')
                        <div class="mb-3">
                            <label class="form-label">{{ __('pages.title') }}</label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', $survey->title) }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('pages.description') }}</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description', $survey->description) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('pages.due_date') }}</label>
                            <input type="datetime-local" name="due_at" class="form-control"
                                   value="{{ old('due_at', $survey->due_at?->format('Y-m-d\TH:i')) }}">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_mandatory" value="1" id="mandatory"
                                   @checked(old('is_mandatory', $survey->is_mandatory))>
                            <label class="form-check-label" for="mandatory">{{ __('pages.feedback_mandatory_label') }}</label>
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('pages.save') }}</button>
                    </form>
                    <hr>
                    <div class="d-flex flex-wrap gap-2">
                        @if($survey->status === 'draft')
                            <form method="POST" action="{{ route('feedback.surveys.publish', $survey) }}">@csrf
                                <button type="submit" class="btn btn-success btn-sm">{{ __('pages.publish_feedback') }}</button>
                            </form>
                        @elseif($survey->status === 'open')
                            <form method="POST" action="{{ route('feedback.surveys.close', $survey) }}">@csrf
                                <button type="submit" class="btn btn-warning btn-sm">{{ __('pages.close_feedback') }}</button>
                            </form>
                        @endif
                        @if(!$survey->submissions()->exists())
                            <form method="POST" action="{{ route('feedback.surveys.destroy', $survey) }}"
                                  onsubmit="return confirm('{{ __('pages.confirm_delete') }}')">@csrf @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('pages.delete') }}</button>
                            </form>
                        @endif
                    </div>
                    <p class="small text-muted mt-2 mb-0">{{ __('pages.feedback_status_'.$survey->status) }}</p>
                </div>
            </div>

            <div class="app-card card">
                <div class="card-header fw-semibold">{{ __('pages.add_question') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('feedback.surveys.questions.store', $survey) }}" id="question-form">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small">{{ __('pages.question_type') }}</label>
                            <select name="question_type" class="form-select form-select-sm" id="q-type">
                                <option value="rating">{{ __('pages.type_rating') }}</option>
                                <option value="slider">{{ __('pages.type_slider') }}</option>
                                <option value="mcq">{{ __('pages.type_mcq') }}</option>
                                <option value="text">{{ __('pages.type_text') }}</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">{{ __('pages.question_scope') }}</label>
                            <select name="scope" class="form-select form-select-sm" id="q-scope">
                                <option value="general">{{ __('pages.scope_general') }}</option>
                                <option value="session">{{ __('pages.scope_session') }}</option>
                                <option value="lecture">{{ __('pages.scope_lecture') }}</option>
                                <option value="instructor">{{ __('pages.scope_instructor') }}</option>
                            </select>
                        </div>
                        <div class="mb-2 d-none" id="scope-session">
                            <select name="session_id" class="form-select form-select-sm">
                                <option value="">{{ __('pages.select_session') }}</option>
                                @foreach($sessions as $session)
                                    <option value="{{ $session->session_id }}">{{ $session->session_title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2 d-none" id="scope-lecture">
                            <select name="lecture_id" class="form-select form-select-sm">
                                <option value="">{{ __('pages.select_lecture') }}</option>
                                @foreach($lectures as $lecture)
                                    <option value="{{ $lecture->lecture_id }}">{{ $lecture->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2 d-none" id="scope-instructor">
                            <select name="target_user_id" class="form-select form-select-sm">
                                <option value="">{{ __('pages.select_instructor') }}</option>
                                @foreach($staff as $member)
                                    <option value="{{ $member->user_id }}">{{ $member->displayName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">{{ __('pages.question_label') }}</label>
                            <input type="text" name="label" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <input type="text" name="help_text" class="form-control form-control-sm" placeholder="{{ __('pages.help_text_optional') }}">
                        </div>
                        <div class="mb-2 d-none" id="cfg-mcq">
                            <textarea name="choices" class="form-control form-control-sm" rows="3" placeholder="{{ __('pages.mcq_choices_hint') }}"></textarea>
                        </div>
                        <div class="mb-2 d-none row g-2" id="cfg-slider">
                            <div class="col-6"><input type="number" name="min" class="form-control form-control-sm" placeholder="Min" value="1"></div>
                            <div class="col-6"><input type="number" name="max" class="form-control form-control-sm" placeholder="Max" value="10"></div>
                        </div>
                        <div class="mb-2 d-none" id="cfg-rating">
                            <input type="number" name="max_rating" class="form-control form-control-sm" value="5" min="3" max="10" placeholder="{{ __('pages.max_stars') }}">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_required" value="1" id="q-req" checked>
                            <label class="form-check-label small" for="q-req">{{ __('pages.required_question') }}</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('pages.add_question') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="app-card card">
                <div class="card-header fw-semibold">{{ __('pages.questions') }} ({{ $survey->questions->count() }})</div>
                <div class="card-body">
                    @forelse($survey->questions as $question)
                        <div class="d-flex justify-content-between align-items-start border-bottom py-3">
                            <div>
                                <span class="badge bg-light text-dark me-1">#{{ $question->order_index }}</span>
                                <span class="badge bg-primary me-1">{{ $question->question_type }}</span>
                                <span class="badge bg-secondary">{{ $question->scope }}</span>
                                @if(!$question->is_required)<span class="badge bg-warning text-dark">{{ __('pages.optional') }}</span>@endif
                                <div class="fw-semibold mt-1">{{ $question->scopeLabel() }}</div>
                            </div>
                            @if($survey->status === 'draft')
                                <form method="POST" action="{{ route('feedback.surveys.questions.destroy', [$survey, $question]) }}">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">&times;</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted-theme mb-0">{{ __('pages.feedback_no_questions') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const scopeEl = document.getElementById('q-scope');
const typeEl = document.getElementById('q-type');
function toggleScope() {
    ['session','lecture','instructor'].forEach(s => {
        document.getElementById('scope-'+s)?.classList.toggle('d-none', scopeEl.value !== s);
    });
}
function toggleType() {
    document.getElementById('cfg-mcq')?.classList.toggle('d-none', typeEl.value !== 'mcq');
    document.getElementById('cfg-slider')?.classList.toggle('d-none', typeEl.value !== 'slider');
    document.getElementById('cfg-rating')?.classList.toggle('d-none', typeEl.value !== 'rating');
}
scopeEl?.addEventListener('change', toggleScope);
typeEl?.addEventListener('change', toggleType);
toggleScope(); toggleType();
</script>
@endpush
