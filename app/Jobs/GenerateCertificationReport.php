<?php

namespace App\Jobs;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\CertificationReport;
use App\Services\Certification\Reports\ReportRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

/**
 * Rapport de certification PDF (W06.3, F-MOD-06, T-35, UXUI §11) : généré en file d'attente dans
 * une langue, stocké pour toujours avec son empreinte ; jamais régénéré (une ligne existante
 * arrête le job). Identifiants scalaires seulement (TRD §19). En cas d'échec, le job est relancé ;
 * aucune ligne n'est écrite tant que le fichier n'est pas enregistré.
 */
class GenerateCertificationReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public string $certificationId, public string $locale) {}

    public function handle(ReportRenderer $renderer): void
    {
        $certification = Certification::query()->with(['module.author', 'version'])->find($this->certificationId);
        if ($certification === null || $certification->decided_at === null
            || ! in_array($certification->status, [CertificationStatus::Granted, CertificationStatus::Revoked], true)) {
            return;
        }
        if (CertificationReport::query()->where('certification_id', $certification->id)->where('locale', $this->locale)->exists()) {
            return; // jamais régénéré
        }

        $previous = App::getLocale();
        App::setLocale($this->locale);
        try {
            $pdf = $renderer->render('reports.certification', [
                'certification' => $certification,
                'reference' => self::reference($certification),
                'rtl' => $this->locale === 'ar',
            ]);
        } finally {
            App::setLocale($previous);
        }

        $disk = Storage::disk(config('koryhub.publication.archives_disk'));
        $path = 'certifications/'.$certification->id.'/report-'.$this->locale.'.pdf';
        $disk->put($path, $pdf);

        CertificationReport::create([
            'certification_id' => $certification->id,
            'locale' => $this->locale,
            'path' => $path,
            'sha256' => hash('sha256', $pdf),
            'generated_at' => now(),
        ]);
    }

    /** Référence du dossier affichée dans le rapport : CERT-année-8 caractères de l'identifiant. */
    public static function reference(Certification $certification): string
    {
        return 'CERT-'.$certification->requested_at->format('Y').'-'.strtoupper(substr(str_replace('-', '', $certification->id), -8));
    }
}
