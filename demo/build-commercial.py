#!/usr/bin/env python3
"""DBellOps 720p commercial from actual browser screenshots; no synthetic app UI."""
from pathlib import Path
import subprocess, shutil
root=Path("demo/output")
frames=sorted(root.glob("*.png"))
frames=[p for p in frames if "error-" not in p.name]
if len(frames)<3:
    raise SystemExit("Need at least 3 authentic browser screenshots")
scenes=[
    ("SELLER OPERATIONS, SIMPLIFIED", "Your entire business. One dashboard."),
    ("A CLEAR VIEW OF PERFORMANCE", "Track revenue, activity and key metrics."),
    ("EVERY SHOW, IN ONE PLACE", "Stay organized from start to finish."),
    ("INVENTORY WITHOUT THE CHAOS", "Products, stock and locations in sync."),
    ("REPORTING THAT MAKES SENSE", "Turn your numbers into decisions."),
    ("MEET DBELLOPS", "Built for ambitious live sellers."),
]
font="/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
clips=[]
for i,img in enumerate(frames[:6]):
    heading,sub=scenes[i]
    dest=root/f"commercial-scene-{i:02}.mp4"
    # Bottom caption band keeps the real app fully visible above it.
    vf=(
      "scale=1280:720:force_original_aspect_ratio=decrease,"
      "pad=1280:720:(ow-iw)/2:(oh-ih)/2,"
      "drawbox=x=0:y=564:w=1280:h=156:color=0x071e29@0.92:t=fill,"
      "drawbox=x=0:y=564:w=9:h=156:color=0x14b8a6:t=fill,"
      f"drawtext=fontfile={font}:text='{heading}':fontsize=33:fontcolor=white:x=46:y=586,"
      f"drawtext=fontfile={font}:text='{sub}':fontsize=21:fontcolor=0x99f6e4:x=46:y=644,"
      "fade=t=in:st=0:d=0.35,fade=t=out:st=4.15:d=0.35,format=yuv420p"
    )
    subprocess.run(["ffmpeg","-y","-loop","1","-t","4.5","-i",str(img),
      "-vf",vf,"-r","24","-c:v","libx264","-preset","veryfast","-pix_fmt","yuv420p",str(dest)],check=True,
      stdout=subprocess.DEVNULL,stderr=subprocess.PIPE)
    clips.append(dest)
concat=root/"commercial-scenes.txt"
concat.write_text("".join(f"file '{p.resolve()}'\n" for p in clips))
silent=root/"DBellOps_Commercial_Silent.mp4"
subprocess.run(["ffmpeg","-y","-f","concat","-safe","0","-i",str(concat),"-c","copy",str(silent)],check=True,
 stdout=subprocess.DEVNULL,stderr=subprocess.PIPE)
script=(
 "Running a live selling business shouldn't feel like running ten different systems. "
 "Meet D Bell Ops. Your shows, inventory, and performance in one place. "
 "See the numbers that matter, review show reports, and keep your operation moving. "
 "Spend less time managing spreadsheets and more time growing your business. "
 "D Bell Ops. Built for live sellers."
)
(root/"commercial-voiceover-script.txt").write_text(script+"\n")
if shutil.which("espeak-ng"):
    audio=root/"commercial-narration.wav"
    subprocess.run(["espeak-ng","-v","en-us","-s","142","-w",str(audio),script],check=True)
    output=root/"DBellOps_Commercial.mp4"
    subprocess.run(["ffmpeg","-y","-i",str(silent),"-i",str(audio),
      "-map","0:v:0","-map","1:a:0","-c:v","copy","-c:a","aac","-b:a","160k",
      "-af","apad","-t","27","-movflags","+faststart",str(output)],check=True,
      stdout=subprocess.DEVNULL,stderr=subprocess.PIPE)
else:
    shutil.copyfile(silent,root/"DBellOps_Commercial.mp4")
print("Built commercial from",len(clips),"real application captures")
