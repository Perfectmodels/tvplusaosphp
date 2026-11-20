<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\SecurityLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Tentatives autorisées depuis une même adresse IP. */
    protected const MAX_PAR_IP = 5;

    /** Tentatives autorisées sur un même COMPTE (toutes IP confondues). */
    protected const MAX_PAR_COMPTE = 10;

    /** Durée du blocage après trop de tentatives (secondes). */
    protected const DUREE_BLOCAGE = 900; // 15 minutes

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $email = (string) $this->string('email');
        $candidat = User::where('email', $email)->first();

        if ($candidat && $candidat->exists && (bool) $candidat->is_active === false) {
            // Bloqué avant attempt pour ne pas révéler l'existence du compte.
            $this->enregistrerEchec($email, 'compte désactivé');

            throw ValidationException::withMessages([
                'email' => 'Ce compte est désactivé. Contactez votre administrateur.',
            ]);
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            $this->enregistrerEchec($email, 'identifiants invalides');

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->throttleCompteKey());

        app(SecurityLogger::class)->connexionReussie($email, Auth::id());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        // 1. Protection par IP (anti force brute distribuée sur un même réseau).
        if (RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_PAR_IP)) {
            $this->declencherVerrouillage($this->throttleKey());
        }

        // 2. Protection par COMPTE — bloque même si l'attaquant change d'IP.
        if (RateLimiter::tooManyAttempts($this->throttleCompteKey(), self::MAX_PAR_COMPTE)) {
            $this->declencherVerrouillage($this->throttleCompteKey());
        }
    }

    /**
     * @throws ValidationException
     */
    protected function declencherVerrouillage(string $cle): void
    {
        event(new Lockout($this));

        $secondes = max(1, (int) RateLimiter::availableIn($cle));

        app(SecurityLogger::class)->compteVerrouille(
            (string) $this->string('email'),
            $secondes
        );

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $secondes,
                'minutes' => (int) ceil($secondes / 60),
            ]),
        ]);
    }

    protected function enregistrerEchec(string $email, string $raison): void
    {
        RateLimiter::hit($this->throttleKey(), self::DUREE_BLOCAGE);
        RateLimiter::hit($this->throttleCompteKey(), self::DUREE_BLOCAGE);

        app(SecurityLogger::class)->connexionEchouee($email, $raison);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    /**
     * Clé de verrouillage au niveau du COMPTE (indépendante de l'IP).
     */
    public function throttleCompteKey(): string
    {
        return 'compte:'.Str::transliterate(Str::lower($this->string('email')));
    }
}
