# What is a kory

A **kory** (invariable: one kory, two kory) is a piece of code that has already run in production, published with what you need to understand and reuse it.

## Where the name comes from

The name comes from the cowrie, the shell long used as currency in Africa: a kory is something valuable that people exchange.

## What a kory contains

| Item | Role |
|---|---|
| The code | The useful files, and only those |
| `README.md` | The problem solved and the pitfalls avoided |
| The architecture | The components (classes, interfaces, functions…) and their links, drawn on the page |
| `kory.json` | The manifest: name, version, license, language, files, requirements |

## An example: mobile-money-resilient

`@moussa/mobile-money-resilient` collects a Mobile Money payment without double charging despite network outages. Its README explains the problem (a customer who pays twice when the network drops) and the pitfalls avoided (one idempotency key per order, checking with the operator when the webhook never arrives). Its architecture shows `PaymentService`, the `PaymentGateway` interface and the `PendingPaymentQueue`.

## Versions

Each release has an `x.y.z` number (for example `1.2.0`, never `1.2`). A published version never changes: to fix something, publish a new version. The author can deprecate a version, with a message for the people who use it.

## Accepted licenses

MIT, Apache-2.0, BSD-2-Clause, BSD-3-Clause, ISC, MPL-2.0, GPL-2.0, GPL-3.0, LGPL-3.0, AGPL-3.0, Unlicense. MIT is the default.

## Public or private

A private module is visible only to its author, like a draft. A public module is visible to everyone; it can no longer become private once someone else has downloaded it.
