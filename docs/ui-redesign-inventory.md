# UI redesign — page inventory

Generated from the code (Filament pages, resource pages, Livewire components). Status: `todo` → `done` (on the new design system; checked) or `n/a` (no UI / redirect).

Phase 1 (light default + design-system layer) applies to every screen that uses Filament sections, tables, tabs, badges and the --vx tokens; `done` below means the screen's own layout has also been reviewed against the mockups.

Total: 209


## Dashboard

| Status | Screen | Kind | View |
|---|---|---|---|
| done | DashboardImproved | Page | `filament.pages.dashboard-improved` |
| todo | HorizonDashboard | Page | `filament.pages.horizon-dashboard` |

## Fulfillment

| Status | Screen | Kind | View |
|---|---|---|---|
| todo | FulfillmentDashboard | Livewire | `livewire.fulfillment-dashboard` |
| todo | FulfillmentCenter | Page | `filament.pages.fulfillment-center` |
| todo | ShowShipments | Page | `filament.pages.show-shipments` |
| todo | FulfillmentPackages › CreateFulfillmentPackage | Resource page | `(Filament default)` |
| todo | FulfillmentPackages › EditFulfillmentPackage | Resource page | `(Filament default)` |
| todo | FulfillmentPackages › ListFulfillmentPackages | Resource page | `(Filament default)` |
| todo | FulfillmentResource › ListFulfillmentShows | Resource page | `filament.resources.fulfillment-resource.pages.list-fulfillment-shows` |
| todo | FulfillmentResource › ViewFulfillmentShow | Resource page | `filament.resources.fulfillment-resource.pages.view-fulfillment-show` |
| todo | ShipmentResource › ListShipments | Resource page | `(Filament default)` |

## Inventory

| Status | Screen | Kind | View |
|---|---|---|---|
| todo | BarcodeScanner | Livewire | `livewire.barcode-scanner` |
| todo | InventoryArrivalFeed | Livewire | `livewire.inventory-arrival-feed` |
| todo | InventoryDashboard | Livewire | `livewire.inventory-dashboard` |
| todo | InventoryItemMapper | Livewire | `livewire.inventory-item-mapper` |
| todo | InventoryPriceAcknowledgment | Livewire | `livewire.inventory-price-acknowledgment-safe` |
| todo | InventoryQuickStockModal | Livewire | `livewire.inventory-quick-stock-modal` |
| todo | InventoryRecentChanges | Livewire | `livewire.inventory-recent-changes` |
| todo | InventorySelectorModal | Livewire | `livewire.inventory-selector-modal` |
| todo | ManifestLinesSummary | Livewire | `livewire.manifest-lines-summary` |
| todo | ArchivedPallets | Page | `filament.pages.archived-pallets` |
| todo | BarcodePrinter | Page | `filament.pages.barcode-printer` |
| todo | DuplicateProductDetector | Page | `filament.pages.duplicate-product-detector` |
| todo | ImportInventorySheet | Page | `filament.pages.import-inventory-sheet` |
| todo | InventoryActivity | Page | `filament.pages.inventory-activity` |
| todo | InventoryAge | Page | `filament.pages.inventory-age` |
| todo | InventoryAnalytics | Page | `filament.pages.inventory-analytics` |
| todo | InventoryCatalog | Page | `filament.pages.inventory-catalog` |
| todo | InventoryCount | Page | `filament.pages.inventory-count` |
| todo | InventoryGuide | Page | `filament.pages.inventory-guide` |
| todo | InventoryHealth | Page | `filament.pages.inventory-health` |
| todo | InventoryImporter | Page | `filament.pages.inventory-importer` |
| todo | InventoryOverview | Page | `filament.pages.inventory-overview` |
| todo | InventoryPopupSettings | Page | `filament.pages.inventory-popup-settings` |
| todo | InventoryReconciliation | Page | `filament.pages.inventory-reconciliation` |
| todo | InventoryReport | Page | `filament.pages.inventory-report` |
| todo | InventoryScanner | Page | `filament.pages.inventory-scanner-enhanced` |
| todo | InventorySearch | Page | `filament.pages.inventory-search` |
| todo | InventoryValueDashboard | Page | `filament.pages.inventory-value-dashboard` |
| todo | InventoryVelocityAnalytics | Page | `filament.pages.inventory-velocity-analytics` |
| todo | PalletReceivingHistory | Page | `filament.pages.pallet-receiving-history` |
| todo | PalletStatusDashboard | Page | `filament.pages.pallet-status-dashboard` |
| todo | ProductHealthDashboard | Page | `filament.pages.product-health-dashboard` |
| todo | ProductInsights | Page | `filament.pages.product-insights` |
| todo | QuickAddStock | Page | `filament.pages.quick-add-stock` |
| todo | ReceivingAnalytics | Page | `filament.pages.receiving-analytics` |
| todo | ReceivingSessionsHistory | Page | `filament.pages.receiving-sessions-history` |
| todo | StockStatus | Page | `filament.pages.stock-status` |
| todo | StockTransfer | Page | `filament.pages.stock-transfer` |
| todo | InventoryItemResource › CreateInventoryItem | Resource page | `filament.pages.create-inventory-item-wrapper` |
| todo | InventoryItemResource › EditInventoryItem | Resource page | `filament.resources.inventory-item-resource.pages.edit-inventory-item` |
| todo | InventoryItemResource › ListInventoryItems | Resource page | `filament.resources.inventory-item-resource.pages.list-inventory-items` |
| todo | InventoryItemResource › ManageStock | Resource page | `filament.resources.inventory-item-resource.pages.manage-stock` |
| todo | InventoryItemResource › QuickAddInventoryItem | Resource page | `filament.pages.quick-add-inventory-item` |
| done | InventoryItemResource › ViewInventoryItem | Resource page | `filament.resources.inventory-item-resource.pages.view-inventory-item` |
| todo | InventoryLocationResource › CreateInventoryLocation | Resource page | `(Filament default)` |
| todo | InventoryLocationResource › EditInventoryLocation | Resource page | `(Filament default)` |
| todo | InventoryLocationResource › ListInventoryLocations | Resource page | `(Filament default)` |
| todo | InventoryLocationResource › ViewInventoryLocation | Resource page | `(Filament default)` |
| todo | InventoryMovementResource › ListInventoryMovements | Resource page | `(Filament default)` |
| todo | InventoryMovementResource › ViewInventoryMovement | Resource page | `filament.resources.inventory-movement-resource.pages.view-inventory-movement` |
| todo | InventoryStockResource › EditInventoryStock | Resource page | `(Filament default)` |
| todo | InventoryStockResource › ListInventoryStock | Resource page | `(Filament default)` |
| todo | PalletResource › AddPalletLines | Resource page | `filament.resources.pallet-resource.pages.add-pallet-lines` |
| todo | PalletResource › CreatePallet | Resource page | `(Filament default)` |
| todo | PalletResource › EditPallet | Resource page | `(Filament default)` |
| todo | PalletResource › ImportManifest | Resource page | `filament.pages.import-manifest` |
| todo | PalletResource › ListPallets | Resource page | `(Filament default)` |
| todo | PalletResource › PalletItems | Resource page | `filament.resources.pallet-resource.pages.pallet-items` |
| todo | PalletResource › ReceivePallet | Resource page | `filament.pages.receive-pallet` |
| todo | PalletResource › StagePallet | Resource page | `filament.pages.stage-pallet` |
| todo | PalletResource › ViewPallet | Resource page | `filament.pages.view-pallet` |
| todo | ProductIdentityResource › ListProductIdentities | Resource page | `(Filament default)` |
| todo | ReceivingSessionResource › CreateReceivingSession | Resource page | `(Filament default)` |
| todo | ReceivingSessionResource › ListReceivingSessions | Resource page | `(Filament default)` |
| todo | ReceivingSessionResource › ReviewReceivingSession | Resource page | `filament.resources.receiving-session-resource.pages.review-receiving-session` |
| todo | ShowResource › ShowInventoryBreakdown | Resource page | `filament.pages.show-inventory-breakdown` |
| todo | VendorResource › CreateVendor | Resource page | `(Filament default)` |
| todo | VendorResource › EditVendor | Resource page | `(Filament default)` |
| todo | VendorResource › ListVendors | Resource page | `(Filament default)` |
| todo | VendorResource › ViewVendor | Resource page | `(Filament default)` |

## Other

| Status | Screen | Kind | View |
|---|---|---|---|
| todo | AnnouncementPreview | Livewire | `livewire.announcement-preview` |
| todo | ChannelSwitcher | Livewire | `livewire.channel-switcher` |
| todo | ClockWidget | Livewire | `livewire.assistant-clock` |
| todo | FeedbackWidget | Livewire | `livewire.feedback-widget` |
| todo | HiddenSidebar | Livewire | `filament.components.hidden-sidebar` |
| todo | ItemSelectionModal | Livewire | `livewire.item-selection-modal` |
| todo | AiMonitoring | Page | `filament.pages.ai-monitoring` |
| todo | AiOperations | Page | `filament.pages.ai-operations` |
| todo | Communications | Page | `filament.pages.communications` |
| todo | CostAdjustmentHistory | Page | `filament.pages.cost-adjustment-history` |
| todo | DemoData | Page | `filament.pages.demo-data` |
| todo | EditProfile | Page | `filament.pages.edit-profile` |
| todo | GettingStarted | Page | `filament.pages.getting-started` |
| todo | Handbook | Page | `filament.pages.handbook` |
| todo | HelpCenter | Page | `filament.pages.help-center` |
| todo | HowItWorks | Page | `filament.pages.how-it-works` |
| todo | LogViewer | Page | `filament.pages.log-viewer` |
| todo | ManagerHub | Page | `filament.pages.manager-hub` |
| todo | MyPay | Page | `filament.pages.my-pay` |
| todo | MyPokedex | Page | `filament.pages.my-pokedex` |
| todo | QuickAddContainerScan | Page | `filament.pages.quick-add-container-scan` |
| todo | SystemHealth | Page | `filament.pages.system-health` |
| todo | TwoFactorAuth | Page | `filament.pages.two-factor-auth` |
| todo | TwoFactorVerify | Page | `filament.pages.two-factor-verify` |
| todo | ActivityLogResource › ListActivityLogs | Resource page | `(Filament default)` |
| todo | ActivityLogResource › ViewActivityLog | Resource page | `(Filament default)` |
| todo | DeductionRequestResource › ListDeductionRequests | Resource page | `(Filament default)` |
| todo | DeductionRequestResource › ViewDeductionRequest | Resource page | `(Filament default)` |
| todo | FeatureFlagResource › EditFeatureFlag | Resource page | `(Filament default)` |
| todo | FeatureFlagResource › ListFeatureFlags | Resource page | `(Filament default)` |
| todo | FeedbackTicketResource › EditFeedbackTicket | Resource page | `(Filament default)` |
| todo | FeedbackTicketResource › ListFeedbackTickets | Resource page | `(Filament default)` |
| todo | FeedbackTicketResource › ViewFeedbackTicket | Resource page | `(Filament default)` |
| todo | ShippingSurchargeResource › ListShippingSurcharges | Resource page | `(Filament default)` |

## Owner tools

| Status | Screen | Kind | View |
|---|---|---|---|
| todo | AppSettings | Page | `filament.pages.app-settings` |
| todo | EmailLog | Page | `filament.pages.email-log` |
| todo | ImportStatus | Page | `filament.pages.import-status` |
| todo | MyNotifications | Page | `filament.pages.my-notifications` |
| todo | NavigationManager | Page | `filament.pages.navigation-manager` |
| todo | NotificationSettings | Page | `filament.pages.notification-settings` |
| todo | WhatnotApiChanges | Page | `filament.pages.whatnot-api-changes` |
| todo | WhatnotApiDashboard | Page | `filament.pages.whatnot-api-dashboard` |
| todo | WhatnotBackfill | Page | `filament.pages.whatnot-backfill` |
| todo | WhatnotScraperPage | Page | `filament.pages.whatnot-scraper` |
| todo | WhatnotSheetCheck | Page | `filament.pages.whatnot-sheet-check` |
| todo | WhatnotSyncPage | Page | `filament.pages.whatnot-sync` |
| todo | RoleResource › CreateRole | Resource page | `(Filament default)` |
| todo | RoleResource › EditRole | Resource page | `filament.resources.role-resource.pages.edit-role` |
| todo | RoleResource › ListRoles | Resource page | `filament.resources.role-resource.pages.list-roles` |
| todo | UserResource › CreateUser | Resource page | `(Filament default)` |
| todo | UserResource › EditUser | Resource page | `(Filament default)` |
| todo | UserResource › ListUsers | Resource page | `filament.resources.user-resource.pages.list-users` |
| todo | UserResource › ViewUser | Resource page | `(Filament default)` |
| todo | WhatnotChannelResource › CreateWhatnotChannel | Resource page | `(Filament default)` |
| todo | WhatnotChannelResource › EditWhatnotChannel | Resource page | `(Filament default)` |
| todo | WhatnotChannelResource › ListWhatnotChannels | Resource page | `(Filament default)` |
| todo | WhatnotChannelResource › ViewWhatnotChannel | Resource page | `(Filament default)` |

## Payroll

| Status | Screen | Kind | View |
|---|---|---|---|
| todo | BusinessSwitcher | Livewire | `livewire.business-switcher` |
| todo | Businesses | Page | `filament.pages.businesses` |
| todo | PayRunBackfill | Page | `filament.pages.pay-run-backfill` |
| todo | PaymentStructures | Page | `filament.pages.payment-structures` |
| todo | PayrollOverview | Page | `filament.pages.payroll-overview` |
| todo | PayrollSimulator | Page | `filament.pages.payroll-simulator` |
| todo | PayoutResource › ListPayouts | Resource page | `filament.resources.payout-resource.pages.list-payouts` |
| todo | PayoutResource › ViewPayout | Resource page | `filament.resources.payout-resource.pages.view-payout` |
| todo | StreamerLoanResource › CreateStreamerLoan | Resource page | `(Filament default)` |
| todo | StreamerLoanResource › EditStreamerLoan | Resource page | `(Filament default)` |
| todo | StreamerLoanResource › ListStreamerLoans | Resource page | `(Filament default)` |
| todo | StreamerLoanResource › ViewStreamerLoan | Resource page | `(Filament default)` |
| todo | WeeklyPayoutBatchResource › CreateWeeklyPayoutBatch | Resource page | `(Filament default)` |
| todo | WeeklyPayoutBatchResource › EditWeeklyPayoutBatch | Resource page | `(Filament default)` |
| todo | WeeklyPayoutBatchResource › ListWeeklyPayoutBatches | Resource page | `(Filament default)` |
| todo | WeeklyPayoutBatchResource › ViewWeeklyPayoutBatch | Resource page | `filament.resources.weekly-payout-batch-resource.pages.view-pay-run-workspace` |

## Reports

| Status | Screen | Kind | View |
|---|---|---|---|
| todo | ManagerProfitShareDashboard | Livewire | `livewire.manager-profit-share-dashboard` |
| todo | StreamerProfitShareDashboard | Livewire | `livewire.streamer-profit-share-dashboard-v2` |
| todo | ManagerProfitShare | Page | `filament.pages.manager-profit-share` |
| todo | ProfitLoss | Page | `filament.pages.profit-loss` |
| todo | ProfitSharePacket | Page | `filament.pages.profit-share-packet` |
| todo | Reports | Page | `filament.pages.reports` |
| todo | ReportsOverview | Page | `filament.pages.reports-overview` |
| todo | ShowPerformance | Page | `filament.pages.show-performance` |
| todo | StreamerAnalytics | Page | `filament.pages.streamer-analytics` |
| todo | StreamerProfitShare | Page | `filament.pages.streamer-profit-share` |
| todo | WhatnotLosses | Page | `filament.pages.whatnot-losses` |
| todo | LedgerResource › ListLedgerEntries | Resource page | `(Filament default)` |
| todo | MissingItemReportResource › CreateMissingItemReport | Resource page | `(Filament default)` |
| todo | MissingItemReportResource › EditMissingItemReport | Resource page | `(Filament default)` |
| todo | MissingItemReportResource › ListMissingItemReports | Resource page | `(Filament default)` |
| todo | WhatnotLedgerResource › LedgerTips | Resource page | `filament.resources.whatnot-ledger-resource.pages.ledger-tips` |
| todo | WhatnotLedgerResource › ListWhatnotLedgerEntries | Resource page | `filament.resources.whatnot-ledger-resource.pages.list-whatnot-ledger-entries` |

## Shows

| Status | Screen | Kind | View |
|---|---|---|---|
| todo | AdminMatchShowItem | Livewire | `livewire.admin-match-show-item` |
| todo | CreateManualShow | Livewire | `livewire.create-manual-show` |
| todo | EndOfStreamForm | Livewire | `livewire.end-of-stream-form` |
| todo | ShowItemsModal | Livewire | `livewire.show-items-modal` |
| todo | EndOfStreamForm | Page | `filament.pages.end-of-stream-items` |
| todo | NoShowCleanup | Page | `filament.pages.no-show-cleanup` |
| todo | ShowDataAudit | Page | `filament.pages.show-data-audit` |
| todo | ShowFormatComparison | Page | `filament.pages.show-format-comparison` |
| todo | ShowLeaderboard | Page | `filament.pages.show-leaderboard` |
| todo | ShowStatusBoard | Page | `filament.pages.show-status-board` |
| todo | Shows | Page | `filament.pages.shows` |
| todo | StreamWorkflow | Page | `filament.pages.stream-workflow` |
| todo | StreamsOverview | Page | `filament.pages.streams-overview` |
| todo | ShowIngestionLogResource › ListShowIngestionLogs | Resource page | `filament.resources.show-ingestion-log-resource.pages.list-show-ingestion-logs` |
| todo | ShowIngestionLogResource › ViewShowIngestionLog | Resource page | `(Filament default)` |
| todo | ShowResource › AddShowItems | Resource page | `filament.pages.add-show-items` |
| todo | ShowResource › CreateShow | Resource page | `filament.resources.show-resource.pages.create-show` |
| todo | ShowResource › EditShow | Resource page | `filament.resources.show-resource.pages.edit-show` |
| todo | ShowResource › ListShows | Resource page | `(Filament default)` |
| todo | ShowResource › ReviewShowChannels | Resource page | `(Filament default)` |
| todo | ShowResource › ViewShow | Resource page | `filament.resources.show-resource.pages.view-show-workspace` |

## Streamers

| Status | Screen | Kind | View |
|---|---|---|---|
| todo | ShowStreamerLogPanel | Livewire | `livewire.show-streamer-log-panel` |
| todo | StreamerHome | Livewire | `livewire.streamer-home` |
| todo | StreamerLeaderboard | Livewire | `livewire.streamer-leaderboard` |
| todo | StreamerLogItemsModal | Livewire | `livewire.streamer-log-items-modal` |
| todo | StreamerLogTabs | Livewire | `livewire.streamer-log-tabs` |
| todo | StreamerHub | Page | `filament.pages.streamer-hub` |
| todo | StreamerStatement | Page | `filament.pages.streamer-statement` |
| todo | ShowResource › ReviewShowStreamers | Resource page | `filament.resources.show-resource.pages.stream-review` |
| todo | StreamerLogResource › EditStreamerLogEntry | Resource page | `filament.resources.streamer-log-resource.pages.edit-streamer-log-entry` |
| todo | StreamerLogResource › ListStreamerLogEntries | Resource page | `filament.resources.streamer-log-resource.pages.list-streamer-log-entries` |
| todo | StreamerLogResource › ViewStreamerLogEntry | Resource page | `filament.resources.streamer-log-resource.pages.view-streamer-log-entry` |
| todo | StreamerResource › CreateStreamer | Resource page | `(Filament default)` |
| todo | StreamerResource › EditStreamer | Resource page | `(Filament default)` |
| todo | StreamerResource › ListStreamers | Resource page | `(Filament default)` |
| todo | StreamerResource › ViewStreamer | Resource page | `filament.resources.streamer-resource.pages.view-streamer-workspace` |

## Timekeeping

| Status | Screen | Kind | View |
|---|---|---|---|
| todo | TimeAttendance | Page | `filament.pages.time-attendance` |
| todo | Timekeeping | Page | `filament.pages.timekeeping` |
