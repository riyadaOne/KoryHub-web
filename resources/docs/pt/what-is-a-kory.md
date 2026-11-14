# O que é um kory

Um **kory** (invariável: um kory, dois kory) é um pedaço de código que já funcionou em produção, publicado com o necessário para o compreender e reutilizar.

## A origem do nome

O nome vem do búzio (cauri), a concha que durante muito tempo serviu de moeda em África: um kory é algo valioso que se troca.

## O que um kory contém

| Elemento | Papel |
|---|---|
| O código | Os ficheiros úteis, e só esses |
| `README.md` | O problema resolvido e as armadilhas evitadas |
| A arquitetura | Os componentes (classes, interfaces, funções…) e as suas ligações, desenhados na página |
| `kory.json` | O manifesto: nome, versão, licença, linguagem, ficheiros, pré-requisitos |

## Um exemplo: mobile-money-resilient

`@moussa/mobile-money-resilient` recebe um pagamento Mobile Money sem cobrança dupla apesar dos cortes de rede. O seu README explica o problema (um cliente que paga duas vezes quando a rede cai) e as armadilhas evitadas (uma chave de idempotência por encomenda, a verificação junto do operador quando o webhook não chega). A sua arquitetura mostra `PaymentService`, a interface `PaymentGateway` e a fila `PendingPaymentQueue`.

## As versões

Cada publicação tem um número `x.y.z` (por exemplo `1.2.0`, nunca `1.2`). Uma versão publicada nunca muda: para corrigir, publique uma nova versão. O autor pode descontinuar uma versão, com uma mensagem para quem a usa.

## Licenças aceites

MIT, Apache-2.0, BSD-2-Clause, BSD-3-Clause, ISC, MPL-2.0, GPL-2.0, GPL-3.0, LGPL-3.0, AGPL-3.0, Unlicense. A MIT é proposta por defeito.

## Público ou privado

Um módulo privado só é visível pelo seu autor, como um rascunho. Um módulo público é visível por todos; já não pode voltar a ser privado depois de ter sido transferido por outra pessoa.
