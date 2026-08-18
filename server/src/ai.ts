/**
 * A tiny, fully offline "AI" engine. It is deterministic so the app can be run
 * and demonstrated end-to-end without any external API keys or network access.
 * A real deployment would swap this module for a call to a hosted model.
 */

export interface AnalysisRequest {
  title: string;
  description?: string;
  transcript?: string;
}

export type Sentiment = "positive" | "neutral" | "negative";

export interface AnalysisResult {
  summary: string;
  tags: string[];
  sentiment: Sentiment;
  readingTimeSeconds: number;
  /** Which engine produced this result: the built-in scorer or an external agent. */
  engine?: string;
}

/** User-configurable parameters that define what counts as "good" vs "not good". */
export interface Criteria {
  positive: string[];
  negative: string[];
}

const STOP_WORDS = new Set([
  "the", "a", "an", "and", "or", "but", "of", "to", "in", "on", "for", "with",
  "is", "are", "was", "were", "be", "been", "this", "that", "these", "those",
  "it", "its", "as", "at", "by", "from", "how", "what", "why", "you", "your",
  "we", "our", "i", "he", "she", "they", "them", "his", "her", "will", "can",
  "about", "into", "over", "than", "then", "so", "if", "not", "no", "yes",
]);

export const DEFAULT_CRITERIA: Criteria = {
  positive: ["great", "amazing", "love", "best", "awesome", "beautiful", "win", "happy", "improve", "success"],
  negative: ["bad", "worst", "hate", "boring", "fail", "sad", "angry", "problem", "bug", "broken"],
};

function tokenize(text: string): string[] {
  return text
    .toLowerCase()
    .replace(/[^a-z0-9\s]/g, " ")
    .split(/\s+/)
    .filter((w) => w.length > 2 && !STOP_WORDS.has(w));
}

export function extractTags(text: string, max = 5): string[] {
  const counts = new Map<string, number>();
  for (const word of tokenize(text)) {
    counts.set(word, (counts.get(word) ?? 0) + 1);
  }
  return [...counts.entries()]
    .sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]))
    .slice(0, max)
    .map(([word]) => word);
}

function normalizeTerms(terms: string[]): Set<string> {
  return new Set(terms.map((t) => t.trim().toLowerCase()).filter(Boolean));
}

export function scoreSentiment(text: string, criteria: Criteria = DEFAULT_CRITERIA): Sentiment {
  const positive = normalizeTerms(criteria.positive);
  const negative = normalizeTerms(criteria.negative);
  let score = 0;
  for (const w of tokenize(text)) {
    if (positive.has(w)) score += 1;
    if (negative.has(w)) score -= 1;
  }
  if (score > 0) return "positive";
  if (score < 0) return "negative";
  return "neutral";
}

export function analyze(req: AnalysisRequest, criteria: Criteria = DEFAULT_CRITERIA): AnalysisResult {
  const title = (req.title ?? "").trim();
  if (!title) {
    throw new Error("title is required");
  }
  const corpus = [title, req.description ?? "", req.transcript ?? ""].join(". ");
  const tags = extractTags(corpus);
  const topic = tags[0] ?? "this video";
  const wordCount = corpus.split(/\s+/).filter(Boolean).length;
  const sentiment = scoreSentiment(corpus, criteria);

  const summary =
    `"${title}" focuses on ${topic}` +
    (tags.length > 1 ? `, covering ${tags.slice(1, 3).join(" and ")}. ` : ". ") +
    `The content is ${wordCount} words long and reads with a ${sentiment} tone.`;

  return {
    summary,
    tags,
    sentiment,
    // ~3 words/second narration estimate
    readingTimeSeconds: Math.max(1, Math.round(wordCount / 3)),
    engine: "builtin",
  };
}
