# UI redesign — page inventory

Generated from the code (Filament pages, resource pages, Livewire components).

Status:
- `done` — the screen's own layout was reviewed against the mockups and changed where needed.
- `system` — the app-wide passes below are applied; the screen's own layout has not been individually reviewed yet.

App-wide passes applied to every screen:
1. Light mode by default (a saved theme choice is respected); dark mode kept.
2. Design-system layer: brand tokens for light and dark; shared page header, cards, tables, tabs (purple pills), badges, buttons, inputs, dropdowns, modals.
3. Density: page padding 32px -> 20/24px (12px on phones); padding, gaps and headings one step smaller in 91 views; compact empty states; 24px report titles.
4. Dark mode: white cards and near-black text without dark variants fixed.
5. Popovers capped at the screen width (Filament and custom panels).
6. Accessibility: icon-only buttons labelled; light-mode secondary text raised to AA contrast (361 elements); reduced motion respected.
7. Buttons in the mockup style: gradient primary, tinted secondary colours, bordered gray; solid buttons kept in modal footers.
8. Reports and workflow screens use the same palette tokens; stat tiles (report tiles and Filament stat widgets) with tinted icon chips.
9. Top navigation: white bar in light mode with a purple pill for hover and the current section; logos on a navy chip; navy bar kept in dark mode.
Total: 209


## Dashboard

| Status | Screen | Kind | View |
|---|---|---|---|
| done | DashboardImproved | Page | `filament.pages.dashboard-improved` |
| system | HorizonDashboard | Page | `filament.pages.horizon-dashboard` |

## Fulfillment

| Status | Screen | Kind | View |
|---|---|---|---|
| system | FulfillmentDashboard | Livewire | `livewire.fulfillment-dashboard` |
| system | FulfillmentCenter | Page | `filament.pages.fulfillment-center` |
| system | ShowShipments | Page | `filament.pages.show-shipments` |
| system | FulfillmentPackages › CreateFulfillmentPackage | Resource page | `(Filament default)` |
| system | FulfillmentPackages › EditFulfillmentPackage | Resource page | `(Filament default)` |
| system | FulfillmentPackages › ListFulfillmentPackages | Resource page | `(Filament default)` |
| system | FulfillmentResource › ListFulfillmentShows | Resource page | `filament.resources.fulfillment-resource.pages.list-fulfillment-shows` |
| system | FulfillmentResource › ViewFulfillmentShow | Resource page | `filament.resources.fulfillment-resource.pages.view-fulfillment-show` |
| system | ShipmentResource › ListShipments | Resource page | `(Filament default)` |

## Inventory

| Status | Screen | Kind | View |
|---|---|---|---|
| system | BarcodeScanner | Livewire | `livewire.barcode-scanner` |
| system | InventoryArrivalFeed | Livewire | `livewire.inventory-arrival-feed` |
| system | InventoryDashboard | Livewire | `livewire.inventory-dashboard` |
| system | InventoryItemMapper | Livewire | `livewire.inventory-item-mapper` |
| system | InventoryPriceAcknowledgment | Livewire | `livewire.inventory-price-acknowledgment-safe` |
| system | InventoryQuickStockModal | Livewire | `livewire.inventory-quick-stock-modal` |
| system | InventoryRecentChanges | Livewire | `livewire.inventory-recent-changes` |
| system | InventorySelectorModal | Livewire | `livewire.inventory-selector-modal` |
| system | ManifestLinesSummary | Livewire | `livewire.manifest-lines-summary` |
| system | ArchivedPallets | Page | `filament.pages.archived-pallets` |
| system | BarcodePrinter | Page | `filament.pages.barcode-printer` |
| system | DuplicateProductDetector | Page | `filament.pages.duplicate-product-detector` |
| system | ImportInventorySheet | Page | `filament.pages.import-inventory-sheet` |
| system | InventoryActivity | Page | `filament.pages.inventory-activity` |
| system | InventoryAge | Page | `filament.pages.inventory-age` |
| system | InventoryAnalytics | Page | `filament.pages.inventory-analytics` |
| system | InventoryCatalog | Page | `filament.pages.inventory-catalog` |
| system | InventoryCount | Page | `filament.pages.inventory-count` |
| system | InventoryGuide | Page | `filament.pages.inventory-guide` |
| system | InventoryHealth | Page | `filament.pages.inventory-health` |
| system | InventoryImporter | Page | `filament.pages.inventory-importer` |
| system | InventoryOverview | Page | `filament.pages.inventory-overview` |
| system | InventoryPopupSettings | Page | `filament.pages.inventory-popup-settings` |
| system | InventoryReconciliation | Page | `filament.pages.inventory-reconciliation` |
| system | InventoryReport | Page | `filament.pages.inventory-report` |
| system | InventoryScanner | Page | `filament.pages.inventory-scanner-enhanced` |
| system | InventorySearch | Page | `filament.pages.inventory-search` |
| system | InventoryValueDashboard | Page | `filament.pages.inventory-value-dashboard` |
| system | InventoryVelocityAnalytics | Page | `filament.pages.inventory-velocity-analytics` |
| system | PalletReceivingHistory | Page | `filament.pages.pallet-receiving-history` |
| system | PalletStatusDashboard | Page | `filament.pages.pallet-status-dashboard` |
| system | ProductHealthDashboard | Page | `filament.pages.product-health-dashboard` |
| system | ProductInsights | Page | `filament.pages.product-insights` |
| system | QuickAddStock | Page | `filament.pages.quick-add-stock` |
| system | ReceivingAnalytics | Page | `filament.pages.receiving-analytics` |
| system | ReceivingSessionsHistory | Page | `filament.pages.receiving-sessions-history` |
| system | StockStatus | Page | `filament.pages.stock-status` |
| system | StockTransfer | Page | `filament.pages.stock-transfer` |
| system | InventoryItemResource › CreateInventoryItem | Resource page | `filament.pages.create-inventory-item-wrapper` |
| system | InventoryItemResource › EditInventoryItem | Resource page | `filament.resources.inventory-item-resource.pages.edit-inventory-item` |
| system | InventoryItemResource › ListInventoryItems | Resource page | `filament.resources.inventory-item-resource.pages.list-inventory-items` |
| system | InventoryItemResource › ManageStock | Resource page | `filament.resources.inventory-item-resource.pages.manage-stock` |
| system | InventoryItemResource › QuickAddInventoryItem | Resource page | `filament.pages.quick-add-inventory-item` |
| done | InventoryItemResource › ViewInventoryItem | Resource page | `filament.resources.inventory-item-resource.pages.view-inventory-item` |
| system | InventoryLocationResource › CreateInventoryLocation | Resource page | `(Filament default)` |
| system | InventoryLocationResource › EditInventoryLocation | Resource page | `(Filament default)` |
| system | InventoryLocationResource › ListInventoryLocations | Resource page | `(Filament default)` |
| system | InventoryLocationResource › ViewInventoryLocation | Resource page | `(Filament default)` |
| system | InventoryMovementResource › ListInventoryMovements | Resource page | `(Filament default)` |
| system | InventoryMovementResource › ViewInventoryMovement | Resource page | `filament.resources.inventory-movement-resource.pages.view-inventory-movement` |
| system | InventoryStockResource › EditInventoryStock | Resource page | `(Filament default)` |
| system | InventoryStockResource › ListInventoryStock | Resource page | `(Filament default)` |
| system | PalletResource › AddPalletLines | Resource page | `filament.resources.pallet-resource.pages.add-pallet-lines` |
| system | PalletResource › CreatePallet | Resource page | `(Filament default)` |
| system | PalletResource › EditPallet | Resource page | `(Filament default)` |
| system | PalletResource › ImportManifest | Resource page | `filament.pages.import-manifest` |
| system | PalletResource › ListPallets | Resource page | `(Filament default)` |
| system | PalletResource › PalletItems | Resource page | `filament.resources.pallet-resource.pages.pallet-items` |
| system | PalletResource › ReceivePallet | Resource page | `filament.pages.receive-pallet` |
| system | PalletResource › StagePallet | Resource page | `filament.pages.stage-pallet` |
| system | PalletResource › ViewPallet | Resource page | `filament.pages.view-pallet` |
| system | ProductIdentityResource › ListProductIdentities | Resource page | `(Filament default)` |
| system | ReceivingSessionResource › CreateReceivingSession | Resource page | `(Filament default)` |
| system | ReceivingSessionResource › ListReceivingSessions | Resource page | `(Filament default)` |
| system | ReceivingSessionResource › ReviewReceivingSession | Resource page | `filament.resources.receiving-session-resource.pages.review-receiving-session` |
| system | ShowResource › ShowInventoryBreakdown | Resource page | `filament.pages.show-inventory-breakdown` |
| system | VendorResource › CreateVendor | Resource page | `(Filament default)` |
| system | VendorResource › EditVendor | Resource page | `(Filament default)` |
| system | VendorResource › ListVendors | Resource page | `(Filament default)` |
| system | VendorResource › ViewVendor | Resource page | `(Filament default)` |

## Other

| Status | Screen | Kind | View |
|---|---|---|---|
| system | AnnouncementPreview | Livewire | `livewire.announcement-preview` |
| system | ChannelSwitcher | Livewire | `livewire.channel-switcher` |
| system | ClockWidget | Livewire | `livewire.assistant-clock` |
| system | FeedbackWidget | Livewire | `livewire.feedback-widget` |
| system | HiddenSidebar | Livewire | `filament.components.hidden-sidebar` |
| system | ItemSelectionModal | Livewire | `livewire.item-selection-modal` |
| system | AiMonitoring | Page | `filament.pages.ai-monitoring` |
| system | AiOperations | Page | `filament.pages.ai-operations` |
| system | Communications | Page | `filament.pages.communications` |
| system | CostAdjustmentHistory | Page | `filament.pages.cost-adjustment-history` |
| system | DemoData | Page | `filament.pages.demo-data` |
| system | EditProfile | Page | `filament.pages.edit-profile` |
| system | GettingStarted | Page | `filament.pages.getting-started` |
| system | Handbook | Page | `filament.pages.handbook` |
| system | HelpCenter | Page | `filament.pages.help-center` |
| system | HowItWorks | Page | `filament.pages.how-it-works` |
| system | LogViewer | Page | `filament.pages.log-viewer` |
| system | ManagerHub | Page | `filament.pages.manager-hub` |
| system | MyPay | Page | `filament.pages.my-pay` |
| system | MyPokedex | Page | `filament.pages.my-pokedex` |
| system | QuickAddContainerScan | Page | `filament.pages.quick-add-container-scan` |
| system | SystemHealth | Page | `filament.pages.system-health` |
| system | TwoFactorAuth | Page | `filament.pages.two-factor-auth` |
| system | TwoFactorVerify | Page | `filament.pages.two-factor-verify` |
| system | ActivityLogResource › ListActivityLogs | Resource page | `(Filament default)` |
| system | ActivityLogResource › ViewActivityLog | Resource page | `(Filament default)` |
| system | DeductionRequestResource › ListDeductionRequests | Resource page | `(Filament default)` |
| system | DeductionRequestResource › ViewDeductionRequest | Resource page | `(Filament default)` |
| system | FeatureFlagResource › EditFeatureFlag | Resource page | `(Filament default)` |
| system | FeatureFlagResource › ListFeatureFlags | Resource page | `(Filament default)` |
| system | FeedbackTicketResource › EditFeedbackTicket | Resource page | `(Filament default)` |
| system | FeedbackTicketResource › ListFeedbackTickets | Resource page | `(Filament default)` |
| system | FeedbackTicketResource › ViewFeedbackTicket | Resource page | `(Filament default)` |
| system | ShippingSurchargeResource › ListShippingSurcharges | Resource page | `(Filament default)` |

## Owner tools

| Status | Screen | Kind | View |
|---|---|---|---|
| system | AppSettings | Page | `filament.pages.app-settings` |
| system | EmailLog | Page | `filament.pages.email-log` |
| system | ImportStatus | Page | `filament.pages.import-status` |
| system | MyNotifications | Page | `filament.pages.my-notifications` |
| system | NavigationManager | Page | `filament.pages.navigation-manager` |
| system | NotificationSettings | Page | `filament.pages.notification-settings` |
| system | WhatnotApiChanges | Page | `filament.pages.whatnot-api-changes` |
| system | WhatnotApiDashboard | Page | `filament.pages.whatnot-api-dashboard` |
| system | WhatnotBackfill | Page | `filament.pages.whatnot-backfill` |
| system | WhatnotScraperPage | Page | `filament.pages.whatnot-scraper` |
| system | WhatnotSheetCheck | Page | `filament.pages.whatnot-sheet-check` |
| system | WhatnotSyncPage | Page | `filament.pages.whatnot-sync` |
| system | RoleResource › CreateRole | Resource page | `(Filament default)` |
| system | RoleResource › EditRole | Resource page | `filament.resources.role-resource.pages.edit-role` |
| system | RoleResource › ListRoles | Resource page | `filament.resources.role-resource.pages.list-roles` |
| system | UserResource › CreateUser | Resource page | `(Filament default)` |
| system | UserResource › EditUser | Resource page | `(Filament default)` |
| system | UserResource › ListUsers | Resource page | `filament.resources.user-resource.pages.list-users` |
| system | UserResource › ViewUser | Resource page | `(Filament default)` |
| system | WhatnotChannelResource › CreateWhatnotChannel | Resource page | `(Filament default)` |
| system | WhatnotChannelResource › EditWhatnotChannel | Resource page | `(Filament default)` |
| system | WhatnotChannelResource › ListWhatnotChannels | Resource page | `(Filament default)` |
| system | WhatnotChannelResource › ViewWhatnotChannel | Resource page | `(Filament default)` |

## Payroll

| Status | Screen | Kind | View |
|---|---|---|---|
| system | BusinessSwitcher | Livewire | `livewire.business-switcher` |
| system | Businesses | Page | `filament.pages.businesses` |
| system | PayRunBackfill | Page | `filament.pages.pay-run-backfill` |
| system | PaymentStructures | Page | `filament.pages.payment-structures` |
| system | PayrollOverview | Page | `filament.pages.payroll-overview` |
| system | PayrollSimulator | Page | `filament.pages.payroll-simulator` |
| system | PayoutResource › ListPayouts | Resource page | `filament.resources.payout-resource.pages.list-payouts` |
| system | PayoutResource › ViewPayout | Resource page | `filament.resources.payout-resource.pages.view-payout` |
| system | StreamerLoanResource › CreateStreamerLoan | Resource page | `(Filament default)` |
| system | StreamerLoanResource › EditStreamerLoan | Resource page | `(Filament default)` |
| system | StreamerLoanResource › ListStreamerLoans | Resource page | `(Filament default)` |
| system | StreamerLoanResource › ViewStreamerLoan | Resource page | `(Filament default)` |
| system | WeeklyPayoutBatchResource › CreateWeeklyPayoutBatch | Resource page | `(Filament default)` |
| system | WeeklyPayoutBatchResource › EditWeeklyPayoutBatch | Resource page | `(Filament default)` |
| system | WeeklyPayoutBatchResource › ListWeeklyPayoutBatches | Resource page | `(Filament default)` |
| system | WeeklyPayoutBatchResource › ViewWeeklyPayoutBatch | Resource page | `filament.resources.weekly-payout-batch-resource.pages.view-pay-run-workspace` |

## Reports

| Status | Screen | Kind | View |
|---|---|---|---|
| system | ManagerProfitShareDashboard | Livewire | `livewire.manager-profit-share-dashboard` |
| system | StreamerProfitShareDashboard | Livewire | `livewire.streamer-profit-share-dashboard-v2` |
| system | ManagerProfitShare | Page | `filament.pages.manager-profit-share` |
| system | ProfitLoss | Page | `filament.pages.profit-loss` |
| system | ProfitSharePacket | Page | `filament.pages.profit-share-packet` |
| system | Reports | Page | `filament.pages.reports` |
| system | ReportsOverview | Page | `filament.pages.reports-overview` |
| system | ShowPerformance | Page | `filament.pages.show-performance` |
| system | StreamerAnalytics | Page | `filament.pages.streamer-analytics` |
| system | StreamerProfitShare | Page | `filament.pages.streamer-profit-share` |
| system | WhatnotLosses | Page | `filament.pages.whatnot-losses` |
| system | LedgerResource › ListLedgerEntries | Resource page | `(Filament default)` |
| system | MissingItemReportResource › CreateMissingItemReport | Resource page | `(Filament default)` |
| system | MissingItemReportResource › EditMissingItemReport | Resource page | `(Filament default)` |
| system | MissingItemReportResource › ListMissingItemReports | Resource page | `(Filament default)` |
| system | WhatnotLedgerResource › LedgerTips | Resource page | `filament.resources.whatnot-ledger-resource.pages.ledger-tips` |
| system | WhatnotLedgerResource › ListWhatnotLedgerEntries | Resource page | `filament.resources.whatnot-ledger-resource.pages.list-whatnot-ledger-entries` |

## Shows

| Status | Screen | Kind | View |
|---|---|---|---|
| system | AdminMatchShowItem | Livewire | `livewire.admin-match-show-item` |
| system | CreateManualShow | Livewire | `livewire.create-manual-show` |
| system | EndOfStreamForm | Livewire | `livewire.end-of-stream-form` |
| system | ShowItemsModal | Livewire | `livewire.show-items-modal` |
| system | EndOfStreamForm | Page | `filament.pages.end-of-stream-items` |
| system | NoShowCleanup | Page | `filament.pages.no-show-cleanup` |
| system | ShowDataAudit | Page | `filament.pages.show-data-audit` |
| system | ShowFormatComparison | Page | `filament.pages.show-format-comparison` |
| system | ShowLeaderboard | Page | `filament.pages.show-leaderboard` |
| system | ShowStatusBoard | Page | `filament.pages.show-status-board` |
| system | Shows | Page | `filament.pages.shows` |
| system | StreamWorkflow | Page | `filament.pages.stream-workflow` |
| system | StreamsOverview | Page | `filament.pages.streams-overview` |
| system | ShowIngestionLogResource › ListShowIngestionLogs | Resource page | `filament.resources.show-ingestion-log-resource.pages.list-show-ingestion-logs` |
| system | ShowIngestionLogResource › ViewShowIngestionLog | Resource page | `(Filament default)` |
| system | ShowResource › AddShowItems | Resource page | `filament.pages.add-show-items` |
| system | ShowResource › CreateShow | Resource page | `filament.resources.show-resource.pages.create-show` |
| system | ShowResource › EditShow | Resource page | `filament.resources.show-resource.pages.edit-show` |
| system | ShowResource › ListShows | Resource page | `(Filament default)` |
| system | ShowResource › ReviewShowChannels | Resource page | `(Filament default)` |
| system | ShowResource › ViewShow | Resource page | `filament.resources.show-resource.pages.view-show-workspace` |

## Streamers

| Status | Screen | Kind | View |
|---|---|---|---|
| system | ShowStreamerLogPanel | Livewire | `livewire.show-streamer-log-panel` |
| system | StreamerHome | Livewire | `livewire.streamer-home` |
| system | StreamerLeaderboard | Livewire | `livewire.streamer-leaderboard` |
| system | StreamerLogItemsModal | Livewire | `livewire.streamer-log-items-modal` |
| system | StreamerLogTabs | Livewire | `livewire.streamer-log-tabs` |
| system | StreamerHub | Page | `filament.pages.streamer-hub` |
| system | StreamerStatement | Page | `filament.pages.streamer-statement` |
| system | ShowResource › ReviewShowStreamers | Resource page | `filament.resources.show-resource.pages.stream-review` |
| system | StreamerLogResource › EditStreamerLogEntry | Resource page | `filament.resources.streamer-log-resource.pages.edit-streamer-log-entry` |
| system | StreamerLogResource › ListStreamerLogEntries | Resource page | `filament.resources.streamer-log-resource.pages.list-streamer-log-entries` |
| system | StreamerLogResource › ViewStreamerLogEntry | Resource page | `filament.resources.streamer-log-resource.pages.view-streamer-log-entry` |
| system | StreamerResource › CreateStreamer | Resource page | `(Filament default)` |
| system | StreamerResource › EditStreamer | Resource page | `(Filament default)` |
| system | StreamerResource › ListStreamers | Resource page | `(Filament default)` |
| system | StreamerResource › ViewStreamer | Resource page | `filament.resources.streamer-resource.pages.view-streamer-workspace` |

## Timekeeping

| Status | Screen | Kind | View |
|---|---|---|---|
| system | TimeAttendance | Page | `filament.pages.time-attendance` |
| system | Timekeeping | Page | `filament.pages.timekeeping` |
