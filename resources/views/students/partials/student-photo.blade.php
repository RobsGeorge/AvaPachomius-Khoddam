@props(['student'])

@if($student->profile_photo)
    <div class="student-roster-photo flex-shrink-0">
        <img src="{{ asset('storage/' . $student->profile_photo) }}"
             alt="{{ $student->displayName() }}"
             class="rounded-circle object-fit-cover">
    </div>
@endif
