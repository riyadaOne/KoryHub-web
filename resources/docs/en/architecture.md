# Architecture

A kory's architecture is its components and their links. It is drawn as a diagram on the module page and it is required to request certification.

## Why declare it

A reader understands in seconds how the kory is built, before reading the code. Components are also used by search.

## kory graph

```
kory graph payment
```

The CLI analyzes the kory's files, shows the components and links it found, then offers to write them into `kory.json`. Nothing is executed: the analysis only reads the code.

## Recognized languages

PHP, JavaScript, TypeScript, Python, Java, Kotlin, Dart and Go. For another language, write the architecture by hand in `kory.json`.

## The format

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

Component kinds: `class`, `interface`, `trait`, `enum`, `function`, `module`. Link kinds: `uses`, `implements`, `extends`, `calls`.

## Reading the diagram

On the module page, the "Architecture" tab draws each component according to its kind (class, interface…) and each link according to its own (uses, implements…).
