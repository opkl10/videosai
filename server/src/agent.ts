import type { AnalysisRequest, AnalysisResult, Criteria, Sentiment } from "./ai.js";
import type { AgentConfig } from "./settings.js";

/**
 * The payload sent to an external agent. This is the contract the user's agent
 * must implement: accept this JSON on POST and return an AnalysisResult-shaped body.
 */
export interface AgentPayload extends AnalysisRequest {
  criteria: Criteria;
}

const VALID_SENTIMENTS: Sentiment[] = ["positive", "neutral", "negative"];

function coerceResult(data: unknown): AnalysisResult {
  if (typeof data !== "object" || data === null) {
    throw new Error("agent returned a non-object response");
  }
  const d = data as Record<string, unknown>;
  if (typeof d.summary !== "string") {
    throw new Error("agent response missing 'summary'");
  }
  const sentiment = VALID_SENTIMENTS.includes(d.sentiment as Sentiment)
    ? (d.sentiment as Sentiment)
    : "neutral";
  const tags = Array.isArray(d.tags) ? d.tags.filter((t): t is string => typeof t === "string") : [];
  const readingTimeSeconds =
    typeof d.readingTimeSeconds === "number" && d.readingTimeSeconds > 0
      ? Math.round(d.readingTimeSeconds)
      : 1;
  const engine = typeof d.engine === "string" && d.engine.trim() ? d.engine : "custom-agent";

  return { summary: d.summary, tags, sentiment, readingTimeSeconds, engine };
}

/** Call the user's external agent. Throws on any failure so callers can fall back. */
export async function callExternalAgent(
  agent: AgentConfig,
  payload: AgentPayload,
  timeoutMs = 5000,
): Promise<AnalysisResult> {
  if (!agent.url) throw new Error("agent url is not configured");

  const headers: Record<string, string> = { "Content-Type": "application/json" };
  if (agent.token) headers.Authorization = `Bearer ${agent.token}`;

  const res = await fetch(agent.url, {
    method: "POST",
    headers,
    body: JSON.stringify(payload),
    signal: AbortSignal.timeout(timeoutMs),
  });

  if (!res.ok) {
    throw new Error(`agent responded with ${res.status}`);
  }
  return coerceResult(await res.json());
}
