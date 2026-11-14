# Publish without exposing production

A kory comes from your production: it must not carry anything that could be used to reach it. The CLI checks a lot, but the final review is yours.

## What must never leave

- Keys, passwords and tokens, even old ones.
- Internal addresses: IPs, server names, private domains, database connection strings.
- Real data: phone numbers, customer names, amounts.
- Server configuration files.

## What the CLI checks before sending

- **Secrets**: a secret scan runs on every file; a detected secret stops publishing.
- **Sensitive files**: `.env*`, `*.pem`, `*.key`, `id_rsa*` never leave.
- **Internal information**: web addresses, IPs, domains, emails and connection strings give a warning, with the file and line. Examples such as `example.com`, `localhost` or `127.0.0.1` are ignored.
- **Privacy**: the CLI never sends folder names, paths on your computer or Git repository addresses.

To see everything without sending anything:

```
kory push payment --dry-run
```

## Extract a kory from a real project

```
kory init payment --from src/Payments/PaymentService.php
```

The CLI starts from this file, follows its links to the project's other files (three levels at most) and offers them. External dependencies (`vendor`, `node_modules`, language packages) become requirements, not files of the kory.

## Replace rather than delete

Replace a real value with an explicit example (`https://api.example.com`, `+235 60 00 00 00`): the code stays readable and the reader understands what to provide.

## If a secret has leaked

1. Revoke it right away with its provider: that is the only real protection.
2. If the version is less than 72 hours old and nobody has downloaded it, delete it from the module page.
3. Otherwise, report it: the moderation team can remove it.
