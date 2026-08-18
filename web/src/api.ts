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
