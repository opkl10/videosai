import express from "express";
import cors from "cors";
import { analyze, type AnalysisRequest } from "./ai.js";
import { videos } from "./data.js";

const app = express();
app.use(cors());
app.use(express.json());

const PORT = Number(process.env.PORT ?? 3001);

app.get("/api/health", (_req, res) => {
  res.json({ status: "ok", service: "videosai-server", time: new Date().toISOString() });
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

app.post("/api/analyze", (req, res) => {
  const body = req.body as Partial<AnalysisRequest>;
  if (!body || typeof body.title !== "string" || !body.title.trim()) {
    return res.status(400).json({ error: "title is required" });
  }
  try {
    const result = analyze({
      title: body.title,
      description: body.description,
      transcript: body.transcript,
    });
    res.json(result);
  } catch (err) {
    res.status(500).json({ error: err instanceof Error ? err.message : "analysis failed" });
  }
});

app.post("/api/videos/:id/analyze", (req, res) => {
  const video = videos.find((v) => v.id === req.params.id);
  if (!video) {
    return res.status(404).json({ error: "video not found" });
  }
  const result = analyze({
    title: video.title,
    description: video.description,
    transcript: video.transcript,
  });
  res.json({ videoId: video.id, ...result });
});

// Only listen when run directly (not when imported by tests).
if (process.env.NODE_ENV !== "test") {
  app.listen(PORT, () => {
    console.log(`videosai-server listening on http://localhost:${PORT}`);
  });
}

export { app };
