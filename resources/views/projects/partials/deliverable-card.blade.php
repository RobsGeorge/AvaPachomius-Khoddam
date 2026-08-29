@php
    /** @var \App\Models\ProjectDeliverable $deliverable */
    $deliverable = $row['deliverable'];
    $submission = $row['submission'];
    $formId = 'deliverable-form-'.$deliverable->project_deliverable_id;
    $maxMb = (int) round(\App\Models\ProjectDeliverable::MAX_UPLOAD_KB / 1024);
    $extensions = \App\Models\ProjectDeliverable::extensionsFor($deliverable->type());
@endphp
<div class="border-bottom py-3">
    <div class="d-flex flex-wrap justify-content-between gap-2">
        <div>
            <div class="fw-semibold">
                {{ $deliverable->title }}
                <span class="badge bg-light text-dark border">{{ __('projects.submission_type_'.$deliverable->type()) }}</span>
            </div>
            @if($deliverable->due_at)
                <div class="small text-muted">{{ __('projects.due_at') }}: {{ $deliverable->due_at->format('Y-m-d H:i') }}</div>
            @endif
        </div>
        <div class="d-flex flex-wrap align-items-start gap-1">
            @if(! $deliverable->is_required)
                <span class="badge bg-secondary">{{ __('projects.optional_deliverable') }}</span>
            @endif
            @if($row['submitted'])
                <span class="badge bg-success">{{ __('projects.submitted') }}</span>
                @if($row['late'])
                    <span class="badge bg-warning text-dark">{{ __('projects.late') }}</span>
                @endif
            @elseif($row['overdue'])
                <span class="badge bg-danger">{{ __('projects.overdue') }}</span>
            @else
                <span class="badge bg-light text-dark border">{{ __('projects.not_submitted') }}</span>
            @endif
        </div>
    </div>

    @if($deliverable->description)
        <div class="small mt-2" style="white-space: pre-wrap;">{{ $deliverable->description }}</div>
    @endif
    @if($deliverable->instructions)
        <div class="small text-muted mt-1" style="white-space: pre-wrap;">
            <span class="fw-semibold">{{ __('projects.instructions') }}:</span> {{ $deliverable->instructions }}
        </div>
    @endif

    @if($submission)
        <div class="mt-2 small">
            <div class="text-muted">
                {{ __('projects.submitted_at', ['when' => $submission->submitted_at?->diffForHumans() ?? '—']) }}
                @if($submission->submitter)
                    · {{ __('projects.submitted_by', ['name' => $submission->submitter->displayName()]) }}
                @endif
            </div>
            @if($submission->link_url)
                <div class="mt-1">
                    <a href="{{ $submission->link_url }}" target="_blank" rel="noopener noreferrer">{{ $submission->link_url }}</a>
                </div>
            @endif
            @if($submission->body)
                <div class="mt-1" style="white-space: pre-wrap;">{{ $submission->body }}</div>
            @endif
            @if($submission->files->isNotEmpty())
                <div class="mt-1">
                    <div class="text-muted">{{ __('projects.submission_files') }}</div>
                    <ul class="list-unstyled mb-0">
                        @foreach($submission->files as $file)
                            <li class="d-flex flex-wrap align-items-center gap-2">
                                <a href="{{ $file->url() }}" target="_blank" rel="noopener noreferrer">{{ $file->displayName() }}</a>
                                @if(($isMember ?? false) && $row['open'])
                                    <form method="POST"
                                          action="{{ route('projects.submission-files.destroy', [$project, $file]) }}"
                                          onsubmit="return confirm(@json(__('projects.confirm_remove_file')));">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link btn-sm text-danger p-0">{{ __('projects.remove_file') }}</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    @if(($isMember ?? false))
        @if(! $row['open'])
            <div class="small text-muted mt-2">{{ __('projects.submission_closed_hint') }}</div>
        @else
            <form method="POST"
                  action="{{ route('projects.deliverables.submit', [$project, $deliverable]) }}"
                  enctype="multipart/form-data"
                  class="mt-2"
                  id="{{ $formId }}">
                @csrf
                @if($deliverable->expectsLink())
                    <label class="form-label small" for="{{ $formId }}-link">{{ __('projects.submission_link') }}</label>
                    <input type="url"
                           id="{{ $formId }}-link"
                           name="link_url"
                           class="form-control form-control-sm @error('link_url') is-invalid @enderror"
                           value="{{ old('link_url', $submission->link_url ?? '') }}"
                           placeholder="https://">
                    @error('link_url')<div class="invalid-feedback d-block small">{{ $message }}</div>@enderror
                @elseif($deliverable->expectsText())
                    <label class="form-label small" for="{{ $formId }}-body">{{ __('projects.submission_text') }}</label>
                    <textarea id="{{ $formId }}-body"
                              name="body"
                              rows="4"
                              class="form-control form-control-sm @error('body') is-invalid @enderror">{{ old('body', $submission->body ?? '') }}</textarea>
                    @error('body')<div class="invalid-feedback d-block small">{{ $message }}</div>@enderror
                @else
                    <label class="form-label small" for="{{ $formId }}-files">
                        {{ $deliverable->allowsMultipleFiles() ? __('projects.add_files') : __('projects.choose_file') }}
                    </label>
                    <input type="file"
                           id="{{ $formId }}-files"
                           name="files[]"
                           class="form-control form-control-sm @error('files') is-invalid @enderror @error('files.*') is-invalid @enderror"
                           accept=".{{ implode(',.', $extensions) }}"
                           @if($deliverable->allowsMultipleFiles()) multiple @endif>
                    <div class="form-text small">
                        {{ __('projects.submission_allowed_types', ['types' => implode(', ', $extensions)]) }}
                        · {{ __('projects.submission_max_size', ['max' => $maxMb]) }}
                        @if($deliverable->allowsMultipleFiles())
                            · {{ __('projects.file_mode_multi', ['max' => $deliverable->maxFiles()]) }}
                        @endif
                    </div>
                    @error('files')<div class="invalid-feedback d-block small">{{ $message }}</div>@enderror
                    @error('files.*')<div class="invalid-feedback d-block small">{{ $message }}</div>@enderror

                    <label class="form-label small mt-2" for="{{ $formId }}-note">{{ __('projects.submission_note') }}</label>
                    <textarea id="{{ $formId }}-note"
                              name="body"
                              rows="2"
                              class="form-control form-control-sm">{{ old('body', $submission->body ?? '') }}</textarea>

                    @if($deliverable->allowsMultipleFiles() && $submission && $submission->files->isNotEmpty())
                        <div class="form-check mt-2">
                            <input type="hidden" name="replace_files" value="0">
                            <input class="form-check-input" type="checkbox" value="1" name="replace_files" id="{{ $formId }}-replace">
                            <label class="form-check-label small" for="{{ $formId }}-replace">{{ __('projects.replace_submission') }}</label>
                        </div>
                    @endif
                @endif

                @error('deliverable')<div class="alert alert-danger py-2 small mt-2">{{ $message }}</div>@enderror

                <button type="submit" class="btn btn-sm btn-primary mt-2">
                    {{ $row['submitted'] ? __('projects.replace_submission') : __('projects.submit_deliverable') }}
                </button>
            </form>
        @endif
    @endif
</div>
