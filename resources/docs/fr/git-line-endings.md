# Fins de ligne Git

Sous Windows, Git peut changer les fins de ligne des fichiers (`LF` ↔ `CRLF`). KoryHub compare les fichiers **octet par octet** : un fichier dont seules les fins de ligne ont changé est vu comme modifié.

## Le symptôme

Après un `git clone` ou un `git checkout`, `kory update` ou `kory remove` signalent des fichiers « modifiés par vous » alors que vous n'y avez pas touché.

## Pourquoi

Le réglage `core.autocrlf=true`, courant sous Windows, convertit les fins de ligne à l'extraction. Les fichiers écrits par `kory pull` ne sont alors plus identiques à ceux du kory.

## Le réglage conseillé

Demandez à Git de ne jamais convertir les fichiers installés par kory. Dans le fichier `.gitattributes` à la racine du projet, ajoutez une ligne par dossier d'installation :

```
src/Payments/** -text
```

Puis faites relire les fichiers à Git :

```
git add --renormalize .
```

## Ce que KoryHub compare

Les empreintes de `kory.lock` sont calculées sur les octets exacts de chaque fichier, sans conversion. C'est ce qui permet de savoir avec certitude si vous avez modifié un fichier.
