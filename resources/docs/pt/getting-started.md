# Começar

O KoryHub é um registo de **kory**: pedaços de código provados em produção, com a sua documentação e arquitetura, que partilha e que outros reutilizam. O site serve para encontrar e ler os kory; o CLI `kory` serve para os publicar e integrar nos seus projetos.

## O percurso em 4 comandos

1. Ligar o seu terminal à sua conta:

```
kory login
```

2. Preparar um kory no seu projeto:

```
kory init pagamento
```

3. Publicá-lo:

```
kory push pagamento
```

4. Instalar o kory de outra pessoa noutro projeto:

```
kory pull @moussa/mobile-money-resilient
```

Cada etapa é detalhada nas páginas seguintes: [Instalar o CLI](install), [Publicar](publish), [Integrar um kory](integrate).

## O que precisa

- Uma conta KoryHub, com um endereço de e-mail verificado para publicar.
- O CLI `kory`, um único ficheiro sem dependências, para Windows, macOS e Linux.

## Onde encontrar ajuda

- `kory help` lista os comandos; um comando mal utilizado mostra a sua forma correta.
- As mensagens de erro dizem sempre o que bloqueia e o que fazer.
- As regras de moderação e de certificação estão na página [Governação](https://koryhub.dev/governance).
