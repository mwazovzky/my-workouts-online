<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProgramController extends Controller
{
    public function enroll(Request $request, Program $program): Response
    {
        $user = $request->user();

        $program->users()->syncWithoutDetaching([$user->id]);

        return response()->noContent();
    }
}
