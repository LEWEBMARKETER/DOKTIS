<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleUtilisateur;
use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Crée un nouveau cabinet (tenant) avec son compte administrateur.
     */
    public function registerCabinet(Request $request)
    {
        $data = $request->validate([
            'cabinet_nom' => ['required', 'string', 'max:255'],
            'cabinet_type' => ['nullable', 'in:dentaire,medical,mixte'],
            'cabinet_telephone' => ['nullable', 'string', 'max:50'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        $cabinet = Cabinet::create([
            'nom' => $data['cabinet_nom'],
            'slug' => Str::slug($data['cabinet_nom']).'-'.Str::lower(Str::random(6)),
            'type' => $data['cabinet_type'] ?? 'mixte',
            'telephone' => $data['cabinet_telephone'] ?? null,
            'plan' => 'essai',
            'essai_expire_at' => now()->addDays(30),
        ]);

        $admin = User::create([
            'cabinet_id' => $cabinet->id,
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => Hash::make($data['admin_password']),
            'role' => RoleUtilisateur::Administrateur,
        ]);

        $token = $admin->createToken('dokta')->plainTextToken;

        return response()->json([
            'cabinet' => $cabinet,
            'user' => $admin,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $user->actif) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        $token = $user->createToken('dokta')->plainTextToken;

        return response()->json([
            'user' => $user->load('cabinet'),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('cabinet'));
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($data);

        // Réponse volontairement identique pour ne pas permettre l'énumération des comptes.
        return response()->json(['message' => 'Si ce compte existe, un lien de réinitialisation a été envoyé.']);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
            $user->tokens()->delete();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => 'Mot de passe réinitialisé.']);
    }
}
