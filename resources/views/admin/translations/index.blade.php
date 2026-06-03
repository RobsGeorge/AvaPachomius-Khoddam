@extends('layouts.app')

@section('title', __('admin.translations'))

@section('content')
<div class="animate-in">
    <h1 class="page-title">{{ __('admin.translations') }}</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if($translationsTableMissing ?? false)
        <div class="alert alert-warning">
            {{ __('admin.translations_table_missing') }}
        </div>
    @endif

    <div class="app-card card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">{{ __('admin.locale') }}</label>
                    <select name="locale" class="form-select" onchange="this.form.submit()">
                        @foreach(config('translation.supported_locales', ['ar', 'en']) as $code)
                            <option value="{{ $code }}" @selected($locale === $code)>
                                {{ config('translation.locale_labels.' . $code) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('admin.group') }}</label>
                    <select name="group" class="form-select" onchange="this.form.submit()">
                        @foreach($groups as $groupName)
                            <option value="{{ $groupName }}" @selected($group === $groupName)>{{ $groupName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('app.search') }}</label>
                    <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('admin.search_placeholder') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">{{ __('app.search') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="app-card card">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>{{ __('admin.key') }}</th>
                        <th>{{ __('admin.file_default') }}</th>
                        <th>{{ __('admin.db_override') }}</th>
                        <th>{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($keys as $key)
                        @php
                            $default = $fileLines[$key] ?? '';
                            $override = $dbLines[$key] ?? '';
                            $otherLocale = $locale === 'ar' ? 'en' : 'ar';
                        @endphp
                        <tr>
                            <td><code>{{ $key }}</code></td>
                            <td class="text-muted-theme">{{ $default }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.translations.store') }}" class="d-flex gap-2">
                                    @csrf
                                    <input type="hidden" name="group" value="{{ $group }}">
                                    <input type="hidden" name="key" value="{{ $key }}">
                                    <input type="hidden" name="locale" value="{{ $locale }}">
                                    <input type="text" name="value" class="form-control form-control-sm"
                                           value="{{ old('value', $override ?: $default) }}">
                                    <button type="submit" class="btn btn-sm btn-primary">{{ __('admin.add_override') }}</button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.translations.auto') }}">
                                    @csrf
                                    <input type="hidden" name="group" value="{{ $group }}">
                                    <input type="hidden" name="key" value="{{ $key }}">
                                    <input type="hidden" name="from_locale" value="{{ $otherLocale }}">
                                    <input type="hidden" name="to_locale" value="{{ $locale }}">
                                    <button type="submit" class="btn btn-sm btn-outline-theme">
                                        <i class="bi bi-stars"></i> {{ __('admin.auto_translate') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted-theme py-4">{{ __('pages.no_keys') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
