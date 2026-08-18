import { useEffect, useState } from "react";
import {
  analyzeCustom,
  analyzeVideo,
  fetchVideos,
  getSettings,
  updateSettings,
  type AnalysisResult,
  type Settings,
  type VideoSummary,
} from "./api";

function formatDuration(seconds: number): string {
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return `${m}:${s.toString().padStart(2, "0")}`;
}

function ResultCard({ result }: { result: AnalysisResult }) {
  return (
    <div className="result" data-testid="analysis-result">
      <div className="result-head">
        <h3>AI summary</h3>
        {result.engine && <span className="engine">{result.engine}</span>}
      </div>
      <p className="summary">{result.summary}</p>
      <div className="tags">
        {result.tags.map((tag) => (
          <span className="tag" key={tag}>
            #{tag}
          </span>
        ))}
      </div>
      <div className="meta">
        <span className={`badge badge-${result.sentiment}`}>{result.sentiment}</span>
        <span className="reading">~{result.readingTimeSeconds}s narration</span>
      </div>
    </div>
  );
}

export function App() {
  const [videos, setVideos] = useState<VideoSummary[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loadingId, setLoadingId] = useState<string | null>(null);
  const [results, setResults] = useState<Record<string, AnalysisResult>>({});

  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [customResult, setCustomResult] = useState<AnalysisResult | null>(null);
  const [customBusy, setCustomBusy] = useState(false);

  // Settings: good/bad criteria + external agent config.
  const [positive, setPositive] = useState("");
  const [negative, setNegative] = useState("");
  const [agentUrl, setAgentUrl] = useState("");
  const [agentEnabled, setAgentEnabled] = useState(false);
  const [settingsMsg, setSettingsMsg] = useState<string | null>(null);
  const [settingsBusy, setSettingsBusy] = useState(false);

  function applySettings(s: Settings) {
    setPositive(s.criteria.positive.join(", "));
    setNegative(s.criteria.negative.join(", "));
    setAgentUrl(s.agent.url);
    setAgentEnabled(s.agent.enabled);
  }

  useEffect(() => {
    fetchVideos()
      .then(setVideos)
      .catch((e) => setError(String(e)));
    getSettings()
      .then(applySettings)
      .catch((e) => setError(String(e)));
  }, []);

  function parseTerms(value: string): string[] {
    return value
      .split(",")
      .map((t) => t.trim())
      .filter(Boolean);
  }

  async function handleSaveSettings(e: React.FormEvent) {
    e.preventDefault();
    setSettingsBusy(true);
    setSettingsMsg(null);
    setError(null);
    try {
      const saved = await updateSettings({
        criteria: { positive: parseTerms(positive), negative: parseTerms(negative) },
        agent: { enabled: agentEnabled, url: agentUrl.trim() },
      });
      applySettings(saved);
      setSettingsMsg(
        `Saved. Engine: ${saved.agent.enabled ? `custom agent (${saved.agent.url})` : "built-in"}.`,
      );
    } catch (e) {
      setError(e instanceof Error ? e.message : String(e));
    } finally {
      setSettingsBusy(false);
    }
  }

  async function handleAnalyze(id: string) {
    setLoadingId(id);
    setError(null);
    try {
      const result = await analyzeVideo(id);
      setResults((prev) => ({ ...prev, [id]: result }));
    } catch (e) {
      setError(String(e));
    } finally {
      setLoadingId(null);
    }
  }

  async function handleCustom(e: React.FormEvent) {
    e.preventDefault();
    setCustomBusy(true);
    setError(null);
    try {
      const result = await analyzeCustom({ title, description });
      setCustomResult(result);
    } catch (e) {
      setError(e instanceof Error ? e.message : String(e));
    } finally {
      setCustomBusy(false);
    }
  }

  return (
    <div className="page">
      <header className="hero">
        <h1>
          Videos<span className="accent">AI</span>
        </h1>
        <p>Generate instant AI summaries, tags, and sentiment for your video library.</p>
      </header>

      {error && <div className="error">{error}</div>}

      <section className="settings">
        <h2>Scoring criteria &amp; agent</h2>
        <p className="hint">
          Define the good/bad parameters used for sentiment, and optionally route analysis to a
          custom agent you build.
        </p>
        <form onSubmit={handleSaveSettings} className="settings-form">
          <label>
            Good signals (comma separated)
            <input
              type="text"
              value={positive}
              onChange={(e) => setPositive(e.target.value)}
              placeholder="great, amazing, love"
              data-testid="positive-input"
            />
          </label>
          <label>
            Bad signals (comma separated)
            <input
              type="text"
              value={negative}
              onChange={(e) => setNegative(e.target.value)}
              placeholder="bad, broken, boring"
              data-testid="negative-input"
            />
          </label>
          <label>
            Custom agent URL
            <input
              type="text"
              value={agentUrl}
              onChange={(e) => setAgentUrl(e.target.value)}
              placeholder="http://localhost:4000/analyze"
              data-testid="agent-url-input"
            />
          </label>
          <label className="toggle">
            <input
              type="checkbox"
              checked={agentEnabled}
              onChange={(e) => setAgentEnabled(e.target.checked)}
              data-testid="agent-enabled"
            />
            Use custom agent (falls back to built-in on failure)
          </label>
          <button type="submit" disabled={settingsBusy} data-testid="save-settings">
            {settingsBusy ? "Saving…" : "Save settings"}
          </button>
          {settingsMsg && (
            <p className="settings-msg" data-testid="settings-msg">
              {settingsMsg}
            </p>
          )}
        </form>
      </section>

      <section>
        <h2>Video library</h2>
        <div className="grid">
          {videos.map((video) => (
            <article className="card" key={video.id}>
              <div className="thumb">▶</div>
              <div className="card-body">
                <h3>{video.title}</h3>
                <p className="desc">{video.description}</p>
                <div className="card-meta">
                  <span>{formatDuration(video.durationSeconds)}</span>
                  <button
                    onClick={() => handleAnalyze(video.id)}
                    disabled={loadingId === video.id}
                  >
                    {loadingId === video.id ? "Analyzing…" : "Analyze with AI"}
                  </button>
                </div>
                {results[video.id] && <ResultCard result={results[video.id]} />}
              </div>
            </article>
          ))}
        </div>
      </section>

      <section className="custom">
        <h2>Analyze your own video</h2>
        <form onSubmit={handleCustom}>
          <input
            type="text"
            placeholder="Video title"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            required
          />
          <textarea
            placeholder="Description or transcript (optional)"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            rows={3}
          />
          <button type="submit" disabled={customBusy || !title.trim()}>
            {customBusy ? "Analyzing…" : "Generate summary"}
          </button>
        </form>
        {customResult && <ResultCard result={customResult} />}
      </section>

      <footer>
        <span>VideosAI starter · configurable criteria · pluggable agent</span>
      </footer>
    </div>
  );
}
