import { test } from "node:test";
import assert from "node:assert/strict";
import { analyze, extractTags } from "./ai.js";

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
