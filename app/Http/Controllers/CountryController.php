<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class CountryController extends Controller
{
    public function index()
    {
        $response = Http::get('https://restcountries.com/v3.1/all?fields=name,cca2');

        if (! $response->successful()) {
            return response()->json([]);
        }

        $countries = collect($response->json() ?? [])
            ->map(function ($country) {
                return [
                    'name' => data_get($country, 'name.common'),
                    'code' => data_get($country, 'cca2'),
                ];
            })
            ->filter(fn ($country) => !empty($country['name']) && !empty($country['code']))
            ->reject(fn ($country) => $country['name'] === 'Israel')
            ->sortBy('name')
            ->values()
            ->values();

        return response()->json($countries);
    }
}
