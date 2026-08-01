<?php

namespace App\Support;

class StatusDisplay
{
    /**
     * Icon emoji for each status
     */
    public static function getIcon(string $status): string
    {
        return match($status) {
            // Streamer Log statuses
            'pending' => '🔄',
            'streamer_reviewed' => '👀',
            'admin_approved' => '✅',

            // Show statuses
            'draft' => '📝',
            'pending_review' => '🔍',
            'approved' => '✅',
            'rejected' => '❌',
            'completed' => '✅',

            // Shipment statuses
            'ready_to_ship' => '📦',
            'Ready to Ship' => '📦',
            'shipped' => '🚚',
            'Shipped' => '🚚',
            'in_transit' => '🚚',
            'delivered' => '📍',
            'Delivered' => '📍',

            // Payout statuses
            'draft' => '📝',
            'approved' => '✅',
            'paid' => '💰',
            'rejected' => '❌',

            // Inventory statuses
            'in_stock' => '✅',
            'low_stock' => '⚠️',
            'out_of_stock' => '❌',

            // Generic
            'active' => '✅',
            'inactive' => '⏸️',
            'pending' => '⏳',
            'processing' => '⚙️',
            'error' => '❌',

            default => '•',
        };
    }

    /**
     * Color scheme for each status (Tailwind class)
     */
    public static function getColor(string $status): string
    {
        return match($status) {
            // Streamer Log
            'pending' => 'amber',
            'streamer_reviewed' => 'blue',
            'admin_approved' => 'green',

            // Show
            'draft' => 'gray',
            'pending_review' => 'amber',
            'approved' => 'green',
            'rejected' => 'red',
            'completed' => 'green',

            // Shipment
            'ready_to_ship', 'Ready to Ship' => 'blue',
            'shipped', 'Shipped' => 'indigo',
            'in_transit' => 'indigo',
            'delivered', 'Delivered' => 'green',

            // Payout
            'draft' => 'gray',
            'approved' => 'amber',
            'paid' => 'green',
            'rejected' => 'red',

            // Inventory
            'in_stock' => 'green',
            'low_stock' => 'amber',
            'out_of_stock' => 'red',

            // Generic
            'active' => 'green',
            'inactive' => 'gray',
            'pending' => 'amber',
            'processing' => 'blue',
            'error' => 'red',

            default => 'gray',
        };
    }

    /**
     * Human-readable description for each status
     */
    public static function getDescription(string $status): string
    {
        return match($status) {
            // Streamer Log
            'pending' => 'Awaiting streamer review and item mapping',
            'streamer_reviewed' => 'Streamer has reviewed items, awaiting admin approval',
            'admin_approved' => 'Approved and ready for fulfillment',

            // Show
            'draft' => 'Show created but not yet submitted',
            'pending_review' => 'Awaiting admin review and approval',
            'approved' => 'Approved and ready for fulfillment',
            'rejected' => 'Rejected by admin, revision needed',
            'completed' => 'Show completed and fulfilled',

            // Shipment
            'ready_to_ship' => 'Packed and ready for carrier pickup',
            'Ready to Ship' => 'Packed and ready for carrier pickup',
            'shipped' => 'Handed off to carrier',
            'Shipped' => 'Handed off to carrier',
            'in_transit' => 'On its way to customer',
            'delivered' => 'Delivered to customer',
            'Delivered' => 'Delivered to customer',

            // Payout
            'draft' => 'Payout calculated, awaiting approval',
            'approved' => 'Approved, ready for payment processing',
            'paid' => 'Payment successfully processed',
            'rejected' => 'Rejected and requires adjustment',

            // Inventory
            'in_stock' => 'Sufficient stock available',
            'low_stock' => 'Below reorder level, needs attention',
            'out_of_stock' => 'No stock available',

            // Generic
            'active' => 'Currently active',
            'inactive' => 'Currently inactive',
            'pending' => 'Awaiting action',
            'processing' => 'Currently being processed',
            'error' => 'An error occurred',

            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
