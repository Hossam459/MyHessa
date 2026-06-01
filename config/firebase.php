<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Firebase project
    |--------------------------------------------------------------------------
    |
    | Project key resolved by kreait/laravel-firebase. Must match one of the
    | keys defined under `projects` below.
    |
    */

    'default' => env('FIREBASE_PROJECT', 'app'),

    'projects' => [

        'app' => [

            /*
            |------------------------------------------------------------------
            | Credentials / Service Account
            |------------------------------------------------------------------
            |
            | Absolute path (or path relative to the project root) to the
            | Firebase service-account JSON file. Falls back to
            | GOOGLE_APPLICATION_CREDENTIALS, then to the file stored under
            | storage/firebase-credentials.json.
            |
            */

            'credentials' => env(
                'FIREBASE_CREDENTIALS',
                env('GOOGLE_APPLICATION_CREDENTIALS', storage_path('firebase-credentials.json'))
            ),

            'project_id' => env('FIREBASE_PROJECT_ID'),

            'messaging' => [
                'http_client_options' => [
                    'timeout' => (float) env('FIREBASE_HTTP_TIMEOUT', 10.0),
                ],
            ],
        ],
    ],
];
