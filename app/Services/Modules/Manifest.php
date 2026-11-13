<?php

namespace App\Services\Modules;

use App\Enums\ComponentKind;
use App\Enums\License;
use App\Enums\LinkKind;
use App\Support\SemVer;

/**
 * kory.json publié (F-REG-01, F-REG-22, F-REG-27, TRD §18, §22) : normalisé par le CLI, sans
 * chemin local. Champs : name, version, description, license, language, tags, files, requires
 * (prérequis, 50 au plus) et architecture (50 composants, 150 liens au plus, facultative).
 */
final readonly class Manifest
{
    public const MAX_TAGS = 10;

    public const MAX_COMPONENTS = 50;

    public const MAX_LINKS = 150;

    public const MAX_REQUIREMENTS = 50;

    /** Identifiant d'un composant : lettres, chiffres, « _ », « . », « - » (TRD §22). */
    public const COMPONENT_ID = '/^[A-Za-z0-9_.-]{1,64}$/';

    /**
     * @param  list<string>  $tags
     * @param  list<string>  $files
     * @param  mixed  $requires  brut, contrôlé par validate()
     * @param  mixed  $architecture  brut, contrôlé par validate()
     */
    private function __construct(
        public string $name,
        public SemVer $version,
        public ?string $description,
        public ?string $licenseSpdx,
        public ?string $language,
        public array $tags,
        public array $files,
        public mixed $requires = null,
        public mixed $architecture = null,
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
            requires: $data['requires'] ?? null,
            architecture: $data['architecture'] ?? null,
        );
    }

    /**
     * Prérequis validés (F-REG-27).
     *
     * @return list<array{ecosystem: ?string, name: string, version: ?string}>
     */
    public function requirements(): array
    {
        if ($this->requires === null) {
            return [];
        }
        if (! is_array($this->requires) || ! array_is_list($this->requires) || count($this->requires) > self::MAX_REQUIREMENTS) {
            throw new PublicationError('manifest_invalid', ['field' => 'requires']);
        }

        $seen = [];
        $out = [];
        foreach ($this->requires as $i => $item) {
            $name = is_array($item) ? ($item['name'] ?? null) : null;
            $ecosystem = is_array($item) ? ($item['ecosystem'] ?? null) : null;
            $version = is_array($item) ? ($item['version'] ?? null) : null;

            if (! is_string($name) || $name === '' || mb_strlen($name) > 255
                || ($ecosystem !== null && (! is_string($ecosystem) || ! preg_match('/^[a-z0-9.+-]{1,64}$/', $ecosystem)))
                || ($version !== null && (! is_string($version) || mb_strlen($version) > 64))) {
                throw new PublicationError('manifest_invalid', ['field' => "requires.{$i}"]);
            }

            $key = ($ecosystem ?? '').'|'.$name;
            if (isset($seen[$key])) {
                throw new PublicationError('manifest_invalid', ['field' => "requires.{$i}"]);
            }
            $seen[$key] = true;
            $out[] = ['ecosystem' => $ecosystem, 'name' => $name, 'version' => $version];
        }

        return $out;
    }

    /**
     * Architecture validée (F-REG-22, F-REG-24, TRD §22) : chaque composant pointe vers un fichier
     * du kory, chaque lien relie deux composants déclarés, jamais un composant à lui-même.
     *
     * @param  list<string>  $archiveFiles
     * @return array{components: list<array{id: string, kind: ComponentKind, file: string}>, links: list<array{from: string, to: string, kind: LinkKind}>}
     */
    public function architecture(array $archiveFiles): array
    {
        if ($this->architecture === null) {
            return ['components' => [], 'links' => []];
        }

        $components = is_array($this->architecture) ? ($this->architecture['components'] ?? []) : null;
        $links = is_array($this->architecture) ? ($this->architecture['links'] ?? []) : null;
        if (! is_array($components) || ! array_is_list($components) || count($components) > self::MAX_COMPONENTS) {
            throw new PublicationError('manifest_invalid', ['field' => 'architecture.components']);
        }
        if (! is_array($links) || ! array_is_list($links) || count($links) > self::MAX_LINKS) {
            throw new PublicationError('manifest_invalid', ['field' => 'architecture.links']);
        }

        $files = array_flip($archiveFiles);
        $ids = [];
        $outComponents = [];
        foreach ($components as $i => $component) {
            $id = is_array($component) ? ($component['id'] ?? null) : null;
            $file = is_array($component) ? ($component['file'] ?? null) : null;
            $kind = is_array($component) && is_string($component['kind'] ?? null) ? ComponentKind::tryFrom($component['kind']) : null;

            if (! is_string($id) || ! preg_match(self::COMPONENT_ID, $id) || isset($ids[$id]) || ! is_string($file) || ! isset($files[$file]) || $kind === null) {
                throw new PublicationError('manifest_invalid', ['field' => "architecture.components.{$i}"]);
            }
            $ids[$id] = true;
            $outComponents[] = ['id' => $id, 'kind' => $kind, 'file' => $file];
        }

        $seen = [];
        $outLinks = [];
        foreach ($links as $i => $link) {
            $from = is_array($link) ? ($link['from'] ?? null) : null;
            $to = is_array($link) ? ($link['to'] ?? null) : null;
            $kind = is_array($link) && is_string($link['kind'] ?? null) ? LinkKind::tryFrom($link['kind']) : null;

            if (! is_string($from) || ! is_string($to) || ! isset($ids[$from], $ids[$to]) || $from === $to || $kind === null || isset($seen["{$from}|{$to}|{$kind->value}"])) {
                throw new PublicationError('manifest_invalid', ['field' => "architecture.links.{$i}"]);
            }
            $seen["{$from}|{$to}|{$kind->value}"] = true;
            $outLinks[] = ['from' => $from, 'to' => $to, 'kind' => $kind];
        }

        return ['components' => $outComponents, 'links' => $outLinks];
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
