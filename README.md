# Going Green Transport block theme

Eightshift block theme for [goinggreentransport.com](https://goinggreentransport.com), built by Delta9 Digital.

- PHP namespace `GoingGreenTransportTheme\`, text domain `going-green-transport`, post-meta prefix `ggt_`.
- The theme owns the brand: `theme.json` tokens, fonts, templates, parts, patterns, block styles,
  post types and meta. Custom blocks live in the shared
  [delta9-digital-blocks-plugin](https://github.com/Delta9-Digital-ORG/delta9-digital-blocks-plugin)
  and carry no brand values.
- Lives as a submodule of the site repo `delta9-digital/com-goinggreentransport` at
  `wp-content/themes/going-green-transport-block-theme`. Design source, tokens and the build plan
  are in that repo (`design-system/`, `docs/`).

## Develop

```bash
composer install
npm install
npm start        # watch
npm run build    # production build into public/
```

`@eightshift/frontend-libs` is pinned to **12.0.0** (babel-loader); keep the blocks plugin on the
same version.
