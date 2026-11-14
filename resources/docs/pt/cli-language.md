# Língua do CLI

O `kory` fala português, francês, inglês e árabe.

## A língua do sistema

Por defeito, o CLI usa a língua do seu sistema quando é uma das quatro; caso contrário, o inglês.

## Escolher a língua

```
kory config set lang pt
```

Línguas: `en`, `fr`, `ar`, `pt`. Para ver a língua escolhida:

```
kory config get lang
```

## As mensagens da API

As mensagens de erro da API (versão do CLI recusada, limite de transferências…) são escritas e traduzidas pelo servidor na língua do CLI: são mostradas tal como chegam.
