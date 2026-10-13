"""Fail CI for new payroll failures while reporting existing base failures."""
import sys
import xml.etree.ElementTree as ET


def failures(path):
    root = ET.parse(path).getroot()
    cases = list(root.iter("testcase"))
    if not cases:
        raise RuntimeError(f"No test results in {path}")
    return {
        (case.get("class") or case.get("classname") or "", case.get("name", ""))
        for case in cases
        if case.find("failure") is not None or case.find("error") is not None
    }


baseline, current = map(failures, sys.argv[1:3])
new = current - baseline
print(f"Base failures: {len(baseline)}; current failures: {len(current)}; new failures: {len(new)}")
for group, values in [("Existing failures", current & baseline), ("New failures", new)]:
    for classname, name in sorted(values):
        print(f"{group}: {classname}::{name}")
sys.exit(bool(new))
