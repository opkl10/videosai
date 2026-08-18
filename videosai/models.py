"""Core data structures of an analysis report."""

from __future__ import annotations

import enum
from dataclasses import dataclass, field
from typing import Any


class Severity(enum.Enum):
    """How strongly a finding should push the creator to act."""

    GOOD = "good"
    INFO = "info"
    WARNING = "warning"
    CRITICAL = "critical"

    @property
    def rank(self) -> int:
        return {"good": 0, "info": 1, "warning": 2, "critical": 3}[self.value]


@dataclass(frozen=True)
class Finding:
    """A single observation about the video.

    ``code`` is a stable identifier; the human readable text lives in
    :mod:`videosai.messages` so reports can be rendered in any language.
    ``params`` are interpolated into the message templates.
    """

    code: str
    severity: Severity
    penalty: float = 0.0
    params: dict[str, Any] = field(default_factory=dict)
    at: float | None = None
    timestamps: tuple[float, ...] = ()

    def as_dict(self) -> dict[str, Any]:
        return {
            "code": self.code,
            "severity": self.severity.value,
            "penalty": round(self.penalty, 2),
            "params": self.params,
            "at": self.at,
            "timestamps": list(self.timestamps),
        }


@dataclass
class CategoryResult:
    """Score and findings for one analysis category (hook, audio, ...)."""

    key: str
    score: float
    weight: float = 0.0
    metrics: dict[str, Any] = field(default_factory=dict)
    findings: list[Finding] = field(default_factory=list)

    @property
    def issues(self) -> list[Finding]:
        return [f for f in self.findings if f.severity is not Severity.GOOD]

    @property
    def strengths(self) -> list[Finding]:
        return [f for f in self.findings if f.severity is Severity.GOOD]

    def as_dict(self) -> dict[str, Any]:
        return {
            "key": self.key,
            "score": round(self.score, 1),
            "weight": round(self.weight, 3),
            "metrics": self.metrics,
            "findings": [f.as_dict() for f in self.findings],
        }


@dataclass(frozen=True)
class ThumbnailCandidate:
    """A frame that would work well as a cover image."""

    time: float
    score: float
    sharpness: float
    brightness: float
    saturation: float
    file: str | None = None

    def as_dict(self) -> dict[str, Any]:
        return {
            "time": round(self.time, 2),
            "score": round(self.score, 1),
            "sharpness": round(self.sharpness, 1),
            "brightness": round(self.brightness, 1),
            "saturation": round(self.saturation, 3),
            "file": self.file,
        }


GRADE_BANDS: tuple[tuple[float, str], ...] = (
    (90, "A"),
    (80, "B"),
    (70, "C"),
    (60, "D"),
    (0, "F"),
)


def grade_for(score: float) -> str:
    for threshold, grade in GRADE_BANDS:
        if score >= threshold:
            return grade
    return "F"


@dataclass
class Report:
    """The full result of analysing one video."""

    file: str
    platform: str
    score: float
    media: dict[str, Any]
    categories: list[CategoryResult]
    thumbnail_candidates: list[ThumbnailCandidate] = field(default_factory=list)
    version: str = ""

    @property
    def grade(self) -> str:
        return grade_for(self.score)

    def category(self, key: str) -> CategoryResult | None:
        for category in self.categories:
            if category.key == key:
                return category
        return None

    @property
    def findings(self) -> list[Finding]:
        return [f for category in self.categories for f in category.findings]

    @property
    def codes(self) -> set[str]:
        return {f.code for f in self.findings}

    def issues(self) -> list[Finding]:
        """Issues ordered by how much they cost the score."""
        issues = [f for f in self.findings if f.severity is not Severity.GOOD]
        return sorted(issues, key=lambda f: (-f.severity.rank, -f.penalty))

    def strengths(self) -> list[Finding]:
        return [f for f in self.findings if f.severity is Severity.GOOD]

    def priorities(self, limit: int = 3) -> list[Finding]:
        """The fixes with the highest score impact, biggest first."""
        issues = [f for f in self.findings if f.severity is not Severity.GOOD]
        return sorted(issues, key=lambda f: -f.penalty)[:limit]

    def as_dict(self) -> dict[str, Any]:
        return {
            "version": self.version,
            "file": self.file,
            "platform": self.platform,
            "score": round(self.score, 1),
            "grade": self.grade,
            "media": self.media,
            "categories": [c.as_dict() for c in self.categories],
            "thumbnail_candidates": [t.as_dict() for t in self.thumbnail_candidates],
        }
