<?php
$navItems = [
    'natives' => [
        'label' => '📋 Natives',
        'url' => 'index.php',
        'active' => false
    ],
    'tools' => [
        'label' => '🔧 Tools',
        'url' => '#',
        'active' => false,
        'submenu' => [
            ['label' => '🔄 List Converter', 'url' => 'converter.php'],
			['label' => '⚡ Script Generator', 'url' => 'creator.php'],
        ]
    ],
    'documentation' => [
        'label' => '📚 Docs',
        'url' => '#',
        'active' => false
    ],
    'about' => [
        'label' => 'ℹ️ About',
        'url' => '#',
        'active' => false
    ]
];
?>