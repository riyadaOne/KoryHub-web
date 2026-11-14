# Publier sans exposer sa production

Un kory vient de votre production : il ne doit rien emporter qui permette de l'atteindre. Le CLI vérifie beaucoup de choses, mais la relecture reste la vôtre.

## Ce qui ne doit jamais partir

- Les clés, mots de passe et jetons, même anciens.
- Les adresses internes : IP, noms de serveurs, domaines privés, chaînes de connexion à une base.
- Les données réelles : numéros de téléphone, noms de clients, montants.
- Les fichiers de configuration du serveur.

## Ce que le CLI vérifie avant l'envoi

- **Secrets** : une recherche de secrets tourne sur chaque fichier ; un secret détecté arrête la publication.
- **Fichiers sensibles** : `.env*`, `*.pem`, `*.key`, `id_rsa*` ne partent jamais.
- **Informations internes** : adresses web, IP, domaines, e-mails et chaînes de connexion donnent un avertissement, avec le fichier et la ligne. Les exemples comme `example.com`, `localhost` ou `127.0.0.1` sont ignorés.
- **Vie privée** : le CLI n'envoie ni nom de dossier, ni chemin de votre machine, ni adresse de dépôt Git.

Pour tout voir sans rien envoyer :

```
kory push paiement --dry-run
```

## Extraire un kory d'un projet réel

```
kory init paiement --from src/Payments/PaymentService.php
```

Le CLI part de ce fichier, suit ses liens vers les autres fichiers du projet (sur trois niveaux au plus) et les propose. Les dépendances externes (`vendor`, `node_modules`, paquets du langage) deviennent des prérequis, pas des fichiers du kory.

## Remplacer plutôt que supprimer

Remplacez une valeur réelle par un exemple explicite (`https://api.example.com`, `+235 60 00 00 00`) : le code reste lisible et le lecteur comprend ce qu'il doit fournir.

## Si un secret est parti

1. Révoquez-le tout de suite chez son fournisseur : c'est la seule protection réelle.
2. Si la version a moins de 72 heures et n'a pas été téléchargée, supprimez-la depuis la fiche.
3. Sinon, signalez-la : l'équipe de modération peut la retirer.
