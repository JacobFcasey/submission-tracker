"""Convert the Submission Tracker / CAPS integration analysis Markdown into a
native .docx file without any third-party dependencies.

This script intentionally mirrors the structure of
C:\\Users\\JacobMakopo\\CAPS\\generate_caps_analysis_docx.py so the same
markdown-to-docx pipeline is used across both repositories.
"""

import os
import re
import zipfile
from xml.sax.saxutils import escape


INPUT_FILE = "Submission_Tracker_CAPS_Integration_Analysis.md"
OUTPUT_FILE = "Submission_Tracker_CAPS_Integration_Analysis.docx"


def runs_for_text(text):
    """Return WordprocessingML runs for inline text, honouring **bold**,
    *italic* and `code` markdown markers."""
    pattern = re.compile(r"(\*\*[^*]+\*\*|\*[^*]+\*|`[^`]+`)")
    pieces = pattern.split(text)
    runs = []
    for piece in pieces:
        if not piece:
            continue
        if piece.startswith("**") and piece.endswith("**"):
            inner = piece[2:-2]
            runs.append(
                f"<w:r><w:rPr><w:b/></w:rPr>"
                f"<w:t xml:space=\"preserve\">{escape(inner)}</w:t></w:r>"
            )
        elif piece.startswith("*") and piece.endswith("*"):
            inner = piece[1:-1]
            runs.append(
                f"<w:r><w:rPr><w:i/></w:rPr>"
                f"<w:t xml:space=\"preserve\">{escape(inner)}</w:t></w:r>"
            )
        elif piece.startswith("`") and piece.endswith("`"):
            inner = piece[1:-1]
            runs.append(
                f"<w:r><w:rPr><w:rFonts w:ascii=\"Consolas\" w:hAnsi=\"Consolas\"/></w:rPr>"
                f"<w:t xml:space=\"preserve\">{escape(inner)}</w:t></w:r>"
            )
        else:
            runs.append(
                f"<w:r><w:t xml:space=\"preserve\">{escape(piece)}</w:t></w:r>"
            )
    return "".join(runs)


def paragraph_xml(text, style=None):
    style_xml = f"<w:pPr><w:pStyle w:val=\"{style}\"/></w:pPr>" if style else ""
    return f"<w:p>{style_xml}{runs_for_text(text)}</w:p>"


def table_xml(rows):
    """Build a basic Word table from a list of rows (list of cells)."""
    tbl_pr = (
        "<w:tblPr>"
        "<w:tblW w:w=\"0\" w:type=\"auto\"/>"
        "<w:tblBorders>"
        "<w:top w:val=\"single\" w:sz=\"4\" w:color=\"808080\"/>"
        "<w:left w:val=\"single\" w:sz=\"4\" w:color=\"808080\"/>"
        "<w:bottom w:val=\"single\" w:sz=\"4\" w:color=\"808080\"/>"
        "<w:right w:val=\"single\" w:sz=\"4\" w:color=\"808080\"/>"
        "<w:insideH w:val=\"single\" w:sz=\"4\" w:color=\"C0C0C0\"/>"
        "<w:insideV w:val=\"single\" w:sz=\"4\" w:color=\"C0C0C0\"/>"
        "</w:tblBorders>"
        "</w:tblPr>"
    )
    rendered_rows = []
    for row_index, cells in enumerate(rows):
        rendered_cells = []
        for cell in cells:
            cell_paragraph = (
                f"<w:p><w:pPr><w:spacing w:after=\"0\"/></w:pPr>"
                f"{runs_for_text(cell)}</w:p>"
            )
            rendered_cells.append(
                f"<w:tc><w:tcPr><w:tcW w:w=\"0\" w:type=\"auto\"/></w:tcPr>"
                f"{cell_paragraph}</w:tc>"
            )
        rendered_rows.append(f"<w:tr>{''.join(rendered_cells)}</w:tr>")
    return f"<w:tbl>{tbl_pr}{''.join(rendered_rows)}</w:tbl>"


def markdown_to_blocks(markdown_text):
    """Walk the markdown line-by-line, emitting (kind, payload) tuples that
    the document builder understands."""
    blocks = []
    buffer = []
    table_buffer = []

    def flush_paragraph():
        nonlocal buffer
        if buffer:
            blocks.append(("paragraph", ("Normal", " ".join(buffer).strip())))
            buffer = []

    def flush_table():
        nonlocal table_buffer
        if not table_buffer:
            return
        # Drop the header-separator row (---|---|---) if present.
        cleaned = [
            row for row in table_buffer
            if not all(re.fullmatch(r":?-+:?", cell.strip()) for cell in row)
        ]
        if cleaned:
            blocks.append(("table", cleaned))
        table_buffer = []

    for raw_line in markdown_text.splitlines():
        line = raw_line.rstrip()
        stripped = line.strip()

        if stripped.startswith("|") and stripped.endswith("|"):
            flush_paragraph()
            cells = [c.strip() for c in stripped.strip("|").split("|")]
            table_buffer.append(cells)
            continue
        else:
            flush_table()

        if not stripped:
            flush_paragraph()
            continue

        if line.startswith("# "):
            flush_paragraph()
            blocks.append(("paragraph", ("Title", line[2:].strip())))
            continue

        if line.startswith("## "):
            flush_paragraph()
            blocks.append(("paragraph", ("Heading1", line[3:].strip())))
            continue

        if line.startswith("### "):
            flush_paragraph()
            blocks.append(("paragraph", ("Heading2", line[4:].strip())))
            continue

        if line.startswith("#### "):
            flush_paragraph()
            blocks.append(("paragraph", ("Heading3", line[5:].strip())))
            continue

        m = re.match(r"^(\d+)\.\s+(.*)$", line)
        if m:
            flush_paragraph()
            blocks.append(("paragraph", ("ListNumber", m.group(2).strip())))
            continue

        if line.startswith("- "):
            flush_paragraph()
            blocks.append(("paragraph", ("ListParagraph", line[2:].strip())))
            continue

        buffer.append(line)

    flush_paragraph()
    flush_table()
    return blocks


def build_document_xml(blocks):
    body = []
    for kind, payload in blocks:
        if kind == "paragraph":
            style, text = payload
            body.append(paragraph_xml(text, style))
        elif kind == "table":
            body.append(table_xml(payload))
            # A trailing empty paragraph keeps Word happy after a table.
            body.append("<w:p/>")
    sect = (
        "<w:sectPr>"
        "<w:pgSz w:w=\"12240\" w:h=\"15840\"/>"
        "<w:pgMar w:top=\"1440\" w:right=\"1440\" w:bottom=\"1440\" w:left=\"1440\" "
        "w:header=\"708\" w:footer=\"708\" w:gutter=\"0\"/>"
        "</w:sectPr>"
    )
    return (
        "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>"
        "<w:document xmlns:w=\"http://schemas.openxmlformats.org/wordprocessingml/2006/main\">"
        f"<w:body>{''.join(body)}{sect}</w:body>"
        "</w:document>"
    )


def build_styles_xml():
    return """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal">
    <w:name w:val="Normal"/>
    <w:qFormat/>
    <w:rPr><w:sz w:val="22"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Title">
    <w:name w:val="Title"/>
    <w:basedOn w:val="Normal"/>
    <w:qFormat/>
    <w:pPr><w:spacing w:after="240"/></w:pPr>
    <w:rPr><w:b/><w:sz w:val="36"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="heading 1"/>
    <w:basedOn w:val="Normal"/>
    <w:next w:val="Normal"/>
    <w:qFormat/>
    <w:pPr><w:spacing w:before="240" w:after="120"/></w:pPr>
    <w:rPr><w:b/><w:sz w:val="28"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading2">
    <w:name w:val="heading 2"/>
    <w:basedOn w:val="Normal"/>
    <w:next w:val="Normal"/>
    <w:qFormat/>
    <w:pPr><w:spacing w:before="160" w:after="80"/></w:pPr>
    <w:rPr><w:b/><w:sz w:val="24"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading3">
    <w:name w:val="heading 3"/>
    <w:basedOn w:val="Normal"/>
    <w:next w:val="Normal"/>
    <w:qFormat/>
    <w:pPr><w:spacing w:before="120" w:after="60"/></w:pPr>
    <w:rPr><w:b/><w:sz w:val="22"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="ListParagraph">
    <w:name w:val="List Paragraph"/>
    <w:basedOn w:val="Normal"/>
    <w:qFormat/>
    <w:pPr><w:ind w:left="720" w:hanging="360"/></w:pPr>
    <w:rPr><w:sz w:val="22"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="ListNumber">
    <w:name w:val="List Number"/>
    <w:basedOn w:val="Normal"/>
    <w:qFormat/>
    <w:pPr><w:ind w:left="720" w:hanging="360"/></w:pPr>
    <w:rPr><w:sz w:val="22"/></w:rPr>
  </w:style>
</w:styles>
"""


def build_content_types_xml():
    return """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
"""


def build_root_rels_xml():
    return """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
"""


def build_document_rels_xml():
    return """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
"""


def build_core_xml():
    return """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
 xmlns:dc="http://purl.org/dc/elements/1.1/"
 xmlns:dcterms="http://purl.org/dc/terms/"
 xmlns:dcmitype="http://purl.org/dc/dcmitype/"
 xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>Submission Tracker and CAPS - Technical Analysis and Integration Proposal</dc:title>
  <dc:creator>Claude Code</dc:creator>
  <cp:lastModifiedBy>Claude Code</cp:lastModifiedBy>
  <dcterms:created xsi:type="dcterms:W3CDTF">2026-04-15T00:00:00Z</dcterms:created>
  <dcterms:modified xsi:type="dcterms:W3CDTF">2026-04-15T00:00:00Z</dcterms:modified>
</cp:coreProperties>
"""


def build_app_xml():
    return """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"
 xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>Microsoft Office Word</Application>
</Properties>
"""


def main():
    here = os.path.dirname(os.path.abspath(__file__))
    input_path = os.path.join(here, INPUT_FILE)
    output_path = os.path.join(here, OUTPUT_FILE)

    with open(input_path, "r", encoding="utf-8") as f:
        markdown = f.read()

    blocks = markdown_to_blocks(markdown)
    document_xml = build_document_xml(blocks)

    if os.path.exists(output_path):
        os.remove(output_path)

    with zipfile.ZipFile(output_path, "w", compression=zipfile.ZIP_DEFLATED) as docx:
        docx.writestr("[Content_Types].xml", build_content_types_xml())
        docx.writestr("_rels/.rels", build_root_rels_xml())
        docx.writestr("word/document.xml", document_xml)
        docx.writestr("word/styles.xml", build_styles_xml())
        docx.writestr("word/_rels/document.xml.rels", build_document_rels_xml())
        docx.writestr("docProps/core.xml", build_core_xml())
        docx.writestr("docProps/app.xml", build_app_xml())

    print(output_path)


if __name__ == "__main__":
    main()
