# Publish

A kory leaves your project in three commands. Nothing is sent without your confirmation.

## Log in

```
kory login
```

The CLI shows a code and opens the browser: you authorize it on the website. With no browser on this computer, open the address shown from another device. To check which account is linked: `kory whoami`.

## Prepare

```
kory init payment
```

The command creates `.kory/payment/kory.json` and `.kory/payment/README.md`, with the "Problem solved" and "Pitfalls avoided" sections to fill in. Useful options:

- `--add <path>` adds a file or folder (can be repeated);
- `--license <SPDX>` picks the license (MIT by default);
- `--from <path>` starts from a file and follows its dependencies in the project (see [Publish without exposing production](publish-safely)).

A project with a single kory can also have a `kory.json` at its root.

## Choose the files

Files listed in `.koryignore` never leave, even if they are in the manifest. Always ignored: `.env*`, `*.pem`, `*.key`, `id_rsa*`, `.git`, `node_modules`, `vendor` and the `.kory` folder.

## Publish the kory

```
kory push payment
```

The CLI shows what will be sent (files, size, latest published version), then asks for confirmation. To check everything without sending anything:

```
kory push payment --dry-run
```

A new module stays private, like a draft, unless you choose to make it public (`--public`).

## What KoryHub refuses

- An archive larger than 10 MB.
- Binary files (except documentation images) and symbolic links.
- A detected secret (API key, password, token): publishing stops and the file is shown.
- A version number already published or badly formed.

Internal addresses and names (IPs, domains, connection strings) give a warning: replace them with examples, or publish anyway.

## Several kory in one project

Each kory has its own `.kory/<name>/` folder. `kory list` lists them; always give the name: `kory push <name>`.

## Deprecate, archive

From the module page on the website: deprecate a version (with a message), archive the module (no new versions, still downloadable). A published version can be deleted within 72 hours if nobody has downloaded it.
