# Media Cloud Sync – admin app

Source of the plugin's admin screens (React 19, Redux Toolkit, React Router).
It compiles to `../assets/js/backend.js` and `../assets/css/backend.css`, which the
plugin loads. The plugin ships those built files, so you only need this folder to
change or rebuild the admin UI.

## Requirements

- Node.js 18.18 or later
- Yarn 1 (or npm)

## Setup

```sh
cd wp-content/plugins/media-cloud-sync/app
yarn install
```

## Build

```sh
yarn build
```

Writes the minified `../assets/js/backend.js` and `../assets/css/backend.css`, and
updates `../languages/media-cloud-sync-en_US.po` with the app's strings.

## Develop

```sh
yarn start
```

Rebuilds on every change. To have the browser reload the WordPress admin after each
rebuild, copy `.env-sample` to `.env` and set:

| Key | Meaning |
|---|---|
| `PROXY` | Your local WordPress URL, e.g. `http://localhost/site/` |
| `HOST` | Address BrowserSync listens on (`localhost`) |
| `PORT` | BrowserSync port, e.g. `3000` |

Then open `http://HOST:PORT/wp-admin/` instead of the site URL. Without `.env`,
`yarn start` only rebuilds.

## Layout

- `src/App.js` – entry; `src/Main.js` – app root and routes.
- `src/pages/` – one folder per admin screen; `src/components/` – shared UI.
- `src/redux/` – store and slices; `src/hooks/` – app-wide hooks.
- `src/runtime/` – `window.wpmcs`, the API add-ons (e.g. Media Cloud Sync PRO) build against.

## Other commands

- `yarn test` – ESLint.
- `yarn analyze` – bundle size breakdown (needs a `yarn start` build for source maps).
