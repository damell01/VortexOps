window.vxPayoutFormulaBuilder = (formula, wire, fields, samples) => ({
    formula, fields, samples, search: '', percentage: 8, number: 10, result: null, error: '', busy: false, example: '',
    examples: {
        'Net percentage': 'streamer_share_net * (payout_percentage / 100)',
        'Gross less product cost': '(gross_revenue - product_cost) * (payout_percentage / 100)',
        'Hours and shipping': 'hours_worked * hourly_rate + pwe_count * pwe_rate + label_count * label_rate',
        'Net percentage and tips': 'streamer_share_net * (payout_percentage / 100) + tip_share',
    },
    init() {
        this.$watch('formula', () => { this.result = null; this.error = ''; });
        this.$watch('samples', () => { this.result = null; this.error = ''; });
    },
    get visibleFields() {
        return Object.entries(this.fields).filter(([key, value]) => `${key} ${value[0]}`.toLowerCase().includes(this.search.toLowerCase()));
    },
    get usedFields() {
        const names = new Set((this.formula || '').match(/[A-Za-z_]\w*/g) || []);
        return Object.entries(this.fields).filter(([key]) => names.has(key));
    },
    insert(value) {
        const input = this.$refs.formula;
        const start = input.selectionStart ?? (this.formula || '').length;
        const end = input.selectionEnd ?? start;
        const text = this.formula || '';
        const inserted = ` ${value} `;
        this.formula = text.slice(0, start) + inserted + text.slice(end);
        this.$nextTick(() => { input.focus(); input.setSelectionRange(start + inserted.length, start + inserted.length); });
    },
    insertPercentage() {
        const value = Number(this.percentage);
        if (String(this.percentage).trim() === '' || !Number.isFinite(value) || value < 0 || value > 100) { this.error = 'Enter a percentage from 0 to 100.'; return; }
        this.insert(`(${this.literal(value)} / 100)`);
    },
    insertNumber() {
        const value = Number(this.number);
        if (String(this.number).trim() === '' || !Number.isFinite(value) || Math.abs(value) > 1e12) { this.error = 'Enter a valid number.'; return; }
        this.insert(value < 0 ? `(0 - ${this.literal(Math.abs(value))})` : this.literal(value));
    },
    literal(value) {
        const text = String(value);
        if (!text.includes('e')) return text;
        const [mantissa, exponent] = text.split('e');
        const [whole, fraction = ''] = mantissa.split('.');
        const digits = whole + fraction;
        const point = whole.length + Number(exponent);
        if (point <= 0) return '0.' + '0'.repeat(-point) + digits;
        if (point >= digits.length) return digits + '0'.repeat(point - digits.length);
        return digits.slice(0, point) + '.' + digits.slice(point);
    },
    async preview() {
        if (this.busy) return;
        this.busy = true; this.error = ''; this.result = null;
        const formula = this.formula || '';
        const samples = JSON.parse(JSON.stringify(this.samples));
        try {
            const response = await wire.previewPayoutFormula(formula, samples);
            if (this.formula !== formula || JSON.stringify(this.samples) !== JSON.stringify(samples)) return;
            if (response.error) this.error = response.error;
            else this.result = new Intl.NumberFormat('en-US', {style: 'currency', currency: 'USD'}).format(response.amount);
        } catch (error) {
            this.error = 'Could not preview. Check the formula and sample values, then try again.';
        } finally { this.busy = false; }
    },
});
