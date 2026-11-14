<?php

namespace App\Services\Certification;

use App\Enums\CertificationStatus;
use App\Enums\VersionStatus;
use App\Models\Certification;
use App\Models\ModuleVersion;

/**
 * Contrôles préliminaires d'une demande de certification (F-MOD-02, F-MOD-17, plan W06.1) :
 * architecture déclarée, README avec « Problème résolu » et « Pièges évités » (titres du
 * modèle de kory init, dans les 4 langues), licence de la liste, version publiée (ni
 * dépréciée ni retirée ; « à risque » en W07), aucun autre dossier ouvert sur le module.
 * Le détail est une donnée courte, traduite à l'affichage.
 */
class PreliminaryChecks
{
    public const CODES = ['architecture_declared', 'readme_sections', 'license_allowed', 'version_published', 'no_open_case'];

    /** Titres de section du modèle de README (CLI, init.readme_template), par section. */
    private const SECTIONS = [
        'problem' => ['problem solved', 'problème résolu', 'المشكلة المحلولة', 'problema resolvido'],
        'pitfalls' => ['pitfalls avoided', 'pièges évités', 'المزالق التي تم تجنبها', 'armadilhas evitadas'],
    ];

    /**
     * @return list<array{code: string, passed: bool, detail: ?string}>
     */
    public function run(ModuleVersion $version, ?Certification $current = null): array
    {
        $components = $version->components()->count();
        $missing = $this->missingSections((string) $version->readme);
        $openCase = Certification::query()
            ->where('module_id', $version->module_id)
            ->whereIn('status', CertificationStatus::open())
            ->when($current, fn ($q) => $q->whereKeyNot($current->id))
            ->exists();

        return [
            ['code' => 'architecture_declared', 'passed' => $components > 0, 'detail' => $components > 0 ? $components.'/'.$version->links()->count() : null],
            ['code' => 'readme_sections', 'passed' => $missing === [], 'detail' => $missing === [] ? null : implode(',', $missing)],
            ['code' => 'license_allowed', 'passed' => $version->license !== null, 'detail' => $version->license?->spdx()],
            // Une version à risque (W07) ne se certifie pas : détail « at_risk ».
            ['code' => 'version_published', 'passed' => $version->status === VersionStatus::Published && $version->at_risk_at === null, 'detail' => $version->at_risk_at !== null ? 'at_risk' : $version->status->value],
            ['code' => 'no_open_case', 'passed' => ! $openCase, 'detail' => null],
        ];
    }

    /** @param  list<array{passed: bool}>  $results */
    public static function allPassed(array $results): bool
    {
        return collect($results)->every(fn (array $r) => $r['passed']);
    }

    /** @return list<string> sections absentes : problem, pitfalls */
    private function missingSections(string $readme): array
    {
        $headings = collect(preg_split('/\R/u', $readme))
            ->filter(fn (string $line) => preg_match('/^\s{0,3}#{1,6}\s+/u', $line))
            ->map(fn (string $line) => mb_strtolower(trim(preg_replace('/^\s{0,3}#{1,6}\s+/u', '', $line))))
            ->all();

        return array_values(array_keys(array_filter(self::SECTIONS, fn (array $titles) => ! collect($headings)->contains(
            fn (string $heading) => collect($titles)->contains(fn (string $title) => str_starts_with($heading, $title))
        ))));
    }
}
