<?php

/**
 * Creates one plain staff account for every department involved in the Faculty of
 * Engineering and Technology (FET) request workflow, plus one Super Admin.
 *
 * Usage, from the campusdesk/ directory:
 *
 *     php artisan tinker snippet.php
 *
 * Tinker includes this file before the shell starts, so the variables created here
 * ($faculty, $departments, $report, ...) stay available for inspection afterwards.
 *
 * Safe to re-run: existing accounts are reset (password, admin level, department
 * membership) instead of failing on duplicate emails. Test data only — never run
 * this against production.
 */

use App\Models\Department;
use App\Models\Faculty;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Support\Str;

if (app()->environment('production')) {
    throw new \RuntimeException('Refusing to create test accounts in the production environment.');
}

$facultyCode = 'FET';
$password = 'TestPassword123!';
$domain = 'example.test';

$faculty = Faculty::where('code', $facultyCode)->firstOrFail();
$departmentIds = Department::where('faculty_id', $faculty->id)->whereIn('type', ['academic', 'records'])->pluck('id');

// Literal department IDs in any request type's route: the central Records Office desks (TRD, AOE, AOC).
foreach (RequestType::all() as $requestType) {
    foreach ($requestType->default_department_sequence as $entry) {
        if (is_numeric($entry)) {
            $departmentIds->push((int) $entry);
        }
    }
}

$departments = Department::whereIn('id', $departmentIds->unique()->values())->orderBy('code')->get();
$report = [];

foreach ($departments as $department) {
    $email = 'staff.'.Str::slug($department->code).'@'.$domain;
    $name = $department->code.' Staff Tester';
    $user = User::where('email', $email)->first();

    if ($user === null) {
        $user = User::factory()->staff()->create(['name' => $name, 'email' => $email, 'password' => $password]);
    } else {
        if (! $user->staffProfile) {
            throw new \RuntimeException($email.' exists without a staff profile. Change '.$domain.' or delete it.');
        }
        $user->update(['name' => $name, 'password' => $password, 'role' => 'staff']);
        $user->forceFill(['disabled_at' => null])->save();
        $user->staffProfile->update(['admin_level' => null]);
    }

    $user->staffProfile->departments()->syncWithoutDetaching([$department->id => ['is_primary' => true]]);
    $report[] = [$email, $department->code, 'null (plain staff)', $department->name];
}

$superAdminEmail = 'super-admin@'.$domain;
$superAdmin = User::where('email', $superAdminEmail)->first();

if ($superAdmin === null) {
    $superAdmin = User::factory()->staff('super_admin')->create(['name' => 'Super Admin Tester', 'email' => $superAdminEmail, 'password' => $password]);
} else {
    if (! $superAdmin->staffProfile) {
        throw new \RuntimeException($superAdminEmail.' exists without a staff profile. Change '.$domain.' or delete it.');
    }
    $superAdmin->update(['name' => 'Super Admin Tester', 'password' => $password, 'role' => 'staff']);
    $superAdmin->forceFill(['disabled_at' => null])->save();
    $superAdmin->staffProfile->update(['admin_level' => 'super_admin']);
}

$report[] = [$superAdminEmail, '-', 'super_admin', 'All faculties (no department)'];

foreach ($report as $row) {
    printf("%-34s %-9s %-19s %s\n", $row[0], $row[1], $row[2], $row[3]);
}

echo "\nPassword for all accounts: {$password}\n";
