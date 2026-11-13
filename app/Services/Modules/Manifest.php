<?php

namespace App\Services\Modules;

use App\Enums\License;
use App\Support\SemVer;

/**
 * kory.json publié (F-REG-01, TRD §18, §22) : normalisé par le CLI, sans chemin local.
 * Champs lus en W03 bloc A : name, version, description, license, language, tags, files.
 * « requires » et « architecture » sont contrôlés et enregistrés au bloc C.
 */
final readonly class Manifest
{
    public const MAX_TAGS = 10;

    /**
     * @param  list<string>  $tags
     * @param  list<string>  $files
     */
    private function __construct(
        public string $name,
        public SemVer $version,
        public ?string $description,
        public ?string $licenseSpdx,
        public ?string $language,
        public array $tags,
        public array $files,
    ) {}

    /**
     * Lit le manifeste ; seul un numéro de version illisible empêche de garder une version
     * rejetée. Les autres erreurs sont vérifiées par validate(), une fois la version connue.
     */
    public static function read(string $json): self
    {
        $data = json_decode($json, true, 32);
        if (! is_array($data)) {
            throw new PublicationError('manifest_missing');
        }

        $version = is_string($data['version'] ?? null) ? SemVer::parse($data['version']) : null;
        if ($version === null) {
            throw new PublicationError('version_invalid', ['version' => is_string($data['version'] ?? null) ? mb_substr($data['version'], 0, 64) : '?']);
        }

        return new self(
            name: is_string($data['name'] ?? null) ? $data['name'] : '',
            version: $version,
            description: is_string($data['description'] ?? null) ? $data['description'] : null,
            licenseSpdx: is_string($data['license'] ?? null) ? $data['license'] : null,
            language: is_string($data['language'] ?? null) ? $data['language'] : null,
            tags: self::strings($data['tags'] ?? []),
            files: self::strings($data['files'] ?? null) ?? [],
        );
    }

    /**
     * Contrôle le manifeste par rapport au module et aux fichiers de l'archive.
     *
     * @param  list<string>  $archiveFiles  fichiers de l'archive, hors kory.json et README.md
     */
    public function validate(string $fullName, array $archiveFiles): License
    {
        if ($this->name !== $fullName) {
            throw new PublicationError('manifest_invalid', ['field' => 'name']);
        }
        if ($this->description !== null && mb_strlen($this->description) > 500) {
            throw new PublicationError('manifest_invalid', ['field' => 'description']);
        }
        if ($this->language !== null && ! preg_match('/^[a-z0-9+#.-]{1,64}$/', $this->language)) {
            throw new PublicationError('manifest_invalid', ['field' => 'language']);
        }
        if (count($this->tags) > self::MAX_TAGS) {
            throw new PublicationError('manifest_invalid', ['field' => 'tags']);
        }
        foreach ($this->tags as $tag) {
            if (! preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $tag)) {
                throw new PublicationError('manifest_invalid', ['field' => 'tags']);
            }
        }

        $license = License::fromSpdx($this->licenseSpdx ?? '');
        if ($license === null) {
            throw new PublicationError('license_unknown', ['license' => mb_substr((string) $this->licenseSpdx, 0, 64)]);
        }

        foreach ($this->files as $path) {
            if (! ArchiveReader::isSafePath($path)) {
                throw new PublicationError('manifest_invalid', ['field' => 'files']);
            }
        }

        $declared = array_unique($this->files);
        sort($declared);
        sort($archiveFiles);
        if ($declared !== $archiveFiles) {
            $difference = array_values(array_merge(array_diff($declared, $archiveFiles), array_diff($archiveFiles, $declared)));
            throw new PublicationError('files_mismatch', ['path' => $difference[0] ?? '?']);
        }

        return $license;
    }

    /** @return list<string>|null */
    private static function strings(mixed $value): ?array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return null;
        }

        return array_values(array_filter($value, 'is_string'));
    }
}
