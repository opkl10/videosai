import { test } from "node:test";
import assert from "node:assert/strict";
import { analyze, extractTags, scoreSentiment } from "./ai.js";
import { getSettings, updateSettings } from "./settings.js";

test("extractTags returns ranked keywords without stop words", () => {
  const tags = extractTags("The video video pipeline pipeline pipeline serverless");
  assert.equal(tags[0], "pipeline");
  assert.ok(tags.includes("video"));
  assert.ok(!tags.includes("the"));
});

test("analyze produces a deterministic summary and tags", () => {
  const a = analyze({ title: "Great serverless pipeline", description: "amazing pipeline" });
  const b = analyze({ title: "Great serverless pipeline", description: "amazing pipeline" });
  assert.deepEqual(a, b);
  assert.ok(a.summary.includes("Great serverless pipeline"));
  assert.equal(a.sentiment, "positive");
  assert.ok(a.tags.length > 0);
});

test("analyze throws when title is missing", () => {
  assert.throws(() => analyze({ title: "" }));
});

test("custom criteria change the sentiment verdict", () => {
  const text = "the quarterly numbers look shiny and sparkly";
  assert.equal(scoreSentiment(text), "neutral");
  assert.equal(scoreSentiment(text, { positive: ["shiny", "sparkly"], negative: [] }), "positive");
  assert.equal(scoreSentiment(text, { positive: [], negative: ["shiny"] }), "negative");
});

test("updateSettings validates, normalizes, and dedupes criteria terms", () => {
  const updated = updateSettings({
    criteria: { positive: ["  Great ", "great", "WIN"], negative: ["Broken"] },
  });
  assert.deepEqual(updated.criteria.positive, ["great", "win"]);
  assert.deepEqual(updated.criteria.negative, ["broken"]);
  assert.equal(getSettings().criteria.positive.includes("great"), true);
});

test("enabling an agent without a url is rejected", () => {
  assert.throws(() => updateSettings({ agent: { enabled: true, url: "" } }));
});
