# Integrar um kory

O CLI instala um kory no seu projeto, regista o que escreveu e deixa-lhe sempre a última palavra sobre os seus ficheiros.

## Encontrar

```
kory search pagamento mobile
kory info @moussa/mobile-money-resilient
```

`search` aceita `--category`, `--language` e `--limit`. `info` mostra a descrição, as versões, a licença, os pré-requisitos e a arquitetura.

## Instalar

```
kory pull @moussa/mobile-money-resilient
```

O CLI mostra os pré-requisitos a instalar por si, pede a pasta de instalação (ou `--dir <pasta>`), transfere, **verifica a impressão digital** e escreve os ficheiros. Para uma versão precisa: `kory pull @moussa/mobile-money-resilient@1.1.0`. Um módulo não certificado ou uma versão descontinuada são assinalados antes da instalação.

## Ficheiros já existentes

Para cada ficheiro que já existe, escolhe: manter, substituir ou ver a diferença. Sem terminal (script, integração contínua), os seus ficheiros são sempre mantidos.

## Atualizar

```
kory update @moussa/mobile-money-resilient
```

O CLI mostra a nova versão e pede confirmação. Um ficheiro que alterou desde a instalação nunca é substituído sem o seu acordo.

## Retirar

```
kory remove @moussa/mobile-money-resilient
```

Os ficheiros do kory são apagados; os que alterou só o são depois de uma segunda confirmação.

## Listar e verificar

`kory list` lista os kory preparados neste projeto. Para verificar os kory instalados:

```
kory audit
```

Cada kory de `kory.lock` é verificado: atualizado, atualização disponível, descontinuado, em risco, removido ou não encontrado. O código de saída é 1 quando uma versão está removida ou em risco: útil na integração contínua.

## O ficheiro kory.lock

Na raiz do projeto, regista para cada kory a versão, a impressão digital do arquivo, a pasta de instalação e a impressão digital de cada ficheiro. Guarde-o no controlo de versões com o seu código.
