# Install the CLI

`kory` is a single executable with no dependencies. The install script downloads the published version, **checks its SHA-256 checksum** and installs it only if it matches. No administrator rights are needed.

## Windows

In PowerShell:

```
irm https://koryhub.dev/install.ps1 | iex
```

`kory.exe` is installed in `%LOCALAPPDATA%\Programs\kory`, and this folder is added to your PATH. Then open a **new** terminal.

## macOS and Linux

In a terminal:

```
curl -fsSL https://koryhub.dev/install.sh | sh
```

`kory` is installed in `~/.local/bin`. If this folder is not in your PATH, the script shows the line to add to `~/.profile`.

## Check the installation

```
kory version
```

The command shows the installed version and tells you when a newer one exists.

## If PowerShell blocks the script

If PowerShell refuses to run scripts, allow them for your account only, then run the install command again:

```
Set-ExecutionPolicy -Scope CurrentUser RemoteSigned
```

The executables are not signed yet: Windows may show a warning the first time.

## Update

When a new version exists, `kory` tells you, at most once a day. It never updates itself: run the install command again.

## Uninstall

Delete the `kory` (or `kory.exe`) file and, if you wish, the kory settings folder in your user configuration folder. Also revoke the terminal's token from your settings on the website.
