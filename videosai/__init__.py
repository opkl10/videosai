"""videosai - automated feedback on videos for content creators.

Analyze a video file and get a scored report saying what works, what does not,
and what to fix first, per publishing platform.

    from videosai import analyze_video, to_markdown

    report = analyze_video("clip.mp4", platform="tiktok")
    print(report.score, report.grade)
    print(to_markdown(report, lang="he"))
"""

from __future__ import annotations

from .engine import analyze_video, pick_thumbnails
from .errors import FFmpegMissingError, MediaError, VideosaiError
from .models import CategoryResult, Finding, Report, Severity, ThumbnailCandidate
from .platforms import PLATFORMS, Platform, get_platform
from .render import to_html, to_json, to_markdown, to_text
from .version import __version__

__all__ = [
    "PLATFORMS",
    "CategoryResult",
    "FFmpegMissingError",
    "Finding",
    "MediaError",
    "Platform",
    "Report",
    "Severity",
    "ThumbnailCandidate",
    "VideosaiError",
    "__version__",
    "analyze_video",
    "get_platform",
    "pick_thumbnails",
    "to_html",
    "to_json",
    "to_markdown",
    "to_text",
]
