#!/usr/bin/env python3
"""
Turn the client-supplied "Thai FRUITS" reference book into a PHP data file.

The PDF is the Thai Department of Agriculture / Department of Export Promotion
export fruit guide. It carries the botanical name, cultivar detail, storage
regime and season for each fruit - real, attributable facts, which is exactly
the kind of specification this catalogue should be showing and exactly the kind
this project refuses to invent.

Nothing here is generated or inferred. Every value written out is a string
lifted from the PDF; a field the book does not give is simply absent.

Run:  pdftotext -layout "Thai FRUITS.pdf" thaifruits.txt
      python3 tools/extract_fruit_reference.py thaifruits.txt

Writes tools/data/thai_fruit_reference.php, which tools/import_fruit_reference.php
then loads into product_specs. Committing the generated file means the import
can be re-run on a host with no Python and no copy of the PDF.
"""
import re
import sys
import os

# Sections that appear as a heading on their own line inside an entry.
BLOCK_HEADINGS = ("Usage", "Nutrition", "Storage", "Season", "Preparation and eating")

# Section titles that end an entry but are not set in capitals, so the
# all-caps rule below does not catch them. Without this the last detailed
# fruit's season reads "year round Miscellaneous fruits".
STOP_LINES = ("Miscellaneous fruits", "Contents", "Introduction")

# "Label : value" lines worth keeping. Anything else in the body is prose about
# preparation, which reads badly as a spec row and is left out.
INLINE_LABELS = ("Shape", "Weight", "Colour", "Color", "Taste", "Size")

# The Thai script in this PDF is a legacy PageMaker encoding that pdftotext
# cannot decode - it comes through as mojibake like "°≈â«¬". Any line that is
# mostly non-ASCII is therefore dropped rather than stored as rubbish. The
# romanised "Thai name" line is plain ASCII and survives this.
def is_mojibake(line: str) -> bool:
    if not line:
        return True
    non_ascii = sum(1 for ch in line if ord(ch) > 127)
    return non_ascii > len(line) * 0.3


def clean(value: str) -> str:
    value = re.sub(r"\s+", " ", value).strip()
    # The book writes degrees Celsius as "13 ÌC" - that Ì is the mangled degree
    # sign. The spacing around it varies ("13 ÌC", "10 Ì C"), so it is matched
    # rather than replaced literally.
    value = re.sub(r"\s*Ì\s*C\b", " °C", value)
    # pdftotext hyphenates across the PDF's line breaks: "natu- ral".
    value = re.sub(r"(\w)- (\w)", r"\1\2", value)
    return value.strip(" .;,")


def parse(text: str) -> list:
    lines = text.splitlines()

    # Each entry runs from its "Common name :" line to the next one.
    starts = [i for i, ln in enumerate(lines) if "Common name" in ln and ":" in ln]
    entries = []

    for n, start in enumerate(starts):
        end = starts[n + 1] if n + 1 < len(starts) else len(lines)
        block = [ln.strip() for ln in lines[start:end]]

        entry = {"cultivars": [], "blocks": {}}
        # True once a label has been seen twice - the fruit lists several
        # cultivars, and their values cannot be told apart from this text.
        seen_labels = set()
        current = None
        buffer = []

        def flush():
            if current and buffer:
                entry["blocks"][current] = clean(" ".join(buffer))

        for raw in block:
            if not raw or is_mojibake(raw):
                continue
            # Page numbers and the dotted rules between sections.
            if re.fullmatch(r"[\d\s○·]+", raw):
                continue

            # The next fruit's section title is set in capitals on its own line
            # ("DRAGON FRUIT"). Without this the previous fruit's last section
            # swallows it and the season reads "year round DRAGON FRUIT".
            if re.fullmatch(r"[A-Z][A-Z \-]{2,}", raw) or raw in STOP_LINES:
                flush()
                current, buffer = None, []
                continue

            m = re.match(r"^(Common name|Thai name|Scientific name)\s*:\s*(.+)$", raw)
            if m:
                flush()
                current, buffer = None, []
                entry[m.group(1)] = clean(m.group(2))
                continue

            heading = next((h for h in BLOCK_HEADINGS if raw == h or raw.startswith(h + " ")), None)
            if heading:
                flush()
                current = heading
                # "Season" is followed by "availability : ..." on the next line,
                # so the remainder of the heading line is kept if there is one.
                rest = raw[len(heading):].strip(" :")
                buffer = [rest] if rest else []
                continue

            m = re.match(r"^(%s)\s*:\s*(.*)$" % "|".join(INLINE_LABELS), raw)
            if m and current is None:
                label = "Colour" if m.group(1) == "Color" else m.group(1)
                if label in seen_labels:
                    entry["multi_cultivar"] = True
                seen_labels.add(label)
                entry["cultivars"].append([label, clean(m.group(2))])
                continue

            if current:
                buffer.append(raw)
            elif entry["cultivars"]:
                # A wrapped continuation of the label above ("Colour : thick skin
                # with dark" / "purple colour; white flesh"). Dropping it silently
                # truncated values mid-phrase.
                entry["cultivars"][-1][1] = clean(entry["cultivars"][-1][1] + " " + raw)

        flush()

        if entry.get("Common name"):
            entries.append(entry)

    return entries


def php_string(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def main() -> int:
    if len(sys.argv) < 2:
        print(__doc__)
        return 1

    with open(sys.argv[1], encoding="utf-8", errors="replace") as fh:
        entries = parse(fh.read())

    here = os.path.dirname(os.path.abspath(__file__))
    out_path = os.path.join(here, "data", "thai_fruit_reference.php")

    rows = []
    for e in entries:
        specs = []

        if e.get("Thai name"):
            specs.append(("Thai name", e["Thai name"]))
        if e.get("Scientific name"):
            specs.append(("Scientific name", e["Scientific name"]))

        # Where the book describes several cultivars of one fruit (bananas get
        # three), the physical descriptions are dropped entirely rather than
        # concatenated. Joining them produces "bright yellow skin; golden yellow
        # skin; smoky yellow skin" against a single "Colour" heading, which
        # reads as a description of one product and is wrong about all three.
        # The unambiguous fields below are kept either way.
        if not e.get("multi_cultivar"):
            merged = {}
            for label, value in e["cultivars"]:
                if value:
                    merged.setdefault(label, []).append(value)
            for label in ("Shape", "Size", "Weight", "Colour", "Taste"):
                if label in merged:
                    specs.append((label, "; ".join(merged[label])))

        blocks = e["blocks"]
        if blocks.get("Storage"):
            specs.append(("Storage", blocks["Storage"]))
        if blocks.get("Season"):
            specs.append(("Season", blocks["Season"].replace("availability : ", "")))
        if blocks.get("Usage"):
            specs.append(("Typical uses", blocks["Usage"].lstrip("- ").replace(" - ", "; ")))
        if blocks.get("Nutrition"):
            specs.append(("Nutrition", blocks["Nutrition"]))

        # A name on its own carries no information worth a spec table.
        if len(specs) < 2:
            continue

        # product_specs.spec_value is VARCHAR(400).
        specs = [(k, v[:400]) for k, v in specs if v]
        rows.append((e["Common name"], specs))

    with open(out_path, "w", encoding="utf-8") as fh:
        fh.write("<?php\n")
        fh.write("/**\n")
        fh.write(" * Reference data for fresh fruit, extracted from the client-supplied\n")
        fh.write(' * "Thai FRUITS" export guide (Thai Department of Agriculture).\n')
        fh.write(" *\n")
        fh.write(" * GENERATED FILE - do not edit by hand. Regenerate with:\n")
        fh.write(' *   pdftotext -layout "Thai FRUITS.pdf" thaifruits.txt\n')
        fh.write(" *   python3 tools/extract_fruit_reference.py thaifruits.txt\n")
        fh.write(" *\n")
        fh.write(" * Every value below is quoted from that book. Nothing is inferred, and\n")
        fh.write(" * none of it is commercial - there are no pack sizes, prices, MOQs or\n")
        fh.write(" * certifications here, because the book does not state them and they\n")
        fh.write(" * would be the client's own trade terms to declare.\n")
        fh.write(" */\n\n")
        fh.write("return [\n")
        for name, specs in rows:
            fh.write("    %s => [\n" % php_string(name))
            for k, v in specs:
                fh.write("        [%s, %s],\n" % (php_string(k), php_string(v)))
            fh.write("    ],\n")
        fh.write("];\n")

    print("Wrote %d fruits with %d spec rows to %s"
          % (len(rows), sum(len(s) for _, s in rows), out_path))
    return 0


if __name__ == "__main__":
    sys.exit(main())
