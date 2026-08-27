# Silex Web

This repository owns the next Silex website implementation. The public
staging deployment is available at <https://silex.nekmata.com/>.

The website renders and publishes documentation owned by the repositories
that implement Silex and its packages. It must not become a second editable
copy of those Markdown sources. The current production website remains in
`Matanek/Silex-Website` until an explicit cutover.

## Requirements

- PHP 8.2 or newer;
- Composer 2.

## Validate

```sh
composer install
composer check
```

## Deployment

Every push to `main` validates the exact commit, uploads an immutable release
to the VPS, atomically updates the `current` symlink, and checks the public
HTTPS endpoint. See [deploy/README.md](deploy/README.md) for the server
contract and required GitHub settings.
