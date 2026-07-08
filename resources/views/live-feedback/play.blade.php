@extends('layouts.app')

@section('title', __('pages.live_feedback_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:820px;">
    <div class="text-center mb-4">
        <h1 class="page-title">{{ __('pages.live_feedback_title') }}</h1>
        <p class="text-muted-theme">{{ $course->title }} — {{ $module->title }}</p>
    </div>

    @if(session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif

    <div class="app-card card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('live-feedback.submit', $session) }}" id="feedback-form">
                @csrf
                @foreach(['lecture','speaker','workshop','timing','content'] as $key)
                    <div class="border-bottom pb-4 mb-4">
                        <h5 class="fw-bold mb-3">{{ __('pages.rate_'.$key) }}</h5>
                        <x-star-rating :name="$key.'_rating'" :label="__('pages.rating')" :value="$response?->{$key.'_rating'}" />
                        <textarea name="{{ $key }}_comments" class="form-control mt-2" rows="2"
                                  placeholder="{{ __('pages.comments_optional') }}">{{ old($key.'_comments', $response?->{$key.'_comments'}) }}</textarea>
                    </div>
                @endforeach
                <textarea name="notes" class="form-control mb-3" rows="3" placeholder="{{ __('pages.notes_optional') }}">{{ old('notes', $response?->notes) }}</textarea>
                <button type="submit" class="btn btn-primary w-100">{{ __('pages.submit_feedback') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const partialUrl = @json(route('live-feedback.partial', $session));
document.querySelectorAll('input[type=radio]').forEach(r => {
    r.addEventListener('change', () => {
        const fd = new FormData(document.getElementById('feedback-form'));
        fetch(partialUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }, body: fd });
    });
});
</script>
@endpush
