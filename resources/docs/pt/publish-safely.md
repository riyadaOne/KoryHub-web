# Publicar sem expor a produção

Um kory vem da sua produção: não deve levar nada que permita chegar até ela. O CLI verifica muita coisa, mas a revisão final é sua.

## O que nunca deve sair

- Chaves, palavras-passe e tokens, mesmo antigos.
- Endereços internos: IP, nomes de servidores, domínios privados, cadeias de ligação a uma base de dados.
- Dados reais: números de telefone, nomes de clientes, montantes.
- Ficheiros de configuração do servidor.

## O que o CLI verifica antes do envio

- **Segredos**: uma pesquisa de segredos corre em cada ficheiro; um segredo detetado para a publicação.
- **Ficheiros sensíveis**: `.env*`, `*.pem`, `*.key`, `id_rsa*` nunca saem.
- **Informações internas**: endereços web, IP, domínios, e-mails e cadeias de ligação dão um aviso, com o ficheiro e a linha. Exemplos como `example.com`, `localhost` ou `127.0.0.1` são ignorados.
- **Privacidade**: o CLI nunca envia nomes de pastas, caminhos do seu computador nem endereços de repositórios Git.

Para ver tudo sem enviar nada:

```
kory push pagamento --dry-run
```

## Extrair um kory de um projeto real

```
kory init pagamento --from src/Payments/PaymentService.php
```

O CLI parte deste ficheiro, segue as suas ligações para os outros ficheiros do projeto (três níveis no máximo) e propõe-nos. As dependências externas (`vendor`, `node_modules`, pacotes da linguagem) tornam-se pré-requisitos, não ficheiros do kory.

## Substituir em vez de apagar

Substitua um valor real por um exemplo explícito (`https://api.example.com`, `+235 60 00 00 00`): o código continua legível e o leitor percebe o que tem de fornecer.

## Se um segredo saiu

1. Revogue-o imediatamente junto do fornecedor: é a única proteção real.
2. Se a versão tem menos de 72 horas e ninguém a transferiu, elimine-a na página do módulo.
3. Caso contrário, denuncie-a: a equipa de moderação pode removê-la.
