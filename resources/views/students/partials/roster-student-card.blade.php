@props(['student', 'whatsappMessage' => null, 'showAge' => false])

<article class="data-card {{ $showAge ? '' : 'mb-3' }}{{ $student->isBirthdayToday() ? ' data-card-birthday-today' : '' }}">
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
            <dt>{{ __('pages.national_id') }}</dt>
            <dd>{{ $student->national_id ?? '—' }}</dd>
        </div>
        <div class="data-meta-row">
            <dt>{{ __('pages.email') }}</dt>
            <dd>{{ $student->email ?? '—' }}</dd>
        </div>
        <div class="data-meta-row">
            <dt>{{ __('pages.phone') }}</dt>
            <dd>{{ $student->formattedMobile() ?? '—' }}</dd>
        </div>
        <div class="data-meta-row">
            <dt>{{ __('pages.birth_date') }}</dt>
            <dd>
                @if($student->date_of_birth)
                    {{ $student->date_of_birth->format('d/m/Y') }}
                    @if($showAge)
                        <span class="text-muted small">({{ $student->date_of_birth->age }} {{ __('students.years_old') }})</span>
                    @endif
                @else
                    —
                @endif
            </dd>
        </div>
    </dl>

    @include('students.partials.contact-actions', [
        'user' => $student,
        'whatsappMessage' => $whatsappMessage,
    ])
</article>
