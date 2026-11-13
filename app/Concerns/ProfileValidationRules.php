<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, Unique|ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?string $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Nom @auteur (W01) : 2 à 39 caractères, minuscules, chiffres et tirets, sans tiret au
     * début ni à la fin ; unique, y compris parmi les comptes supprimés (le nom reste réservé) ;
     * jamais un nom réservé. La règle n'est pas affichée à l'écran, le validateur la renvoie.
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function handleRules(): array
    {
        return [
            'required',
            'string',
            'min:2',
            'max:39',
            'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
            Rule::notIn(config('koryhub.reserved_handles')),
            Rule::unique(User::class, 'handle'),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?string $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
