{{--
    Rapport de certification PDF (UXUI §11, F-MOD-06) : en-tête, objet (empreinte complète),
    décision sans nom d'administrateur, évaluation, adresse de vérification. A4, sans fond coloré ;
    arabe de droite à gauche. Rendu par Chrome à partir de ce HTML autonome (styles en ligne).
--}}
@php($version = $certification->version)
@php($module = $certification->module)
@php($url = route('modules.version', ['author' => $module->author->handle, 'module' => $module->name, 'number' => $version->version]))
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('certification.report.title') }} · {{ $reference }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; color: #18181B; font-family: "IBM Plex Sans", "IBM Plex Sans Arabic", "Segoe UI", "Noto Sans Arabic", Arial, sans-serif; font-size: 11pt; line-height: 1.5; }
        .mono { font-family: "IBM Plex Mono", Consolas, monospace; direction: ltr; unicode-bidi: isolate; }
        .head { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0F766E; padding-bottom: 10pt; }
        .mark { display: flex; align-items: center; gap: 6pt; font-weight: 600; font-size: 13pt; }
        .mark svg { width: 22pt; height: 22pt; color: #0F766E; }
        .ref { color: #52525B; font-size: 9pt; }
        h1 { font-size: 18pt; margin: 18pt 0 10pt; }
        table { border-collapse: collapse; width: 100%; margin: 6pt 0 14pt; }
        th, td { text-align: start; vertical-align: top; padding: 5pt 6pt; border-bottom: 1px solid #E4E4E7; }
        th { width: 34%; color: #52525B; font-weight: 400; }
        .checksum { font-size: 8.5pt; word-break: break-all; }
        .verdict { border: 1.5pt solid #15803D; color: #15803D; border-radius: 4pt; padding: 8pt 10pt; font-weight: 600; margin: 12pt 0 6pt; }
        h2 { font-size: 12pt; margin: 16pt 0 4pt; }
        p { margin: 0 0 6pt; white-space: pre-line; }
        .foot { margin-top: 22pt; padding-top: 8pt; border-top: 1px solid #E4E4E7; color: #52525B; font-size: 9pt; }
    </style>
</head>
<body>
    <div class="head">
        <span class="mark"><x-app-logo-icon /> KoryHub</span>
        <span class="ref">{{ __('certification.report.reference') }} <span class="mono">{{ $reference }}</span></span>
    </div>

    <h1>{{ __('certification.report.title') }}</h1>

    <table>
        <tr><th>{{ __('certification.report.module') }}</th><td class="mono">{{ $module->fullName() }}</td></tr>
        <tr><th>{{ __('certification.report.version') }}</th><td class="mono">{{ $version->version }}</td></tr>
        <tr><th>{{ __('certification.report.checksum') }}</th><td class="mono checksum">{{ $version->archive_sha256 }}</td></tr>
        <tr><th>{{ __('certification.report.license_language') }}</th><td>{{ $version->license->spdx() }}@if ($version->language) · {{ $version->language }}@endif</td></tr>
    </table>

    <div class="verdict">{{ __('certification.report.decision', ['badge' => __('certification.badge'), 'date' => $certification->decided_at->utc()->locale(app()->getLocale())->isoFormat('LLL')]) }}</div>
    <p>{{ __('certification.report.team') }}</p>

    <h2>{{ __('certification.admin.architecture_comment') }}</h2>
    <p>{{ $certification->architecture_comment }}</p>

    <h2>{{ __('certification.admin.documentation_comment') }}</h2>
    <p>{{ $certification->documentation_comment }}</p>

    <div class="foot">{{ __('certification.report.verification') }} <span class="mono">{{ $url }}</span></div>
</body>
</html>
