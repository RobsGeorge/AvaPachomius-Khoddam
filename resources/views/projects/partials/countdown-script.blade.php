<script>
(function () {
    var nodes = document.querySelectorAll('[data-project-countdown]');
    if (! nodes.length) {
        return;
    }

    var closedLabel = @json(__('projects.join_window_closed_short'));

    function render() {
        nodes.forEach(function (node) {
            var target = new Date(node.getAttribute('data-project-countdown')).getTime();
            var left = target - Date.now();

            if (isNaN(target)) {
                return;
            }

            if (left <= 0) {
                node.textContent = closedLabel;
                return;
            }

            var seconds = Math.floor(left / 1000);
            var days = Math.floor(seconds / 86400);
            var hours = Math.floor((seconds % 86400) / 3600);
            var minutes = Math.floor((seconds % 3600) / 60);
            var parts = [];

            if (days) {
                parts.push(days + 'd');
            }
            if (days || hours) {
                parts.push(hours + 'h');
            }
            parts.push(minutes + 'm');

            node.textContent = parts.join(' ');
        });
    }

    render();
    setInterval(render, 30000);
})();
</script>
