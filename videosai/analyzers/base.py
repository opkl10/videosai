"""Shared context and helpers for analyzers."""

from __future__ import annotations

from dataclasses import dataclass
from typing import Protocol, Sequence

from ..features import Keyframe, Timeline
from ..media import AudioStats, MediaInfo
from ..models import CategoryResult, Finding
from ..platforms import Platform


@dataclass
class AnalysisContext:
    """Everything the analyzers need, measured once per video."""

    info: MediaInfo
    platform: Platform
    timeline: Timeline
    keyframes: list[Keyframe]
    cuts: list[float]
    audio: AudioStats | None

    @property
    def duration(self) -> float:
        return self.info.duration

    def cuts_between(self, start: float, end: float) -> list[float]:
        return [t for t in self.cuts if start <= t < end]

    def keyframes_between(self, start: float, end: float) -> list[Keyframe]:
        return [k for k in self.keyframes if start <= k.time < end]


class Analyzer(Protocol):
    key: str

    def __call__(self, context: AnalysisContext) -> CategoryResult: ...


def score_from(findings: Sequence[Finding]) -> float:
    """Start at 100 and subtract each finding's penalty."""
    total = 100.0 - sum(f.penalty for f in findings)
    return max(0.0, min(100.0, total))


def scaled_penalty(excess: float, span: float, minimum: float, maximum: float) -> float:
    """Penalty that grows with how far a metric is out of range."""
    if span <= 0:
        return maximum
    ratio = max(0.0, min(1.0, excess / span))
    return round(minimum + (maximum - minimum) * ratio, 2)
