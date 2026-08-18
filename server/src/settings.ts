import { DEFAULT_CRITERIA, type Criteria } from "./ai.js";

/** Configuration for delegating analysis to an external agent the user builds. */
export interface AgentConfig {
  enabled: boolean;
  /** HTTP endpoint that implements the agent contract (POST -> AnalysisResult). */
  url: string;
  /** Optional bearer token forwarded to the agent as `Authorization`. */
  token?: string;
}

export interface Settings {
  criteria: Criteria;
  agent: AgentConfig;
}

let settings: Settings = {
  criteria: {
    positive: [...DEFAULT_CRITERIA.positive],
    negative: [...DEFAULT_CRITERIA.negative],
  },
  agent: { enabled: false, url: "", token: "" },
};

function cleanTerms(value: unknown): string[] | undefined {
  if (!Array.isArray(value)) return undefined;
  const seen = new Set<string>();
  for (const item of value) {
    if (typeof item !== "string") continue;
    const term = item.trim().toLowerCase();
    if (term) seen.add(term);
  }
  return [...seen];
}

export function getSettings(): Settings {
  return settings;
}

export interface SettingsPatch {
  criteria?: { positive?: unknown; negative?: unknown };
  agent?: { enabled?: unknown; url?: unknown; token?: unknown };
}

/** Validate and merge a partial update. Returns the new settings. */
export function updateSettings(patch: SettingsPatch): Settings {
  const next: Settings = {
    criteria: {
      positive: [...settings.criteria.positive],
      negative: [...settings.criteria.negative],
    },
    agent: { ...settings.agent },
  };

  if (patch.criteria) {
    const positive = cleanTerms(patch.criteria.positive);
    const negative = cleanTerms(patch.criteria.negative);
    if (positive) next.criteria.positive = positive;
    if (negative) next.criteria.negative = negative;
  }

  if (patch.agent) {
    if (typeof patch.agent.enabled === "boolean") next.agent.enabled = patch.agent.enabled;
    if (typeof patch.agent.url === "string") next.agent.url = patch.agent.url.trim();
    if (typeof patch.agent.token === "string") next.agent.token = patch.agent.token.trim();
  }

  if (next.agent.enabled && !next.agent.url) {
    throw new Error("agent.url is required when agent.enabled is true");
  }

  settings = next;
  return settings;
}
