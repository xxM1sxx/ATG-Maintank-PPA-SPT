#!/usr/bin/env python3
"""Extract SOUNDING mm / VOLUME Liter pairs from SUCOFINDO tank calibration PDFs."""
import re
import sys
from pathlib import Path

try:
    from pypdf import PdfReader
except ImportError:
    from PyPDF2 import PdfReader  # type: ignore


def parse_num(s: str) -> float:
    return float(s.replace(",", "").strip())


SKIP_PREFIXES = ("Page", "Catatan", "TANKI", "KAPASITAS", "PT.", "SITE", "Volume", "SOUNDING", "VOLUME")
SKIP_EXACT = {"mm", "Liter", ""}


def extract_pairs(text: str) -> list[tuple[int, float]]:
    pairs: dict[int, float] = {}
    for line in text.splitlines():
        line = line.strip()
        if not line or line in SKIP_EXACT:
            continue
        if any(line.startswith(p) for p in SKIP_PREFIXES):
            continue
        parts = re.split(r"\s+", line)
        nums = [p for p in parts if re.match(r"^[\d,]+$", p)]
        if len(nums) < 4 or len(nums) % 2 != 0:
            continue
        for i in range(0, len(nums), 2):
            try:
                sounding = int(parse_num(nums[i]))
                volume = parse_num(nums[i + 1])
                if 0 <= sounding <= 10000 and 0 <= volume <= 100000:
                    pairs[sounding] = volume
            except ValueError:
                continue
    if pairs:
        max_sounding = max(pairs.keys(), key=lambda s: pairs[s])
        pairs = {s: v for s, v in pairs.items() if s <= max_sounding}
    return sorted(pairs.items())


def main() -> int:
    if len(sys.argv) < 3:
        print("Usage: extract_calibration_pdf.py <input.pdf> <output.csv>")
        return 1
    pdf_path = Path(sys.argv[1])
    out_path = Path(sys.argv[2])
    reader = PdfReader(str(pdf_path))
    text = "\n".join(page.extract_text() or "" for page in reader.pages)
    pairs = extract_pairs(text)
    if not pairs:
        print(f"No pairs found in {pdf_path}", file=sys.stderr)
        return 1
    out_path.parent.mkdir(parents=True, exist_ok=True)
    with out_path.open("w", encoding="utf-8") as f:
        f.write("sounding_mm,volume_l\n")
        for s, v in pairs:
            f.write(f"{s},{v}\n")
    print(f"Wrote {len(pairs)} points to {out_path}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
