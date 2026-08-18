# VideosAI

A small full-stack starter that generates AI-style **summaries**, **tags**, and
**sentiment** for a video library. The "AI" engine is a deterministic, fully
offline module, so the app runs end-to-end with no API keys or network access.

## Stack

- **server/** — Express + TypeScript API (`/api/videos`, `/api/analyze`, `/api/videos/:id/analyze`)
- **web/** — React + Vite + TypeScript UI (dev server proxies `/api` to the server)
- npm workspaces monorepo

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
```

## Cloud Agent environment

`.cursor/environment.json` installs dependencies with `npm install` and starts
two terminals (`server` and `web`) so the full stack is running when an agent
boots.
