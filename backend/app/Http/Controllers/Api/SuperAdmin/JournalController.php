<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\JournalAction;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    public function index(Request $request)
    {
        $query = JournalAction::query();

        if ($action = $request->query('action')) {
            $query->where('action', 'like', "%{$action}%");
        }

        if ($sujetType = $request->query('sujet_type')) {
            $query->where('sujet_type', $sujetType);
        }

        return $query->orderByDesc('created_at')->paginate($request->integer('par_page', 50));
    }
}
