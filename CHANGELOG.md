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
