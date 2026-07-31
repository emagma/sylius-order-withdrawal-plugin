# Documentation media

The shot list for the plugin's screenshots and recordings, and how to reproduce them.

**None of these files exist yet.** The README and the docs already reference them, so they render
as broken images until the assets land here. Filenames below are the ones referenced — keep them.

## What's needed

| File | Used in | Shows |
| --- | --- | --- |
| `demo.gif` | `README.md` | The whole journey, short loop: fill the form → submit → admin confirms |
| `demo.mp4` | linked from `README.md` | The same take, full length and with the email steps |
| `shop-form.png` | `README.md`, `usage/customer-journey.md` | The storefront form, filled in, above the fold |
| `admin-grid.png` | `README.md`, `usage/admin-guide.md` | The admin grid with a handful of requests in mixed states |
| `admin-show.png` | `README.md`, `usage/admin-guide.md` | One request's detail page, with its transition buttons visible |

The plugin's logo is **not** in this directory: it lives at the repository root as
`EmagmaOrderWithdrawalPlugin.svg`, where the README header references it.

## How to capture them

Everything can be produced against the demo image, which ships fixtures and a mail catcher:

```bash
docker build -f docker/demo/Dockerfile --build-arg SYMFONY_VERSION="6.4.*" -t owp-demo .
docker run --rm -p 8080:80 -p 8025:8025 owp-demo
```

- Shop form → <http://localhost:8080/en_US/withdrawal-request>
- Admin → <http://localhost:8080/admin> (`sylius` / `sylius`)
- Emails → <http://localhost:8025>

### Conventions

- **English locale** (`/en_US/`) — the docs are in English, and the screenshots should match.
- **1440 × 900 viewport**, light theme, no browser chrome, no personal bookmarks bar.
- **Plausible data.** `Jean Dupont`, order `000000123`, `jean.dupont@example.com`. Never real
  customer data.
- **The grid needs variety** — seed at least four requests covering all four states, so the badge
  colours are visible.
- Keep the GIF **under ~5 MB** and around 20–30 seconds; put the long version in the MP4.

## Where they should live

Committed here, under `docs/media/`, so the README renders on GitHub, on Packagist and in forks
without depending on an external host. That means keeping them small — compress the PNGs, and
prefer one good GIF over several.

<!-- TODO(marketing): capture the assets above and drop them in this directory, then remove the
     TODO comments in README.md, usage/customer-journey.md and usage/admin-guide.md. -->
