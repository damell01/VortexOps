#!/usr/bin/env python3
from pathlib import Path

README = Path('README.md')
START = '<!-- current-operations-screenshots:start -->'
END = '<!-- current-operations-screenshots:end -->'

SECTION = f'''{START}
## Current Operations Workflow — September 2026

The current operational UI follows one clear handoff:

**Shows → Streamer Report → Admin Review → Fulfillment → Payroll Ready → Pay Run → Paid**

These screenshots are captured automatically from the current seeded application by `tests/Browser/handbook-screenshot.spec.ts`. The same images are used by the in-app Handbook, so the README and operator documentation stay aligned with the UI.

### Shows Command Center

![Shows Command Center](public/guide/manual/ops-shows-command-center.png)

### Show Workspace

![Show Workspace](public/guide/manual/ops-show-workspace.png)

### Streamer Report / Admin Review

![Admin Review Workspace](public/guide/manual/ops-admin-review.png)

### Fulfillment Workspace

![Fulfillment Workspace](public/guide/manual/ops-fulfillment-center.png)

### Packing Workstation

![Packing Workstation](public/guide/manual/ops-packing-workstation.png)

### Payroll Command Center

![Payroll Command Center](public/guide/manual/ops-payroll-command-center.png)

### Pay Run Workspace

![Pay Run Workspace](public/guide/manual/ops-pay-run-workspace.png)

{END}
'''

text = README.read_text()

if START in text and END in text:
    before, remainder = text.split(START, 1)
    _, after = remainder.split(END, 1)
    updated = before.rstrip() + '\n\n' + SECTION.rstrip() + '\n\n' + after.lstrip()
else:
    marker = '\n---\n\n## User Roles & Permissions'
    if marker not in text:
        raise SystemExit('README insertion marker was not found')
    updated = text.replace(marker, '\n---\n\n' + SECTION.rstrip() + '\n\n---\n\n## User Roles & Permissions', 1)

README.write_text(updated)
