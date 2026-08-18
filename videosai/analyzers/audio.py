"""Loudness, clipping and dead air."""

from __future__ import annotations

from ..models import CategoryResult, Finding, Severity
from .base import AnalysisContext, scaled_penalty, score_from

KEY = "audio"
CLIPPING_TRUE_PEAK = -0.3
LONG_GAP_SECONDS = 1.2
FLAT_LOUDNESS_RANGE = 1.0
MIN_SAMPLE_RATE = 32000
#: volumedetect reports RMS, not LUFS, so judge it less strictly.
APPROXIMATE_EXTRA_TOLERANCE = 2.0


def analyze(context: AnalysisContext) -> CategoryResult:
    platform = context.platform
    audio = context.audio

    if audio is None:
        finding = Finding(code="AUDIO_MISSING", severity=Severity.CRITICAL, penalty=55.0)
        return CategoryResult(key=KEY, score=score_from([finding]), metrics={}, findings=[finding])

    findings: list[Finding] = []
    target = platform.loudness_target
    tolerance = platform.loudness_tolerance
    if audio.loudness_source == "volumedetect":
        tolerance += APPROXIMATE_EXTRA_TOLERANCE

    loudness = audio.integrated_lufs
    if loudness is not None:
        delta = loudness - target
        if delta < -tolerance:
            findings.append(
                Finding(
                    code="AUDIO_QUIET",
                    severity=Severity.WARNING if delta > -12 else Severity.CRITICAL,
                    penalty=scaled_penalty(-delta - tolerance, 12.0, 10, 35),
                    params={"delta": round(-delta, 1), "lufs": loudness, "target": target},
                )
            )
        elif delta > tolerance:
            findings.append(
                Finding(
                    code="AUDIO_LOUD",
                    severity=Severity.WARNING,
                    penalty=scaled_penalty(delta - tolerance, 8.0, 8, 20),
                    params={"delta": round(delta, 1), "lufs": loudness, "target": target},
                )
            )

    if audio.true_peak_db is not None and audio.true_peak_db > CLIPPING_TRUE_PEAK:
        findings.append(
            Finding(
                code="AUDIO_CLIPPING",
                severity=Severity.WARNING,
                penalty=15.0,
                params={"true_peak": audio.true_peak_db},
            )
        )

    gaps = audio.long_gaps(LONG_GAP_SECONDS)
    if gaps:
        findings.append(
            Finding(
                code="AUDIO_DEAD_GAPS",
                severity=Severity.WARNING,
                penalty=min(22.0, 6.0 * len(gaps)),
                params={"gaps": len(gaps), "threshold": LONG_GAP_SECONDS},
                timestamps=tuple(start for start, _ in gaps[:6]),
            )
        )

    if audio.loudness_range is not None and 0 <= audio.loudness_range < FLAT_LOUDNESS_RANGE:
        findings.append(
            Finding(
                code="AUDIO_OVERCOMPRESSED",
                severity=Severity.INFO,
                penalty=6.0,
                params={"lra": audio.loudness_range},
            )
        )

    sample_rate = context.info.audio_sample_rate
    if sample_rate and sample_rate < MIN_SAMPLE_RATE:
        findings.append(
            Finding(
                code="AUDIO_LOW_SAMPLE_RATE",
                severity=Severity.INFO,
                penalty=6.0,
                params={"sample_rate": sample_rate},
            )
        )

    if not findings:
        findings.append(
            Finding(
                code="AUDIO_GOOD",
                severity=Severity.GOOD,
                params={"lufs": loudness if loudness is not None else target},
            )
        )

    return CategoryResult(
        key=KEY,
        score=score_from(findings),
        metrics={
            "integrated_lufs": loudness,
            "loudness_range": audio.loudness_range,
            "true_peak_db": audio.true_peak_db,
            "silence_ratio": round(audio.silence_ratio, 3),
            "long_gaps": len(gaps),
            "sample_rate": sample_rate,
        },
        findings=findings,
    )
