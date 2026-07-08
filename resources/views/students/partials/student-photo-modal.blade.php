<div class="modal fade" id="studentPhotoModal" tabindex="-1" aria-labelledby="studentPhotoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="studentPhotoModalLabel">{{ __('pages.profile_photo_modal_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('pages.close') }}"></button>
            </div>
            <div class="modal-body text-center">
                <p class="fw-semibold mb-3" id="studentPhotoModalName"></p>
                <img src="" alt="" id="studentPhotoModalImage" class="img-fluid rounded-circle student-photo-modal-image">
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('studentPhotoModal');
        if (!modal) return;

        modal.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            if (!trigger) return;

            const url = trigger.getAttribute('data-photo-url');
            const name = trigger.getAttribute('data-photo-name');
            const image = document.getElementById('studentPhotoModalImage');
            const title = document.getElementById('studentPhotoModalName');

            if (image && url) {
                image.src = url;
                image.alt = name || '';
            }
            if (title) {
                title.textContent = name || '';
            }
        });
    });
    </script>
    @endpush
@endonce
