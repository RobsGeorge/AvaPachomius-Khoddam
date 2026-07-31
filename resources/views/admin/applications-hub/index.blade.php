@extends('layouts.app')

@section('title', __('applications_hub.title'))

@section('content')
@php
    use App\Support\Applications\ApplicationQueueItem;
@endphp
<div class="container-fluid py-4 animate-in student-data-hub">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('applications_hub.title') }}</h1>
            <p class="text-muted-theme mb-0">
                {{ $isPlatformReviewer ? __('applications_hub.intro_platform') : __('applications_hub.intro_scoped') }}
            </p>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3 admin-filter-bar">
        <a href="{{ route('admin.applications-hub.index', array_filter(['filter' => $filter])) }}"
           class="btn btn-sm {{ ! $typeFilter ? 'btn-primary' : 'btn-outline-secondary' }}">
            {{ __('applications_hub.type_all') }} ({{ array_sum($typeCounts) }})
        </a>
        @foreach(ApplicationQueueItem::types() as $typeKey)
            @continue(! ($canSee[$typeKey] ?? false))
            <a href="{{ route('admin.applications-hub.index', array_filter(['type' => $typeKey, 'filter' => $filter])) }}"
               class="btn btn-sm {{ $typeFilter === $typeKey ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ __('applications_hub.type_'.$typeKey) }} ({{ $typeCounts[$typeKey] ?? 0 }})
            </a>
        @endforeach
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3 admin-filter-bar">
        <a href="{{ route('admin.applications-hub.index', array_filter(['type' => $typeFilter])) }}"
           class="btn btn-sm {{ ! $filter ? 'btn-primary' : 'btn-outline-secondary' }}">
            {{ __('applications_hub.filter_all') }} ({{ array_sum($counts) }})
        </a>
        @foreach($statusKeys as $statusKey)
            <a href="{{ route('admin.applications-hub.index', array_filter(['type' => $typeFilter, 'filter' => $statusKey])) }}"
               class="btn btn-sm {{ $filter === $statusKey ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ __('applications_hub.status_'.$statusKey) }} ({{ $counts[$statusKey] ?? 0 }})
            </a>
        @endforeach
    </div>

    <div class="table-responsive app-card card shadow-sm mb-3">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('applications_hub.type') }}</th>
                    <th>{{ __('applications_hub.applicant') }}</th>
                    <th>{{ __('applications_hub.subject') }}</th>
                    <th>{{ __('applications_hub.submitted_at') }}</th>
                    <th>{{ __('applications_hub.status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>
                            <span class="badge bg-secondary">{{ __('applications_hub.type_'.$item->type) }}</span>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $item->applicantLabel }}</div>
                            @if(filled($item->applicantSecondary))
                                <div class="small text-muted-theme">{{ $item->applicantSecondary }}</div>
                            @endif
                        </td>
                        <td>{{ $item->subjectLabel }}</td>
                        <td>{{ $item->submittedAt?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>
                            <span class="badge bg-secondary">{{ __('applications_hub.status_'.$item->status) }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ $item->showUrl }}" class="btn btn-sm btn-primary">
                                {{ __('applications_hub.review') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted-theme py-4">
                            @if($filter)
                                {{ __('applications_hub.no_applications') }}
                            @elseif($isPlatformReviewer)
                                {{ __('applications_hub.no_applications_platform') }}
                            @else
                                {{ __('applications_hub.no_applications_scoped') }}
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($items->hasPages())
            <div class="card-body">{{ $items->links() }}</div>
        @endif
    </div>

    <div class="small text-muted-theme">
        <span class="fw-semibold">{{ __('applications_hub.per_type_queues') }}:</span>
        @if($canSee[ApplicationQueueItem::TYPE_COURSE] ?? false)
            <a href="{{ route('admin.course-applications.index') }}">{{ __('applications_hub.type_course') }}</a>
        @endif
        @if($canSee[ApplicationQueueItem::TYPE_SERVICE] ?? false)
            · <a href="{{ route('admin.service-applications.index') }}">{{ __('applications_hub.type_service') }}</a>
        @endif
        @if($canSee[ApplicationQueueItem::TYPE_CHURCH] ?? false)
            · <a href="{{ route('superadmin.church-applications.index') }}">{{ __('applications_hub.type_church') }}</a>
        @endif
    </div>
</div>
@endsection
