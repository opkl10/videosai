export interface VideoSummary {
  id: string;
  title: string;
  description: string;
  durationSeconds: number;
}

export interface AnalysisResult {
  videoId?: string;
  summary: string;
  tags: string[];
  sentiment: "positive" | "neutral" | "negative";
  readingTimeSeconds: number;
  engine?: string;
}

export interface Criteria {
  positive: string[];
  negative: string[];
}

export interface AgentConfig {
  enabled: boolean;
  url: string;
  token?: string;
}

export interface Settings {
  criteria: Criteria;
  agent: AgentConfig;
}

export async function getSettings(): Promise<Settings> {
  const res = await fetch("/api/settings");
  if (!res.ok) throw new Error(`Failed to load settings (${res.status})`);
  return res.json();
}

export async function updateSettings(patch: Partial<Settings>): Promise<Settings> {
  const res = await fetch("/api/settings", {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(patch),
  });
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error(body.error ?? `Failed to save settings (${res.status})`);
  }
  return res.json();
}

export async function fetchVideos(): Promise<VideoSummary[]> {
  const res = await fetch("/api/videos");
  if (!res.ok) throw new Error(`Failed to load videos (${res.status})`);
  return res.json();
}

export async function analyzeVideo(id: string): Promise<AnalysisResult> {
  const res = await fetch(`/api/videos/${id}/analyze`, { method: "POST" });
  if (!res.ok) throw new Error(`Analysis failed (${res.status})`);
  return res.json();
}

export async function analyzeCustom(input: {
  title: string;
  description?: string;
  transcript?: string;
}): Promise<AnalysisResult> {
  const res = await fetch("/api/analyze", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(input),
  });
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error(body.error ?? `Analysis failed (${res.status})`);
  }
  return res.json();
}
