<script type="application/json" id="attendance-status-messages">
{!! json_encode([
    'enterPermissionReason' => __('pages.enter_permission_reason'),
    'statusUpdated' => __('pages.status_updated'),
    'statusUpdateError' => __('pages.status_update_error'),
    'permissionUpdated' => __('pages.permission_updated'),
    'permissionUpdateError' => __('pages.permission_update_error'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) !!}
</script>
