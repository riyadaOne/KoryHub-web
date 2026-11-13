<?php

namespace Tests\Support;

/**
 * Construit des archives .tar.gz pour les tests de publication, y compris des archives piégées
 * (lien, « .. », chemin absolu, en-tête pax) que kory push ne produirait jamais.
 */
class TarBuilder
{
    /** @var list<string> */
    private array $blocks = [];

    public function file(string $path, string $content, string $type = '0', string $link = ''): self
    {
        if (strlen($path) > 100) {
            $this->pax($path);
            $path = substr(basename($path), 0, 100);
        }

        $this->blocks[] = $this->header($path, strlen($content), $type, $link);
        if ($content !== '') {
            $this->blocks[] = str_pad($content, (int) ceil(strlen($content) / 512) * 512, "\0");
        }

        return $this;
    }

    public function symlink(string $path, string $target): self
    {
        return $this->file($path, '', '2', $target);
    }

    /** Écrit l'archive dans un fichier temporaire et renvoie son chemin. */
    public function save(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'kory-test-').'.tar.gz';
        file_put_contents($path, gzencode(implode('', $this->blocks).str_repeat("\0", 1024)));

        return $path;
    }

    /**
     * Archive d'un kory valide : kory.json normalisé, README.md et fichiers.
     *
     * @param  array<string, string>  $files
     * @param  array<string, mixed>  $manifest  champs remplacés
     */
    public static function kory(string $name, string $version = '1.0.0', array $files = ['src/PaymentService.php' => "<?php\n\nfinal class PaymentService {}\r\n"], array $manifest = [], ?string $readme = null): self
    {
        $json = json_encode(array_merge([
            'name' => $name,
            'version' => $version,
            'description' => 'Encaissement Mobile Money résistant aux coupures réseau.',
            'license' => 'MIT',
            'language' => 'php',
            'tags' => ['mobile-money', 'offline'],
            'files' => array_keys($files),
        ], $manifest), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $builder = (new self)->file('kory.json', $json)->file('README.md', $readme ?? "# Paiement\n\n## Problème résolu\n");
        foreach ($files as $path => $content) {
            $builder->file($path, $content);
        }

        return $builder;
    }

    private function pax(string $path): void
    {
        $record = ' path='.$path."\n";
        $length = strlen($record);
        $length += strlen((string) ($length + strlen((string) $length)));
        $data = $length.$record;

        $this->blocks[] = $this->header('PaxHeader', strlen($data), 'x', '');
        $this->blocks[] = str_pad($data, (int) ceil(strlen($data) / 512) * 512, "\0");
    }

    private function header(string $path, int $size, string $type, string $link): string
    {
        $header = str_pad($path, 100, "\0")
            .str_pad('0000644', 7, '0', STR_PAD_LEFT)."\0"
            .str_pad('0', 7, '0', STR_PAD_LEFT)."\0"
            .str_pad('0', 7, '0', STR_PAD_LEFT)."\0"
            .str_pad(decoct($size), 11, '0', STR_PAD_LEFT)."\0"
            .str_pad('0', 11, '0', STR_PAD_LEFT)."\0"
            .'        '
            .$type
            .str_pad($link, 100, "\0")
            ."ustar\0"
            .'00'
            .str_repeat("\0", 32 + 32 + 8 + 8 + 155 + 12);

        $sum = 0;
        for ($i = 0; $i < 512; $i++) {
            $sum += ord($header[$i]);
        }

        return substr_replace($header, str_pad(decoct($sum), 6, '0', STR_PAD_LEFT)."\0 ", 148, 8);
    }
}
