# Fins de linha no Git

No Windows, o Git pode alterar os fins de linha dos ficheiros (`LF` ↔ `CRLF`). O KoryHub compara os ficheiros **byte a byte**: um ficheiro em que só os fins de linha mudaram é visto como alterado.

## O sintoma

Depois de um `git clone` ou `git checkout`, `kory update` ou `kory remove` indicam ficheiros «alterados por si» sem que lhes tenha tocado.

## Porquê

A definição `core.autocrlf=true`, comum no Windows, converte os fins de linha na extração. Os ficheiros escritos por `kory pull` deixam então de ser idênticos aos do kory.

## A definição aconselhada

Peça ao Git para nunca converter os ficheiros instalados pelo kory. No ficheiro `.gitattributes` na raiz do projeto, adicione uma linha por pasta de instalação:

```
src/Payments/** -text
```

Depois faça o Git voltar a ler os ficheiros:

```
git add --renormalize .
```

## O que o KoryHub compara

As impressões digitais de `kory.lock` são calculadas sobre os bytes exatos de cada ficheiro, sem conversão. É assim que o KoryHub sabe com certeza se alterou um ficheiro.
