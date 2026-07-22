<?php

return [
    'background' => '#0f4c5c',
    'foreground' => '#f7f3e9',
    'corner_radius' => 12,
    'icon_size' => 36,

    /**
     * Routes outside NavigationHub that still have a stable entry icon.
     *
     * @var array<string, string>
     */
    'route_icons' => [
        'notifications.*' => 'bi-bell',
        'help.*' => 'bi-question-circle',
        'account.*' => 'bi-person-circle',
    ],
];
