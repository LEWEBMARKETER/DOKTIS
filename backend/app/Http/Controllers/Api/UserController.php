<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleUtilisateur;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return User::where('cabinet_id', $request->user()->cabinet_id)
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', Rule::enum(RoleUtilisateur::class)->except(RoleUtilisateur::SuperAdmin)],
            'specialite' => ['nullable', 'string', 'max:255'],
            'password' => ['required', Password::min(8)],
        ]);

        $user = User::create([
            ...$data,
            'cabinet_id' => $request->user()->cabinet_id,
            'password' => Hash::make($data['password']),
        ]);

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->cabinet_id !== $request->user()->cabinet_id, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'role' => ['sometimes', Rule::enum(RoleUtilisateur::class)->except(RoleUtilisateur::SuperAdmin)],
            'specialite' => ['nullable', 'string', 'max:255'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $user->update($data);

        return response()->json($user);
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->cabinet_id !== $request->user()->cabinet_id, 404);
        abort_if($user->id === $request->user()->id, 422, 'Vous ne pouvez pas vous supprimer vous-même.');

        $user->delete();

        return response()->json(status: 204);
    }
}
