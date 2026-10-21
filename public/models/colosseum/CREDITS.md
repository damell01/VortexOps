# Vortex Colosseum — model credits

Fan-made / fan-ripped Pokémon models used internally by the Show Leaderboard
arena. Pokémon and every character name are trademarks of Nintendo, Game Freak
and The Pokémon Company; these files are not licensed by them. Keep this page
off anything public-facing.

Every file here came from the Pokémon 3D API asset collection
(https://github.com/Pokemon-3D-api/assets, MIT for the collection), and was then
cut down for the arena: only the battle clips are kept (renamed to idle,
battle, attack, hit, down_start, down_loop, down_end, roar), textures are
kept at up to 1024 px WebP, and meshes are meshopt-compressed.

| File | Model | Author | Licence | Source |
|------|-------|--------|---------|--------|
| mewtwo.glb | Mewtwo (Official) | Mariokart07 (https://sketchfab.com/Mariokart07) | CC BY 4.0 | https://sketchfab.com/3d-models/mewtwo-official-d8aebb93c39243b3a62022591202a97a |
| dragonite.glb | Dragonite (Official) | Mariokart07 (https://sketchfab.com/Mariokart07) | CC BY 4.0 | https://sketchfab.com/3d-models/dragonite-official-874c7fcdbd344c7bb9e8ed3e6a04dfac |
| eevee.glb | Pokedex 3d Pro Eevee | seth the yutyrannus (https://sketchfab.com/slang107123456789) | CC BY 4.0 | https://sketchfab.com/3d-models/pokedex-3d-pro-eevee-08d0e011acc64f92b77045384a4fbc3b |
| pikachu.glb | Pikachu (with Thunderbolt clip) | Not recorded in the file | Not recorded | https://github.com/Pokemon-3D-api/assets/blob/main/models/opt/regular/25.glb |
| charizard.glb | Charizard (with dizzy clip) | Not recorded in the file | Not recorded | https://github.com/Pokemon-3D-api/assets/blob/main/models/opt/regular/6.glb |
| gengar.glb | Gengar | Not recorded in the file | Not recorded | https://github.com/Pokemon-3D-api/assets/blob/main/models/opt/regular/94.glb |

Motions the source files don't include (Pikachu, Charizard, Gengar and Eevee
have at most one clip) are animated in code by resources/js/colosseum.

To add a fighter: take its GLB from the collection above, run it through the
same clip-trim + meshopt pass, drop it here, add a row to this table and an
entry to resources/js/colosseum/roster.js.
