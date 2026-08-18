"""Cut rhythm and frozen shots."""

from __future__ import annotations

from ..models import CategoryResult, Finding, Severity
from .base import AnalysisContext, score_from, scaled_penalty

KEY = "pacing"
STATIC_MOTION = 0.7
MIN_DURATION_FOR_RATE = 8.0


def analyze(context: AnalysisContext) -> CategoryResult:
    platform = context.platform
    duration = max(context.duration, 0.001)
    cuts_per_minute = len(context.cuts) / duration * 60.0
    min_cpm, max_cpm = platform.cuts_per_minute

    findings: list[Finding] = []

    if duration >= MIN_DURATION_FOR_RATE and min_cpm > 0 and cuts_per_minute < min_cpm:
        findings.append(
            Finding(
                code="PACING_SLOW",
                severity=Severity.WARNING,
                penalty=scaled_penalty(min_cpm - cuts_per_minute, min_cpm, 8, 25),
                params={
                    "cuts_per_minute": round(cuts_per_minute, 1),
                    "platform": platform.key,
                    "min_cpm": min_cpm,
                    "max_cpm": max_cpm,
                },
            )
        )

    static = context.timeline.longest_static_run(STATIC_MOTION, platform.max_static_seconds)
    if static is not None:
        start, length = static
        findings.append(
            Finding(
                code="PACING_FROZEN_SHOT",
                severity=Severity.WARNING,
                penalty=scaled_penalty(length - platform.max_static_seconds, 6.0, 8, 22),
                params={"longest_static": round(length, 1)},
                at=start,
            )
        )

    if cuts_per_minute > max_cpm > 0:
        findings.append(
            Finding(
                code="PACING_FRANTIC",
                severity=Severity.INFO,
                penalty=10.0,
                params={"cuts_per_minute": round(cuts_per_minute, 1), "max_cpm": max_cpm},
            )
        )

    if not findings:
        # On very short clips the cut rate says little, so only vouch for what
        # was actually measured: no frozen shots.
        rate_measured = duration >= MIN_DURATION_FOR_RATE
        findings.append(
            Finding(
                code="PACING_GOOD" if rate_measured else "PACING_STABLE",
                severity=Severity.GOOD,
                params={
                    "cuts_per_minute": round(cuts_per_minute, 1),
                    "platform": platform.key,
                    "max_static": platform.max_static_seconds,
                },
            )
        )

    return CategoryResult(
        key=KEY,
        score=score_from(findings),
        metrics={
            "cuts": len(context.cuts),
            "cuts_per_minute": round(cuts_per_minute, 1),
            "longest_static_seconds": round(static[1], 1) if static else 0.0,
        },
        findings=findings,
    )
