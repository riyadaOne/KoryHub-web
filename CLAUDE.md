# CLAUDE.md — KoryHub · application web

Application Laravel de KoryHub : site public, espace connecté, administration, documentation et API du CLI, sur quatre sous-domaines. Les spécifications sont dans `../../KoryHub-docs/01-specifications/` ; les règles de l'espace de travail dans `../../KoryHub-docs/CLAUDE.md` (elles s'appliquent ici).

Les règles générées par Laravel Boost (conventions Laravel, Livewire, Flux, Pest, Pint) sont dans `AGENTS.md`, régénéré par Boost et non versionné :

@AGENTS.md

## Règles propres à KoryHub

| Sujet | Règle | Référence |
|---|---|---|
| Domaines | Un fichier de routes par espace (`admin.php`, `api.php`, `docs.php`, `web.php`), enregistrés dans cet ordre dans `bootstrap/app.php` ; domaines lus dans `config/koryhub.php` | TRD §6 |
| Schéma | Clé UUID v7 (`HasUuids`) sur toute table métier ; suppression logique sauf exception notée dans `tests/Feature/Conventions/SchemaConventionsTest.php` ; `$fillable` obligatoire ; pas de JSON pour des données structurées | TRD §23 |
| Migrations | `php artisan migrate --database=mysql_migrate` (utilisateur MySQL à tous les droits) ; l'application tourne avec un utilisateur aux droits limités | TRD §21 |
| Audit | Écrire par `App\Services\Audit\AuditLogger` ; tables en ajout seul, protégées par des déclencheurs MySQL ; aucune donnée personnelle | TRD §21 |
| Statuts | Enums qui implémentent `App\Enums\HasStatusBadge` : libellé traduit et couleur dans l'enum ; transitions dans un service, refus par `App\Exceptions\InvalidTransition` | UXUI §8, TRD §11 |
| Langues | Aucun texte en dur : `__('fichier.clé')` dans `lang/{en,fr,ar,pt}` ; les 4 langues ensemble ; arabe de droite à gauche | TRD §20 |
| Interface | Mises en page `layouts/public`, `layouts/app`, `layouts/admin` ; composants maison dans `resources/views/components` ; jamais d'alerte native (`<x-confirm-step>`) | UXUI §5, §6 |
| Jobs | Identifiants scalaires seulement, jamais un model | TRD §19 |
| Tests | Pest sur MySQL (`koryhub_test`, connexion `mysql_testing`) ; tests ciblés seulement (`php artisan test --filter=...`) ; `vendor/bin/pint --dirty --format agent` après toute modification PHP | TRD §24 |
| Secrets | `.env` jamais lu ni modifié par Claude ; `.env.example` avec des valeurs factices | Règles de l'espace |
