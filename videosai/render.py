"""Report renderers: JSON, Markdown, plain text and standalone HTML."""

from __future__ import annotations

import html
import json
from collections.abc import Iterable
from functools import partial

from . import messages
from .messages import format_time
from .models import Finding, Report, Severity
from .platforms import PLATFORMS

SEVERITY_MARKS = {
    Severity.CRITICAL: "!!",
    Severity.WARNING: "!",
    Severity.INFO: "i",
    Severity.GOOD: "+",
}

SEVERITY_COLORS = {
    "critical": "#e5484d",
    "warning": "#f5a524",
    "info": "#3b82f6",
    "good": "#30a46c",
}


def _platform_label(report: Report, lang: str) -> str:
    platform = PLATFORMS.get(report.platform)
    return platform.label(lang) if platform else report.platform


def _score_color(score: float) -> str:
    if score >= 80:
        return SEVERITY_COLORS["good"]
    if score >= 60:
        return SEVERITY_COLORS["warning"]
    return SEVERITY_COLORS["critical"]


def to_json(report: Report, indent: int = 2) -> str:
    return json.dumps(report.as_dict(), ensure_ascii=False, indent=indent)


def _finding_line(finding: Finding, lang: str, with_fix: bool = True) -> str:
    text = messages.render(finding, lang)
    at = f" ({messages.ui('at', lang)} {format_time(finding.at)})" if finding.at is not None else ""
    line = f"**{text.title}**{at}"
    if text.detail:
        line += f" - {text.detail}"
    if with_fix and text.fix:
        line += f"\n  - {messages.ui('fix_prefix', lang)}: {text.fix}"
    return line


def to_markdown(report: Report, lang: str = messages.DEFAULT_LANGUAGE) -> str:
    lang = messages.normalize_language(lang)
    ui = partial(messages.ui, lang=lang)
    lines: list[str] = []

    lines.append(f"# {ui('report_title')}")
    lines.append("")
    lines.append(f"- **{ui('file')}**: `{report.file}`")
    lines.append(f"- **{ui('platform')}**: {_platform_label(report, lang)}")
    lines.append(f"- **{ui('overall')}**: {report.score:.1f} {ui('score_unit')} ({report.grade})")
    media = report.media
    lines.append(
        f"- **{ui('duration')}**: {media.get('duration', 0):.1f}s | "
        f"**{ui('resolution')}**: {media.get('width')}x{media.get('height')} | "
        f"**{ui('fps')}**: {float(media.get('fps') or 0):.0f} | "
        f"**{ui('audio_track')}**: "
        f"{ui('audio_yes') if media.get('has_audio') else ui('audio_no')}"
    )
    lines.append("")

    priorities = report.priorities()
    if priorities:
        lines.append(f"## {ui('priorities')}")
        lines.append("")
        for index, finding in enumerate(priorities, start=1):
            text = messages.render(finding, lang)
            fix = f" {ui('fix_prefix')}: {text.fix}" if text.fix else ""
            lines.append(f"{index}. **{text.title}** - {text.detail}{fix}")
        lines.append("")

    lines.append(f"## {ui('what_to_fix')}")
    lines.append("")
    issues = report.issues()
    if not issues:
        lines.append(ui("no_issues"))
    for finding in issues:
        severity = messages.severity_label(finding.severity.value, lang)
        lines.append(f"- [{severity}] {_finding_line(finding, lang)}")
    lines.append("")

    lines.append(f"## {ui('what_works')}")
    lines.append("")
    strengths = report.strengths()
    if not strengths:
        lines.append(ui("no_strengths"))
    for finding in strengths:
        lines.append(f"- {_finding_line(finding, lang, with_fix=False)}")
    lines.append("")

    lines.append(f"## {ui('categories')}")
    lines.append("")
    lines.append(f"| {ui('category')} | {ui('score')} | {ui('weight')} |")
    lines.append("| --- | --- | --- |")
    for category in report.categories:
        label = messages.category_label(category.key, lang)
        lines.append(f"| {label} | {category.score:.0f} | {category.weight * 100:.0f}% |")
    lines.append("")

    lines.append(f"## {ui('metrics')}")
    lines.append("")
    for category in report.categories:
        if not category.metrics:
            continue
        lines.append(f"**{messages.category_label(category.key, lang)}**")
        lines.append("")
        lines.append(f"| {ui('metric')} | {ui('value')} |")
        lines.append("| --- | --- |")
        for key, value in category.metrics.items():
            lines.append(f"| {messages.metric_label(key, lang)} | {_format_value(value)} |")
        lines.append("")

    if report.thumbnail_candidates:
        lines.append(f"## {ui('thumbnails')}")
        lines.append("")
        for candidate in report.thumbnail_candidates:
            suffix = f" - `{candidate.file}`" if candidate.file else ""
            lines.append(
                f"- {ui('time')} {format_time(candidate.time)} | "
                f"{ui('score')} {candidate.score:.0f}{suffix}"
            )
        lines.append("")

    lines.append(f"_{ui('generated_by')} {report.version}_")
    return "\n".join(lines)


def _format_value(value: object) -> str:
    if value is None:
        return "-"
    if isinstance(value, bool):
        return "yes" if value else "no"
    if isinstance(value, float):
        return f"{value:.2f}".rstrip("0").rstrip(".")
    return str(value)


def to_text(report: Report, lang: str = messages.DEFAULT_LANGUAGE) -> str:
    """Compact summary for the terminal."""
    lang = messages.normalize_language(lang)
    ui = partial(messages.ui, lang=lang)
    lines = [
        f"{ui('report_title')}: {report.file}",
        (
            f"{ui('platform')}: {_platform_label(report, lang)} | "
            f"{ui('overall')}: {report.score:.1f}/100 ({report.grade})"
        ),
        "",
    ]

    for category in report.categories:
        label = messages.category_label(category.key, lang)
        bar_length = round(category.score / 10)
        bar = "#" * bar_length + "." * (10 - bar_length)
        lines.append(f"  {bar} {category.score:5.1f}  {label}")
    lines.append("")

    priorities = report.priorities()
    if priorities:
        lines.append(f"{ui('priorities')}:")
        for index, finding in enumerate(priorities, start=1):
            text = messages.render(finding, lang)
            lines.append(f"  {index}. {text.title} - {text.detail}")
            if text.fix:
                lines.append(f"     {ui('fix_prefix')}: {text.fix}")
        lines.append("")

    other = [f for f in report.issues() if f not in priorities]
    if other:
        lines.append(f"{ui('what_to_fix')}:")
        for finding in other:
            text = messages.render(finding, lang)
            lines.append(f"  {SEVERITY_MARKS[finding.severity]} {text.title} - {text.detail}")
        lines.append("")

    strengths = report.strengths()
    if strengths:
        lines.append(f"{ui('what_works')}:")
        for finding in strengths:
            text = messages.render(finding, lang)
            lines.append(f"  + {text.title} - {text.detail}")
        lines.append("")

    if report.thumbnail_candidates:
        lines.append(f"{ui('thumbnails')}:")
        for candidate in report.thumbnail_candidates:
            suffix = f" -> {candidate.file}" if candidate.file else ""
            lines.append(
                f"  {format_time(candidate.time)} ({ui('score')} {candidate.score:.0f}){suffix}"
            )
        lines.append("")

    return "\n".join(lines).rstrip() + "\n"


_HTML_TEMPLATE = """<!DOCTYPE html>
<html lang="{lang}" dir="{direction}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{title} - {file}</title>
<style>
:root {{ color-scheme: dark; }}
* {{ box-sizing: border-box; }}
body {{ margin: 0; padding: 32px 20px 64px; font-family: -apple-system, "Segoe UI", Rubik, Arial, sans-serif;
  background: #0f1115; color: #e9edf5; line-height: 1.6; }}
.wrap {{ max-width: 880px; margin: 0 auto; }}
h1 {{ font-size: 26px; margin: 0 0 4px; }}
h2 {{ font-size: 19px; margin: 36px 0 12px; }}
.sub {{ color: #99a2b3; font-size: 14px; margin-bottom: 24px; word-break: break-all; }}
.hero {{ display: flex; align-items: center; gap: 24px; background: #171a21; border: 1px solid #23283340;
  border-radius: 16px; padding: 22px; flex-wrap: wrap; }}
.ring {{ width: 128px; height: 128px; border-radius: 50%; display: grid; place-items: center;
  background: conic-gradient({score_color} {score_deg}deg, #23283a 0deg); flex: none; }}
.ring > div {{ width: 104px; height: 104px; border-radius: 50%; background: #171a21; display: grid;
  place-items: center; text-align: center; }}
.ring b {{ font-size: 30px; display: block; line-height: 1.1; }}
.ring span {{ font-size: 12px; color: #99a2b3; }}
.facts {{ display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; flex: 1 1 340px; }}
.fact {{ background: #1d212b; border-radius: 10px; padding: 10px 12px; }}
.fact span {{ display: block; font-size: 12px; color: #99a2b3; }}
.cats {{ display: grid; gap: 10px; }}
.cat {{ background: #171a21; border-radius: 12px; padding: 12px 16px; }}
.cat-head {{ display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 8px; }}
.bar {{ height: 8px; background: #23283a; border-radius: 999px; overflow: hidden; }}
.bar > i {{ display: block; height: 100%; border-radius: 999px; }}
.card {{ background: #171a21; border-radius: 12px; padding: 14px 18px; margin-bottom: 10px;
  border-inline-start: 4px solid #23283a; }}
.card h3 {{ margin: 0 0 4px; font-size: 16px; }}
.card p {{ margin: 0; color: #c3cad8; font-size: 14px; }}
.card .fix {{ margin-top: 8px; font-size: 14px; color: #9fe0bd; }}
.tag {{ font-size: 11px; padding: 2px 8px; border-radius: 999px; background: #23283a; color: #c3cad8;
  margin-inline-start: 8px; vertical-align: middle; }}
table {{ width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 20px; }}
th, td {{ text-align: start; padding: 7px 10px; border-bottom: 1px solid #23283a; }}
th {{ color: #99a2b3; font-weight: 600; }}
.thumbs {{ display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }}
.thumbs figure {{ margin: 0; background: #171a21; border-radius: 12px; overflow: hidden; }}
.thumbs img {{ width: 100%; display: block; }}
.thumbs figcaption {{ padding: 8px 12px; font-size: 13px; color: #99a2b3; }}
footer {{ margin-top: 40px; color: #6f7787; font-size: 12px; }}
</style>
</head>
<body>
<div class="wrap">
<h1>{title}</h1>
<div class="sub">{file}</div>
<div class="hero">
  <div class="ring"><div><b>{score:.0f}</b><span>{grade}</span></div></div>
  <div class="facts">{facts}</div>
</div>
{body}
<footer>{generated_by} {version}</footer>
</div>
</body>
</html>
"""


def _fact(label: str, value: str) -> str:
    return f'<div class="fact"><span>{html.escape(label)}</span>{html.escape(value)}</div>'


def _cards(findings: Iterable[Finding], lang: str, with_fix: bool = True) -> str:
    parts = []
    for finding in findings:
        text = messages.render(finding, lang)
        color = SEVERITY_COLORS[finding.severity.value]
        tag = messages.severity_label(finding.severity.value, lang)
        at = (
            f'<span class="tag">{messages.ui("at", lang)} {format_time(finding.at)}</span>'
            if finding.at is not None
            else ""
        )
        fix = (
            f'<div class="fix">{html.escape(messages.ui("fix_prefix", lang))}: {html.escape(text.fix)}</div>'
            if with_fix and text.fix
            else ""
        )
        parts.append(
            f'<div class="card" style="border-inline-start-color:{color}">'
            f"<h3>{html.escape(text.title)}"
            f'<span class="tag" style="color:{color}">{html.escape(tag)}</span>{at}</h3>'
            f"<p>{html.escape(text.detail)}</p>{fix}</div>"
        )
    return "".join(parts)


def to_html(report: Report, lang: str = messages.DEFAULT_LANGUAGE) -> str:
    """A single self-contained HTML file, no assets required."""
    lang = messages.normalize_language(lang)
    ui = partial(messages.ui, lang=lang)
    media = report.media

    facts = "".join(
        [
            _fact(ui("platform"), _platform_label(report, lang)),
            _fact(ui("duration"), f"{float(media.get('duration') or 0):.1f}s"),
            _fact(ui("resolution"), f"{media.get('width')}x{media.get('height')}"),
            _fact(ui("fps"), f"{float(media.get('fps') or 0):.0f}"),
            _fact(
                ui("audio_track"),
                ui("audio_yes") if media.get("has_audio") else ui("audio_no"),
            ),
        ]
    )

    sections: list[str] = []

    categories = "".join(
        f'<div class="cat"><div class="cat-head"><span>{html.escape(messages.category_label(c.key, lang))}'
        f'</span><span>{c.score:.0f} · {c.weight * 100:.0f}%</span></div>'
        f'<div class="bar"><i style="width:{max(2.0, c.score):.0f}%;background:{_score_color(c.score)}"></i></div></div>'
        for c in report.categories
    )
    sections.append(f'<h2>{ui("categories")}</h2><div class="cats">{categories}</div>')

    priorities = report.priorities()
    if priorities:
        sections.append(f'<h2>{ui("priorities")}</h2>{_cards(priorities, lang)}')

    issues = [f for f in report.issues() if f not in priorities]
    if issues:
        sections.append(f'<h2>{ui("what_to_fix")}</h2>{_cards(issues, lang)}')
    elif not priorities:
        sections.append(f'<h2>{ui("what_to_fix")}</h2><p>{html.escape(ui("no_issues"))}</p>')

    strengths = report.strengths()
    if strengths:
        sections.append(
            f'<h2>{ui("what_works")}</h2>{_cards(strengths, lang, with_fix=False)}'
        )

    thumbnails = report.thumbnail_candidates
    if thumbnails:
        figures = []
        for candidate in thumbnails:
            image = (
                f'<img src="{html.escape(candidate.file)}" alt="{format_time(candidate.time)}">'
                if candidate.file
                else ""
            )
            figures.append(
                f"<figure>{image}<figcaption>{format_time(candidate.time)} · "
                f'{html.escape(ui("score"))} {candidate.score:.0f}</figcaption></figure>'
            )
        sections.append(f'<h2>{ui("thumbnails")}</h2><div class="thumbs">{"".join(figures)}</div>')

    metric_tables = []
    for category in report.categories:
        if not category.metrics:
            continue
        rows = "".join(
            f"<tr><td>{html.escape(messages.metric_label(key, lang))}</td>"
            f"<td>{html.escape(_format_value(value))}</td></tr>"
            for key, value in category.metrics.items()
        )
        metric_tables.append(
            f"<table><thead><tr><th colspan='2'>"
            f"{html.escape(messages.category_label(category.key, lang))}</th></tr>"
            f'<tr><th>{html.escape(ui("metric"))}</th><th>{html.escape(ui("value"))}</th></tr>'
            f"</thead><tbody>{rows}</tbody></table>"
        )
    if metric_tables:
        sections.append(f'<h2>{ui("metrics")}</h2>{"".join(metric_tables)}')

    return _HTML_TEMPLATE.format(
        lang=lang,
        direction="rtl" if lang == "he" else "ltr",
        title=html.escape(ui("report_title")),
        file=html.escape(report.file),
        score=report.score,
        score_color=_score_color(report.score),
        score_deg=report.score / 100 * 360,
        grade=html.escape(f"{ui('grade')} {report.grade}"),
        facts=facts,
        body="".join(sections),
        generated_by=html.escape(ui("generated_by")),
        version=html.escape(report.version),
    )
