# VPS deployment

GitHub Actions deploys each validated `main` commit as an immutable release:

```text
/srv/silex/web/
  current -> /srv/silex/web/releases/<website-sha>-<content-digest>
  releases/
    <website-sha>-<content-digest>/
      public/
      var/content/
        silex-version.txt
        sources/
          Silex/Docs/
          Packages/
          Silex-Registry/
```

Apache serves `/srv/silex/web/current/public` for
`https://silex.nekmata.com/`. The deployment account owns only
`/srv/silex/web`; it has no sudo privileges and does not own the Apache
configuration.

The expected virtual host is versioned in
`deploy/apache/silex.nekmata.com.conf`. Updating that file does not change the
server automatically: Apache configuration remains an explicit administrator
operation, separate from application deployments.

The production HTTP bootstrap virtual host is versioned in
`deploy/apache/silex-lang.org.conf`. It deliberately remains on HTTP until the
domain points to the VPS. After the DNS change has propagated, obtain and
install its certificate with:

```sh
sudo certbot --apache -d silex-lang.org --redirect
```

Certbot then owns the HTTPS augmentation and renewal configuration on the VPS.

## DNS and production cutover

GitHub Pages remains the production service until the candidate has deployed
and passed its staging checks. At OVH, replace the four GitHub Pages `A`
records for the zone root with:

```text
Type: A
Subdomain: @
Target: 92.222.25.45
TTL: 300
```

After public DNS resolves to the VPS, run the Certbot command above and verify
the home page, `/docs`, `/packages/STD`, and `/registry` over HTTPS. Only then
disable GitHub Pages in `Matanek/Silex-Website`.

The dedicated PHP-FPM pool is versioned in `deploy/php/silex-web.conf`. Its
OPcache settings revalidate the real path behind the atomic `current` symlink;
without this, PHP-FPM may continue serving a previous release after a switch.

The VPS requires OpenSSH, `rsync`, Apache, and PHP 8.2-FPM. The GitHub-hosted
runner requires PHP, Composer, Node.js, npm, OpenSSH, and `rsync`. Tailwind is
built by GitHub Actions; Node.js is not required on the VPS.

## GitHub secrets

- `VPS_HOST`: VPS hostname or address;
- `VPS_USER`: dedicated deployment account;
- `VPS_SSH_KEY`: private Ed25519 deployment key;
- `VPS_KNOWN_HOSTS`: pinned SSH host-key entry.

Optional GitHub variables:

- `VPS_SSH_PORT`, default `22`;
- `VPS_SITE_ROOT`, default `/srv/silex/web`.

The workflow fetches canonical sources from the latest semantic release tag of
Silex and every registered package, while reading current immutable
registrations from the registry. It copies only the package manifests and
Markdown needed by the website into the immutable release. These files are a
deployment snapshot: their owning repositories remain the only editable
sources of truth.

Website pushes, manual runs, and `ecosystem-content-updated` repository
dispatches rebuild the snapshot immediately. A six-hour schedule provides
eventual reconciliation if a release hook is delayed or unavailable. The
release directory combines the website SHA with the snapshot digest, making a
content-only refresh immutable and independently deployable.
