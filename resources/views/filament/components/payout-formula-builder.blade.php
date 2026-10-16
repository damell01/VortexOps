<div class="vx-formula" data-vx-formula x-data="vxPayoutFormulaBuilder($wire.entangle('{{ $model }}'), $wire, @js(\App\Support\PayoutFormula::FIELDS), @js(\App\Support\PayoutFormula::samples()))">
    <h3>Build a payout formula</h3>
    <p>Click fields and operators to build the calculation for one person on one show. You can also type directly.</p>
    <label class="mt-3">Find a field<input type="search" x-model="search" placeholder="Search revenue, hours, shipments…" autocomplete="off"></label>
    <div class="vx-formula-fields" aria-label="Available formula fields">
        <template x-for="[key, field] in visibleFields" :key="key">
            <button type="button" @click="insert(key)" :title="field[1]" x-text="field[0]"></button>
        </template>
    </div>
    <label>Formula<textarea x-ref="formula" x-model="formula" rows="3" maxlength="1000" spellcheck="false" autocomplete="off" placeholder="streamer_share_net * (payout_percentage / 100)"></textarea></label>
    @error($model)<p class="vx-formula-error" role="alert">{{ $message }}</p>@enderror
    <div class="vx-formula-toolbar" aria-label="Formula operators">
        <template x-for="[value, label] in [['+','+'],['-','−'],['*','×'],['/','÷'],['(','('],[')',')']]" :key="value"><button type="button" @click="insert(value)" :aria-label="'Insert ' + label" x-text="label"></button></template>
        <div><label class="sr-only">Percentage</label><input aria-label="Percentage" type="number" min="0" max="100" step="any" x-model="percentage"><button type="button" @click="insertPercentage">Insert %</button></div>
        <div><input aria-label="Number" type="number" step="any" x-model="number"><button type="button" @click="insertNumber">Insert number</button></div>
    </div>
    <p>Percentages insert as ÷ 100. For example: Net × (8 ÷ 100) means 8% of net.</p>
    <div class="vx-formula-example"><select aria-label="Formula example" x-model="example"><option value="">Choose an example…</option><template x-for="(_, name) in examples" :key="name"><option :value="name" x-text="name"></option></template></select><button type="button" :disabled="!example" @click="formula = examples[example]">Replace with example</button></div>
    <details open>
        <summary>Sample values — for preview only</summary>
        <p>These are editable examples, not live payroll data. Only fields used in your formula appear here.</p>
        <div class="vx-formula-samples"><template x-for="[key, field] in usedFields" :key="key"><label :title="field[1]"><span x-text="field[0]"></span><input type="number" step="any" :aria-label="'Sample ' + field[0]" x-model="samples[key]"></label></template></div>
    </details>
    <div class="vx-formula-preview"><button type="button" @click="preview" :disabled="busy || !(formula || '').trim()" x-text="busy ? 'Calculating…' : 'Preview calculation'"></button><output x-cloak x-show="result !== null" x-text="result" aria-live="polite"></output><span class="vx-formula-error" x-cloak x-show="error" x-text="error" role="alert"></span></div>
    <p class="vx-formula-note">Preview shows the formula subtotal. Existing owner fees, monthly tip separation and any additional in-house hourly pay still apply. Include tips explicitly in the formula when required. Save the team rates or individual override to apply it; preview saves nothing.</p>
</div>
