"""The analysis pipeline: measure once, then run every analyzer."""

from __future__ import annotations

from pathlib import Path

from .analyzers import ANALYZERS, AnalysisContext
from .features import build_keyframes, build_timeline, merge_cuts, motion_cuts
from .media import measure_audio, probe, save_frame, scene_cuts
from .models import Report, ThumbnailCandidate
from .platforms import Platform, get_platform
from .scoring import apply_weights, overall_score
from .version import __version__

DEFAULT_SAMPLE_FPS = 4.0
DEFAULT_KEYFRAMES = 9
#: Safe zone bands used for measurement even when the platform has none.
MEASUREMENT_SAFE_ZONE = (0.12, 0.20)


def _keyframe_count(duration: float, requested: int) -> int:
    if requested > 0:
        return requested
    return max(5, min(24, int(duration // 3) + 3))


def analyze_video(
    path: str | Path,
    platform: str | Platform = "tiktok",
    *,
    sample_fps: float = DEFAULT_SAMPLE_FPS,
    keyframes: int = DEFAULT_KEYFRAMES,
    scene_threshold: float = 0.35,
    thumbnails_dir: str | Path | None = None,
    thumbnail_count: int = 3,
) -> Report:
    """Analyze one video file and return a scored report.

    ``thumbnails_dir`` is optional; when given, the best cover frames are
    written there as JPEGs.
    """
    target = platform if isinstance(platform, Platform) else get_platform(platform)
    info = probe(path)

    timeline = build_timeline(
        info,
        fps=sample_fps,
        safe_zone_top=target.safe_zone_top or MEASUREMENT_SAFE_ZONE[0],
        safe_zone_bottom=target.safe_zone_bottom or MEASUREMENT_SAFE_ZONE[1],
    )
    frames = build_keyframes(info, count=_keyframe_count(info.duration, keyframes))
    cuts = merge_cuts(scene_cuts(info, threshold=scene_threshold), motion_cuts(timeline))
    context = AnalysisContext(
        info=info,
        platform=target,
        timeline=timeline,
        keyframes=frames,
        cuts=cuts,
        audio=measure_audio(info),
    )

    categories = [analyzer(context) for analyzer in ANALYZERS.values()]
    apply_weights(categories, target.weights)

    media = info.as_dict()
    if context.audio is not None:
        media["audio"] = context.audio.as_dict()
    media["cuts"] = [round(t, 2) for t in context.cuts]

    return Report(
        file=str(path),
        platform=target.key,
        score=overall_score(categories),
        media=media,
        categories=categories,
        thumbnail_candidates=pick_thumbnails(
            context, limit=thumbnail_count, output_dir=thumbnails_dir
        ),
        version=__version__,
    )


def pick_thumbnails(
    context: AnalysisContext, limit: int = 3, output_dir: str | Path | None = None
) -> list[ThumbnailCandidate]:
    """Rank sampled frames as cover images, optionally exporting them."""
    if limit <= 0 or not context.keyframes:
        return []

    ranked = sorted(context.keyframes, key=lambda k: -k.cover_score)
    chosen: list[ThumbnailCandidate] = []
    for frame in ranked:
        # Keep suggestions spread out instead of three frames of one shot.
        if any(abs(frame.time - candidate.time) < 1.0 for candidate in chosen):
            continue
        chosen.append(
            ThumbnailCandidate(
                time=frame.time,
                score=frame.cover_score,
                sharpness=frame.sharpness,
                brightness=frame.brightness,
                saturation=frame.saturation,
            )
        )
        if len(chosen) >= limit:
            break

    if output_dir is None:
        return chosen

    directory = Path(output_dir)
    exported: list[ThumbnailCandidate] = []
    for index, candidate in enumerate(chosen, start=1):
        stem = Path(context.info.path).stem
        destination = directory / f"{stem}-cover-{index}-{candidate.time:.2f}s.jpg"
        saved = save_frame(context.info, candidate.time, destination)
        exported.append(
            candidate if saved is None else ThumbnailCandidate(**{**candidate.__dict__, "file": str(saved)})
        )
    return exported
