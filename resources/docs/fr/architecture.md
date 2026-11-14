# Architecture

L'architecture d'un kory, ce sont ses composants et leurs liens. Elle s'affiche en diagramme sur la fiche et elle est obligatoire pour demander la certification.

## Pourquoi la déclarer

Un lecteur comprend en quelques secondes comment le kory est construit, avant de lire le code. Les composants servent aussi à la recherche.

## kory graph

```
kory graph paiement
```

Le CLI analyse les fichiers du kory, affiche les composants et les liens trouvés, puis propose de les écrire dans `kory.json`. Rien n'est exécuté : l'analyse lit seulement le code.

## Langages reconnus

PHP, JavaScript, TypeScript, Python, Java, Kotlin, Dart et Go. Pour un autre langage, écrivez l'architecture à la main dans `kory.json`.

## Le format

```json
"architecture": {
  "components": [
    { "id": "PaymentService", "file": "src/PaymentService.php", "kind": "class" },
    { "id": "PaymentGateway", "file": "src/Contracts/PaymentGateway.php", "kind": "interface" }
  ],
  "links": [
    { "from": "PaymentService", "to": "PaymentGateway", "kind": "uses" }
  ]
}
```

Types de composant : `class`, `interface`, `trait`, `enum`, `function`, `module`. Types de lien : `uses`, `implements`, `extends`, `calls`.

## Lire le diagramme

Sur la fiche, l'onglet « Architecture » dessine chaque composant selon son type (classe, interface…) et chaque lien selon le sien (utilise, implémente…).
