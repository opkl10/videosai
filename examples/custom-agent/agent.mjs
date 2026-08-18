#!/usr/bin/env node
/**
 * Example custom agent for VideosAI.
 *
 * This is a reference implementation of the agent contract. Build your own agent
 * in any language/framework as long as it:
 *   - accepts POST <url> with a JSON body:  AgentPayload
 *       { title, description?, transcript?, criteria: { positive[], negative[] } }
 *   - responds with a JSON body:            AnalysisResult
 *       { summary, tags[], sentiment: "positive"|"neutral"|"negative",
 *         readingTimeSeconds, engine? }
 *
 * Zero dependencies so it can run anywhere: `node examples/custom-agent/agent.mjs`
 * Then, in the VideosAI UI, set the agent URL to http://localhost:4000/analyze
 * and enable it.
 */
import http from "node:http";

const PORT = Number(process.env.AGENT_PORT ?? 4000);

function readBody(req) {
  return new Promise((resolve, reject) => {
    let raw = "";
    req.on("data", (chunk) => (raw += chunk));
    req.on("end", () => {
      try {
        resolve(raw ? JSON.parse(raw) : {});
      } catch (e) {
        reject(e);
      }
    });
    req.on("error", reject);
  });
}

function tokenize(text) {
  return String(text)
    .toLowerCase()
    .replace(/[^a-z0-9\s]/g, " ")
    .split(/\s+/)
    .filter((w) => w.length > 2);
}

function analyze(payload) {
  const { title = "", description = "", transcript = "", criteria = {} } = payload;
  const positive = new Set((criteria.positive ?? []).map((t) => String(t).toLowerCase()));
  const negative = new Set((criteria.negative ?? []).map((t) => String(t).toLowerCase()));

  const corpus = [title, description, transcript].join(". ");
  const words = tokenize(corpus);

  let score = 0;
  const hits = { positive: [], negative: [] };
  for (const w of words) {
    if (positive.has(w)) {
      score += 1;
      hits.positive.push(w);
    }
    if (negative.has(w)) {
      score -= 1;
      hits.negative.push(w);
    }
  }
  const sentiment = score > 0 ? "positive" : score < 0 ? "negative" : "neutral";

  // Distinct, agent-flavored summary so it is visibly different from the built-in engine.
  const verdict = score > 0 ? "recommended ✅" : score < 0 ? "needs work ⚠️" : "neutral";
  const matched = [...new Set([...hits.positive, ...hits.negative])].slice(0, 5);
  const summary =
    `[custom-agent] "${title}" scored ${score} against your criteria — ${verdict}. ` +
    (matched.length
      ? `Matched signals: ${matched.join(", ")}.`
      : "No configured good/bad signals were found.");

  return {
    summary,
    tags: matched,
    sentiment,
    readingTimeSeconds: Math.max(1, Math.round(words.length / 3)),
    engine: "custom-agent (example)",
  };
}

const server = http.createServer(async (req, res) => {
  res.setHeader("Access-Control-Allow-Origin", "*");
  res.setHeader("Access-Control-Allow-Headers", "Content-Type, Authorization");
  res.setHeader("Access-Control-Allow-Methods", "POST, OPTIONS");

  if (req.method === "OPTIONS") {
    res.writeHead(204).end();
    return;
  }
  if (req.method === "GET" && req.url === "/health") {
    res.writeHead(200, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ status: "ok", agent: "example" }));
    return;
  }
  if (req.method === "POST" && (req.url === "/analyze" || req.url === "/")) {
    try {
      const payload = await readBody(req);
      if (!payload.title || typeof payload.title !== "string") {
        res.writeHead(400, { "Content-Type": "application/json" });
        res.end(JSON.stringify({ error: "title is required" }));
        return;
      }
      const result = analyze(payload);
      res.writeHead(200, { "Content-Type": "application/json" });
      res.end(JSON.stringify(result));
    } catch (e) {
      res.writeHead(400, { "Content-Type": "application/json" });
      res.end(JSON.stringify({ error: String(e && e.message ? e.message : e) }));
    }
    return;
  }
  res.writeHead(404, { "Content-Type": "application/json" });
  res.end(JSON.stringify({ error: "not found" }));
});

server.listen(PORT, () => {
  console.log(`example custom agent listening on http://localhost:${PORT} (POST /analyze)`);
});
