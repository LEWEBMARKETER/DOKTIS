<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Models\PatientAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:patient_accounts,email'],
            'telephone' => ['required', 'string', 'max:50', 'unique:patient_accounts,telephone'],
            'password' => ['required', 'string', 'min:8'],
            'date_naissance' => ['nullable', 'date'],
            'sexe' => ['nullable', 'in:M,F'],
        ]);

        $account = PatientAccount::create([
            ...collect($data)->except('password')->all(),
            'password' => Hash::make($data['password']),
        ]);

        $token = $account->createToken('dokta-patient')->plainTextToken;

        return response()->json(['account' => $account, 'token' => $token], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'identifiant' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $account = PatientAccount::where('email', $credentials['identifiant'])
            ->orWhere('telephone', $credentials['identifiant'])
            ->first();

        if (! $account || ! Hash::check($credentials['password'], $account->password) || ! $account->actif) {
            throw ValidationException::withMessages([
                'identifiant' => ['Identifiants invalides.'],
            ]);
        }

        $token = $account->createToken('dokta-patient')->plainTextToken;

        return response()->json(['account' => $account, 'token' => $token]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $account = $request->user();

        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'prenom' => ['sometimes', 'string', 'max:255'],
            'date_naissance' => ['nullable', 'date'],
            'sexe' => ['nullable', 'in:M,F'],
            'contact_urgence_nom' => ['nullable', 'string', 'max:255'],
            'contact_urgence_telephone' => ['nullable', 'string', 'max:50'],
        ]);

        $account->update($data);

        return response()->json($account);
    }
}
