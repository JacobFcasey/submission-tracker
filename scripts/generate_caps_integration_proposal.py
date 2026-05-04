from pathlib import Path
from xml.sax.saxutils import escape
from zipfile import ZipFile, ZIP_DEFLATED


TITLE = "Formal Integration Proposal: Current System and CAPS"

BODY = """
Formal Integration Proposal: Current System and CAPS

1. Executive Summary
This proposal positions the current Laravel system as the operational intake and control portal, and CAPS as the transactional processing and settlement platform.
The recommendation is not to merge the two systems conceptually. They serve different strengths. The current system already handles assignment control, deadline tracking, file submission, audit visibility, and user-level accountability. CAPS handles policy, premium, payment, reconciliation, allocation, refunds, statements, and financial reporting. The integration should preserve that separation.

2. Current-State Assessment
2.1 Current system capabilities
The current Laravel application already supports:
- user authentication and role-based permissions
- company and municipality administration
- assignment of users to municipality/company combinations
- deadline creation and deadline calendars
- file submission packages per company/municipality
- support for original files, workings files, and systems-import files
- submission history and export
- notifications
- audits and operational reports
- limited external Casey/CAPS API consumption for premium batch detail

2.2 CAPS capabilities
From the provided CAPS documents, CAPS supports:
- member onboarding and maintenance
- staged batch imports and error handling
- policy and premium generation/import
- payment import and export
- reconciliation
- allocations and manual allocations
- refunds
- member statements
- payment submissions
- dashboard and financial reporting
- richer action-group based access control

2.3 Core conclusion
There is partial overlap around submissions, batches, reporting, and access scoping, but CAPS is the dominant business-processing system. The current system should support CAPS, not compete with it.

3. Integration Objectives
The integration should achieve these business outcomes:
- provide a single place for users to manage municipal/company submission work
- ensure every submission is tied to an assignment and deadline
- send approved submission packages into CAPS without manual re-entry
- track CAPS processing results against the originating submission
- expose batch errors and completion statuses back to operational users
- preserve auditability across both systems
- reduce email/manual file handling outside a controlled workflow

4. Target Architecture
4.1 Recommended system roles
Current Laravel system:
- assignment management
- deadline scheduling
- submission intake
- document/file storage
- operational dashboard for submission responsibility
- audit and user notification layer
- CAPS tracking front-end

CAPS:
- business processing engine
- member/policy/payment system of record
- batch validation and staging
- reconciliation and allocation engine
- refunds/statements/submissions engine
- financial and settlement reporting source

4.2 Logical flow
1. Admin creates municipality deadlines and assigns users to companies.
2. User uploads required files in the current system.
3. Current system validates assignment, deadline, and package completeness.
4. Current system creates a submission package record.
5. Current system dispatches the package to CAPS.
6. CAPS creates the appropriate batch and processes it.
7. CAPS returns:
- batch id
- batch type
- processing status
- validation errors
- summary counts
- downloadable outputs where relevant
8. Current system stores the returned CAPS references and displays status to users.
9. Users and admins monitor progress from the current system without needing to inspect CAPS directly for every case.

5. Capability Mapping
5.1 Strong alignment
These current features map well into CAPS integration:
- municipality/company assignment -> CAPS organization/municipality scope
- deadlines -> CAPS monthly/batch operational windows
- upload packages -> CAPS import source files
- history pages -> CAPS batch traceability
- reports/audits -> cross-system operational oversight
- notifications -> CAPS exception feedback to users

5.2 Missing capabilities in the current system
These must be added if the system is to act as a proper CAPS front-end:
- a formal submission_to_caps integration workflow
- persistent CAPS batch reference storage
- local status model aligned with CAPS processing stages
- structured error/result storage from CAPS
- retry/resubmit controls
- batch detail drill-down
- integration monitoring
- possible webhook endpoint or polling scheduler

6. Proposed Integration Patterns
6.1 Primary integration pattern: API-based batch dispatch
Preferred approach:
- current system sends submission metadata and file references to CAPS via API
- CAPS creates a business batch and returns identifiers
- current system polls CAPS or receives callback updates
This is the cleanest option because it keeps ownership boundaries clear.

6.2 Alternative pattern: file handoff
If CAPS requires file drops instead of rich APIs:
- current system exports package files in CAPS-required formats
- CAPS import jobs consume them
- current system stores the handoff reference and later syncs status
This works, but is weaker operationally because feedback is slower and less structured.

6.3 Status synchronization options
Option A: Polling
- Laravel scheduler checks CAPS batch status every few minutes
- simpler if CAPS does not support callbacks

Option B: Webhooks
- CAPS pushes status changes to the current system
- better near-real-time behavior
- needs stronger authentication and replay protection

Recommended:
- start with polling
- add webhooks later if CAPS supports them cleanly

7. Data Contract Proposal
7.1 New local fields on uploads/submissions
Add fields such as:
- caps_batch_type
- caps_batch_id
- caps_status
- caps_status_detail
- caps_error_count
- caps_warning_count
- caps_response_payload
- caps_submitted_at
- caps_last_synced_at
- caps_completed_at
- caps_sync_error
- integration_attempts

7.2 Suggested batch type values
Examples:
- member_import
- policy_import
- premium_batch
- payment_import
- payment_recon
- payment_allocation
- submission_generation
Not every upload will map to every type. The type should be explicit at dispatch time.

7.3 Suggested local status model
Use a lifecycle such as:
- draft
- submitted_local
- dispatched_to_caps
- accepted_by_caps
- processing_in_caps
- completed_in_caps
- completed_with_errors
- failed_in_caps
- cancelled
This is much more useful than only Pending, Processing, Completed, Rejected.

8. Functional Integration Design
8.1 Submission intake
Current behavior:
- user uploads original/workings/system-import files

Enhancement:
- let the user choose the CAPS process type where appropriate
- validate required package rules per type
- prevent dispatch if assignment/deadline requirements are not met

8.2 CAPS dispatch
Add a dispatch action that:
- validates the upload is complete
- prepares metadata:
  user
  municipality
  company
  submission date
  deadline date
  local reference
  file locations or file payload
- calls CAPS endpoint
- stores returned batch ids and initial status

8.3 Status tracking
Add a sync service that:
- queries CAPS by caps_batch_id
- updates local status
- stores summary counts and errors
- triggers user/admin notifications on failures or completion

8.4 Results view
Add a submission result screen showing:
- local upload metadata
- CAPS batch type and id
- CAPS status
- counts of processed records
- counts of errors/warnings
- error summaries
- links to downloaded outputs or CAPS detail views if available

9. Security and Access Control
9.1 Local control
Keep current assignment and role control in Laravel. A user should only dispatch or view CAPS-linked batches for companies and municipalities they are assigned to.

9.2 CAPS authentication
Use service credentials or OAuth/JWT between systems. Do not rely on end-user credentials for server-to-server dispatch unless CAPS explicitly requires delegated identity.

9.3 Auditability
Record:
- who uploaded the package
- who dispatched it to CAPS
- when CAPS accepted it
- status transitions
- errors returned by CAPS
- any manual retry actions
This should be part of the local audit trail even if CAPS also audits internally.

10. Reporting Model
Recommended split:
Current system reports:
- submissions due
- submissions pending
- user workload
- upload completion and re-upload trends
- CAPS dispatch status by municipality/company/user

CAPS reports:
- member/policy processing outcomes
- premium batch outcomes
- payment reconciliation/allocation outcomes
- refunds/statements/submissions
- financial totals

The Laravel app should summarize CAPS process state, not rebuild CAPS financial reporting.

11. Gap Analysis and Required Changes
11.1 High-priority changes
Required before meaningful integration:
- add a proper integration status model
- add persistent CAPS batch linkage fields
- implement dispatch service to CAPS
- implement CAPS status sync service
- add UI for CAPS-linked batch results
- normalize upload/submission concepts
- fix or complete missing submission creation flow if POST /submissions is intended

11.2 Medium-priority changes
Useful next:
- richer validation by submission type
- retry/resubmit workflow
- downloadable CAPS error files
- SLA/aging dashboards for CAPS processing
- cross-system reference search

11.3 Low-priority changes
Later enhancements:
- webhook support
- automated exception routing
- two-way deep linking into CAPS
- consolidated executive reporting

12. Implementation Phases
Phase 1: Foundation
Goal: make the current system CAPS-aware.
Deliverables:
- schema changes for CAPS references and statuses
- service configuration cleanup
- integration service abstraction
- UI indicators for CAPS state
- manual dispatch action
- scheduler-based sync job

Phase 2: Operational integration
Goal: support real processing cycles.
Deliverables:
- batch-type specific dispatch
- result/error views
- notifications on failure/completion
- admin monitoring page
- retry controls
- operational metrics

Phase 3: Process maturity
Goal: reduce manual supervision.
Deliverables:
- webhook support if CAPS supports it
- automatic dispatch by rule
- exception queues
- SLA alerts
- management dashboards across both systems

13. Key Risks
- domain mismatch: trying to force CAPS business logic into Laravel will create duplication and inconsistency
- status drift: local status and CAPS batch status can diverge if sync logic is weak
- file contract ambiguity: if CAPS import expectations are not precise, dispatch will fail frequently
- security sprawl: direct user-to-CAPS access may complicate support and permissions
- reporting confusion: users may expect financial truth in Laravel when it should remain in CAPS

14. Recommendations
The recommended architecture is:
- keep the current system as the submission, assignment, deadline, and visibility layer
- keep CAPS as the processing and settlement layer
- integrate through explicit batch dispatch and status synchronization
- avoid replicating CAPS domains such as policy, reconciliation, allocation, refund, and statements inside Laravel
- expand the current system only where needed to make CAPS processing visible and auditable

15. Proposed Next Deliverables
Recommended immediate next steps:
1. Produce a detailed capability matrix: Current System vs CAPS vs Integrated Target State.
2. Draft the API contract for Laravel to CAPS integration.
3. Define the Laravel schema changes for CAPS batch tracking.
4. Break implementation into phased engineering tasks with dependencies.
""".strip()


def make_paragraph(text: str, bold: bool = False) -> str:
    text = escape(text)
    if not text:
        return "<w:p/>"
    run_props = "<w:rPr><w:b/></w:rPr>" if bold else ""
    return (
        "<w:p>"
        "<w:r>"
        f"{run_props}"
        f"<w:t xml:space=\"preserve\">{text}</w:t>"
        "</w:r>"
        "</w:p>"
    )


def build_document_xml() -> str:
    paragraphs = [make_paragraph(TITLE, bold=True)]

    for line in BODY.splitlines():
        if not line.strip():
            paragraphs.append("<w:p/>")
            continue
        bold = line[:2].isdigit() or line.startswith("Formal Integration Proposal:")
        paragraphs.append(make_paragraph(line, bold=bold))

    body = "".join(paragraphs)
    sect = (
        "<w:sectPr>"
        "<w:pgSz w:w=\"12240\" w:h=\"15840\"/>"
        "<w:pgMar w:top=\"1440\" w:right=\"1440\" w:bottom=\"1440\" w:left=\"1440\" "
        "w:header=\"708\" w:footer=\"708\" w:gutter=\"0\"/>"
        "</w:sectPr>"
    )

    return (
        "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>"
        "<w:document xmlns:wpc=\"http://schemas.microsoft.com/office/word/2010/wordprocessingCanvas\" "
        "xmlns:mc=\"http://schemas.openxmlformats.org/markup-compatibility/2006\" "
        "xmlns:o=\"urn:schemas-microsoft-com:office:office\" "
        "xmlns:r=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships\" "
        "xmlns:m=\"http://schemas.openxmlformats.org/officeDocument/2006/math\" "
        "xmlns:v=\"urn:schemas-microsoft-com:vml\" "
        "xmlns:wp14=\"http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing\" "
        "xmlns:wp=\"http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing\" "
        "xmlns:w10=\"urn:schemas-microsoft-com:office:word\" "
        "xmlns:w=\"http://schemas.openxmlformats.org/wordprocessingml/2006/main\" "
        "xmlns:w14=\"http://schemas.microsoft.com/office/word/2010/wordml\" "
        "xmlns:wpg=\"http://schemas.microsoft.com/office/word/2010/wordprocessingGroup\" "
        "xmlns:wpi=\"http://schemas.microsoft.com/office/word/2010/wordprocessingInk\" "
        "xmlns:wne=\"http://schemas.microsoft.com/office/word/2006/wordml\" "
        "xmlns:wps=\"http://schemas.microsoft.com/office/word/2010/wordprocessingShape\" "
        "mc:Ignorable=\"w14 wp14\">"
        f"<w:body>{body}{sect}</w:body>"
        "</w:document>"
    )


CONTENT_TYPES = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
"""

ROOT_RELS = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
"""

DOC_RELS = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>
"""

CORE_XML = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
 xmlns:dc="http://purl.org/dc/elements/1.1/"
 xmlns:dcterms="http://purl.org/dc/terms/"
 xmlns:dcmitype="http://purl.org/dc/dcmitype/"
 xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>Formal Integration Proposal: Current System and CAPS</dc:title>
  <dc:creator>OpenAI Codex</dc:creator>
  <cp:lastModifiedBy>OpenAI Codex</cp:lastModifiedBy>
  <dcterms:created xsi:type="dcterms:W3CDTF">2026-04-13T00:00:00Z</dcterms:created>
  <dcterms:modified xsi:type="dcterms:W3CDTF">2026-04-13T00:00:00Z</dcterms:modified>
</cp:coreProperties>
"""

APP_XML = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"
 xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>Microsoft Office Word</Application>
</Properties>
"""


def main() -> None:
    output = Path("Formal_CAPS_Integration_Proposal.docx")
    output.parent.mkdir(parents=True, exist_ok=True)

    with ZipFile(output, "w", compression=ZIP_DEFLATED) as docx:
        docx.writestr("[Content_Types].xml", CONTENT_TYPES)
        docx.writestr("_rels/.rels", ROOT_RELS)
        docx.writestr("word/document.xml", build_document_xml())
        docx.writestr("word/_rels/document.xml.rels", DOC_RELS)
        docx.writestr("docProps/core.xml", CORE_XML)
        docx.writestr("docProps/app.xml", APP_XML)

    print(output.resolve())


if __name__ == "__main__":
    main()
