<?php

namespace App\Services\Modules;

/**
 * Lit une archive .tar.gz envoyée par kory push et en écrit les fichiers dans un dossier
 * temporaire (TRD §13, §22). Lecture en flux, entrée par entrée, pour appliquer les limites
 * avant d'écrire (bombe de décompression) : taille décompressée, nombre de fichiers, chemins
 * (aucun « .. », aucun chemin absolu), aucun lien. Les octets sont copiés tels quels (T-19).
 *
 * Formats reconnus : ustar et pax (en-têtes « x » écrits par archive/tar de Go).
 */
class ArchiveReader
{
    private const BLOCK = 512;

    /** Extensions et signatures des images acceptées dans la documentation. */
    private const IMAGES = [
        'png' => "\x89PNG\r\n\x1a\n",
        'jpg' => "\xFF\xD8\xFF",
        'jpeg' => "\xFF\xD8\xFF",
        'gif' => 'GIF8',
        'webp' => 'RIFF',
    ];

    /** @var resource|null */
    private $stream = null;

    private int $read = 0;

    public function __construct(
        private int $maxBytes,
        private int $maxFiles,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (int) config('koryhub.publication.max_uncompressed_mb') * 1024 * 1024,
            (int) config('koryhub.publication.max_files'),
        );
    }

    /**
     * Extrait l'archive dans $destination (qui doit exister et être vide).
     *
     * @return array<string, array{size: int, sha256: string, is_image: bool, binary: bool, svg: bool}> par chemin
     */
    public function extract(string $archive, string $destination): array
    {
        $this->stream = @gzopen($archive, 'rb');
        if ($this->stream === false) {
            throw new PublicationError('archive_invalid');
        }

        $this->read = 0;
        $entries = [];
        $paxPath = null;

        try {
            while (true) {
                $header = $this->readBytes(self::BLOCK);
                if (strlen($header) < self::BLOCK) {
                    throw new PublicationError('archive_invalid');
                }
                if (trim($header, "\0") === '') {
                    break;
                }

                $this->checkChecksum($header);

                $type = $header[156];
                $size = $this->octal(substr($header, 124, 12));
                $path = rtrim(substr($header, 0, 100), "\0");
                $prefix = rtrim(substr($header, 345, 155), "\0");
                if (str_starts_with(substr($header, 257, 6), 'ustar') && $prefix !== '') {
                    $path = $prefix.'/'.$path;
                }

                if ($type === 'x' || $type === 'g') {
                    $records = $this->readData($size);
                    if ($type === 'x') {
                        $paxPath = $this->paxPath($records);
                    }

                    continue;
                }

                $path = $paxPath ?? $path;
                $paxPath = null;

                if ($type === '5') {
                    $this->checkPath(rtrim($path, '/'));
                    $this->skipData($size);

                    continue;
                }
                if ($type === '1' || $type === '2') {
                    throw new PublicationError('link_forbidden', ['path' => $this->printable($path)]);
                }
                if ($type !== '0' && $type !== "\0") {
                    throw new PublicationError('archive_invalid');
                }

                $this->checkPath($path);
                if (isset($entries[$path])) {
                    throw new PublicationError('archive_invalid');
                }
                if (count($entries) >= $this->maxFiles) {
                    $this->limitsReached();
                }

                $entries[$path] = $this->writeFile($destination, $path, $size);
            }
        } finally {
            gzclose($this->stream);
            $this->stream = null;
        }

        return $entries;
    }

    /** Chemin relatif sûr : pas vide, pas absolu, ni « . » ni « .. », pas de barre inverse ni de caractère de contrôle. */
    public static function isSafePath(string $path): bool
    {
        if ($path === '' || strlen($path) > 1024 || str_starts_with($path, '/') || preg_match('/[\x00-\x1F\x7F\\\\:]/', $path)) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    private function checkPath(string $path): void
    {
        if (! self::isSafePath($path)) {
            throw new PublicationError('path_forbidden', ['path' => $this->printable($path)]);
        }
    }

    /** @return array{size: int, sha256: string, is_image: bool, binary: bool, svg: bool} */
    private function writeFile(string $destination, string $path, int $size): array
    {
        $target = $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0700, true);
        }

        $out = fopen($target, 'wb');
        $hash = hash_init('sha256');
        $head = '';
        $remaining = $size;

        while ($remaining > 0) {
            $chunk = $this->readBytes(min(65536, $remaining));
            if ($chunk === '') {
                fclose($out);
                throw new PublicationError('archive_invalid');
            }
            if (strlen($head) < 8192) {
                $head .= substr($chunk, 0, 8192 - strlen($head));
            }
            hash_update($hash, $chunk);
            fwrite($out, $chunk);
            $remaining -= strlen($chunk);
        }
        fclose($out);
        $this->skipPadding($size);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $isImage = isset(self::IMAGES[$extension]) && str_starts_with($head, self::IMAGES[$extension])
            && ($extension !== 'webp' || substr($head, 8, 4) === 'WEBP');

        return [
            'size' => $size,
            'sha256' => hash_final($hash),
            'is_image' => $isImage,
            'binary' => ! $isImage && str_contains($head, "\0"),
            'svg' => $extension === 'svg' || (bool) preg_match('/^\s*(<\?xml[^>]*>\s*)?<svg[\s>]/i', $head),
        ];
    }

    private function readBytes(int $length): string
    {
        if ($this->read + $length > $this->maxBytes) {
            $this->limitsReached();
        }

        $data = '';
        while (strlen($data) < $length) {
            $chunk = gzread($this->stream, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $data .= $chunk;
        }
        $this->read += strlen($data);

        return $data;
    }

    private function readData(int $size): string
    {
        if ($size > 65536) {
            throw new PublicationError('archive_invalid');
        }

        $data = $this->readBytes($size);
        $this->skipPadding($size);

        return $data;
    }

    private function skipData(int $size): void
    {
        $remaining = $size;
        while ($remaining > 0) {
            $chunk = $this->readBytes(min(65536, $remaining));
            if ($chunk === '') {
                throw new PublicationError('archive_invalid');
            }
            $remaining -= strlen($chunk);
        }
        $this->skipPadding($size);
    }

    private function skipPadding(int $size): void
    {
        $padding = (self::BLOCK - $size % self::BLOCK) % self::BLOCK;
        if ($padding > 0) {
            $this->readBytes($padding);
        }
    }

    /** Chemin long d'un en-tête pax (« 27 path=dossier/fichier.php\n »). */
    private function paxPath(string $records): ?string
    {
        $path = null;
        $offset = 0;

        while ($offset < strlen($records)) {
            $space = strpos($records, ' ', $offset);
            if ($space === false) {
                break;
            }
            $length = (int) substr($records, $offset, $space - $offset);
            if ($length <= 0) {
                throw new PublicationError('archive_invalid');
            }
            $record = substr($records, $space + 1, $length - ($space - $offset) - 2);
            if (str_starts_with($record, 'path=')) {
                $path = substr($record, 5);
            }
            if (str_starts_with($record, 'linkpath=')) {
                throw new PublicationError('link_forbidden', ['path' => $this->printable($path ?? '')]);
            }
            $offset += $length;
        }

        return $path;
    }

    private function octal(string $field): int
    {
        if ((ord($field[0]) & 0x80) !== 0) {
            throw new PublicationError('archive_invalid');
        }

        $value = trim($field, " \0");

        return $value === '' ? 0 : (preg_match('/^[0-7]+$/', $value) ? (int) octdec($value) : throw new PublicationError('archive_invalid'));
    }

    private function checkChecksum(string $header): void
    {
        $expected = $this->octal(substr($header, 148, 8));
        $sum = 0;
        for ($i = 0; $i < self::BLOCK; $i++) {
            $sum += ($i >= 148 && $i < 156) ? 32 : ord($header[$i]);
        }

        if ($sum !== $expected) {
            throw new PublicationError('archive_invalid');
        }
    }

    private function limitsReached(): never
    {
        throw new PublicationError('archive_limits', [
            'max_files' => $this->maxFiles,
            'max_mb' => intdiv($this->maxBytes, 1024 * 1024),
        ]);
    }

    private function printable(string $path): string
    {
        return mb_substr((string) preg_replace('/[\x00-\x1F\x7F]/', '?', $path), 0, 200);
    }
}
