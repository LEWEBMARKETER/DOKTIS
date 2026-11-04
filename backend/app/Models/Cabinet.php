<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Cabinet extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom', 'slug', 'type', 'telephone', 'whatsapp', 'email', 'adresse', 'ville', 'quartier',
        'pays', 'logo_path', 'plan', 'essai_expire_at', 'actif',
        'latitude', 'longitude', 'description', 'langues_parlees',
        'accepte_urgences', 'accessible_pmr', 'site_web', 'facebook_url', 'instagram_url',
        'visible_annuaire', 'statut_fiche',
    ];

    public const STATUT_NON_REVENDIQUEE = 'non_revendiquee';

    public const STATUT_EN_ATTENTE = 'en_attente';

    public const STATUT_VERIFIEE = 'verifiee';

    protected static function booted(): void
    {
        static::creating(function (Cabinet $cabinet) {
            $cabinet->pays ??= config('dokta.pays');

            if (empty($cabinet->slug)) {
                $cabinet->slug = static::genererSlug($cabinet->nom, $cabinet->ville);
            }
        });

        // Les slugs de ville et de quartier alimentent les pages publiques
        // « /specialite/ville/quartier » : on les garde synchronisés.
        static::saving(function (Cabinet $cabinet) {
            if ($cabinet->isDirty('ville') || ! $cabinet->exists) {
                $cabinet->ville_slug = $cabinet->ville ? Str::slug($cabinet->ville) : null;
            }
            if ($cabinet->isDirty('quartier') || ! $cabinet->exists) {
                $cabinet->quartier_slug = $cabinet->quartier ? Str::slug($cabinet->quartier) : null;
            }
        });
    }

    public static function genererSlug(string $nom, ?string $ville = null): string
    {
        $base = Str::slug(trim($nom.' '.($ville ?? ''))) ?: 'cabinet';
        $slug = $base;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }

    /**
     * Le numéro WhatsApp est stocké en chiffres seuls, au format
     * international sans « + » (celui qu'attend un lien wa.me).
     */
    protected function whatsapp(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => static::numeroInternational($value));
    }

    /**
     * « +241 77 12 34 56 », « 00241 77… » et « 077 12 34 56 » (format
     * national gabonais à 9 chiffres) donnent tous « 24177123456 ».
     */
    public static function numeroInternational(?string $numero): ?string
    {
        $chiffres = preg_replace('/\D+/', '', (string) $numero);

        if ($chiffres === '') {
            return null;
        }

        if (str_starts_with($chiffres, '00')) {
            return substr($chiffres, 2);
        }

        if (strlen($chiffres) === 9 && $chiffres[0] === '0') {
            return config('dokta.indicatif_pays').substr($chiffres, 1);
        }

        return $chiffres;
    }

    /**
     * Indique si le cabinet est ouvert à l'instant, d'après ses horaires
     * saisis en heure locale. Null si aucun horaire n'est renseigné.
     */
    public function estOuvert(?Carbon $moment = null): ?bool
    {
        $horaires = $this->relationLoaded('horaires') ? $this->horaires : $this->horaires()->get();

        if ($horaires->isEmpty()) {
            return null;
        }

        $moment = ($moment ?? now())->copy()->setTimezone(config('dokta.fuseau_horaire_local'));
        $jour = $horaires->firstWhere('jour_semaine', $moment->dayOfWeek);

        if (! $jour || $jour->ferme || ! $jour->heure_ouverture || ! $jour->heure_fermeture) {
            return false;
        }

        $heure = $moment->format('H:i:s');

        return substr($jour->heure_ouverture, 0, 8) <= $heure && $heure < substr($jour->heure_fermeture, 0, 8);
    }

    public function scopeVisiblesDansAnnuaire(Builder $query): Builder
    {
        return $query->where('actif', true)->where('visible_annuaire', true);
    }

    public function scopeOuvertsMaintenant(Builder $query, ?Carbon $moment = null): Builder
    {
        $moment = ($moment ?? now())->copy()->setTimezone(config('dokta.fuseau_horaire_local'));
        $heure = $moment->format('H:i:s');

        return $query->whereHas('horaires', function (Builder $q) use ($moment, $heure) {
            $q->where('jour_semaine', $moment->dayOfWeek)
                ->where('ferme', false)
                ->where('heure_ouverture', '<=', $heure)
                ->where('heure_fermeture', '>', $heure);
        });
    }

    /**
     * Remplace les horaires de la semaine (une entrée par jour fournie).
     *
     * @param  array<int, array{jour_semaine:int, heure_ouverture?:?string, heure_fermeture?:?string, ferme?:bool}>  $horaires
     */
    public function remplacerHoraires(array $horaires): void
    {
        foreach ($horaires as $horaire) {
            CabinetHoraire::updateOrCreate(
                ['cabinet_id' => $this->id, 'jour_semaine' => $horaire['jour_semaine']],
                [
                    'heure_ouverture' => $horaire['heure_ouverture'] ?? null,
                    'heure_fermeture' => $horaire['heure_fermeture'] ?? null,
                    'ferme' => $horaire['ferme'] ?? false,
                ]
            );
        }

        $this->unsetRelation('horaires');
    }

    protected function casts(): array
    {
        return [
            'essai_expire_at' => 'datetime',
            'actif' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'langues_parlees' => 'array',
            'accepte_urgences' => 'boolean',
            'accessible_pmr' => 'boolean',
            'note_moyenne' => 'decimal:2',
            'visible_annuaire' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function specialites(): BelongsToMany
    {
        return $this->belongsToMany(Specialite::class, 'cabinet_specialite');
    }

    public function mutuelles(): BelongsToMany
    {
        return $this->belongsToMany(Mutuelle::class, 'cabinet_mutuelle');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(CabinetPhoto::class)->orderBy('ordre');
    }

    public function avis(): HasMany
    {
        return $this->hasMany(Avis::class);
    }

    public function horaires(): HasMany
    {
        return $this->hasMany(CabinetHoraire::class)->orderBy('jour_semaine');
    }

    public function services(): HasMany
    {
        return $this->hasMany(CabinetService::class);
    }

    public function statistiquesJour(): HasMany
    {
        return $this->hasMany(CabinetStatistiqueJour::class);
    }

    public function demandesRevendication(): HasMany
    {
        return $this->hasMany(DemandeRevendication::class);
    }

    public function rafraichirNoteMoyenne(): void
    {
        $stats = $this->avis()->where('statut', 'visible')->selectRaw('avg(note) as moyenne, count(*) as total')->first();

        $this->update([
            'note_moyenne' => round((float) $stats->moyenne, 2),
            'nombre_avis' => (int) $stats->total,
        ]);
    }
}
