@props(['student', 'whatsappMessage' => null])

<article class="data-card mb-3{{ $student->isBirthdayToday() ? ' data-card-birthday-today' : '' }}">
    <div class="d-flex align-items-center gap-3 mb-3">
        @include('students.partials.student-photo', ['student' => $student])
        <div class="data-card-title mb-0">
            {{ $student->displayName() }}
            @if($student->isBirthdayToday())
                <span class="badge bg-success ms-1">{{ __('students.birthday_today') }}</span>
            @endif
        </div>
    </div>
    <dl class="data-meta-list mb-2">
        <div class="data-meta-row">
            <dt>{{ __('pages.birth_date') }}</dt>
            <dd>{{ $student->date_of_birth?->format('d/m') ?? '—' }}</dd>
        </div>
    </dl>
    @include('students.partials.contact-actions', [
        'user' => $student,
        'whatsappMessage' => $whatsappMessage,
        'showEmail' => false,
    ])
</article>
