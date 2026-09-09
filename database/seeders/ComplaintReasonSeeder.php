<?php

namespace Database\Seeders;

use App\Models\ComplaintReason;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ComplaintReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reasons = [
            ['name' => 'Policy Violation', 'description' => 'A complaint about a suspected policy violation.', 'priority' => 'HIGH'],
            ['name' => 'General Inquiry', 'description' => 'A general policy-related question or inquiry.', 'priority' => 'LOW'],
            ['name' => 'Documentation Issue', 'description' => 'A complaint about missing or incorrect documentation.', 'priority' => 'MEDIUM'],
        ];

        foreach ($reasons as $reason) {
            ComplaintReason::updateOrCreate(
                ['name' => $reason['name']],
                ['description' => $reason['description'], 'priority' => $reason['priority'], 'active' => true]
            );
        }
    }
}
