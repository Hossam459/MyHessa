<?php

namespace App\Http\Controllers\Regions;

use App\Http\Controllers\Controller;
use App\Http\Traits\HttpResponses;
use App\Http\Traits\Access;
use App\Models\Governorate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;


class RegionsController extends Controller
{
    use HttpResponses, Access;

public function getAllGovernorates(): JsonResponse
    {
        $lang = app()->getLocale();

        $governorates = Governorate::with('cities')->get()->map(function ($gov) use ($lang) {
            return [
                'id' => $gov->id,
                'name' => $lang === 'ar' ? $gov->name_ar : $gov->name_en,
                'cities' => $gov->cities->map(function ($city) use ($lang) {
                    return [
                        'id' => $city->id,
                        'name' => $lang === 'ar' ? $city->name_ar : $city->name_en,
                        'latitude' => $city->latitude,
                        'longitude' => $city->longitude,
                    ];
                }),
            ];
        });

        return $this->success( $governorates);
    }

}