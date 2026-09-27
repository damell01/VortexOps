import os, subprocess, sys
from pathlib import Path
root=Path(os.environ.get("VORTEX_COLLECTOR_ROOT") or Path(sys.executable).parent)
runner=root/"scrapling_runner.py"
collector=root/"scrapling_collector.cjs"
node=Path(sys.executable).parent/"node.exe"
if not node.exists():
    node=Path(os.environ.get("VORTEX_NODE_EXE","node"))
env=os.environ.copy()
env["PYTHON"]=sys.executable
env["VORTEX_PACKAGED"]="1"
raise SystemExit(subprocess.call([str(node),str(collector)],cwd=str(root),env=env))
