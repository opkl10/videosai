import path from "node:path";
import fs from "node:fs";
import { fileURLToPath } from "node:url";
import express from "express";
import cors from "cors";
import { analyze, type AnalysisRequest, type AnalysisResult } from "./ai.js";
import { videos } from "./data.js";
import { getSettings, updateSettings } from "./settings.js";
import { callExternalAgent } from "./agent.js";

const app = express();
app.use(cors());
app.use(express.json());

const PORT = Number(process.env.PORT ?? 3001);
const __dirname = path.dirname(fileURLToPath(import.meta.url));
// server/src or server/dist -> ../../web/dist
const WEB_DIST = path.resolve(__dirname, "../../web/dist");

/**
 * Run analysis through the configured external agent when enabled, otherwise
 * through the built-in engine. Falls back to the built-in engine if the agent
 * fails, annotating the result so the caller can see what happened.
 */
async function runAnalysis(req: AnalysisRequest): Promise<AnalysisResult> {
  const { agent, criteria } = getSettings();
  if (agent.enabled && agent.url) {
    try {
      return await callExternalAgent(agent, { ...req, criteria });
    } catch (err) {
      const reason = err instanceof Error ? err.message : "unknown error";
      console.warn(`external agent failed (${reason}); falling back to built-in engine`);
      return { ...analyze(req, criteria), engine: `builtin (agent fallback: ${reason})` };
    }
  }
  return analyze(req, criteria);
}

app.get("/api/health", (_req, res) => {
  res.json({ status: "ok", service: "videosai-server", time: new Date().toISOString() });
});

app.get("/api/settings", (_req, res) => {
  res.json(getSettings());
});

app.put("/api/settings", (req, res) => {
  try {
    const updated = updateSettings(req.body ?? {});
    res.json(updated);
  } catch (err) {
    res.status(400).json({ error: err instanceof Error ? err.message : "invalid settings" });
  }
});

app.get("/api/videos", (_req, res) => {
  res.json(
    videos.map(({ id, title, description, durationSeconds }) => ({
      id,
      title,
      description,
      durationSeconds,
    })),
  );
});

app.post("/api/analyze", async (req, res) => {
  const body = req.body as Partial<AnalysisRequest>;
  if (!body || typeof body.title !== "string" || !body.title.trim()) {
    return res.status(400).json({ error: "title is required" });
  }
  try {
    const result = await runAnalysis({
      title: body.title,
      description: body.description,
      transcript: body.transcript,
    });
    res.json(result);
  } catch (err) {
    res.status(500).json({ error: err instanceof Error ? err.message : "analysis failed" });
  }
});

app.post("/api/videos/:id/analyze", async (req, res) => {
  const video = videos.find((v) => v.id === req.params.id);
  if (!video) {
    return res.status(404).json({ error: "video not found" });
  }
  try {
    const result = await runAnalysis({
      title: video.title,
      description: video.description,
      transcript: video.transcript,
    });
    res.json({ videoId: video.id, ...result });
  } catch (err) {
    res.status(500).json({ error: err instanceof Error ? err.message : "analysis failed" });
  }
});

// In production, serve the built React app from this same server so the whole
// stack runs as a single process. Falls back to index.html for client-side routes.
const hasWebBuild = fs.existsSync(path.join(WEB_DIST, "index.html"));
if (hasWebBuild) {
  app.use(express.static(WEB_DIST));
  app.use((req, res, next) => {
    if (req.method !== "GET" || req.path.startsWith("/api")) return next();
    res.sendFile(path.join(WEB_DIST, "index.html"));
  });
}

// Only listen when run directly (not when imported by tests).
if (process.env.NODE_ENV !== "test") {
  app.listen(PORT, () => {
    console.log(`videosai-server listening on http://localhost:${PORT}`);
    console.log(
      hasWebBuild
        ? `serving web app from ${WEB_DIST}`
        : "no web build found (run `npm run build`); API only",
    );
  });
}

export { app };
