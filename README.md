# VideosAI

A small full-stack starter that generates AI-style **summaries**, **tags**, and
**sentiment** for a video library. The "AI" engine is a deterministic, fully
offline module, so the app runs end-to-end with no API keys or network access.

## Stack

- **server/** — Express + TypeScript API (`/api/videos`, `/api/analyze`, `/api/videos/:id/analyze`, `/api/settings`)
- **web/** — React + Vite + TypeScript UI (dev server proxies `/api` to the server)
- **examples/custom-agent/** — reference external agent you can connect (see below)
- npm workspaces monorepo

## Configurable criteria & pluggable agent

- **Good/bad parameters**: the sentiment scorer uses editable "good signals" and
  "bad signals" term lists. Edit them in the UI ("Scoring criteria & agent") or via
  `PUT /api/settings`. They are forwarded to any connected agent too.
- **Custom agent**: route analysis to an external agent you build. Set its URL and
  enable it in the UI (or via `/api/settings`). The server POSTs each request to
  your agent and uses its response, falling back to the built-in engine on failure.
  See [`examples/custom-agent/README.md`](examples/custom-agent/README.md) for the
  request/response contract and a runnable example.

## Getting started

```bash
npm install          # install all workspaces
npm run dev          # start server (:3001) and web (:5173) together
```

Then open http://localhost:5173.

Run pieces individually:

```bash
npm run dev:server   # API only, http://localhost:3001
npm run dev:web      # UI only, http://localhost:5173
npm run dev:agent    # example custom agent, http://localhost:4000
```

## Useful commands

```bash
npm run build        # type-check + build server and web
npm run typecheck    # type-check all workspaces
npm test             # run server unit tests
```

## API quick check

```bash
curl http://localhost:3001/api/health
curl http://localhost:3001/api/videos
curl -X POST http://localhost:3001/api/analyze \
  -H 'Content-Type: application/json' \
  -d '{"title":"Great serverless pipeline","description":"amazing pipeline"}'

# read/update criteria and agent config
curl http://localhost:3001/api/settings
curl -X PUT http://localhost:3001/api/settings \
  -H 'Content-Type: application/json' \
  -d '{"criteria":{"positive":["shiny"],"negative":["broken"]},"agent":{"enabled":false,"url":""}}'
```

## Cloud Agent environment

`.cursor/environment.json` installs dependencies with `npm install` and starts
two terminals (`server` and `web`) so the full stack is running when an agent
boots.
