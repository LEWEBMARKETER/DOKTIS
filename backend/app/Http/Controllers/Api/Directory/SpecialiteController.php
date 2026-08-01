<?php

namespace App\Http\Controllers\Api\Directory;

use App\Http\Controllers\Controller;
use App\Models\Specialite;

class SpecialiteController extends Controller
{
    public function index()
    {
        return Specialite::orderBy('nom')->get();
    }
}
