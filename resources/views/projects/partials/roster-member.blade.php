@php
    $member = $member ?? null;
    $photo = $member && $member->profile_photo ? asset('storage/'.$member->profile_photo) : null;
    $mobile = $member ? trim((string) $member->mobile_number) : '';
@endphp
@if($member)
    <li class="d-flex align-items-center gap-2 mb-3">
        @if($photo)
            <img src="{{ $photo }}"
                 alt="{{ $member->displayName() }}"
                 width="40" height="40"
                 class="rounded-circle object-fit-cover flex-shrink-0">
        @else
            <span class="rounded-circle bg-body-secondary d-inline-flex align-items-center justify-content-center flex-shrink-0"
                  style="width:40px;height:40px;" aria-hidden="true">
                <i class="bi bi-person"></i>
            </span>
        @endif
        <span class="min-w-0">
            <span class="fw-semibold d-block">{{ $member->displayName() }}</span>
            <span class="small text-muted d-block">
                @if($mobile !== '')
                    <a href="tel:{{ $mobile }}" class="text-decoration-none">{{ $mobile }}</a>
                @else
                    {{ __('projects.phone_missing') }}
                @endif
                @if($member->email)
                    · <a href="mailto:{{ $member->email }}" class="text-decoration-none">{{ __('projects.contact_email') }}</a>
                @endif
            </span>
        </span>
    </li>
@endif
