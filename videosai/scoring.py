"""Combining category scores into one number."""

from __future__ import annotations

from collections.abc import Mapping, Sequence

from .models import CategoryResult


def apply_weights(
    categories: Sequence[CategoryResult], weights: Mapping[str, float]
) -> list[CategoryResult]:
    """Attach normalized weights to each category, in place."""
    raw = {c.key: float(weights.get(c.key, 0.0)) for c in categories}
    if sum(raw.values()) <= 0:
        raw = {c.key: 1.0 for c in categories}
    total = sum(raw.values())
    for category in categories:
        category.weight = raw[category.key] / total
    return list(categories)


def overall_score(categories: Sequence[CategoryResult]) -> float:
    """Weighted average of the category scores."""
    total_weight = sum(c.weight for c in categories)
    if total_weight <= 0:
        return 0.0
    weighted = sum(c.score * c.weight for c in categories)
    return round(weighted / total_weight, 1)
