# Instalar o CLI

O `kory` é um único executável, sem dependências. O script de instalação transfere a versão publicada, **verifica a sua impressão digital SHA-256** e só a instala se corresponder. Não são precisos direitos de administrador.

## Windows

No PowerShell:

```
irm https://koryhub.dev/install.ps1 | iex
```

O `kory.exe` é instalado em `%LOCALAPPDATA%\Programs\kory` e esta pasta é adicionada ao seu PATH. Abra depois um **novo** terminal.

## macOS e Linux

Num terminal:

```
curl -fsSL https://koryhub.dev/install.sh | sh
```

O `kory` é instalado em `~/.local/bin`. Se esta pasta não estiver no seu PATH, o script mostra a linha a adicionar a `~/.profile`.

## Verificar a instalação

```
kory version
```

O comando mostra a versão instalada e avisa quando existe uma versão mais recente.

## Se o PowerShell bloquear o script

Se o PowerShell recusar executar scripts, autorize-os só para a sua conta e volte a executar o comando de instalação:

```
Set-ExecutionPolicy -Scope CurrentUser RemoteSigned
```

Os executáveis ainda não estão assinados: o Windows pode mostrar um aviso na primeira execução.

## Atualizar

Quando existe uma nova versão, o `kory` avisa-o, no máximo uma vez por dia. Nunca se atualiza sozinho: volte a executar o comando de instalação.

## Desinstalar

Apague o ficheiro `kory` (ou `kory.exe`) e, se quiser, a pasta de definições do kory na sua pasta de configuração de utilizador. Revogue também o token do terminal nas suas definições no site.
