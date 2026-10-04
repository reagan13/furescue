<?php

declare(strict_types=1);

/**
 * Single source of admin navigation.
 *
 * Provides PHP data only. No page HTML. The admin shell
 * (views/layouts/admin.php) consumes this to render real links.
 *
 * Each item carries enough metadata for the shell:
 *   key      lowercase nav key, matched against $activeNav
 *   label    visible label
 *   href     real, navigable URL
 *   icon     lucide icon name
 *   group    owning group label (implicit via nesting)
 *   badgeKey optional dynamic badge key (resolved server-side)
 *   badgeCls optional badge modifier class
 *
 * Routes that are part of the folder-per-page refactor point at their
 * directory URL (/admin/<page>/). Routes not yet migrated keep their
 * existing targets so navigation does not break.
 */

$adminNav = [
    [
        'label' => 'Overview',
        'items' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/admin/', 'icon' => 'layout-dashboard'],
            ['key' => 'analytics', 'label' => 'Analytics', 'href' => '/admin/analytics/', 'icon' => 'bar-chart-3'],
        ],
    ],
    [
        'label' => 'Rescue Management',
        'items' => [
            ['key' => 'reports', 'label' => 'Reports', 'href' => '/admin/reports/', 'icon' => 'map-pin', 'badgeKey' => 'reports', 'badgeCls' => 'stamp--accent'],
            ['key' => 'cases', 'label' => 'Cases', 'href' => '/admin/cases/', 'icon' => 'clipboard-list', 'badgeKey' => 'cases'],
            ['key' => 'rescuers', 'label' => 'Rescuers', 'href' => '/admin/rescuers/', 'icon' => 'siren', 'badgeKey' => 'rescuers'],
        ],
    ],
    [
        'label' => 'Animal Management',
        'items' => [
            ['key' => 'animals', 'label' => 'Animals', 'href' => '/admin/animals/', 'icon' => 'paw-print'],
            ['key' => 'health records', 'label' => 'Health Records', 'href' => '/admin/health-records/', 'icon' => 'heart-pulse', 'badgeKey' => 'health', 'badgeCls' => 'stamp--muted'],
        ],
    ],
    [
        'label' => 'Adoption',
        'items' => [
            ['key' => 'listings', 'label' => 'Listings', 'href' => '/admin/listings/', 'icon' => 'home'],
            ['key' => 'applications', 'label' => 'Applications', 'href' => '/admin/applications/', 'icon' => 'file-check', 'badgeKey' => 'applications', 'badgeCls' => 'stamp--accent'],
        ],
    ],
    [
        'label' => 'Content',
        'items' => [
            ['key' => 'e-learning', 'label' => 'E-Learning', 'href' => '/admin/elearning/', 'icon' => 'book-open'],
        ],
    ],
    [
        'label' => 'Communication',
        'items' => [
            ['key' => 'messages', 'label' => 'Messages', 'href' => '/admin/messages/', 'icon' => 'message-square'],
            ['key' => 'notifications', 'label' => 'Notifications', 'href' => '/admin/notifications/', 'icon' => 'bell', 'badgeKey' => 'notifications', 'badgeCls' => 'stamp--coral'],
        ],
    ],
    [
        'label' => 'System',
        'items' => [
            ['key' => 'users', 'label' => 'Users', 'href' => '/admin/users/', 'icon' => 'users'],
            ['key' => 'account', 'label' => 'Account', 'href' => '/account/', 'icon' => 'user'],
        ],
    ],
];
