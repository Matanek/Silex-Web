# Silex Web

This repository owns the next Silex website implementation. The public
staging deployment is available at <https://silex.nekmata.com/>.

The website renders and publishes documentation owned by the repositories
that implement Silex and its packages. It must not become a second editable
copy of those Markdown sources. The current production website remains in
`Matanek/Silex-Website` until an explicit cutover.

In a Silex workspace checkout, the application automatically reads the sibling
`Silex/Docs`, `Packages/`, and `Silex-Registry/` directories. Override those
sources for another environment with `SILEX_DOCS_ROOT`, `SILEX_PACKAGES_ROOT`,
and `SILEX_REGISTRY_ROOT`. The website and all published documentation use one
direct English route tree; no locale negotiation or translation is required.

Production releases contain an immutable snapshot under `var/content/sources`.
The deployment build fetches Silex, the registry, and every registered package,
checks out the latest semantic release tag of Silex and each package, then
copies only their manifests and canonical Markdown documentation. Local
workspace sources keep priority so Herd continues to reflect live repository
changes without rebuilding that snapshot.

The deployment runs for website pushes, manual requests, ecosystem dispatches,
and a six-hour reconciliation schedule. Its immutable release identifier
combines the website commit with the content-snapshot digest, so a new Silex or
package tag can never be hidden behind an already completed website release.

The displayed Silex version resolves, in order, from `SILEX_VERSION`, locally
from `../Silex/Toolchain/build.zig.zon`, or from the immutable release file
`var/content/silex-version.txt`. A Silex release hook can therefore publish
content and version metadata without making this repository their owner.

The application deliberately uses no database. Slim 4 owns HTTP routing and
middleware, Twig renders pages, League CommonMark converts documentation, and
Tailwind CSS produces the static stylesheet.

## Requirements

- PHP 8.2 or newer;
- Composer 2;
- Node.js and npm for the Tailwind build.

## Validate

```sh
composer install
npm ci
npm run build
npm run content:build
composer check
```

Start the local front controller with:

```sh
composer serve
```

The website is then available at <http://127.0.0.1:8080/>. For live CSS
rebuilds, run `npm run dev` in a second terminal. With Laravel Herd, link the
`public/` directory under an explicit name such as `silex`.

## Deployment

Every push to `main` validates the exact commit, uploads an immutable release
to the VPS, atomically updates the `current` symlink, and checks the public
HTTPS endpoint. See [deploy/README.md](deploy/README.md) for the server
contract and required GitHub settings.
