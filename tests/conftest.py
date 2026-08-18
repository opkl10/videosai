"""Shared fixtures: synthetic in-memory data plus real FFmpeg generated clips."""

from __future__ import annotations

import shutil
import subprocess
from pathlib import Path

import numpy as np
import pytest

from videosai.analyzers.base import AnalysisContext
from videosai.features import Keyframe, Timeline, build_timeline
from videosai.media import AudioStats, MediaInfo
from videosai.platforms import get_platform

FFMPEG_AVAILABLE = bool(shutil.which("ffmpeg") and shutil.which("ffprobe"))
requires_ffmpeg = pytest.mark.skipif(not FFMPEG_AVAILABLE, reason="FFmpeg is not installed")


# --------------------------------------------------------------------------- #
# Synthetic frames and contexts (fast, no FFmpeg)
# --------------------------------------------------------------------------- #
def noise_frame(
    width: int = 90, height: int = 160, brightness: float = 130.0, spread: float = 45.0, seed: int = 0
) -> np.ndarray:
    """A textured frame: high sharpness, mid brightness, some colour."""
    generator = np.random.default_rng(seed)
    values = generator.normal(brightness, spread, size=(height, width, 3))
    return np.clip(values, 0, 255).astype(np.uint8)


def flat_frame(width: int = 90, height: int = 160, brightness: float = 130.0) -> np.ndarray:
    """A featureless frame: no sharpness, no contrast, no colour."""
    return np.full((height, width, 3), int(brightness), dtype=np.uint8)


def timeline_from(frames: list[np.ndarray], fps: float = 4.0, **kwargs) -> Timeline:
    info = make_info(duration=len(frames) / fps)
    samples = [(index / fps, frame) for index, frame in enumerate(frames)]
    return build_timeline(info, fps=fps, frames=samples, **kwargs)


def make_info(**overrides) -> MediaInfo:
    defaults = {
        "path": "clip.mp4",
        "duration": 20.0,
        "width": 1080,
        "height": 1920,
        "fps": 30.0,
        "video_codec": "h264",
        "video_bitrate": 8_000_000,
        "total_bitrate": 8_200_000,
        "has_audio": True,
        "audio_codec": "aac",
        "audio_channels": 2,
        "audio_sample_rate": 48000,
    }
    defaults.update(overrides)
    return MediaInfo(**defaults)


def make_keyframes(
    count: int = 6,
    duration: float = 20.0,
    sharpness: float = 320.0,
    brightness: float = 130.0,
    contrast: float = 45.0,
    saturation: float = 0.3,
    clipped_high: float = 0.0,
) -> list[Keyframe]:
    step = duration / max(1, count)
    return [
        Keyframe(
            time=round(index * step, 3),
            sharpness=sharpness,
            brightness=brightness,
            contrast=contrast,
            saturation=saturation,
            clipped_high=clipped_high,
        )
        for index in range(count)
    ]


def make_audio(**overrides) -> AudioStats:
    defaults = {
        "integrated_lufs": -14.0,
        "loudness_range": 6.0,
        "true_peak_db": -1.5,
        "silences": [],
        "duration": 20.0,
        "loudness_source": "loudnorm",
    }
    defaults.update(overrides)
    return AudioStats(**defaults)


def make_context(
    *,
    platform: str = "tiktok",
    info: MediaInfo | None = None,
    timeline: Timeline | None = None,
    keyframes: list[Keyframe] | None = None,
    cuts: list[float] | None = None,
    audio: AudioStats | None = "default",  # type: ignore[assignment]
) -> AnalysisContext:
    """A healthy analysis context; override pieces to test a specific problem."""
    if info is None and timeline is not None:
        # Keep the declared duration consistent with the supplied timeline.
        info = make_info(duration=len(timeline) / (timeline.fps or 4.0))
    resolved_info = info or make_info()
    frames = [noise_frame(seed=index) for index in range(int(resolved_info.duration * 4))]
    return AnalysisContext(
        info=resolved_info,
        platform=get_platform(platform),
        timeline=timeline if timeline is not None else timeline_from(frames),
        keyframes=keyframes if keyframes is not None else make_keyframes(duration=resolved_info.duration),
        cuts=cuts if cuts is not None else [2.0, 5.0, 8.0, 11.0, 14.0, 17.0],
        audio=make_audio(duration=resolved_info.duration) if audio == "default" else audio,
    )


@pytest.fixture
def context_factory():
    return make_context


# --------------------------------------------------------------------------- #
# Real clips, generated once per test session
# --------------------------------------------------------------------------- #
def _ffmpeg(*args: str) -> None:
    subprocess.run(["ffmpeg", "-y", "-v", "error", *args], check=True, capture_output=True)


def _cut_filter(segments: int, segment_seconds: float) -> str:
    """Concatenate alternating normal / inverted segments to create hard cuts."""
    parts = []
    for index in range(segments):
        start = index * segment_seconds
        invert = ",negate" if index % 2 else ""
        parts.append(f"[0:v]trim={start}:{start + segment_seconds},setpts=PTS-STARTPTS{invert}[v{index}]")
    labels = "".join(f"[v{index}]" for index in range(segments))
    return ";".join(parts) + ";" + labels + f"concat=n={segments}:v=1:a=0[v]"


def _speech_like_filter(duration: float, gains_db: tuple[float, ...]) -> str:
    """Concatenate sine chunks at different volumes, so loudness has a range."""
    chunk = duration / len(gains_db)
    parts = []
    for index, gain in enumerate(gains_db):
        start = index * chunk
        parts.append(
            f"[1:a]atrim={start}:{start + chunk},asetpts=PTS-STARTPTS,volume={gain}dB[a{index}]"
        )
    labels = "".join(f"[a{index}]" for index in range(len(gains_db)))
    return ";".join(parts) + ";" + labels + f"concat=n={len(gains_db)}:v=0:a=1[a]"


def _make_good_clip(path: Path) -> None:
    """Vertical 1080x1920, 16s, a cut every 2s, audio around -14 LUFS."""
    duration = 16.0
    video_filter = _cut_filter(8, 2.0)
    audio_filter = _speech_like_filter(duration, (10, 4, 9, 6))
    _ffmpeg(
        "-f", "lavfi", "-i", f"testsrc=size=1080x1920:rate=30:duration={duration}",
        "-f", "lavfi", "-i", f"sine=frequency=420:duration={duration}",
        "-filter_complex", f"{video_filter};{audio_filter}",
        "-map", "[v]", "-map", "[a]",
        "-c:v", "libx264", "-preset", "ultrafast", "-b:v", "6M", "-pix_fmt", "yuv420p",
        "-c:a", "aac", "-b:a", "128k", "-ar", "48000", "-shortest", str(path),
    )


def _make_dark_silent_clip(path: Path) -> None:
    """Horizontal 640x360, 6s, frozen, almost black, no audio track."""
    _ffmpeg(
        "-f", "lavfi", "-i", "color=c=0x0d0d0d:size=640x360:rate=24:duration=6",
        "-c:v", "libx264", "-preset", "ultrafast", "-pix_fmt", "yuv420p", str(path),
    )


def _make_quiet_clip(path: Path) -> None:
    """Vertical, well exposed, but the audio is far too quiet."""
    _ffmpeg(
        "-f", "lavfi", "-i", "testsrc=size=1080x1920:rate=30:duration=8",
        "-f", "lavfi", "-i", "sine=frequency=420:duration=8",
        "-af", "volume=-30dB",
        "-c:v", "libx264", "-preset", "ultrafast", "-b:v", "6M", "-pix_fmt", "yuv420p",
        "-c:a", "aac", "-ar", "48000", "-shortest", str(path),
    )


def _make_dead_air_clip(path: Path) -> None:
    """Audio only starts after 1.6 seconds of silence."""
    _ffmpeg(
        "-f", "lavfi", "-i", "testsrc=size=1080x1920:rate=30:duration=8",
        "-f", "lavfi", "-i", "sine=frequency=420:duration=6.4",
        "-af", "adelay=1600:all=1,volume=8dB",
        "-c:v", "libx264", "-preset", "ultrafast", "-b:v", "6M", "-pix_fmt", "yuv420p",
        "-c:a", "aac", "-ar", "48000", "-shortest", str(path),
    )


_CLIP_BUILDERS = {
    "good": _make_good_clip,
    "dark_silent": _make_dark_silent_clip,
    "quiet": _make_quiet_clip,
    "dead_air": _make_dead_air_clip,
}


@pytest.fixture(scope="session")
def clips(tmp_path_factory: pytest.TempPathFactory) -> dict[str, Path]:
    if not FFMPEG_AVAILABLE:
        pytest.skip("FFmpeg is not installed")
    directory = tmp_path_factory.mktemp("clips")
    paths = {}
    for name, builder in _CLIP_BUILDERS.items():
        path = directory / f"{name}.mp4"
        builder(path)
        paths[name] = path
    return paths
