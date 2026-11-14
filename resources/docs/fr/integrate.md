# Intégrer un kory

Le CLI installe un kory dans votre projet, garde trace de ce qu'il a écrit, et vous laisse toujours le dernier mot sur vos fichiers.

## Trouver

```
kory search paiement mobile
kory info @moussa/mobile-money-resilient
```

`search` accepte `--category`, `--language` et `--limit`. `info` montre la description, les versions, la licence, les prérequis et l'architecture.

## Installer

```
kory pull @moussa/mobile-money-resilient
```

Le CLI affiche les prérequis à installer vous-même, demande le dossier d'installation (ou `--dir <dossier>`), télécharge, **vérifie l'empreinte**, puis écrit les fichiers. Pour une version précise : `kory pull @moussa/mobile-money-resilient@1.1.0`. Un module non certifié ou une version dépréciée sont signalés avant l'installation.

## Fichiers déjà présents

Pour chaque fichier qui existe déjà, vous choisissez : garder, remplacer, ou voir la différence. Sans terminal (script, intégration continue), vos fichiers sont toujours gardés.

## Mettre à jour

```
kory update @moussa/mobile-money-resilient
```

Le CLI montre la nouvelle version et demande confirmation. Un fichier que vous avez modifié depuis l'installation n'est jamais écrasé sans votre accord.

## Retirer

```
kory remove @moussa/mobile-money-resilient
```

Les fichiers du kory sont supprimés ; ceux que vous avez modifiés ne le sont qu'après une seconde confirmation.

## Lister et contrôler

`kory list` liste les kory préparés dans ce projet. Pour contrôler les kory installés :

```
kory audit
```

Chaque kory de `kory.lock` est contrôlé : à jour, mise à jour disponible, déprécié, à risque, retiré ou introuvable. Le code de sortie est 1 si une version est retirée ou à risque : utile en intégration continue.

## Le fichier kory.lock

À la racine du projet, il note pour chaque kory la version, l'empreinte de l'archive, le dossier d'installation et l'empreinte de chaque fichier. Versionnez-le avec votre code.
