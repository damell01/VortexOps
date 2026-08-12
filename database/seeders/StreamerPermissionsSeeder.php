<?php

namespace Database\Seeders;

use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StreamerPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // First, generate Filament Shield permissions if they don't exist
        if (Permission::count() === 0) {
            $this->generatePermissions();
        }

        // Get or create the streamer role
        $streamerRole = Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);

        // Define which resources streamers should have access to
        $streamerResourcePermissions = [
            'InventoryItemResource',
            'ShowResource',
            'StreamerLogResource',
            'StreamerResource',
            'StreamerLoanResource',
            'DeductionRequestResource',
            'FeedbackTicketResource',
        ];

        // Define which actions streamers can perform on each resource
        $allowedActions = [
            'ViewAny',
            'View',
            'Create',
            'Update',
        ];

        // Special case: Payout is view-only
        $payoutActions = ['ViewAny', 'View'];

        $permissionsToAssign = [];

        // Collect all permissions for the defined resources
        foreach ($streamerResourcePermissions as $resource) {
            $actions = $resource === 'PayoutResource' ? $payoutActions : $allowedActions;
            foreach ($actions as $action) {
                $permissionName = "{$action}:{$resource}";
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission) {
                    $permissionsToAssign[] = $permission->id;
                }
            }
        }

        // Sync permissions to the streamer role
        $streamerRole->syncPermissions(
            Permission::whereIn('id', $permissionsToAssign)->get()
        );

        if ($this->command) {
            $this->command->info("Assigned " . count($permissionsToAssign) . " permissions to streamer role.");
        }
    }

    private function generatePermissions(): void
    {
        $panel = Filament::getPanel('admin');
        $resources = $panel->getResources();

        foreach ($resources as $resource) {
            $actions = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Restore', 'ForceDelete', 'DeleteAny', 'RestoreAny', 'ReplicateAny', 'Reorder'];
            foreach ($actions as $action) {
                $name = $action . ':' . class_basename($resource);
                Permission::firstOrCreate(
                    ['name' => $name],
                    ['guard_name' => 'web']
                );
            }
        }

        if ($this->command) {
            $this->command->info("Generated Filament Shield permissions.");
        }
    }
}
