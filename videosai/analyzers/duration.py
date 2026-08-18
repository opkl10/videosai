"""Length against what the platform rewards."""

from __future__ import annotations

from ..models import CategoryResult, Finding, Severity
from .base import AnalysisContext, score_from, scaled_penalty

KEY = "duration"


def analyze(context: AnalysisContext) -> CategoryResult:
    platform = context.platform
    duration = context.duration
    minimum, maximum = platform.ideal_duration
    findings: list[Finding] = []

    if platform.max_duration is not None and duration > platform.max_duration:
        findings.append(
            Finding(
                code="DURATION_OVER_LIMIT",
                severity=Severity.CRITICAL,
                penalty=45.0,
                params={
                    "duration": round(duration, 1),
                    "max_allowed": platform.max_duration,
                    "platform": platform.key,
                },
            )
        )
    elif duration < minimum:
        findings.append(
            Finding(
                code="DURATION_SHORT",
                severity=Severity.INFO if duration > minimum * 0.6 else Severity.WARNING,
                penalty=scaled_penalty(minimum - duration, minimum, 8, 25),
                params={
                    "duration": round(duration, 1),
                    "min_d": minimum,
                    "max_d": maximum,
                    "platform": platform.key,
                },
            )
        )
    elif duration > maximum:
        findings.append(
            Finding(
                code="DURATION_LONG",
                severity=Severity.INFO if duration < maximum * 1.5 else Severity.WARNING,
                penalty=scaled_penalty(duration - maximum, maximum, 8, 25),
                params={
                    "duration": round(duration, 1),
                    "min_d": minimum,
                    "max_d": maximum,
                    "platform": platform.key,
                },
            )
        )
    else:
        findings.append(
            Finding(
                code="DURATION_GOOD",
                severity=Severity.GOOD,
                params={"duration": round(duration, 1), "platform": platform.key},
            )
        )

    return CategoryResult(
        key=KEY,
        score=score_from(findings),
        metrics={
            "duration": round(duration, 1),
            "ideal_range": f"{minimum:.0f}-{maximum:.0f}",
        },
        findings=findings,
    )
