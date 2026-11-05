<?php

use Illuminate\Support\Facades\Route;

// L'API ne sert pas de pages : la racine indique simplement que le service répond.
Route::get('/', fn () => response()->json([
    'service' => 'DOKTIS API',
    'statut' => 'ok',
]));
