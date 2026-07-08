@props(['announcement' => null, 'action', 'method' => 'POST', 'courses', 'students', 'selectedCourse' => null])

@php
    $channels = old('channels', $announcement?->channels ?? []);
    $targetMode = old('target_mode', $announcement?->target_mode ?? \App\Models\Announcement::TARGET_COURSE);
    $selectedUsers = old('target_user_ids', $announcement ? $announcement->targetUsers->pluck('user_id')->all() : []);
@endphp

<form method="POST" action="{{ $action }}" class="student-data-hub">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label" for="title">{{ __('pages.title') }}</label>
                <input type="text" name="title" id="title" class="form-control" required maxlength="200"
                       value="{{ old('title', $announcement?->title ?? '') }}">
            </div>

            <div class="mb-3">
                <label class="form-label" for="body">{{ __('pages.description') }}</label>
                <textarea name="body" id="body" rows="6" class="form-control" required maxlength="10000">{{ old('body', $announcement?->body ?? '') }}</textarea>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="course_id">{{ __('pages.course') }}</label>
                    <select name="course_id" id="course_id" class="form-select">
                        <option value="">{{ __('pages.select_course') }}</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->course_id }}"
                                @selected((string) old('course_id', $announcement?->course_id ?? $selectedCourse ?? '') === (string) $course->course_id)>
                                {{ $course->title }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('announcements.target') }}</label>
                    <select name="target_mode" id="target_mode" class="form-select">
                        <option value="{{ \App\Models\Announcement::TARGET_COURSE }}" @selected($targetMode === \App\Models\Announcement::TARGET_COURSE)>
                            {{ __('announcements.target_course') }}
                        </option>
                        <option value="{{ \App\Models\Announcement::TARGET_USERS }}" @selected($targetMode === \App\Models\Announcement::TARGET_USERS)>
                            {{ __('announcements.target_users') }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="mb-3" id="target-users-wrap" @if($targetMode !== \App\Models\Announcement::TARGET_USERS) hidden @endif>
                <label class="form-label">{{ __('announcements.recipients') }}</label>
                <select name="target_user_ids[]" class="form-select" multiple size="8">
                    @foreach($students as $student)
                        <option value="{{ $student->user_id }}" @selected(in_array($student->user_id, $selectedUsers, true))>
                            {{ $student->displayName() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <span class="form-label d-block">{{ __('announcements.channels') }}</span>
                @foreach(\App\Models\Announcement::channelOptions() as $channel)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="channels[{{ $channel }}]" value="1"
                               id="channel_{{ $channel }}"
                               @checked(old("channels.$channel", $channels[$channel] ?? false))>
                        <label class="form-check-label" for="channel_{{ $channel }}">
                            {{ __('announcements.channel_'.$channel) }}
                        </label>
                    </div>
                @endforeach
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="banner_starts_at">{{ __('announcements.banner_starts') }}</label>
                    <input type="datetime-local" name="banner_starts_at" id="banner_starts_at" class="form-control"
                           value="{{ old('banner_starts_at', $announcement?->banner_starts_at ? $announcement->banner_starts_at->format('Y-m-d\TH:i') : '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="banner_ends_at">{{ __('announcements.banner_ends') }}</label>
                    <input type="datetime-local" name="banner_ends_at" id="banner_ends_at" class="form-control"
                           value="{{ old('banner_ends_at', $announcement?->banner_ends_at ? $announcement->banner_ends_at->format('Y-m-d\TH:i') : '') }}">
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary">{{ __('pages.save') }}</button>
        <a href="{{ route('announcements.manage.index') }}" class="btn btn-outline-secondary">{{ __('pages.cancel') }}</a>
    </div>
</form>

@push('scripts')
<script>
document.getElementById('target_mode')?.addEventListener('change', function () {
    const wrap = document.getElementById('target-users-wrap');
    if (!wrap) return;
    wrap.hidden = this.value !== '{{ \App\Models\Announcement::TARGET_USERS }}';
});
document.getElementById('course_id')?.addEventListener('change', function () {
    if (!this.value) return;
    const url = new URL(window.location.href);
    url.searchParams.set('course_id', this.value);
    window.location = url.toString();
});
</script>
@endpush
