# VortexOps Payroll Structures & Pay Run Automation

This document is the implementation contract for the payroll changes discussed with operations/payroll. It describes how VortexOps should evolve without replacing the existing weekly Pay Run (`WeeklyPayoutBatch`) or the existing payout/formula engine.

## 1. Core model

A **Pay Run is weekly**. It is not one Whatnot show and should not be remodeled as one Pay Run per show.

- Individual Whatnot shows generate streamer payroll contributions.
- Fulfillment activity generates fulfillment payroll contributions.
- Those contributions roll into the existing weekly Pay Run.
- The existing Finalize → Submitted to ADP → Paid workflow stays intact.

Conceptually:

```text
STREAMER SHOWS ──> show-level calculations ──┐
                                             ├──> WEEKLY PAY RUN
FULFILLMENT ────> fulfillment calculations ──┘
```

## 2. Payment structures

Create two default payment structures:

1. **Streamer Payment Structure**
2. **Fulfillment Payment Structure**

The structures are defaults, not separate payroll systems. Continue using the existing payout types and formula evaluator.

### Inheritance

Effective compensation is resolved in this order:

```text
individual field override
        ↓
role/payment-structure default
        ↓
legacy/current member value when no structure has been configured
```

Do not clone an entire formula for a team member just because one rate differs. An employee can override only the rate/field that is different and continue inheriting the rest.

Examples:

- Streamer default profit share = 8%; Camden override = 10% → effective 10%.
- Fulfillment default hourly = $15, label rate = $0.25; employee overrides hourly = $17 → effective $17/hr + inherited $0.25/label.

The structure must support the existing fields where applicable:

- payout type
- payout cadence
- payout percentage / profit-share rate
- hourly rate
- package rate
- PWE rate
- label rate
- include tips
- custom payout formula
- burden-rate configuration when relevant

The UI must clearly display **Default**, **Override**, and **Effective** values and allow an admin to reset an override back to the team default.

## 3. Existing formula engine remains authoritative

Do not introduce a second formula engine.

The existing system already supports:

- profit share
- package
- hourly
- flat rate
- PWE + labels
- hybrid
- custom formula

The existing `ProfitShareFormula` also reproduces the payroll spreadsheet's streamer calculation:

```text
burden   = shipments × rate_per_shipment + hours × rate_per_hour
net rev  = gross revenue − product cost − burden
earnings = net rev × profit-share %
```

The signed-paperwork example is therefore:

```text
80 shipments × $2.10        = $168.00
4.45 hours × $80.00         = $356.00
Burden                      = $524.00
$7,371.10 − $3,392 − $524   = $3,455.10
$3,455.10 × 8%              = $276.41
```

Preserve this single source of truth.

## 4. Streamer show workflow

A show is a **calculation source**, not a Pay Run.

For each eligible Whatnot show make the following data available to payroll/formulas where it exists:

- streamer/team member
- show date/title
- gross revenue / total sales
- product cost
- sold and giveaway product cost where available
- hours worked
- shipments
- items sold
- giveaways
- tips
- configured/effective profit-share percentage or other rate
- calculated show earnings

### Product cost

Do not recreate the spreadsheet's Product Cost tab.

VortexOps Inventory and the existing show-order/inventory mapping are the source of truth. Show COGS should come from mapped inventory costs. Missing mappings or an unfiled show report must be visible as a payroll warning instead of being silently hidden.

Historical finalized payouts must continue displaying the inputs/costs/rates that were actually used at the time, even if current inventory cost or compensation settings later change.

### Weekly rollup

Calculate each show contribution first and then sum those show contributions into the weekly Pay Run. Do not replace show-level calculations with one giant weekly profit-share calculation.

The Pay Run review should let payroll see a per-show breakdown such as:

| Date | Show | Gross Revenue | Product Cost | Hours | Shipments | Earnings |
|---|---|---:|---:|---:|---:|---:|
| Mon | Show A | $7,371.10 | $3,392.00 | 4.45 | 80 | $276.41 |
| Wed | Show B | $5,100.00 | $2,200.00 | 3.75 | 61 | $205.00 |
| Fri | Show C | $8,450.00 | $3,600.00 | 5.25 | 92 | $330.00 |

Weekly streamer earnings in this example = `$811.41`.

## 5. Fulfillment workflow

Fulfillment is part of the same weekly Pay Run system but can use different formula inputs.

Existing data/rules may include:

- hours worked
- PWE count
- label count
- shipments/orders processed
- other tracked fulfillment metrics

Do not guess one universal fulfillment formula. Use the fulfillment payment structure plus individual overrides and the existing payout/formula engine. If an exact source metric is not yet reliably attributed to a fulfillment employee, flag/document the missing input rather than inventing attribution.

## 6. Automatic weekly Pay Runs

Pay Runs should set themselves up automatically.

### Required behavior

- Use the existing Monday–Sunday weekly batch model unless payroll settings explicitly change it later.
- Ensure the current/eligible weekly **Draft** Pay Run exists automatically.
- Generate/recalculate eligible draft show payouts and attach them to the correct weekly batch.
- Recalculate the batch total as source activity changes.
- Never duplicate a weekly batch or double-count a show/activity source when the job runs more than once.
- Never automatically mark a Pay Run Paid.
- Never mutate Finalized / Submitted / Paid historical calculations during automatic sync.

The scheduler should call the same application service used by an admin's manual **Recalculate Pay Run** action.

Suggested flow:

```text
pay period opens
      ↓
ensure weekly draft exists
      ↓
shows / fulfillment activity arrive
      ↓
recalculate draft sources
      ↓
validation / readiness check
      ↓
Ready for Review OR Needs Attention
      ↓
payroll reviews and finalizes
```

Readiness can be computed on top of the existing Draft status if adding more persisted statuses would duplicate the current state machine.

Warnings should include specific reasons such as:

- missing show report
- missing hours
- unmapped products / missing cost
- missing payment structure
- invalid formula / missing variable
- fulfillment counts unavailable or estimated
- duplicate/conflicting source activity

## 7. Pay Run automation settings

Add admin settings for at least:

- Automatic Pay Run Setup: on/off
- Auto-recalculate Draft Pay Runs: on/off
- Include active members with no activity: on/off (if supported safely)
- current fixed weekly cadence display (Monday–Sunday) until cadence configuration is intentionally generalized
- last successful automation run
- last automation error, if any

Do not make payroll automation depend on somebody visiting a page.

## 8. Backfill & validation

Add an admin-only historical Pay Run backfill/validation tool.

### Modes

1. **Dry Run / Preview** — default; no writes.
2. **Create Missing** — creates only missing eligible Draft records/batches.
3. **Recalculate Draft** — recalculates open Draft records only.
4. **Compare Finalized** — read-only comparison against Finalized / Submitted / Paid history.

A backfill must use the **same resolver and calculation services as live payroll**. No separate formula implementation is allowed.

### Filters

At minimum:

- From date
- To date
- Team type: All / Streamer / Fulfillment
- Optional team member filter where practical

### Comparison report

Show:

- pay period
- team member
- team type/payment structure
- source activity found
- existing amount
- calculated amount
- difference
- result
- warnings

Possible results:

- MATCH
- DIFFERENCE
- MISSING PAY RUN
- MISSING DATA
- FORMULA ERROR
- ACTIVITY MISMATCH

Every difference must be drillable to the calculation inputs.

### Historical safety

Backfill must never modify Finalized / Submitted / Paid payroll in normal operation. If historical data cannot be reproduced reliably, return a warning such as `HISTORICAL RATE UNKNOWN` instead of presenting an uncertain result as a match.

## 9. Snapshots / auditability

A finalized calculation must retain enough data to reproduce what payroll approved:

- payment structure/team type
- effective rates/parameters
- individual overrides applied
- formula or payout type used
- product cost
- hours
- shipments / PWE / label counts
- gross/net/burden inputs
- calculated earnings

Current payout rows already snapshot several calculation inputs; extend them only where information is missing. Reuse existing Spatie Activity Log / audit conventions for compensation and Pay Run changes.

## 10. UI requirements

### Settings → Payment Structures

Provide two clear cards:

- Streamer Payment Structure
- Fulfillment Payment Structure

Show their current default payout type/formula/rates and how many members inherit versus have custom overrides.

### Team member → Compensation

Show:

- team type
- inherited payment structure
- default values
- individual override values
- effective values
- reset-to-default action

### Pay Run detail

Rename streamer-only wording where the underlying record can represent fulfillment too. Show a clear weekly breakdown with relevant source metrics (show, gross, product cost, hours, shipments/PWE/labels, earnings), calculation notes, and readiness warnings.

### Backfill page

Provide Preview first, then explicitly confirmed write actions for Create Missing / Recalculate Draft. Clearly state that finalized historical payroll is read-only.

## 11. Safety / implementation rules

1. Keep `WeeklyPayoutBatch` as the weekly Pay Run.
2. Keep the existing payout/formula engine and `ProfitShareFormula` as calculation authorities.
3. Default payment structures belong to Streamer and Fulfillment roles; individuals only store differences.
4. Existing people's effective compensation must not change during migration.
5. Automatic sync and backfill must be idempotent.
6. Drafts can recalculate; finalized/paid records are immutable in normal operation.
7. Backfill Preview performs no writes.
8. Use existing Inventory and mapping; do not recreate Product Cost inventory.
9. Do not create one Pay Run per show.
10. Do not assume all fulfillment workers use the same hourly/per-label combination; configuration decides.
11. All automatic/manual/backfill calculations must call the same calculation services.
12. Add tests around inheritance, overrides, automatic weekly setup, duplicate protection, historical immutability, and dry-run backfill.

## 12. Rollout order

1. Payment structures + effective compensation resolver.
2. Individual field overrides and migration safety.
3. Calculation snapshots / historical immutability.
4. Streamer show rollup UX and fulfillment-compatible labels.
5. Idempotent Pay Run sync service + manual recalc action.
6. Scheduler-driven automatic setup/recalculation.
7. Backfill Preview / comparison report.
8. Create Missing / Recalculate Draft backfill modes.
9. Validate several historical weeks before enabling production automation.
10. Capture/update Playwright screenshots and README documentation.
