"""Frame-level feature extraction.

Two passes over the video: a dense low resolution pass that builds a timeline
of brightness / colour / motion, and a sparse high resolution pass used for
sharpness and cover frame selection.
"""

from __future__ import annotations

from collections.abc import Iterable, Sequence
from dataclasses import dataclass, field

import numpy as np

from .media import MediaInfo, extract_frame, sample_frames

_LUMA_WEIGHTS = np.array([0.299, 0.587, 0.114], dtype=np.float32)


def _as_array(values: Sequence[float]) -> np.ndarray:
    return np.asarray(values, dtype=np.float32)


def to_luma(frame: np.ndarray) -> np.ndarray:
    """Rec.601 luma of an RGB frame, as float32 in 0..255."""
    return frame.astype(np.float32) @ _LUMA_WEIGHTS


def saturation_of(frame: np.ndarray) -> float:
    """Mean HSV-style saturation of an RGB frame, in 0..1."""
    values = frame.astype(np.float32)
    maximum = values.max(axis=2)
    minimum = values.min(axis=2)
    return float(np.mean((maximum - minimum) / np.maximum(maximum, 1.0)))


def sharpness_of(luma: np.ndarray) -> float:
    """Variance of the Laplacian: high means crisp edges, low means soft."""
    laplacian = (
        4.0 * luma[1:-1, 1:-1] - luma[:-2, 1:-1] - luma[2:, 1:-1] - luma[1:-1, :-2] - luma[1:-1, 2:]
    )
    return float(np.var(laplacian))


def edge_energy(luma: np.ndarray) -> float:
    """Mean gradient magnitude: a proxy for how busy a region looks."""
    if luma.shape[0] < 2 or luma.shape[1] < 2:
        return 0.0
    dy = np.abs(np.diff(luma, axis=0))[:, :-1]
    dx = np.abs(np.diff(luma, axis=1))[:-1, :]
    return float(np.mean(dx + dy))


def _runs(mask: np.ndarray) -> list[tuple[int, int]]:
    """Inclusive index ranges of consecutive True values."""
    if mask.size == 0:
        return []
    padded = np.concatenate(([False], mask.astype(bool), [False]))
    edges = np.diff(padded.astype(np.int8))
    starts = np.flatnonzero(edges == 1)
    ends = np.flatnonzero(edges == -1) - 1
    return list(zip(starts.tolist(), ends.tolist()))


@dataclass
class Timeline:
    """Per-sample visual features across the whole video."""

    fps: float
    times: np.ndarray
    luma: np.ndarray
    contrast: np.ndarray
    clipped_high: np.ndarray
    clipped_low: np.ndarray
    saturation: np.ndarray
    motion: np.ndarray
    edge_top: np.ndarray = field(default_factory=lambda: np.zeros(0, dtype=np.float32))
    edge_bottom: np.ndarray = field(default_factory=lambda: np.zeros(0, dtype=np.float32))
    edge_middle: np.ndarray = field(default_factory=lambda: np.zeros(0, dtype=np.float32))

    @property
    def interval(self) -> float:
        return 1.0 / self.fps if self.fps else 0.0

    def __len__(self) -> int:
        return int(self.times.size)

    def mask(self, start: float = 0.0, end: float | None = None) -> np.ndarray:
        end = float("inf") if end is None else end
        return (self.times >= start) & (self.times < end)

    def mean(self, values: np.ndarray, start: float = 0.0, end: float | None = None) -> float:
        selected = values[self.mask(start, end)]
        return float(np.mean(selected)) if selected.size else 0.0

    def median(self, values: np.ndarray) -> float:
        return float(np.median(values)) if values.size else 0.0

    def longest_static_run(self, threshold: float, min_seconds: float) -> tuple[float, float] | None:
        """Longest stretch with motion below ``threshold``, as (start, duration)."""
        if self.motion.size == 0 or not self.interval:
            return None
        best: tuple[float, float] | None = None
        for start_index, end_index in _runs(self.motion < threshold):
            duration = (end_index - start_index + 1) * self.interval
            if duration < min_seconds:
                continue
            if best is None or duration > best[1]:
                best = (float(self.times[start_index]), duration)
        return best

    def dark_segments(self, threshold: float, min_seconds: float) -> list[tuple[float, float]]:
        segments = []
        for start_index, end_index in _runs(self.luma < threshold):
            duration = (end_index - start_index + 1) * self.interval
            if duration >= min_seconds:
                segments.append((float(self.times[start_index]), duration))
        return segments


def build_timeline(
    info: MediaInfo,
    fps: float = 4.0,
    target_height: int = 144,
    safe_zone_top: float = 0.12,
    safe_zone_bottom: float = 0.2,
    frames: Iterable[tuple[float, np.ndarray]] | None = None,
) -> Timeline:
    """Sample the video and compute per-frame visual features."""
    times: list[float] = []
    luma_means: list[float] = []
    contrasts: list[float] = []
    clipped_high: list[float] = []
    clipped_low: list[float] = []
    saturations: list[float] = []
    motions: list[float] = []
    edge_top: list[float] = []
    edge_bottom: list[float] = []
    edge_middle: list[float] = []

    previous: np.ndarray | None = None
    source = frames if frames is not None else sample_frames(info, fps=fps, target_height=target_height)
    for timestamp, frame in source:
        luma = to_luma(frame)
        height = luma.shape[0]
        top_rows = max(1, round(height * safe_zone_top))
        bottom_rows = max(1, round(height * safe_zone_bottom))

        times.append(timestamp)
        luma_means.append(float(np.mean(luma)))
        contrasts.append(float(np.std(luma)))
        clipped_high.append(float(np.mean(luma > 250.0) * 100.0))
        clipped_low.append(float(np.mean(luma < 8.0) * 100.0))
        saturations.append(saturation_of(frame))
        edge_top.append(edge_energy(luma[:top_rows]))
        edge_bottom.append(edge_energy(luma[height - bottom_rows :]))
        edge_middle.append(edge_energy(luma[top_rows : height - bottom_rows]))
        motions.append(0.0 if previous is None else float(np.mean(np.abs(luma - previous))))
        previous = luma

    return Timeline(
        fps=fps,
        times=_as_array(times),
        luma=_as_array(luma_means),
        contrast=_as_array(contrasts),
        clipped_high=_as_array(clipped_high),
        clipped_low=_as_array(clipped_low),
        saturation=_as_array(saturations),
        motion=_as_array(motions),
        edge_top=_as_array(edge_top),
        edge_bottom=_as_array(edge_bottom),
        edge_middle=_as_array(edge_middle),
    )


def motion_cuts(timeline: Timeline, min_ratio: float = 4.0, min_absolute: float = 8.0) -> list[float]:
    """Cut candidates from motion spikes in the sampled timeline.

    FFmpeg's scene score is luma driven and misses cuts between shots of
    similar brightness, so these spikes are merged in as a second opinion.
    """
    if timeline.motion.size < 3:
        return []
    baseline = float(np.median(timeline.motion[1:]))
    threshold = max(min_absolute, baseline * min_ratio)
    return [
        float(time)
        for time, motion in zip(timeline.times[1:], timeline.motion[1:])
        if motion >= threshold
    ]


def merge_cuts(primary: Sequence[float], extra: Sequence[float], tolerance: float = 0.5) -> list[float]:
    """Union of two cut lists, dropping duplicates within ``tolerance`` seconds."""
    merged = sorted(primary)
    for candidate in sorted(extra):
        if all(abs(candidate - existing) > tolerance for existing in merged):
            merged.append(candidate)
    return sorted(merged)


@dataclass(frozen=True)
class Keyframe:
    """A high resolution sample used for sharpness and cover selection."""

    time: float
    sharpness: float
    brightness: float
    contrast: float
    saturation: float
    clipped_high: float

    @property
    def cover_score(self) -> float:
        """How well this frame would work as a thumbnail, 0..100."""
        sharpness = min(1.0, self.sharpness / 400.0)
        exposure = 1.0 - min(1.0, abs(self.brightness - 130.0) / 130.0)
        contrast = min(1.0, self.contrast / 60.0)
        colour = min(1.0, self.saturation / 0.35)
        burned = min(1.0, self.clipped_high / 10.0)
        score = 40 * sharpness + 25 * exposure + 20 * contrast + 15 * colour - 20 * burned
        return float(max(0.0, min(100.0, score)))


def keyframe_times(duration: float, count: int = 9, skip_edges: bool = True) -> list[float]:
    """Evenly spaced sample times, avoiding the very first/last frame."""
    count = max(1, count)
    if duration <= 0:
        return [0.0]
    margin = min(0.4, duration * 0.05) if skip_edges else 0.0
    usable = max(0.0, duration - 2 * margin)
    if count == 1:
        return [margin + usable / 2]
    step = usable / (count - 1)
    return [round(margin + index * step, 3) for index in range(count)]


def build_keyframes(
    info: MediaInfo, count: int = 9, target_height: int = 360, times: Sequence[float] | None = None
) -> list[Keyframe]:
    """Decode a handful of frames at higher resolution and measure them."""
    sample_times = list(times) if times is not None else keyframe_times(info.duration, count)
    keyframes: list[Keyframe] = []
    for timestamp in sample_times:
        frame = extract_frame(info, timestamp, target_height=target_height)
        if frame is None:
            continue
        luma = to_luma(frame)
        keyframes.append(
            Keyframe(
                time=timestamp,
                sharpness=sharpness_of(luma),
                brightness=float(np.mean(luma)),
                contrast=float(np.std(luma)),
                saturation=saturation_of(frame),
                clipped_high=float(np.mean(luma > 250.0) * 100.0),
            )
        )
    return keyframes
