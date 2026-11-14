# Use a kory

The CLI installs a kory into your project, keeps track of what it wrote, and always leaves the final word on your files to you.

## Find

```
kory search payment mobile
kory info @moussa/mobile-money-resilient
```

`search` accepts `--category`, `--language` and `--limit`. `info` shows the description, versions, license, requirements and architecture.

## Install

```
kory pull @moussa/mobile-money-resilient
```

The CLI shows the requirements you must install yourself, asks for the install folder (or `--dir <folder>`), downloads, **checks the checksum**, then writes the files. For a specific version: `kory pull @moussa/mobile-money-resilient@1.1.0`. A module that is not certified or a deprecated version is flagged before installation.

## Files that already exist

For each file that already exists, you choose: keep, replace, or see the difference. Without a terminal (script, continuous integration), your files are always kept.

## Update

```
kory update @moussa/mobile-money-resilient
```

The CLI shows the new version and asks for confirmation. A file you changed since installation is never overwritten without your consent.

## Remove

```
kory remove @moussa/mobile-money-resilient
```

The kory's files are deleted; those you changed are deleted only after a second confirmation.

## List and check

`kory list` lists the kory prepared in this project. To check the installed kory:

```
kory audit
```

Each kory in `kory.lock` is checked: up to date, update available, deprecated, at risk, removed or not found. The exit code is 1 when a version is removed or at risk: useful in continuous integration.

## The kory.lock file

At the project root, it records for each kory the version, the archive checksum, the install folder and the checksum of each file. Commit it with your code.
