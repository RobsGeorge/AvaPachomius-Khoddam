<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class DesignTokensController extends Controller
{
    public function show(): JsonResponse
    {
        $path = resource_path('design-tokens/khoddam.tokens.json');

        abort_unless(File::exists($path), 404);

        return response()->json(json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR));
    }
}
