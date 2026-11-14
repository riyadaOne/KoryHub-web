# Arquitetura

A arquitetura de um kory são os seus componentes e as suas ligações. É desenhada em diagrama na página do módulo e é obrigatória para pedir a certificação.

## Porquê declará-la

Um leitor percebe em segundos como o kory está construído, antes de ler o código. Os componentes também servem à pesquisa.

## kory graph

```
kory graph pagamento
```

O CLI analisa os ficheiros do kory, mostra os componentes e as ligações encontrados e propõe escrevê-los em `kory.json`. Nada é executado: a análise só lê o código.

## Linguagens reconhecidas

PHP, JavaScript, TypeScript, Python, Java, Kotlin, Dart e Go. Para outra linguagem, escreva a arquitetura à mão em `kory.json`.

## O formato

```json
"architecture": {
  "components": [
    { "id": "PaymentService", "file": "src/PaymentService.php", "kind": "class" },
    { "id": "PaymentGateway", "file": "src/Contracts/PaymentGateway.php", "kind": "interface" }
  ],
  "links": [
    { "from": "PaymentService", "to": "PaymentGateway", "kind": "uses" }
  ]
}
```

Tipos de componente: `class`, `interface`, `trait`, `enum`, `function`, `module`. Tipos de ligação: `uses`, `implements`, `extends`, `calls`.

## Ler o diagrama

Na página do módulo, o separador «Arquitetura» desenha cada componente conforme o seu tipo (classe, interface…) e cada ligação conforme o seu (usa, implementa…).
