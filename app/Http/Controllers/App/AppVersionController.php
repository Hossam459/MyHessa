<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Traits\HttpResponses;
use App\Http\Traits\Access;

class AppVersionController extends Controller
{   
     use HttpResponses, Access;

   public function check(Request $request, $platform): JsonResponse
{
    $platform = strtolower($platform);

    $validator = Validator::make([
        'platform' => $platform,
        'current_version' => $request->get('current_version'),
    ], [
        'platform' => 'required|in:android,ios',
        'current_version' => 'required|string',
    ]);

    if ($validator->fails()) {
        return $this->error([],$validator->errors()->first());
    }

    $version = AppVersion::where('platform', $platform)->first();

    if (!$version) {
        return$this->error([], 'Platform not supported'
        );
    }

    $currentVersion = $request->current_version;

    $needUpdate = version_compare($currentVersion, $version->latest_version, '<');
    $forceUpdate = version_compare($currentVersion, $version->min_supported_version, '<');

    return $this->success( [
            'platform' => $platform,
            'current_version' => $currentVersion,
            'latest_version' => $version->latest_version,
            'min_supported_version' => $version->min_supported_version,
            'need_update' => $needUpdate,
            'force_update' => $forceUpdate,
            'maintenance_mode' => (bool) $version->maintenance_mode,
            'update_url' => $version->update_url,
            'message' => [
                'ar' => $version->message_ar,
                'en' => $version->message_en,
            ],
        ]
    );
}
}