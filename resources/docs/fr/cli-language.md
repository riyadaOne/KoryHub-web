# Langue du CLI

`kory` parle français, anglais, arabe et portugais.

## La langue du système

Par défaut, le CLI prend la langue de votre système si elle fait partie des quatre, sinon l'anglais.

## Choisir la langue

```
kory config set lang fr
```

Langues : `en`, `fr`, `ar`, `pt`. Pour voir la langue choisie :

```
kory config get lang
```

## Les messages de l'API

Les messages d'erreur de l'API (version du CLI refusée, limite de téléchargement…) sont rédigés et traduits par le serveur dans la langue du CLI : ils s'affichent tels quels.
