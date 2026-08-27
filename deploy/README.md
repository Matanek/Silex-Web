# VPS deployment

GitHub Actions deploys each validated `main` commit as an immutable release:

```text
/srv/silex/web/
  current -> /srv/silex/web/releases/<git-sha>
  releases/
    <git-sha>/
      public/
```

Apache serves `/srv/silex/web/current/public` for
`https://silex.nekmata.com/`. The deployment account owns only
`/srv/silex/web`; it has no sudo privileges and does not own the Apache
configuration.

The expected virtual host is versioned in
`deploy/apache/silex.nekmata.com.conf`. Updating that file does not change the
server automatically: Apache configuration remains an explicit administrator
operation, separate from application deployments.

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

The workflow uploads no source from Silex or its packages. Future builds may
consume their documentation, but the owning repositories remain the editable
sources of truth.
