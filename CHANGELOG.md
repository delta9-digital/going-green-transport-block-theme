# Changelog

## 0.1.0 (unreleased)

- Scaffold from the Delta9 Eightshift theme: build config, bootstrap, enqueue, manifest cache,
  theme.json URL filter, post-meta base class (`ggt_` prefix), block-theme skeleton.

## 0.2.0 (unreleased)

- theme.json tokens from design-system/colors_and_type.css and the redesign comps: 24 colours
  named after the design-system variables, 22 font sizes (exact comp clamps), 14 spacing steps,
  shadows, radii, container, nav and motion custom properties; heading, link, button defaults.
- Fonts: Roboto (wdth+wght, incl. italic), Roboto Condensed (same file at 75%), Inter (opsz+wght,
  incl. italic), Roboto Mono, as latin-subset variable woff2.
- Base SCSS restates body typography over the blocks plugin's normalize rules.

## 0.3.0 (unreleased)

- Header and footer patterns from the Site Nav / Site Footer comps (core blocks), wired into
  parts/header.html and parts/footer.html. Sticky blurred bar, Pricing panel with descriptions,
  drawer below 960px with the Get a Quote button kept visible; lime email band with the
  Contact us ring, logo/contacts, Company / Services / Guide navigation, legal row.
- SiteLinks: every header/footer URL in one map.
- Templates: page/index (full-width content, no title; heroes live in content), single,
  archive/home, search, 404.
- Block styles from the comps: split-pill CTA (lime, and white for lime backgrounds), outline
  pills, text link, eyebrow and mono labels, chips, tag, card and panel surfaces (white, forest,
  lime), check list, rounded image.
