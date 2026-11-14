# Git line endings

On Windows, Git can change file line endings (`LF` ↔ `CRLF`). KoryHub compares files **byte for byte**: a file whose line endings alone changed is seen as modified.

## The symptom

After a `git clone` or `git checkout`, `kory update` or `kory remove` report files "modified by you" although you did not touch them.

## Why

The `core.autocrlf=true` setting, common on Windows, converts line endings on checkout. Files written by `kory pull` are then no longer identical to the kory's.

## The recommended setting

Tell Git never to convert the files installed by kory. In the `.gitattributes` file at the project root, add one line per install folder:

```
src/Payments/** -text
```

Then have Git read the files again:

```
git add --renormalize .
```

## What KoryHub compares

The checksums in `kory.lock` are computed on the exact bytes of each file, with no conversion. That is how KoryHub knows for sure whether you changed a file.
