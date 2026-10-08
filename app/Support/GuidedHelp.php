<?php

namespace App\Support;

use App\Filament\Pages;
use App\Filament\Resources;

final class GuidedHelp
{
    public static function documents(): array
    {
        $user = auth()->user();
        $docs = [];
        if ($user && ($user->isAdmin() || $user->isStreamer() || $user->streamer)) {
            $docs['streamer'] = ['title' => 'Streamer show reporting', 'file' => 'streamer-guide.pdf', 'description' => 'Find your show, log items, check hours, submit, and request a correction.'];
        }
        if ($user?->isAdmin()) {
            $docs['admin'] = ['title' => 'Admin, users & payroll', 'file' => 'admin-guide.pdf', 'description' => 'Set up users and aliases, review shows, and work through weekly payroll.'];
        }
        return $docs;
    }

    public static function tours(): array
    {
        $user = auth()->user();
        if (! $user) return [];
        $definitions = [];
        if ($user->streamer) {
            $definitions['my-shows'] = [Pages\StreamerShows::class, 'Find and report your shows', 'Choose the right show and understand its report status.', [
                ['.vxw-hero', 'Start with Waiting on you', 'This summary tells you which reports still need your attention. Check the show title and date before opening a report.'],
                ['[aria-label="Filter your shows"]', 'Find the right status', 'Waiting on you needs action. Upcoming shows have not aired yet. Submitted reports are in review; Approved reports are complete.'],
                ['#show-grid', 'Open your show', 'Choose Add items or Finish report on the correct show. View report opens an already submitted report. Calendar is another way to find shows by date.'],
                [null, 'Finish in the report', 'Add every item used, check Sold / Giveaway / Promo / Other and quantities, then check hours and notes. Review everything before submitting. The report has its own walkthrough in Help.'],
            ]];
        }
        if ($user->isAdmin() || $user->streamer) {
            $definitions['show-report'] = [Pages\EndOfStreamForm::class, 'Complete a show report', 'Items, hours and notes, review, and corrections.', [
                ['.vxw-hero', 'Check the show', 'Confirm the title and date. If no show is selected, choose one first. Only shows available to your account can be reported.'],
                ['.vxw-wizard', 'Three steps', 'Items → Time & notes → Review. You control when to move between steps. This walkthrough explains the workflow without changing or submitting anything.'],
                ['[wire\\:click="toggleBrowse"]', 'Add inventory used', 'On Items, open Add items, search by product name, SKU or barcode, choose quantities, then Add to report. Check each usage type, especially Sold versus Giveaway.'],
                ['#eos-notes', 'Check hours and notes', 'On Time & notes, check streamed hours and any available fulfillment fields. Explain unusual giveaways, missing products, or special handling in the notes.'],
                [null, 'Review, then submit', 'Review the show, items, quantities, usage types, hours and notes before submitting. After submission the report stays locked. Use Need to change something? to ask an admin to reopen it.'],
            ]];
        }
        if ($user->isAdmin()) {
            $definitions['users'] = [Resources\UserResource::class, 'Set up a user', 'Create accounts and improve show matching with aliases.', [
                ['.fi-header', 'Users and access', 'Open Add User to create an account, or Edit to update an existing one. Choose the role the person needs. Only the owner can grant privileged administrator roles.'],
                ['.fi-ta', 'Find the person', 'Search the list and open the correct user. Leave Password blank when editing to keep the existing password.'],
                [null, 'Streamer setup', 'Choose In-House or Remote and add recurring Whatnot names, handles or show-title wording as aliases. Keep aliases specific so they do not match another streamer.'],
                [null, 'Check compensation', 'Review the default payment structure or individual override. Use Payroll Simulator to test a pay change before applying it to real payroll.'],
            ]];
            $definitions['payroll'] = [Pages\PayrollOverview::class, 'Review weekly payroll', 'Resolve exceptions before preparing and reviewing the pay run.', [
                ['.vxw-hero', 'Check the pay period', 'Confirm the dates and pay-run status. Review an existing run, or prepare this week after the required show work is complete.'],
                ['.vxw-stats', 'Read the key numbers', 'Check the pay-run total, people paid, approved reports and shows needing attention.'],
                ['.vxw-pipeline', 'Work the exceptions', 'Filter Needs streamer, Needs report, Needs approval and Blocked first. Upcoming shows stay visible but are not blockers before they air.'],
                ['.vxw-rows', 'Follow each next action', 'Use each show’s next action to fix missing assignments, reports, costs or compensation. Review eligible entries and totals before finalizing or exporting the pay run.'],
            ]];
        }
        if ($user->isAdmin() || $user->isFulfillment()) {
            $definitions['inventory'] = [Pages\InventoryReport::class, 'Read inventory reports', 'Find stock, low-stock items, values and recent activity.', [
                ['.vx-report-head', 'Inventory at a glance', 'This report shows current stock and money tied up in inventory. Use All Inventory to open the operational stock workspace.'],
                ['.vx-quick-reports', 'Choose a view', 'Choose Current Inventory, Valuation, Low Stock, Recent Activity or another view. Use Filters to narrow the records.'],
                ['.vx-report-sheet', 'Inspect the records', 'Search, sort and page through records. Recent Activity uses the selected activity period; current quantities and values describe stock on hand.'],
            ]];
        }
        $tours = [];
        foreach ($definitions as $id => [$class, $title, $description, $steps]) {
            try {
                if (! $class::canAccess() || NavVisibility::isHiddenForUser($class, $user)) continue;
                $url = is_subclass_of($class, \Filament\Resources\Resource::class) ? $class::getUrl('index') : $class::getUrl(panel: 'admin');
                $tours[$id] = ['title' => $title, 'description' => $description, 'url' => $url, 'path' => parse_url($url, PHP_URL_PATH), 'steps' => array_map(fn ($step) => ['target' => $step[0], 'title' => $step[1], 'body' => $step[2]], $steps)];
            } catch (\Throwable) {
                // Disabled or unavailable screens do not become help links.
            }
        }
        return $tours;
    }
}
