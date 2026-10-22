# Optional hybrid cinematic leaderboard

The existing leaderboard and 2D sprites remain the default. To activate a cinematic backdrop, export a short silent MP4 from the existing Vortex colosseum scene and place it at `public/video/vortex-colosseum-intro.mp4`.

Suggested export: 1.5 seconds, wide crop (1280x360), H.264, no embedded names, Pokemon, figures or podium numbers, no audio, target under 1 MB. Frame the arena behind the existing HTML podiums. Missing media never blocks the leaderboard. The video is requested only when the podium entrance plays (or Replay is clicked), with preload=none; CSS podium, sprites, names and earnings display regardless.

Do not use a full 3D engine on the dashboard. Benchmark LCP, video bytes and mobile performance after uploading the export. On reduced-motion devices, video is hidden.
