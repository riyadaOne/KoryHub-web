# Qu'est-ce qu'un kory

Un **kory** (invariable : un kory, des kory) est un morceau de code qui a déjà tourné en production, publié avec ce qu'il faut pour le comprendre et le réutiliser.

## L'origine du nom

Le nom vient du cauri, le coquillage qui a longtemps servi de monnaie d'échange en Afrique : un kory est quelque chose de précieux que l'on échange.

## Ce qu'un kory contient

| Élément | Rôle |
|---|---|
| Le code | Les fichiers utiles, et seulement eux |
| `README.md` | Le problème résolu et les pièges évités |
| L'architecture | Les composants (classes, interfaces, fonctions…) et leurs liens, dessinés sur la fiche |
| `kory.json` | Le manifeste : nom, version, licence, langage, fichiers, prérequis |

## Un exemple : mobile-money-resilient

`@moussa/mobile-money-resilient` encaisse un paiement Mobile Money sans double débit malgré les coupures réseau. Son README explique le problème (un client qui paie deux fois quand le réseau coupe) et les pièges évités (une clé d'idempotence par commande, la vérification auprès de l'opérateur quand le webhook n'arrive pas). Son architecture montre `PaymentService`, l'interface `PaymentGateway` et la file `PendingPaymentQueue`.

## Les versions

Chaque publication porte un numéro `x.y.z` (par exemple `1.2.0`, jamais `1.2`). Une version publiée ne change plus : pour corriger, publiez une nouvelle version. L'auteur peut déprécier une version, avec un message pour ceux qui l'utilisent.

## Licences acceptées

MIT, Apache-2.0, BSD-2-Clause, BSD-3-Clause, ISC, MPL-2.0, GPL-2.0, GPL-3.0, LGPL-3.0, AGPL-3.0, Unlicense. MIT est proposée par défaut.

## Public ou privé

Un module privé n'est visible que par son auteur, comme un brouillon. Un module public est visible de tous ; il ne peut plus redevenir privé une fois téléchargé par quelqu'un d'autre.
