# kaumzuglauben.getemoji.site — KaumZuGlauben (scaffold built 2026-11-08) — DRAFT CARD

> DRAFT: written by the agent at local-build time. To be moved into
> `docs/sites/kaumzuglauben.md` (plgin repo) and registered in `docs/sites/REGISTRY.md`
> when the site is created/deployed in the next phase. Nothing in this card means the
> site is live — it is NOT deployed yet. Concept: owner's directive 2026-11-08 — more
> German sites whose goal is Facebook traffic via shocking-fact posts; brand settled
> by Muse under his standing branding delegation (veto-able).

German shocking-facts site for the owner's German Facebook groups (the recipe/story
groups inventoried in `~/workspace/fb-pages/GERMAN-GROUPS-MAP.md`, plus the story
audience around Oma Gerda): true facts that read as invented — history shocks, nature
superpowers, body wonders, myths fact-checked, world records. Repo
**github.com/daseknahri/kaumzuglauben** (TO BE CREATED — local repo in
`plgin/kaumzuglauben/`, cloned from the soap-gold self-seeding repo, fully
rebranded). Structurally non-cannibalizing vs the German siblings: Oma Gerda tells
fiction stories, Deutsche Rezepte publishes recipes, KaumZuGlauben publishes verified
facts — the Facebook hook format differs (Anton caps card + sourced fact).

## Stack
Dockerfile (wordpress:php8.3-apache + wp-cli) · `docker-compose.yml` (Coolify magic
vars; SITE_URL default https://kaumzuglauben.getemoji.site, ADSENSE defaults
ca-pub-6869205417923902 / pub-6869205417923902, ADSENSE_ENABLE=0, GA_ENABLE=0,
HISTATS_ID=off) · `docker-compose.local.yml` (local QA only, port 8100,
WP_ENVIRONMENT_TYPE=local) · `docker/site-init.sh` · `seed/seed.php`
(`KZ_SEED_VERSION` 1; idempotent; imports `data/bundles/*.zip` article bundles via
`wpap_publish_article`, pins `post_name` to planned slugs, unconditional rewrite
flush on every start). Plugin = **build-v9 9.48.0** (vendored from plgin `main` —
the released version; 9.48.1 on the unmerged BL-010 branch deliberately NOT
vendored), theme = viral-reader **1.9.37** + child theme **`kaumzuglauben` 1.0.0**.
Admin user `editor`. Locale de_DE (Europe/Berlin), admin en_US.

## Child theme facts
Brand in `wp-content/themes/kaumzuglauben/BRAND.md`: red starburst + white
exclamation-mark emblem on ink; tokens ink #14161A, paper #FFF9EE, shock red
#D92B2B/#A81D1D, amber #F5B301/#B97E04. Display Anton (self-hosted woff2, copied from
the sibling child theme), body Literata serif. Category moods: Geschichte &
Ereignisse #9C3D1E, Natur & Tiere #2F7D3B, Mensch & Körper #1F6FA8, Alltag & Mythen
#B97E04, Rekorde & Kurioses #D92B2B. End-of-article: reader question (`_kz_question`
meta or the German default), FB/WhatsApp share + More popover, next-article card +
sticky bar, sourced-facts note. Ad architecture = sibling pattern but INERT at
launch: no AdSense slot IDs exist for this site yet and ADSENSE_ENABLE=0.
Brand assets wired per playbook §1.6: raster icon committed to `content/images/`,
icons/logo attached at seeding from the child theme `assets/brand/`.

## Seed categories (slugs fixed → CSS mood colours; IDs 2–6 in this order)
`geschichte-ereignisse` Geschichte & Ereignisse (also the default category) ·
`natur-tiere` Natur & Tiere · `mensch-koerper` Mensch & Körper · `alltag-mythen`
Alltag & Mythen · `rekorde-kurioses` Rekorde & Kurioses. ID 1 stays the leftover
default (renamed Allgemein). `vr_noun=article`. Trust pages (7, German):
ueber-uns, kontakt, impressum, datenschutz (registered as
wp_page_for_privacy_policy), nutzungsbedingungen (incl. Haftungsausschluss),
redaktionsrichtlinien, cookie-richtlinie. Impressum (§ 5 DDG / § 18 MStV) mirrors
the sibling German sites' owner-supplied operator block — no invented personal
data. About carries the sourcing promise ("jeder Fakt gegen verlässliche Quellen
geprüft"). Publishing note: publish by numeric category ID — the `&` in pillar
names becomes `&amp;` in name matching and duplicates (playbook §1.10). No posts
seeded (no invented facts); the first batch lands via `data/bundles/`.

## Brand assets
Original AI-generated plates (no stock, no real likenesses, no third-party marks):
red exclamation-mark studio scene hero → hero-1920.jpg (+ 640/768/960/1280 WebP
srcset variants); starburst exclamation emblem → kaumzuglauben-icon-512.png
(+ favicon-32, apple-touch-180); wordmark composited with Pillow (Anton) →
logo.png / logo-light.png (+ WebP twins); og.jpg (hero + ink band, wordmark).

## Status / launch checklist
- 2026-11-08: local repo built + Docker QA (see agent report). NOT pushed, NOT
  deployed, no DNS, no Coolify app, no Facebook Page.
- Pending owner (next phase, each a separate go): create GitHub repo
  daseknahri/kaumzuglauben + push; Hostinger DNS A `kaumzuglauben` → 85.31.239.111
  (getemoji.site zone); Coolify project + app from the repo (Public Git Repository
  route — the GitHub App route is broken until its private key is restored); first
  Deploy click; AdSense stage 2 (ADSENSE_ENABLE=1) when ready — the getemoji.site
  domain is already approved, no new site submission expected; GSC property +
  sitemap; create the KaumZuGlauben Facebook Page (RESPECT the account-level
  Page-creation cooldown — creations are spaced ~24h+; Soap Gold's Page was
  scheduled 2026-11-08 and The Recipe Nook's is queued behind it, so this attempt
  comes after those); wire social auto-posting tokens if wanted; confirm contact
  email (seed defaults SITE_EMAIL/WP_ADMIN_EMAIL to daseknahri@gmail.com).

## Gotchas
Same family gotchas as the sibling cards (plugin ignores item `slug` → seed pins
`post_name`; categories by NAME duplicate on `&`; Coolify empty-env passthrough →
seed hardcodes the publisher fallback). Local QA needs Docker Desktop running;
port 8100 is this site's local port (dales 8096, storyline 8097, recipes 8098,
soap-gold 8099).
