"""Command line interface: ``videosai analyze clip.mp4``."""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

from . import messages
from .engine import DEFAULT_KEYFRAMES, DEFAULT_SAMPLE_FPS, analyze_video
from .errors import VideosaiError
from .platforms import PLATFORMS
from .render import to_html, to_json, to_markdown, to_text
from .version import __version__

RENDERERS = {
    "text": to_text,
    "md": to_markdown,
    "markdown": to_markdown,
    "json": lambda report, lang: to_json(report),
    "html": to_html,
}

EXIT_OK = 0
EXIT_BELOW_THRESHOLD = 1
EXIT_ERROR = 2


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="videosai",
        description="Analyze videos and get concrete feedback on what works and what does not.",
    )
    parser.add_argument("--version", action="version", version=f"videosai {__version__}")
    subparsers = parser.add_subparsers(dest="command")

    analyze = subparsers.add_parser("analyze", help="analyze one or more video files")
    analyze.add_argument("files", nargs="+", type=Path, help="video files to analyze")
    analyze.add_argument(
        "-p",
        "--platform",
        default="tiktok",
        choices=sorted(PLATFORMS),
        help="target platform (default: tiktok)",
    )
    analyze.add_argument(
        "-l",
        "--lang",
        default=messages.DEFAULT_LANGUAGE,
        choices=list(messages.LANGUAGES),
        help="report language (default: he)",
    )
    analyze.add_argument(
        "-f",
        "--format",
        default="text",
        choices=sorted(RENDERERS),
        help="output format (default: text)",
    )
    analyze.add_argument("-o", "--out", type=Path, help="write the report to a file")
    analyze.add_argument(
        "--thumbnails",
        type=Path,
        metavar="DIR",
        help="export the suggested cover frames to this directory",
    )
    analyze.add_argument(
        "--thumbnail-count", type=int, default=3, help="how many cover frames to suggest"
    )
    analyze.add_argument(
        "--sample-fps",
        type=float,
        default=DEFAULT_SAMPLE_FPS,
        help="frames sampled per second for the timeline (default: 4)",
    )
    analyze.add_argument(
        "--keyframes",
        type=int,
        default=DEFAULT_KEYFRAMES,
        help="high resolution samples used for sharpness (0 = scale with duration)",
    )
    analyze.add_argument(
        "--scene-threshold",
        type=float,
        default=0.35,
        help="cut detection sensitivity, 0..1 (default: 0.35)",
    )
    analyze.add_argument(
        "--fail-under",
        type=float,
        metavar="SCORE",
        help="exit with code 1 if the overall score is below this value",
    )

    subparsers.add_parser("platforms", help="list the supported platform presets")

    serve = subparsers.add_parser("serve", help="run the upload server in a browser")
    serve.add_argument("--host", default="127.0.0.1", help="bind address (default: 127.0.0.1)")
    serve.add_argument("--port", type=int, default=8000, help="port (default: 8000)")
    serve.add_argument(
        "--workdir", type=Path, help="where uploads and exported frames live (default: temp dir)"
    )
    serve.add_argument(
        "--max-upload-mb", type=int, default=512, help="reject uploads above this size"
    )
    serve.add_argument(
        "--keep-uploads", action="store_true", help="keep uploaded videos instead of deleting them"
    )
    serve.add_argument("--log-level", default="info", help="uvicorn log level")
    return parser


def _print_platforms(lang: str) -> int:
    rows = []
    for platform in PLATFORMS.values():
        minimum, maximum = platform.ideal_duration
        aspect = f"{platform.target_aspect:.2f}" if platform.target_aspect else "-"
        rows.append(
            f"  {platform.key:<15} {platform.label(lang):<22} "
            f"aspect {aspect:<6} {minimum:.0f}-{maximum:.0f}s  {platform.min_height}p+"
        )
    print("\n".join(rows))
    return EXIT_OK


def _run_serve(args: argparse.Namespace) -> int:
    try:
        from .server import ServerSettings, serve
    except ImportError:
        print(
            'The server needs the optional extra: pip install "videosai[server]"',
            file=sys.stderr,
        )
        return EXIT_ERROR

    settings = ServerSettings(
        max_upload_bytes=args.max_upload_mb * 1024 * 1024,
        keep_uploads=args.keep_uploads,
    )
    if args.workdir:
        settings.workdir = args.workdir

    print(f"videosai {__version__} serving on http://{args.host}:{args.port}")
    serve(host=args.host, port=args.port, settings=settings, log_level=args.log_level)
    return EXIT_OK


def _run_analyze(args: argparse.Namespace) -> int:
    if args.out and len(args.files) > 1:
        print("--out works with a single file only.", file=sys.stderr)
        return EXIT_ERROR

    renderer = RENDERERS[args.format]
    exit_code = EXIT_OK
    for path in args.files:
        try:
            report = analyze_video(
                path,
                platform=args.platform,
                sample_fps=args.sample_fps,
                keyframes=args.keyframes,
                scene_threshold=args.scene_threshold,
                thumbnails_dir=args.thumbnails,
                thumbnail_count=args.thumbnail_count,
            )
        except VideosaiError as error:
            print(f"{path}: {error}", file=sys.stderr)
            exit_code = EXIT_ERROR
            continue

        output = renderer(report, args.lang)
        if args.out:
            args.out.parent.mkdir(parents=True, exist_ok=True)
            args.out.write_text(output, encoding="utf-8")
            print(f"{report.score:.1f}/100 ({report.grade}) -> {args.out}")
        else:
            print(output)

        if args.fail_under is not None and report.score < args.fail_under:
            exit_code = max(exit_code, EXIT_BELOW_THRESHOLD)
    return exit_code


def main(argv: list[str] | None = None) -> int:
    parser = build_parser()
    args = parser.parse_args(argv)

    if args.command == "platforms":
        return _print_platforms(messages.DEFAULT_LANGUAGE)
    if args.command == "analyze":
        return _run_analyze(args)
    if args.command == "serve":
        return _run_serve(args)

    parser.print_help()
    return EXIT_ERROR


if __name__ == "__main__":  # pragma: no cover
    raise SystemExit(main())
