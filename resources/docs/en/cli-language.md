# CLI language

`kory` speaks English, French, Arabic and Portuguese.

## The system language

By default, the CLI uses your system language when it is one of the four, otherwise English.

## Choose the language

```
kory config set lang en
```

Languages: `en`, `fr`, `ar`, `pt`. To see the chosen language:

```
kory config get lang
```

## API messages

API error messages (CLI version refused, download limit…) are written and translated by the server in the CLI's language: they are shown as they are.
