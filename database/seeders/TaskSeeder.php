<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        if (! $admin) {
            return;
        }

        $users = User::all();
        $assignee1 = $users->skip(1)->first() ?? $admin;
        $assignee2 = $users->skip(2)->first() ?? $admin;

        $sampleTasks = [
            [
                'title' => 'Ramesh Sharma ji ko bridal necklace delivery ke liye call karein',
                'description' => 'Custom antique gold necklace ready hai. Final finishing aur HUID hallmark check ho chuka hai. Delivery slot confirm karein.',
                'category' => 'CUSTOMER_FOLLOWUP',
                'priority' => 'HIGH',
                'status' => 'TODO',
                'due_date' => Carbon::today()->format('Y-m-d'),
                'due_time' => '15:30:00',
                'assigned_to' => $assignee1->id,
                'created_by' => $admin->id,
                'checklist' => [
                    ['id' => '1', 'text' => 'Weight certificate verify karein', 'is_completed' => true],
                    ['id' => '2', 'text' => 'Luxury box packing taiyar karein', 'is_completed' => true],
                    ['id' => '3', 'text' => 'Customer se delivery timing confirm karein', 'is_completed' => false],
                ],
                'is_pinned' => true,
                'handover_notes' => 'Customer evening 6 PM aayenge.',
            ],
            [
                'title' => 'Karigar Mohan se 4 pairs 22K Gold Bangles collect karein',
                'description' => 'Order #ORD-1004 ke under 48.5g 22K Plain Bangles ready hain. Weight matching aur hallmark certificate collect karna hai.',
                'category' => 'KARIGAR_WORKSHOP',
                'priority' => 'URGENT',
                'status' => 'IN_PROGRESS',
                'due_date' => Carbon::today()->format('Y-m-d'),
                'due_time' => '17:00:00',
                'assigned_to' => $assignee2->id,
                'created_by' => $admin->id,
                'checklist' => [
                    ['id' => '1', 'text' => 'Gross & Net weight matching check karein', 'is_completed' => true],
                    ['id' => '2', 'text' => 'HUID 6-digit laser mark verify karein', 'is_completed' => false],
                    ['id' => '3', 'text' => 'Karigar ledger me issue receipt update karein', 'is_completed' => false],
                ],
                'is_pinned' => true,
                'handover_notes' => null,
            ],
            [
                'title' => 'Gold Counter 2 - Daily Tray Stock Audit',
                'description' => 'Gold rings aur lightweight chains ka physical barcode scan verification karein.',
                'category' => 'INVENTORY_AUDIT',
                'priority' => 'MEDIUM',
                'status' => 'IN_PROGRESS',
                'due_date' => Carbon::tomorrow()->format('Y-m-d'),
                'due_time' => '11:00:00',
                'assigned_to' => $assignee1->id,
                'created_by' => $admin->id,
                'checklist' => [
                    ['id' => '1', 'text' => 'Tray A rings barcode audit', 'is_completed' => true],
                    ['id' => '2', 'text' => 'Tray B chains barcode audit', 'is_completed' => false],
                    ['id' => '3', 'text' => 'Total gross weight match karein', 'is_completed' => false],
                ],
                'is_pinned' => false,
                'handover_notes' => null,
            ],
            [
                'title' => 'Pending Gold Scheme 11th installment follow-up list',
                'description' => 'Un sabhi 8 customers ko WhatsApp reminder aur call karein jinki monthly gold scheme installment pending hai.',
                'category' => 'BILLING_FINANCE',
                'priority' => 'MEDIUM',
                'status' => 'TODO',
                'due_date' => Carbon::today()->addDays(2)->format('Y-m-d'),
                'due_time' => '12:00:00',
                'assigned_to' => $assignee2->id,
                'created_by' => $admin->id,
                'checklist' => [
                    ['id' => '1', 'text' => 'Gold scheme pending report export karein', 'is_completed' => false],
                    ['id' => '2', 'text' => 'Customers ko payment link share karein', 'is_completed' => false],
                ],
                'is_pinned' => false,
                'handover_notes' => null,
            ],
            [
                'title' => 'Front showcase luxury lighting & velvet pads inspection',
                'description' => 'Main showroom display ke LED spotlights aur antique jewellery velvet trays ki cleaning aur alignment check.',
                'category' => 'MAINTENANCE',
                'priority' => 'LOW',
                'status' => 'COMPLETED',
                'due_date' => Carbon::yesterday()->format('Y-m-d'),
                'due_time' => '10:00:00',
                'assigned_to' => $assignee1->id,
                'created_by' => $admin->id,
                'completed_at' => Carbon::yesterday()->setHour(11),
                'completed_by' => $assignee1->id,
                'checklist' => [
                    ['id' => '1', 'text' => 'Velvet display cushions clean karein', 'is_completed' => true],
                    ['id' => '2', 'text' => 'Showcase lock mechanism check karein', 'is_completed' => true],
                ],
                'is_pinned' => false,
                'handover_notes' => 'Inspection completed successfully by morning shift staff.',
            ],
        ];

        foreach ($sampleTasks as $taskData) {
            Task::create($taskData);
        }
    }
}
