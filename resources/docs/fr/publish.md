# Publier

Un kory part de votre projet en trois commandes. Rien n'est envoyé sans votre confirmation.

## Se connecter

```
kory login
```

Le CLI affiche un code et ouvre le navigateur : vous l'autorisez sur le site. Sans navigateur sur ce poste, ouvrez l'adresse affichée depuis un autre appareil. Pour vérifier le compte relié : `kory whoami`.

## Préparer

```
kory init paiement
```

La commande crée `.kory/paiement/kory.json` et `.kory/paiement/README.md`, avec les sections « Problème résolu » et « Pièges évités » à compléter. Options utiles :

- `--add <chemin>` ajoute un fichier ou un dossier (plusieurs fois possible) ;
- `--license <SPDX>` choisit la licence (MIT par défaut) ;
- `--from <chemin>` part d'un fichier et suit ses dépendances dans le projet (voir [Publier sans exposer sa production](publish-safely)).

Un projet qui ne contient qu'un kory peut aussi avoir un `kory.json` à la racine.

## Choisir les fichiers

Les fichiers listés dans `.koryignore` ne partent jamais, même s'ils sont dans le manifeste. Sont toujours ignorés : `.env*`, `*.pem`, `*.key`, `id_rsa*`, `.git`, `node_modules`, `vendor` et le dossier `.kory`.

## Publier le kory

```
kory push paiement
```

Le CLI montre ce qui va partir (fichiers, taille, dernière version publiée), puis demande confirmation. Pour tout vérifier sans rien envoyer :

```
kory push paiement --dry-run
```

Un nouveau module reste privé, comme un brouillon, sauf si vous choisissez de le rendre public (`--public`).

## Ce que KoryHub refuse

- Une archive de plus de 10 Mo.
- Les fichiers binaires (sauf les images de la documentation) et les liens symboliques.
- Un secret détecté (clé d'API, mot de passe, jeton) : la publication s'arrête et le fichier est indiqué.
- Un numéro de version déjà publié ou mal formé.

Les adresses et noms internes (IP, domaines, chaînes de connexion) donnent un avertissement : remplacez-les par des exemples, ou publiez quand même.

## Plusieurs kory dans un projet

Chaque kory a son dossier `.kory/<nom>/`. `kory list` les liste ; indiquez alors toujours le nom : `kory push <nom>`.

## Déprécier, archiver

Depuis la fiche du module sur le site : déprécier une version (avec un message), archiver le module (plus de nouvelle version, toujours téléchargeable). Une version publiée peut être supprimée dans les 72 heures si personne ne l'a téléchargée.
