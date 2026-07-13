@php
    use App\Models\RoleAssignmentEmailTemplate;
    $open = $section === 'email-templates';
@endphp
<div class="accordion-item app-card card shadow-sm mb-2 border-0">
    <h2 class="accordion-header">
        <button class="accordion-button {{ $open ? '' : 'collapsed' }} py-2" type="button"
                data-bs-toggle="collapse" data-bs-target="#section-email-templates" aria-expanded="{{ $open ? 'true' : 'false' }}">
            <i class="bi bi-envelope-paper me-2"></i>
            <span class="fw-semibold">{{ __('rbac.section_email_templates') }}</span>
        </button>
    </h2>
    <div id="section-email-templates" class="accordion-collapse collapse {{ $open ? 'show' : '' }}" data-bs-parent="#rolesHubAccordion">
        <div class="accordion-body py-2 px-3">
            <p class="text-muted-theme small mb-3">{{ __('rbac.email_templates_intro') }}</p>
            <p class="small text-muted-theme">{{ __('rbac.email_placeholders_help') }}</p>

            <form method="POST" action="{{ route('roles.hub.email-templates.update') }}">
                @csrf
                @method('PUT')

                @foreach(RoleAssignmentEmailTemplate::keys() as $templateKey)
                    <details class="roles-hub-panel mb-3" @if($loop->first) open @endif>
                        <summary class="roles-hub-summary">{{ __('rbac.email_subjects.'.$templateKey) }}</summary>
                        <div class="pt-2">
                            @foreach($emailTemplates[$templateKey] ?? [] as $template)
                                <div class="roles-hub-subpanel mb-3">
                                    <div class="fw-semibold small mb-2">{{ strtoupper($template->locale) }}</div>
                                    <div class="mb-2">
                                        <label class="form-label small mb-1">{{ __('rbac.email_subject_label') }}</label>
                                        <input type="text" name="templates[{{ $template->id }}][subject]"
                                               class="form-control form-control-sm"
                                               value="{{ old("templates.{$template->id}.subject", $template->subject) }}" required>
                                    </div>
                                    <div>
                                        <label class="form-label small mb-1">HTML</label>
                                        <textarea name="templates[{{ $template->id }}][body_html]" rows="5"
                                                  class="form-control form-control-sm font-monospace" required>{{ old("templates.{$template->id}.body_html", $template->body_html) }}</textarea>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endforeach

                <button type="submit" class="btn btn-primary btn-sm">{{ __('app.save') }}</button>
            </form>
        </div>
    </div>
</div>
