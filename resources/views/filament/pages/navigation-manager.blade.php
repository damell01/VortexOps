<x-filament-panels::page>
<style>
.vx-nav-manager{max-width:1500px;margin:0 auto}.vx-nav-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px}.vx-tabs{display:inline-flex;padding:4px;border:1px solid rgb(229 231 235);border-radius:14px;background:#fff}.dark .vx-tabs{border-color:#334155;background:#111827}.vx-tab{padding:8px 14px;border-radius:10px;font-size:13px;font-weight:700;color:#64748b}.vx-tab.active{background:#7c3aed;color:#fff}.vx-manager-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(310px,.65fr);gap:18px}.vx-panel{border:1px solid rgb(226 232 240);background:#fff;border-radius:18px;box-shadow:0 1px 2px rgba(15,23,42,.03)}.dark .vx-panel{border-color:#293548;background:#101827}.vx-group{border:1px solid rgb(226 232 240);border-radius:16px;overflow:hidden;background:#fff}.dark .vx-group{border-color:#293548;background:#0f172a}.vx-group-head{display:flex;align-items:center;gap:10px;padding:12px 14px;background:#f8fafc;border-bottom:1px solid rgb(226 232 240)}.dark .vx-group-head{background:#111827;border-color:#293548}.vx-grip{cursor:grab;color:#94a3b8;font-size:18px;line-height:1}.vx-item{display:grid;grid-template-columns:28px minmax(0,1fr) auto;align-items:center;gap:10px;padding:11px 14px;border-top:1px solid rgb(241 245 249)}.vx-item:first-child{border-top:0}.dark .vx-item{border-color:#1e293b}.vx-item.dragging,.vx-group.dragging{opacity:.45}.vx-drop{min-height:7px}.vx-access{display:inline-flex;padding:3px;border:1px solid rgb(226 232 240);border-radius:11px;gap:2px;background:#f8fafc}.dark .vx-access{border-color:#334155;background:#111827}.vx-state{padding:6px 9px;border-radius:8px;font-size:11px;font-weight:800;color:#64748b}.vx-state.manage.active{background:#dcfce7;color:#15803d}.vx-state.view.active{background:#dbeafe;color:#1d4ed8}.vx-state.hidden.active{background:#fee2e2;color:#b91c1c}.vx-preview{position:sticky;top:90px;overflow:hidden}.vx-preview-shell{background:#111827;color:#e5e7eb;border-radius:16px;padding:16px;min-height:520px}.vx-preview-brand{display:flex;align-items:center;gap:10px;padding:4px 4px 18px;border-bottom:1px solid #263244}.vx-preview-logo{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#7c3aed;color:white;font-weight:900}.vx-preview-group{margin-top:18px}.vx-preview-group-title{font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:#64748b;font-weight:800;margin:0 8px 7px}.vx-preview-item{display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:10px;font-size:12px;color:#cbd5e1}.vx-preview-item:first-of-type{background:#25203c;color:#c4b5fd}.vx-preview-dot{width:7px;height:7px;border-radius:999px;background:#7c3aed}.vx-preview-readonly{margin-left:auto;font-size:9px;color:#93c5fd;background:#172554;border-radius:999px;padding:2px 6px}.vx-role-select{min-width:180px;border:1px solid rgb(203 213 225);border-radius:12px;padding:8px 34px 8px 11px;background:#fff;font-size:13px;font-weight:650}.dark .vx-role-select{background:#111827;border-color:#475569}.vx-help{border:1px solid #ddd6fe;background:#f5f3ff;color:#5b21b6;border-radius:14px;padding:12px 14px;font-size:12px;line-height:1.55}.dark .vx-help{border-color:#4c1d95;background:#21143f;color:#ddd6fe}.vx-mini-input{width:100%;border:1px solid transparent;border-radius:8px;background:transparent;padding:5px 7px;font-weight:700}.vx-mini-input:focus{border-color:#c4b5fd;background:#fff;outline:none}.dark .vx-mini-input:focus{background:#111827}.vx-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:1px solid rgb(203 213 225);border-radius:11px;padding:8px 12px;font-size:12px;font-weight:750;background:white}.dark .vx-btn{background:#111827;border-color:#475569}.vx-btn.primary{border-color:#7c3aed;background:#7c3aed;color:white}.vx-btn.danger{color:#b91c1c}.vx-empty{padding:24px;text-align:center;color:#94a3b8;font-size:13px}
@media(max-width:1000px){.vx-manager-grid{grid-template-columns:1fr}.vx-preview{position:relative;top:auto}.vx-preview-shell{min-height:360px}}
@media(max-width:640px){.vx-item{grid-template-columns:22px minmax(0,1fr)}.vx-access{grid-column:1/-1;width:100%;display:grid;grid-template-columns:repeat(3,1fr)}.vx-state{text-align:center}.vx-nav-toolbar{align-items:stretch}.vx-tabs{width:100%;display:grid;grid-template-columns:1fr 1fr}.vx-tab{text-align:center}.vx-role-select{width:100%}}
</style>

<div class="vx-nav-manager space-y-4" x-data="{
    draggingItem:null,
    draggingGroup:null,
    dropItem(group,index){ if(this.draggingItem){ $wire.moveItem(this.draggingItem,group,index); this.draggingItem=null; } },
    dropGroup(index){ if(this.draggingGroup){ $wire.moveGroup(this.draggingGroup,index); this.draggingGroup=null; } }
}">
    <div class="vx-nav-toolbar">
        <div class="vx-tabs">
            <button type="button" wire:click="setTab('layout')" class="vx-tab {{ $tab === 'layout' ? 'active' : '' }}">Menu Layout</button>
            <button type="button" wire:click="setTab('access')" class="vx-tab {{ $tab === 'access' ? 'active' : '' }}">Role Access</button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold text-gray-500">Preview as</span>
            <select wire:change="selectRole($event.target.value)" class="vx-role-select">
                @foreach($this->roleNames() as $role)
                    <option value="{{ $role }}" @selected($selectedRole === $role)>{{ str($role)->headline() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="vx-help">
        <strong>One source of truth:</strong> Role Access writes to the same visible/read-only settings used by Roles & Permissions. It does not create a second permission system. Detailed action permissions still belong on Roles & Permissions. Owner access remains protected even when an item is removed from the visible sidebar.
    </div>

    <div class="vx-manager-grid">
        <main class="space-y-3">
            @if($tab === 'layout')
                <section class="vx-panel p-4 sm:p-5">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-bold text-gray-950 dark:text-white">Sidebar structure</h3>
                            <p class="mt-1 text-xs text-gray-500">Drag modules and pages into the order you want. You can also rename labels without renaming routes or code.</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" wire:click="resetLayout" wire:confirm="Reset the sidebar back to the code-defined layout?" class="vx-btn danger">Reset Layout</button>
                            <button type="button" wire:click="saveLayout" class="vx-btn primary">Save Layout</button>
                        </div>
                    </div>

                    <div class="space-y-3">
                        @foreach($layoutGroups as $groupIndex => $group)
                            @php
                                $groupItems = collect($layoutItems)
                                    ->filter(fn($item,$class) => isset($catalog[$class]) && ($item['group'] ?? '') === $group['label'])
                                    ->sortBy('sort');
                            @endphp
                            <div class="vx-drop" @dragover.prevent @drop.prevent="dropGroup({{ $groupIndex }})"></div>
                            <div class="vx-group" draggable="true"
                                 x-on:dragstart.stop="draggingGroup='{{ $group['id'] }}'; $el.classList.add('dragging')"
                                 x-on:dragend="$el.classList.remove('dragging')">
                                <div class="vx-group-head">
                                    <span class="vx-grip" title="Drag module">⋮⋮</span>
                                    <input class="vx-mini-input" value="{{ $group['label'] }}"
                                           x-on:change="$wire.renameGroup('{{ $group['id'] }}',$event.target.value)" />
                                    <span class="text-[11px] font-semibold text-gray-400">{{ $groupItems->count() }} items</span>
                                </div>

                                <div>
                                    @forelse($groupItems as $class => $item)
                                        @php $itemIndex = $loop->index; @endphp
                                        <div class="vx-drop" @dragover.prevent @drop.prevent="dropItem('{{ addslashes($group['label']) }}',{{ $itemIndex }})"></div>
                                        <div class="vx-item" draggable="true"
                                             x-on:dragstart.stop="draggingItem='{{ addslashes($class) }}'; draggingGroup=null; $el.classList.add('dragging')"
                                             x-on:dragend="$el.classList.remove('dragging')">
                                            <span class="vx-grip">⋮⋮</span>
                                            <div class="min-w-0">
                                                <input class="vx-mini-input" value="{{ $item['label'] ?: $catalog[$class]['label'] }}"
                                                       x-on:change="$wire.renameItem('{{ addslashes($class) }}',$event.target.value)" />
                                                <div class="truncate px-2 text-[10px] text-gray-400">{{ class_basename($class) }}</div>
                                            </div>
                                            <span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-semibold text-gray-500 dark:bg-gray-800">{{ $roleStates[$class] ?? 'manage' }}</span>
                                        </div>
                                    @empty
                                        <div class="vx-empty">Drop a page into this module.</div>
                                    @endforelse
                                    <div class="vx-drop h-4" @dragover.prevent @drop.prevent="dropItem('{{ addslashes($group['label']) }}',{{ $groupItems->count() }})"></div>
                                </div>
                            </div>
                        @endforeach
                        <div class="vx-drop h-6" @dragover.prevent @drop.prevent="dropGroup({{ count($layoutGroups) }})"></div>
                    </div>
                </section>
            @else
                <section class="vx-panel p-4 sm:p-5">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-bold text-gray-950 dark:text-white">Access for {{ str($selectedRole)->headline() }}</h3>
                            <p class="mt-1 text-xs text-gray-500"><strong>Manage</strong> grants page access plus edits allowed by the existing page/policy. <strong>View Only</strong> grants the page but marks it read-only. <strong>Hidden</strong> removes the page grant and sidebar item.</p>
                        </div>
                        <button type="button" wire:click="resetRoleAccess" wire:confirm="Reset this role back to its built-in access rules?" class="vx-btn danger">Reset Role</button>
                    </div>

                    <div class="space-y-3">
                        @foreach($layoutGroups as $group)
                            @php
                                $groupItems = collect($layoutItems)
                                    ->filter(fn($item,$class) => isset($catalog[$class]) && ($item['group'] ?? '') === $group['label'])
                                    ->sortBy('sort');
                            @endphp
                            @if($groupItems->isNotEmpty())
                                <div class="vx-group">
                                    <div class="vx-group-head"><div class="font-bold text-sm">{{ $group['label'] }}</div></div>
                                    @foreach($groupItems as $class => $item)
                                        @php $state=$roleStates[$class] ?? 'manage'; @endphp
                                        <div class="vx-item">
                                            <span class="grid h-7 w-7 place-items-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-950/40">•</span>
                                            <div class="min-w-0">
                                                <div class="truncate text-sm font-semibold">{{ $item['label'] ?: $catalog[$class]['label'] }}</div>
                                                <div class="truncate text-[10px] text-gray-400">{{ class_basename($class) }}</div>
                                            </div>
                                            <div class="vx-access">
                                                <button type="button" wire:click="setAccess(@js($class),'manage')" class="vx-state manage {{ $state === 'manage' ? 'active' : '' }}">Manage</button>
                                                <button type="button" wire:click="setAccess(@js($class),'view')" class="vx-state view {{ $state === 'view' ? 'active' : '' }}">View Only</button>
                                                <button type="button" wire:click="setAccess(@js($class),'hidden')" class="vx-state hidden {{ $state === 'hidden' ? 'active' : '' }}">Hidden</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif
        </main>

        <aside class="vx-preview">
            <div class="vx-panel p-3">
                <div class="mb-3 flex items-center justify-between px-1">
                    <div><div class="text-xs font-bold">Live sidebar preview</div><div class="text-[10px] text-gray-400">{{ str($selectedRole)->headline() }}</div></div>
                    <span class="rounded-full bg-violet-50 px-2 py-1 text-[10px] font-bold text-violet-700 dark:bg-violet-950/40 dark:text-violet-300">Preview only</span>
                </div>
                <div class="vx-preview-shell">
                    <div class="vx-preview-brand"><div class="vx-preview-logo">V</div><div><div class="text-sm font-extrabold text-white">VortexOps</div><div class="text-[10px] text-slate-500">Operations</div></div></div>
                    @forelse($this->previewGroups as $group => $items)
                        @if(count($items))
                            <div class="vx-preview-group">
                                <div class="vx-preview-group-title">{{ $group }}</div>
                                @foreach($items as $item)
                                    <div class="vx-preview-item"><span class="vx-preview-dot"></span><span class="truncate">{{ $item['label'] }}</span>@if($item['state']==='view')<span class="vx-preview-readonly">View</span>@endif</div>
                                @endforeach
                            </div>
                        @endif
                    @empty
                        <div class="py-12 text-center text-xs text-slate-500">No visible navigation for this role.</div>
                    @endforelse
                </div>
            </div>
        </aside>
    </div>
</div>
</x-filament-panels::page>
