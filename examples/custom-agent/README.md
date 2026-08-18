# Custom agent contract

VideosAI can delegate analysis to an **external agent that you build**. Enable it
in the UI (or via `PUT /api/settings`) by setting the agent URL and toggling it on.
When enabled, the server POSTs each analysis request to your agent and uses its
response. If your agent errors or times out, the server falls back to the built-in
engine and marks the result accordingly.

## Request (server -> your agent)

`POST <your-url>` with JSON:

```json
{
  "title": "Building a serverless video pipeline",
  "description": "optional text",
  "transcript": "optional text",
  "criteria": {
    "positive": ["great", "amazing", "..."],
    "negative": ["bad", "broken", "..."]
  }
}
```

`criteria` are the good/bad parameters configured in the app, forwarded so your
agent can score consistently.

An optional `Authorization: Bearer <token>` header is sent if a token is configured.

## Response (your agent -> server)

Return JSON shaped like `AnalysisResult`:

```json
{
  "summary": "string (required)",
  "tags": ["string", "..."],
  "sentiment": "positive | neutral | negative",
  "readingTimeSeconds": 16,
  "engine": "optional label shown in the UI"
}
```

Only `summary` is strictly required; the server fills sane defaults for the rest.

## Run the example

```bash
npm run dev:agent      # starts on http://localhost:4000
# health check
curl http://localhost:4000/health
```

Then in the UI, set the agent URL to `http://localhost:4000/analyze`, enable it,
and analyze a video — results will be produced by the example agent (look for the
`custom-agent` engine label).
