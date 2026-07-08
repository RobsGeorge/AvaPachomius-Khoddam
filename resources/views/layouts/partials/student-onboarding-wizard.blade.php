@if(!empty($showStudentOnboarding) && !empty($studentOnboardingSteps))
@php
    $onboardingLang = $studentOnboardingLocale ?? 'en';
    $onboardingUi = static fn (string $key, array $replace = []) => \Illuminate\Support\Facades\Lang::get('onboarding.'.$key, $replace, $onboardingLang);
    $onboardingLanguageLabel = config('translation.locale_labels.'.$onboardingLang, strtoupper($onboardingLang));
@endphp

@push('modals')
<div class="modal fade" id="studentOnboardingModal" tabindex="-1" aria-hidden="true" data-show-on-load="1"
     x-data="studentOnboardingWizard({
         steps: @json($studentOnboardingSteps),
         total: {{ count($studentOnboardingSteps) }},
         completeUrl: @json(route('onboarding.complete')),
         labels: {
             next: @json($onboardingUi('next')),
             back: @json($onboardingUi('back')),
             skip: @json($onboardingUi('skip')),
             finish: @json($onboardingUi('finish')),
             step: @json($onboardingUi('step_label', ['current' => ':current', 'total' => ':total'])),
         }
     })">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down onboarding-wizard-dialog">
        <div class="modal-content onboarding-wizard-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <p class="text-muted-theme small mb-1" x-text="stepLabel()"></p>
                    <h5 class="modal-title mb-0">{{ $onboardingUi('modal_title') }}</h5>
                </div>
                <button type="button" class="btn-close" aria-label="{{ __('pages.close') }}" @click="dismiss()" :disabled="completing"></button>
            </div>

            <div class="modal-body pt-3">
                <div class="onboarding-progress mb-4" aria-hidden="true">
                    <template x-for="(_, index) in steps" :key="index">
                        <span class="onboarding-progress-dot" :class="{ 'is-active': index === step, 'is-done': index < step }"></span>
                    </template>
                </div>

                <div class="onboarding-step-panel text-center">
                    <div class="onboarding-step-icon mx-auto mb-3">
                        <i class="bi" :class="currentStep.icon"></i>
                    </div>
                    <h6 class="fw-bold mb-3" x-text="currentStep.title"></h6>
                    <p class="text-muted-theme mb-0 onboarding-step-body" x-text="currentStep.body"></p>
                </div>

                <p class="small text-muted-theme text-center mt-4 mb-0">
                    {{ $onboardingUi('language_note', ['language' => $onboardingLanguageLabel]) }}
                </p>
            </div>

            <div class="modal-footer border-0 pt-0 flex-wrap gap-2 justify-content-between">
                <button type="button" class="btn btn-link text-muted-theme text-decoration-none px-0"
                        @click="dismiss()" :disabled="completing">
                    <span x-text="labels.skip"></span>
                </button>
                <div class="d-flex gap-2 ms-auto">
                    <button type="button" class="btn btn-outline-secondary" x-show="step > 0" @click="back()" :disabled="completing">
                        <span x-text="labels.back"></span>
                    </button>
                    <button type="button" class="btn btn-primary" x-show="step < total - 1" @click="next()" :disabled="completing">
                        <span x-text="labels.next"></span>
                    </button>
                    <button type="button" class="btn btn-primary" x-show="step === total - 1" @click="finish()" :disabled="completing">
                        <span x-text="labels.finish"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('studentOnboardingWizard', (config) => ({
        steps: config.steps,
        total: config.total,
        completeUrl: config.completeUrl,
        labels: config.labels,
        step: 0,
        completing: false,
        modal: null,

        get currentStep() {
            return this.steps[this.step] ?? this.steps[0];
        },

        stepLabel() {
            return this.labels.step
                .replace(':current', String(this.step + 1))
                .replace(':total', String(this.total));
        },

        init() {
            this.$nextTick(() => {
                const el = document.getElementById('studentOnboardingModal');
                if (!el || el.dataset.showOnLoad !== '1') {
                    return;
                }

                this.modal = bootstrap.Modal.getOrCreateInstance(el, {
                    backdrop: 'static',
                    keyboard: false,
                });
                this.modal.show();
            });
        },

        next() {
            if (this.step < this.total - 1) {
                this.step += 1;
            }
        },

        back() {
            if (this.step > 0) {
                this.step -= 1;
            }
        },

        finish() {
            this.complete(true);
        },

        dismiss() {
            this.complete(false);
        },

        complete(fromFinish) {
            if (this.completing) {
                return;
            }

            this.completing = true;

            fetch(this.completeUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.content ?? '',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ finished: fromFinish }),
            }).finally(() => {
                this.completing = false;
                this.modal?.hide();
            });
        },
    }));
});
</script>
@endpush
@endif
