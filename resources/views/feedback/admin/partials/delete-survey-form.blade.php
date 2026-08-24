@if($survey->canStaffDelete())
    <form method="POST"
          action="{{ route('feedback.surveys.destroy', $survey) }}"
          class="{{ $class ?? 'd-inline' }}"
          data-confirm="{{ __('pages.confirm_delete_survey') }}">
        @csrf
        @method('DELETE')
        <button type="submit" class="{{ $buttonClass ?? 'btn btn-outline-danger btn-sm' }}">
            <i class="bi bi-trash"></i> {{ __('pages.delete_survey') }}
        </button>
    </form>
@endif
