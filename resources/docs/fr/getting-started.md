# Démarrer

KoryHub est un registre de **kory** : des morceaux de code éprouvés en production, avec leur documentation et leur architecture, que vous partagez et que d'autres réutilisent. Le site sert à trouver et lire les kory ; le CLI `kory` sert à les publier et à les intégrer dans vos projets.

## Le parcours en 4 commandes

1. Relier votre terminal à votre compte :

```
kory login
```

2. Préparer un kory dans votre projet :

```
kory init paiement
```

3. Le publier :

```
kory push paiement
```

4. Installer le kory de quelqu'un d'autre dans un autre projet :

```
kory pull @moussa/mobile-money-resilient
```

Chaque étape est détaillée dans les pages suivantes : [Installer le CLI](install), [Publier](publish), [Intégrer un kory](integrate).

## Ce qu'il vous faut

- Un compte KoryHub, avec une adresse e-mail vérifiée pour publier.
- Le CLI `kory`, un seul fichier sans dépendance, pour Windows, macOS et Linux.

## Où trouver de l'aide

- `kory help` liste les commandes ; une commande mal utilisée affiche sa forme correcte.
- Les messages d'erreur disent toujours ce qui bloque et quoi faire.
- Les règles de modération et de certification sont sur la page [Gouvernance](https://koryhub.dev/governance).
