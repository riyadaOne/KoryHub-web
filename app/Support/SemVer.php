<?php

namespace App\Support;

/**
 * Version sémantique (SemVer 2.0.0, F-REG-05) : x.y.z, pré-version facultative (-beta.1),
 * métadonnées de construction (+…) refusées pour garder un seul numéro par version.
 */
final readonly class SemVer
{
    private const PATTERN = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?$/';

    public function __construct(
        public int $major,
        public int $minor,
        public int $patch,
        public ?string $prerelease = null,
    ) {}

    public static function parse(string $version): ?self
    {
        if (strlen($version) > 64 || ! preg_match(self::PATTERN, $version, $m)) {
            return null;
        }

        if (max(strlen($m[1]), strlen($m[2]), strlen($m[3])) > 9) {
            return null;
        }

        return new self((int) $m[1], (int) $m[2], (int) $m[3], ($m[4] ?? '') === '' ? null : $m[4]);
    }

    public function __toString(): string
    {
        return "{$this->major}.{$this->minor}.{$this->patch}".($this->prerelease === null ? '' : "-{$this->prerelease}");
    }

    /** Ordre SemVer : -1, 0 ou 1. Une pré-version passe avant la version stable du même numéro. */
    public function compare(self $other): int
    {
        foreach (['major', 'minor', 'patch'] as $part) {
            if ($this->{$part} !== $other->{$part}) {
                return $this->{$part} <=> $other->{$part};
            }
        }

        if ($this->prerelease === $other->prerelease) {
            return 0;
        }
        if ($this->prerelease === null) {
            return 1;
        }
        if ($other->prerelease === null) {
            return -1;
        }

        $mine = explode('.', $this->prerelease);
        $theirs = explode('.', $other->prerelease);

        foreach ($mine as $i => $identifier) {
            if (! array_key_exists($i, $theirs)) {
                return 1;
            }

            $a = ctype_digit($identifier);
            $b = ctype_digit($theirs[$i]);

            $result = match (true) {
                $a && $b => (int) $identifier <=> (int) $theirs[$i],
                $a => -1,
                $b => 1,
                default => strcmp($identifier, $theirs[$i]) <=> 0,
            };

            if ($result !== 0) {
                return $result;
            }
        }

        return count($mine) < count($theirs) ? -1 : 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->compare($other) > 0;
    }
}
