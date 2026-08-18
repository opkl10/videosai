"""Thin FFmpeg / FFprobe layer.

Everything that touches the actual media file goes through here, so the
analyzers can work with plain numbers and numpy arrays.
"""

from __future__ import annotations

import json
import re
import shutil
import subprocess
import tempfile
from dataclasses import dataclass, field
from fractions import Fraction
from pathlib import Path
from typing import Iterator

import numpy as np

from .errors import FFmpegMissingError, MediaError

FFMPEG = "ffmpeg"
FFPROBE = "ffprobe"

_SCENE_TIME_RE = re.compile(r"pts_time:([0-9]+\.?[0-9]*)")
_SILENCE_START_RE = re.compile(r"silence_start: (-?[0-9]+\.?[0-9]*)")
_SILENCE_END_RE = re.compile(r"silence_end: ([0-9]+\.?[0-9]*)")
_MEAN_VOLUME_RE = re.compile(r"mean_volume: (-?[0-9]+\.?[0-9]*) dB")
_MAX_VOLUME_RE = re.compile(r"max_volume: (-?[0-9]+\.?[0-9]*) dB")


def require_ffmpeg() -> None:
    for tool in (FFMPEG, FFPROBE):
        if shutil.which(tool) is None:
            raise FFmpegMissingError(tool)


def _run(cmd: list[str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        cmd,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
        errors="replace",
        check=False,
    )


@dataclass(frozen=True)
class MediaInfo:
    path: str
    duration: float
    width: int
    height: int
    fps: float
    video_codec: str
    video_bitrate: int | None
    total_bitrate: int | None
    has_audio: bool
    audio_codec: str | None = None
    audio_channels: int | None = None
    audio_sample_rate: int | None = None

    @property
    def aspect_ratio(self) -> float:
        return self.width / self.height if self.height else 0.0

    @property
    def is_vertical(self) -> bool:
        return self.aspect_ratio < 1.0

    @property
    def bits_per_pixel(self) -> float | None:
        bitrate = self.video_bitrate or self.total_bitrate
        pixels_per_second = self.width * self.height * self.fps
        if not bitrate or not pixels_per_second:
            return None
        return bitrate / pixels_per_second

    def as_dict(self) -> dict[str, object]:
        return {
            "duration": round(self.duration, 3),
            "width": self.width,
            "height": self.height,
            "fps": round(self.fps, 3),
            "aspect_ratio": round(self.aspect_ratio, 4),
            "video_codec": self.video_codec,
            "video_bitrate": self.video_bitrate,
            "total_bitrate": self.total_bitrate,
            "has_audio": self.has_audio,
            "audio_codec": self.audio_codec,
            "audio_channels": self.audio_channels,
            "audio_sample_rate": self.audio_sample_rate,
        }


def _parse_fps(value: str | None) -> float:
    if not value or value in {"0/0", "N/A"}:
        return 0.0
    try:
        return float(Fraction(value))
    except (ValueError, ZeroDivisionError):
        return 0.0


def _to_int(value: object) -> int | None:
    try:
        return int(float(value))  # type: ignore[arg-type]
    except (TypeError, ValueError):
        return None


def probe(path: str | Path) -> MediaInfo:
    """Read container / stream metadata with ffprobe."""
    require_ffmpeg()
    path = Path(path)
    if not path.exists():
        raise MediaError(f"File not found: {path}")

    result = _run(
        [
            FFPROBE,
            "-v",
            "error",
            "-print_format",
            "json",
            "-show_format",
            "-show_streams",
            str(path),
        ]
    )
    if result.returncode != 0:
        raise MediaError(f"ffprobe failed for {path}: {result.stderr.strip()}")

    try:
        payload = json.loads(result.stdout or "{}")
    except json.JSONDecodeError as exc:
        raise MediaError(f"Could not parse ffprobe output for {path}: {exc}") from exc

    streams = payload.get("streams", [])
    fmt = payload.get("format", {})
    video = next((s for s in streams if s.get("codec_type") == "video"), None)
    audio = next((s for s in streams if s.get("codec_type") == "audio"), None)
    if video is None:
        raise MediaError(f"No video stream found in {path}")

    fps = _parse_fps(video.get("avg_frame_rate")) or _parse_fps(video.get("r_frame_rate"))
    duration = float(fmt.get("duration") or video.get("duration") or 0.0)
    if duration <= 0:
        frames = _to_int(video.get("nb_frames"))
        if frames and fps:
            duration = frames / fps
    if duration <= 0:
        raise MediaError(f"Could not determine the duration of {path}")

    width, height = _to_int(video.get("width")) or 0, _to_int(video.get("height")) or 0
    if not width or not height:
        raise MediaError(f"Could not determine the frame size of {path}")

    return MediaInfo(
        path=str(path),
        duration=duration,
        width=width,
        height=height,
        fps=fps,
        video_codec=str(video.get("codec_name") or "unknown"),
        video_bitrate=_to_int(video.get("bit_rate")),
        total_bitrate=_to_int(fmt.get("bit_rate")),
        has_audio=audio is not None,
        audio_codec=str(audio.get("codec_name")) if audio else None,
        audio_channels=_to_int(audio.get("channels")) if audio else None,
        audio_sample_rate=_to_int(audio.get("sample_rate")) if audio else None,
    )


def scaled_size(info: MediaInfo, target_height: int) -> tuple[int, int]:
    """Even width/height that preserves the source aspect ratio."""
    height = min(target_height, info.height)
    height = max(2, height - (height % 2))
    width = max(2, round(info.width * height / info.height))
    width -= width % 2
    return width, height


def _iter_raw_frames(cmd: list[str], width: int, height: int) -> Iterator[np.ndarray]:
    frame_bytes = width * height * 3
    with tempfile.TemporaryFile() as errfile:
        process = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=errfile)
        assert process.stdout is not None
        frames = 0
        try:
            while True:
                buffer = process.stdout.read(frame_bytes)
                if not buffer or len(buffer) < frame_bytes:
                    break
                frames += 1
                yield np.frombuffer(buffer, dtype=np.uint8).reshape(height, width, 3)
        finally:
            process.stdout.close()
            process.wait()
        if frames == 0:
            errfile.seek(0)
            message = errfile.read().decode("utf-8", "replace").strip()
            raise MediaError(f"FFmpeg returned no frames. {message}")


def sample_frames(
    info: MediaInfo, fps: float = 4.0, target_height: int = 144
) -> Iterator[tuple[float, np.ndarray]]:
    """Yield ``(timestamp, rgb_frame)`` at a fixed sampling rate."""
    require_ffmpeg()
    width, height = scaled_size(info, target_height)
    cmd = [
        FFMPEG,
        "-nostdin",
        "-v",
        "error",
        "-i",
        info.path,
        "-map",
        "0:v:0",
        "-vf",
        f"fps={fps},scale={width}:{height}",
        "-pix_fmt",
        "rgb24",
        "-f",
        "rawvideo",
        "-",
    ]
    for index, frame in enumerate(_iter_raw_frames(cmd, width, height)):
        yield index / fps, frame


def extract_frame(info: MediaInfo, time: float, target_height: int = 360) -> np.ndarray | None:
    """Decode a single frame at ``time`` seconds."""
    require_ffmpeg()
    width, height = scaled_size(info, target_height)
    cmd = [
        FFMPEG,
        "-nostdin",
        "-v",
        "error",
        "-ss",
        f"{max(0.0, time):.3f}",
        "-i",
        info.path,
        "-map",
        "0:v:0",
        "-frames:v",
        "1",
        "-vf",
        f"scale={width}:{height}",
        "-pix_fmt",
        "rgb24",
        "-f",
        "rawvideo",
        "-",
    ]
    try:
        return next(iter(_iter_raw_frames(cmd, width, height)))
    except (StopIteration, MediaError):
        return None


def save_frame(info: MediaInfo, time: float, destination: str | Path) -> Path | None:
    """Write the frame at ``time`` to an image file."""
    require_ffmpeg()
    destination = Path(destination)
    destination.parent.mkdir(parents=True, exist_ok=True)
    result = _run(
        [
            FFMPEG,
            "-nostdin",
            "-v",
            "error",
            "-y",
            "-ss",
            f"{max(0.0, time):.3f}",
            "-i",
            info.path,
            "-map",
            "0:v:0",
            "-frames:v",
            "1",
            "-q:v",
            "2",
            str(destination),
        ]
    )
    if result.returncode != 0 or not destination.exists():
        return None
    return destination


def scene_cuts(info: MediaInfo, threshold: float = 0.35) -> list[float]:
    """Timestamps of hard cuts, detected by FFmpeg's scene score."""
    require_ffmpeg()
    result = _run(
        [
            FFMPEG,
            "-nostdin",
            "-v",
            "info",
            "-i",
            info.path,
            "-map",
            "0:v:0",
            "-vf",
            f"select='gt(scene,{threshold})',showinfo",
            "-an",
            "-f",
            "null",
            "-",
        ]
    )
    cuts = [float(match) for match in _SCENE_TIME_RE.findall(result.stderr)]
    return sorted(t for t in cuts if 0.0 <= t <= info.duration + 1)


@dataclass
class AudioStats:
    """Loudness and silence measurements for the audio track."""

    integrated_lufs: float | None = None
    loudness_range: float | None = None
    true_peak_db: float | None = None
    silences: list[tuple[float, float]] = field(default_factory=list)
    duration: float = 0.0
    #: "loudnorm" for real LUFS, "volumedetect" for the RMS approximation.
    loudness_source: str | None = None

    @property
    def silence_ratio(self) -> float:
        if self.duration <= 0:
            return 0.0
        total = sum(end - start for start, end in self.silences)
        return min(1.0, total / self.duration)

    @property
    def lead_in_silence(self) -> float:
        for start, end in self.silences:
            if start <= 0.15:
                return max(0.0, end)
        return 0.0

    def long_gaps(self, minimum: float = 1.2, skip_lead_in: bool = True) -> list[tuple[float, float]]:
        gaps = []
        for start, end in self.silences:
            if skip_lead_in and start <= 0.15:
                continue
            # A trailing silence is usually just the outro fading out.
            if end >= self.duration - 0.2:
                continue
            if end - start >= minimum:
                gaps.append((start, end))
        return gaps

    def as_dict(self) -> dict[str, object]:
        return {
            "integrated_lufs": self.integrated_lufs,
            "loudness_range": self.loudness_range,
            "true_peak_db": self.true_peak_db,
            "silence_ratio": round(self.silence_ratio, 3),
            "silences": [[round(s, 2), round(e, 2)] for s, e in self.silences],
            "loudness_source": self.loudness_source,
        }


def _parse_silences(stderr: str, duration: float) -> list[tuple[float, float]]:
    starts = [float(v) for v in _SILENCE_START_RE.findall(stderr)]
    ends = [float(v) for v in _SILENCE_END_RE.findall(stderr)]
    silences: list[tuple[float, float]] = []
    for index, start in enumerate(starts):
        start = max(0.0, start)
        end = ends[index] if index < len(ends) else duration
        if end > start:
            silences.append((start, min(end, duration)))
    return silences


def _parse_loudnorm(stderr: str) -> dict[str, float]:
    start = stderr.rfind("{")
    end = stderr.rfind("}")
    if start == -1 or end <= start:
        return {}
    try:
        payload = json.loads(stderr[start : end + 1])
    except json.JSONDecodeError:
        return {}
    values: dict[str, float] = {}
    for key in ("input_i", "input_lra", "input_tp"):
        try:
            values[key] = float(payload[key])
        except (KeyError, TypeError, ValueError):
            continue
    return values


def measure_audio(
    info: MediaInfo, silence_threshold_db: float = -35.0, silence_min: float = 0.4
) -> AudioStats | None:
    """Measure loudness and silence in a single FFmpeg pass."""
    if not info.has_audio:
        return None
    require_ffmpeg()

    filters = (
        f"silencedetect=noise={silence_threshold_db}dB:d={silence_min},"
        "loudnorm=print_format=json"
    )
    result = _run(
        [
            FFMPEG,
            "-nostdin",
            "-v",
            "info",
            "-i",
            info.path,
            "-map",
            "0:a:0",
            "-af",
            filters,
            "-f",
            "null",
            "-",
        ]
    )
    stats = AudioStats(duration=info.duration)
    stats.silences = _parse_silences(result.stderr, info.duration)

    loudnorm = _parse_loudnorm(result.stderr)
    if "input_i" in loudnorm:
        stats.integrated_lufs = loudnorm["input_i"]
        stats.loudness_range = loudnorm.get("input_lra")
        stats.true_peak_db = loudnorm.get("input_tp")
        stats.loudness_source = "loudnorm"
        return stats

    # Older FFmpeg builds, or clips too short for loudnorm: fall back to RMS.
    fallback = _run(
        [
            FFMPEG,
            "-nostdin",
            "-v",
            "info",
            "-i",
            info.path,
            "-map",
            "0:a:0",
            "-af",
            "volumedetect",
            "-f",
            "null",
            "-",
        ]
    )
    mean_volume = _MEAN_VOLUME_RE.search(fallback.stderr)
    max_volume = _MAX_VOLUME_RE.search(fallback.stderr)
    if mean_volume:
        stats.integrated_lufs = float(mean_volume.group(1))
        stats.loudness_source = "volumedetect"
    if max_volume:
        stats.true_peak_db = float(max_volume.group(1))
    return stats
