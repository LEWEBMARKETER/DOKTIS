<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleUtilisateur;
use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use App\Models\JournalAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Crée un nouveau cabinet (tenant) avec son compte administrateur et
     * ouvre directement sa session (cookie HttpOnly via Sanctum SPA, garde
     * "web" — distinct du garde "patient" utilisé par DOKTIS Patient).
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

        Auth::guard('web')->login($admin);
        $request->session()->regenerate();

        JournalAction::enregistrer('cabinet.cree', Cabinet::class, $cabinet->id, acteur: $admin);

        return response()->json([
            'cabinet' => $cabinet,
            'user' => $admin,
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
            JournalAction::enregistrer('auth.echec', User::class, $user?->id, ['email' => $credentials['email']]);

            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        // Régénère l'identifiant de session APRÈS validation des identifiants
        // pour prévenir toute fixation de session.
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        JournalAction::enregistrer('auth.connexion', User::class, $user->id, acteur: $user);

        return response()->json([
            'user' => $user->load('cabinet'),
        ]);
    }

    public function logout(Request $request)
    {
        $user = Auth::guard('web')->user();

        Auth::guard('web')->logout();
        // Ne régénère que le jeton CSRF (pas d'invalidation globale de la
        // session) afin de ne pas déconnecter une éventuelle session
        // DOKTIS Patient active dans le même navigateur.
        $request->session()->regenerateToken();

        if ($user) {
            JournalAction::enregistrer('auth.deconnexion', User::class, $user->id, acteur: $user);
        }

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('cabinet'));
    }
}
