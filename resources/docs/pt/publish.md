# Publicar

Um kory sai do seu projeto em três comandos. Nada é enviado sem a sua confirmação.

## Iniciar sessão

```
kory login
```

O CLI mostra um código e abre o navegador: autoriza-o no site. Sem navegador neste computador, abra o endereço mostrado noutro dispositivo. Para verificar a conta ligada: `kory whoami`.

## Preparar

```
kory init pagamento
```

O comando cria `.kory/pagamento/kory.json` e `.kory/pagamento/README.md`, com as secções «Problema resolvido» e «Armadilhas evitadas» a completar. Opções úteis:

- `--add <caminho>` adiciona um ficheiro ou uma pasta (pode repetir);
- `--license <SPDX>` escolhe a licença (MIT por defeito);
- `--from <caminho>` parte de um ficheiro e segue as suas dependências no projeto (ver [Publicar sem expor a produção](publish-safely)).

Um projeto com um só kory também pode ter um `kory.json` na raiz.

## Escolher os ficheiros

Os ficheiros listados em `.koryignore` nunca saem, mesmo que estejam no manifesto. São sempre ignorados: `.env*`, `*.pem`, `*.key`, `id_rsa*`, `.git`, `node_modules`, `vendor` e a pasta `.kory`.

## Publicar o kory

```
kory push pagamento
```

O CLI mostra o que vai ser enviado (ficheiros, tamanho, última versão publicada) e pede confirmação. Para verificar tudo sem enviar nada:

```
kory push pagamento --dry-run
```

Um módulo novo fica privado, como um rascunho, a menos que escolha torná-lo público (`--public`).

## O que o KoryHub recusa

- Um arquivo com mais de 10 MB.
- Ficheiros binários (exceto imagens da documentação) e ligações simbólicas.
- Um segredo detetado (chave de API, palavra-passe, token): a publicação para e o ficheiro é indicado.
- Um número de versão já publicado ou mal formado.

Endereços e nomes internos (IP, domínios, cadeias de ligação) dão um aviso: substitua-os por exemplos, ou publique na mesma.

## Vários kory num projeto

Cada kory tem a sua pasta `.kory/<nome>/`. `kory list` lista-os; indique sempre o nome: `kory push <nome>`.

## Descontinuar, arquivar

Na página do módulo no site: descontinuar uma versão (com uma mensagem), arquivar o módulo (sem novas versões, sempre disponível para transferir). Uma versão publicada pode ser eliminada nas 72 horas seguintes se ninguém a tiver transferido.
