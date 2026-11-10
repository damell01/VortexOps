{{--
    Assign a show: a centered modal (a full-height sheet on phones) driven by
    Alpine, so it opens instantly. Buttons anywhere on the page dispatch
    `vxf-assign` with { runId, title, users, station } (or just { station }
    from an idle station, which starts by picking the show). Searching shows
    is a small data call; saving is one request that re-renders only the
    stations, queue and side panel.
--}}
<div x-data="{
        open: false, step: 'people', runId: null, title: '', users: [], station: null,
        pickedFromStation: false, search: '', shows: [], loading: false, saving: false, error: '', personQ: '',
        team: @js($this->team), stations: @js($this->stationChoices),
        start(d) {
            this.error = ''; this.personQ = ''; this.search = '';
            this.runId = d.runId ?? null; this.title = d.title ?? '';
            this.users = (d.users || []).map(Number); this.station = d.station ?? null;
            this.pickedFromStation = ! this.runId;
            this.step = this.runId ? 'people' : 'show';
            this.open = true;
            if (! this.runId) this.find();
            this.$nextTick(() => (this.step === 'show' ? this.$refs.showSearch : this.$refs.personSearch)?.focus());
        },
        async find() {
            this.loading = true;
            try { this.shows = await $wire.assignShows(this.search) } catch (e) { this.shows = [] } finally { this.loading = false }
        },
        pickShow(s) {
            this.runId = s.id; this.title = s.title; this.users = (s.users || []).map(Number);
            if (! this.station && s.station) this.station = s.station;
            this.step = 'people';
            this.$nextTick(() => this.$refs.personSearch?.focus());
        },
        has(id) { return this.users.includes(id) },
        toggle(id) { const i = this.users.indexOf(id); i < 0 ? this.users.push(id) : this.users.splice(i, 1) },
        people() { const q = this.personQ.trim().toLowerCase(); return this.team.filter(p => ! q || p.name.toLowerCase().includes(q)) },
        names() { return this.team.filter(p => this.users.includes(p.id)).map(p => p.name.split(' ')[0]).join(', ') },
        async save() {
            if (! this.runId || this.saving) return;
            this.saving = true; this.error = '';
            try {
                const r = await $wire.saveAssignment(this.runId, this.users, this.station);
                if (r && r.ok) this.open = false; else this.error = (r && r.message) || 'Could not save the assignment.';
            } catch (e) { this.error = 'Could not save the assignment. Try again.' } finally { this.saving = false }
        },
    }"
    x-on:vxf-assign.window="start($event.detail)"
    x-on:keydown.escape.window="if (open && $event.isTrusted) open = false"
    wire:ignore>
    <template x-if="open">
        <div class="vxf-modal" role="dialog" aria-modal="true" aria-labelledby="vxf-assign-title">
            <div class="vxf-modal__backdrop" x-on:click="open = false"></div>
            <div class="vxf-modal__card" x-trap.noscroll="open">
                <header class="vxf-modal__head">
                    <div style="min-width:0">
                        <p class="vxf-eyebrow" x-text="step === 'show' ? 'Assign a show' : 'Assign'"></p>
                        <h2 id="vxf-assign-title" x-text="step === 'show' ? 'Which show?' : title"></h2>
                    </div>
                    <button type="button" class="vxf-icon-btn" x-on:click="open = false" aria-label="Close"><x-heroicon-o-x-mark /></button>
                </header>

                {{-- Step 1 (from an idle station): pick a ready show --}}
                <div class="vxf-modal__body" x-show="step === 'show'">
                    <label class="vxf-search">
                        <x-heroicon-o-magnifying-glass />
                        <span class="sr-only">Search shows</span>
                        <input type="search" x-ref="showSearch" placeholder="Search shows ready to pack" x-model="search" x-on:input.debounce.250ms="find()" autocomplete="off">
                    </label>
                    <ul class="vxf-pickshow" x-bind:class="loading && 'vxf-dim'">
                        <template x-for="s in shows" :key="s.id">
                            <li><button type="button" x-on:click="pickShow(s)">
                                <strong x-text="s.title"></strong>
                                <span class="vxf-sub" x-text="[s.date, s.packages !== null ? s.packages + ' packages' : 'packages pending', s.users.length ? s.users.length + ' assigned' : 'unassigned'].filter(Boolean).join(' · ')"></span>
                                <x-heroicon-o-chevron-right />
                            </button></li>
                        </template>
                        <li x-show="! loading && ! shows.length" class="vxf-empty" x-text="search ? 'No ready shows match “' + search + '”.' : 'No shows are ready to pack right now.'"></li>
                    </ul>
                </div>

                {{-- Step 2: who packs it, and where --}}
                <div class="vxf-modal__body" x-show="step === 'people'">
                    <button type="button" class="vxf-linkbtn" x-show="pickedFromStation" x-on:click="step = 'show'; $nextTick(() => $refs.showSearch?.focus())"><x-heroicon-o-arrow-left /> Pick a different show</button>

                    <div class="vxf-pick__label"><strong>Packers</strong><span class="vxf-sub" x-text="users.length ? users.length + ' selected · ' + names() : 'Tap to choose one or more'"></span></div>
                    <label class="vxf-search" x-show="team.length > 6">
                        <x-heroicon-o-magnifying-glass />
                        <span class="sr-only">Search the team</span>
                        <input type="search" x-ref="personSearch" placeholder="Search the team" x-model="personQ" autocomplete="off">
                    </label>
                    <div class="vxf-pick" role="group" aria-label="Packers">
                        <template x-for="p in people()" :key="p.id">
                            <button type="button" class="vxf-pick__chip" x-bind:class="has(p.id) && 'is-on'" x-bind:aria-pressed="has(p.id) ? 'true' : 'false'" x-on:click="toggle(p.id)">
                                <span class="vxf-pick__avatar" x-text="p.initials"></span>
                                <span class="vxf-pick__name" x-text="p.name"></span>
                                <span class="vxf-pick__tick" aria-hidden="true"><x-heroicon-s-check-circle /></span>
                            </button>
                        </template>
                        <p class="vxf-sub" x-show="! people().length" style="margin:0">Nobody on the team matches.</p>
                    </div>

                    <div class="vxf-pick__label"><strong>Station</strong><span class="vxf-sub">Optional: the packer can choose when starting</span></div>
                    <div class="vxf-pick vxf-pick--stations" role="radiogroup" aria-label="Station">
                        <button type="button" class="vxf-pick__chip" x-bind:class="! station && 'is-on'" role="radio" x-bind:aria-checked="! station ? 'true' : 'false'" x-on:click="station = null">
                            <span class="vxf-pick__name">When starting</span>
                        </button>
                        <template x-for="st in stations" :key="st.id">
                            <button type="button" class="vxf-pick__chip" x-bind:class="station === st.id && 'is-on'" role="radio" x-bind:aria-checked="station === st.id ? 'true' : 'false'" x-on:click="station = st.id">
                                <span class="vxf-pick__name" x-text="st.name"></span>
                                <span class="vxf-pick__busy" x-show="st.busy" x-text="'Busy · ' + st.busy"></span>
                            </button>
                        </template>
                    </div>
                    <p class="vxf-flash vxf-flash--bad" role="alert" x-show="error" x-text="error" style="margin:0"></p>
                </div>

                <footer class="vxf-modal__foot" x-show="step === 'people'">
                    <span class="vxf-sub">Newly assigned people get a notification.</span>
                    <button type="button" class="vxf-btn" x-on:click="open = false">Cancel</button>
                    <button type="button" class="vxf-btn vxf-btn--primary" x-on:click="save()" x-bind:disabled="saving">
                        <span x-show="! saving">Save assignment</span><span x-show="saving">Saving…</span>
                    </button>
                </footer>
            </div>
        </div>
    </template>
</div>
