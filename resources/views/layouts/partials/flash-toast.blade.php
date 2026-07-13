@php
    $khoddamFlash = array_filter([
        'success' => session('success'),
        'error' => session('error'),
        'warning' => session('warning'),
        'info' => session('info'),
        'status' => session('status'),
    ], fn ($value) => filled($value));

    if (isset($errors) && $errors->any()) {
        $khoddamFlash['validation'] = $errors->all();
    }

    $khoddamUiConfig = [
        'locale' => app()->getLocale(),
        'dir' => locale_dir(),
        'confirmTitle' => __('ui.confirm_title'),
        'confirmYes' => __('ui.confirm_yes'),
        'confirmCancel' => __('ui.confirm_cancel'),
        'deleteTitle' => __('ui.delete_title'),
        'deleteYes' => __('ui.delete_yes'),
        'toastSuccess' => __('ui.toast_success'),
        'toastError' => __('ui.toast_error'),
        'toastWarning' => __('ui.toast_warning'),
        'toastInfo' => __('ui.toast_info'),
        'validationTitle' => __('ui.validation_title'),
        'promptTitle' => __('ui.prompt_title'),
        'promptPlaceholder' => __('ui.prompt_placeholder'),
        'promptSubmit' => __('ui.prompt_submit'),
    ];
@endphp
@if(! empty($khoddamFlash))
    <script id="khoddam-flash-messages" type="application/json">@json($khoddamFlash)</script>
@endif
<script id="khoddam-ui-config" type="application/json">@json($khoddamUiConfig)</script>
