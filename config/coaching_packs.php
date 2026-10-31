<?php

/*
|--------------------------------------------------------------------------
| Coaching packs — canonical source
|--------------------------------------------------------------------------
|
| Versioned rule packs consumed by the app's pose-analysis engine. The API is
| the authoring point: bump a pack's `version` and the app picks it up over the
| air (the app's bundled copy is only the offline fallback for v1).
|
| Measures: angle (world-landmark joint °), height (1-y), distance (2D euclid),
| offset (|axis delta|), ratio (distance/distance), position (raw coord for
| variance). Aggregates: min|max|mean|variance. Phases are clip fractions.
| Thresholds are v1 calibrations pending golden-clip tuning.
|
*/

$phases = [
    'setup' => [0, 0.3],
    'action' => [0.3, 0.7],
    'contact' => [0.6, 0.8],
    'recovery' => [0.8, 1],
];

$elbow = fn (string $side) => [
    'a' => "{$side}Shoulder",
    'vertex' => "{$side}Elbow",
    'end' => "{$side}Wrist",
];

$knee = fn (string $side) => [
    'a' => "{$side}Hip",
    'vertex' => "{$side}Knee",
    'end' => "{$side}Ankle",
];

return [
    'packs' => [
        [
            'version' => 1,
            'discipline' => 'batting',
            'phases' => $phases,
            'metrics' => [
                ['key' => 'stance_width', 'phase' => 'setup', 'measure' => ['kind' => 'ratio', 'numA' => 'leftAnkle', 'numB' => 'rightAnkle', 'denA' => 'leftShoulder', 'denB' => 'leftAnkle'], 'aggregate' => 'mean', 'unit' => 'norm'],
                ['key' => 'back_knee_flex', 'phase' => 'action', 'measure' => ['kind' => 'angle', ...$knee('right')], 'aggregate' => 'min', 'unit' => 'deg'],
                ['key' => 'head_stability', 'phase' => 'contact', 'measure' => ['kind' => 'position', 'landmark' => 'nose', 'axis' => 'x'], 'aggregate' => 'variance', 'unit' => 'norm'],
                ['key' => 'elbow_extension', 'phase' => 'contact', 'measure' => ['kind' => 'angle', ...$elbow('left')], 'aggregate' => 'max', 'unit' => 'deg'],
            ],
            'checks' => [
                ['metric' => 'stance_width', 'op' => 'outside', 'value' => [0.12, 0.5], 'severity' => 'tip', 'code' => 'batting.stance'],
                ['metric' => 'head_stability', 'op' => 'gt', 'value' => 0.002, 'severity' => 'warn', 'code' => 'batting.head'],
                ['metric' => 'elbow_extension', 'op' => 'lt', 'value' => 150, 'severity' => 'tip', 'code' => 'batting.elbow'],
                ['metric' => 'back_knee_flex', 'op' => 'gt', 'value' => 175, 'severity' => 'tip', 'code' => 'batting.knee'],
            ],
        ],
        [
            'version' => 1,
            'discipline' => 'bowling',
            'phases' => $phases,
            'metrics' => [
                ['key' => 'elbow_flex_min', 'phase' => 'action', 'measure' => ['kind' => 'angle', ...$elbow('right')], 'aggregate' => 'min', 'unit' => 'deg'],
                ['key' => 'release_height', 'phase' => 'contact', 'measure' => ['kind' => 'height', 'landmark' => 'rightWrist'], 'aggregate' => 'max', 'unit' => 'norm'],
                ['key' => 'front_brace', 'phase' => 'contact', 'measure' => ['kind' => 'angle', ...$knee('left')], 'aggregate' => 'max', 'unit' => 'deg'],
                ['key' => 'head_stability', 'phase' => 'action', 'measure' => ['kind' => 'position', 'landmark' => 'nose', 'axis' => 'x'], 'aggregate' => 'variance', 'unit' => 'norm'],
            ],
            'checks' => [
                ['metric' => 'elbow_flex_min', 'op' => 'lt', 'value' => 165, 'severity' => 'warn', 'code' => 'bowling.elbow'],
                ['metric' => 'release_height', 'op' => 'lt', 'value' => 0.72, 'severity' => 'tip', 'code' => 'bowling.release'],
                ['metric' => 'front_brace', 'op' => 'lt', 'value' => 165, 'severity' => 'tip', 'code' => 'bowling.brace'],
                ['metric' => 'head_stability', 'op' => 'gt', 'value' => 0.004, 'severity' => 'tip', 'code' => 'bowling.head'],
            ],
        ],
        [
            'version' => 1,
            'discipline' => 'fielding',
            'phases' => $phases,
            'metrics' => [
                ['key' => 'pickup_depth', 'phase' => 'contact', 'measure' => ['kind' => 'angle', ...$knee('left')], 'aggregate' => 'min', 'unit' => 'deg'],
                ['key' => 'ready_crouch', 'phase' => 'setup', 'measure' => ['kind' => 'angle', ...$knee('left')], 'aggregate' => 'mean', 'unit' => 'deg'],
                ['key' => 'throw_extension', 'phase' => 'recovery', 'measure' => ['kind' => 'angle', ...$elbow('right')], 'aggregate' => 'max', 'unit' => 'deg'],
                ['key' => 'head_over_ball', 'phase' => 'contact', 'measure' => ['kind' => 'offset', 'a' => 'nose', 'b' => 'leftHip', 'axis' => 'x'], 'aggregate' => 'max', 'unit' => 'norm'],
            ],
            'checks' => [
                ['metric' => 'ready_crouch', 'op' => 'gt', 'value' => 160, 'severity' => 'tip', 'code' => 'fielding.ready'],
                ['metric' => 'pickup_depth', 'op' => 'gt', 'value' => 150, 'severity' => 'warn', 'code' => 'fielding.bend'],
                ['metric' => 'throw_extension', 'op' => 'lt', 'value' => 140, 'severity' => 'tip', 'code' => 'fielding.throw'],
                ['metric' => 'head_over_ball', 'op' => 'lt', 'value' => 0.05, 'severity' => 'tip', 'code' => 'fielding.over'],
            ],
        ],
        [
            'version' => 1,
            'discipline' => 'keeping',
            'phases' => $phases,
            'metrics' => [
                ['key' => 'crouch_depth', 'phase' => 'setup', 'measure' => ['kind' => 'angle', ...$knee('left')], 'aggregate' => 'mean', 'unit' => 'deg'],
                ['key' => 'gloves_height', 'phase' => 'contact', 'measure' => ['kind' => 'height', 'landmark' => 'leftWrist'], 'aggregate' => 'min', 'unit' => 'norm'],
                ['key' => 'head_stability', 'phase' => 'contact', 'measure' => ['kind' => 'position', 'landmark' => 'nose', 'axis' => 'x'], 'aggregate' => 'variance', 'unit' => 'norm'],
            ],
            'checks' => [
                ['metric' => 'crouch_depth', 'op' => 'gt', 'value' => 150, 'severity' => 'warn', 'code' => 'keeping.crouch'],
                ['metric' => 'head_stability', 'op' => 'gt', 'value' => 0.003, 'severity' => 'tip', 'code' => 'keeping.rise'],
                ['metric' => 'gloves_height', 'op' => 'gt', 'value' => 0.55, 'severity' => 'tip', 'code' => 'keeping.gloves'],
            ],
        ],
    ],
];
