# Installer le CLI

`kory` est un seul exécutable, sans dépendance. Le script d'installation télécharge la version publiée, **vérifie son empreinte SHA-256** et ne l'installe que si elle correspond. Aucun droit d'administrateur n'est nécessaire.

## Windows

Dans PowerShell :

```
irm https://koryhub.dev/install.ps1 | iex
```

`kory.exe` est installé dans `%LOCALAPPDATA%\Programs\kory`, et ce dossier est ajouté à votre PATH. Ouvrez ensuite un **nouveau** terminal.

## macOS et Linux

Dans un terminal :

```
curl -fsSL https://koryhub.dev/install.sh | sh
```

`kory` est installé dans `~/.local/bin`. Si ce dossier n'est pas dans votre PATH, le script affiche la ligne à ajouter à `~/.profile`.

## Vérifier l'installation

```
kory version
```

La commande affiche la version installée et signale si une version plus récente existe.

## Si PowerShell bloque le script

Si PowerShell refuse d'exécuter des scripts, autorisez-les pour votre compte seulement, puis relancez la commande d'installation :

```
Set-ExecutionPolicy -Scope CurrentUser RemoteSigned
```

Les exécutables ne sont pas encore signés : Windows peut afficher un avertissement au premier lancement.

## Mettre à jour

Quand une nouvelle version existe, `kory` vous le signale, au plus une fois par jour. Il ne se met jamais à jour tout seul : relancez la commande d'installation.

## Désinstaller

Supprimez le fichier `kory` (ou `kory.exe`) et, si vous le souhaitez, le dossier de réglages de kory dans votre dossier de configuration utilisateur. Révoquez aussi le jeton du terminal depuis vos réglages sur le site.
