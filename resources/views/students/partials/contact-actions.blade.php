@props(['user', 'whatsappMessage' => null])

<div class="d-flex flex-wrap gap-2">
    @if($user->email)
        <a href="mailto:{{ $user->email }}" class="btn btn-sm btn-outline-theme">
            <i class="bi bi-envelope"></i> {{ __('students.email') }}
        </a>
    @endif
    @if($user->telUrl())
        <a href="{{ $user->telUrl() }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-telephone"></i> {{ __('students.call') }}
        </a>
    @endif
    @if($user->whatsappUrl($whatsappMessage))
        <a href="{{ $user->whatsappUrl($whatsappMessage) }}" class="btn btn-sm btn-success" target="_blank" rel="noopener noreferrer">
            <i class="bi bi-whatsapp"></i> {{ __('students.whatsapp') }}
        </a>
    @endif
</div>
