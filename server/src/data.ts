export interface Video {
  id: string;
  title: string;
  description: string;
  transcript: string;
  durationSeconds: number;
}

export const videos: Video[] = [
  {
    id: "vid-001",
    title: "Building a serverless video pipeline",
    description:
      "A hands-on walkthrough of an amazing serverless architecture for encoding and streaming video at scale.",
    transcript:
      "In this video we build a serverless pipeline. We upload video, trigger encoding, and stream the output. The results are great and the setup improves developer velocity.",
    durationSeconds: 742,
  },
  {
    id: "vid-002",
    title: "Debugging flaky tests",
    description:
      "Why tests fail intermittently and the common problems behind broken CI pipelines.",
    transcript:
      "Flaky tests are a real problem. A broken test can fail one run and pass the next. We look at timing bugs and shared state that cause boring, hard to reproduce failures.",
    durationSeconds: 531,
  },
  {
    id: "vid-003",
    title: "Designing beautiful dashboards",
    description:
      "Best practices for creating beautiful, readable dashboards that users love.",
    transcript:
      "A great dashboard tells a story. We cover layout, color, and typography to build beautiful interfaces that improve clarity and help users win back time.",
    durationSeconds: 998,
  },
];
