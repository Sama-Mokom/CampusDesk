# Creating Staff Test Users with Tinker

Staff accounts are deliberately not creatable through public HTTP routes. This guide is the supported development-only path for creating accounts to test the staff, department-admin, and super-admin experiences.

Run every command below from `campusdesk/` with a seeded local database. Do not run these commands against production data.

```bash
php artisan tinker
```

The terms used by the application are:

| Test account | `users.role` | `staff_profiles.admin_level` | Department assignment |
| --- | --- | --- | --- |
| Plain staff | `staff` | `null` | Required to receive that department's queue |
| Department admin | `staff` | `dept_admin` | Make its administered department primary |
| Super admin | `staff` | `super_admin` | Optional for the current admin-only routes; useful when also testing staff queues |

The factory creates the `StaffProfile` and a unique `STAFF-XXXXXX` identifier automatically. Department membership is a separate many-to-many assignment; without it, a staff member has no department queue.

## 1. Find the department(s) first

Use an existing department code or name. Inspect the available values rather than assuming IDs:

```php
use App\Models\Department;

Department::query()
    ->with('faculty:id,code,name')
    ->orderBy('code')
    ->get(['id', 'faculty_id', 'code', 'name', 'type'])
    ->map(fn ($department) => [
        'id' => $department->id,
        'code' => $department->code,
        'name' => $department->name,
        'faculty' => $department->faculty?->code,
        'type' => $department->type,
    ]);

// Replace CS with the chosen department code. firstOrFail() prevents a silent bad assignment.
$department = Department::where('code', 'CS')->firstOrFail();
```

For names that are not unique, add the faculty or select by the displayed ID:

```php
$department = Department::where('name', 'Computer Science')
    ->whereHas('faculty', fn ($query) => $query->where('code', 'FET'))
    ->firstOrFail();

// Or: $department = Department::findOrFail(12);
```

## 2. Create one account for each access level

Run the department lookup above, then use exactly one of these variants. Change the name, email, password, and department code for your test case. The password is hashed by the `User` model.

### Plain staff dashboard

```php
use App\Models\User;

$user = User::factory()->staff()->create([
    'name' => 'Plain Staff Tester',
    'email' => 'staff.cs@example.test',
    'password' => 'TestPassword123!',
]);

$user->staffProfile->departments()->attach($department->id, [
    'is_primary' => true,
]);

$user->fresh('staffProfile.departments');
```

Plain staff has `admin_level = null`, is permitted through the `staff` middleware, and can process stages in every assigned department.

### Department-admin dashboard

```php
use App\Models\User;

$user = User::factory()->staff('dept_admin')->create([
    'name' => 'Department Admin Tester',
    'email' => 'dept-admin.cs@example.test',
    'password' => 'TestPassword123!',
]);

// The primary department is the department this account administers in test data.
$user->staffProfile->departments()->attach($department->id, [
    'is_primary' => true,
]);

$user->fresh('staffProfile.departments');
```

### Super-admin dashboard

```php
use App\Models\User;

$user = User::factory()->staff('super_admin')->create([
    'name' => 'Super Admin Tester',
    'email' => 'super-admin@example.test',
    'password' => 'TestPassword123!',
]);

// Add this when the same account must also test a staff queue for one department.
$user->staffProfile->departments()->attach($department->id, [
    'is_primary' => true,
]);

$user->fresh('staffProfile.departments');
```

`super_admin` is an admin level on a staff account, not a separate value for `users.role`.

## 3. Reusable copy-and-edit recipe

This one block accepts the three supported levels and safely validates the department. Set `$adminLevel` to `null`, `'dept_admin'`, or `'super_admin'`.

```php
use App\Models\Department;
use App\Models\User;

$departmentCode = 'CS';
$name = 'Manual Test User';
$email = 'manual-test@example.test'; // Must be unique.
$password = 'TestPassword123!';
$adminLevel = null; // null, 'dept_admin', or 'super_admin'
$isPrimary = true;

if (! in_array($adminLevel, [null, 'dept_admin', 'super_admin'], true)) {
    throw new InvalidArgumentException('adminLevel must be null, dept_admin, or super_admin.');
}

$department = Department::where('code', $departmentCode)->firstOrFail();
$user = User::factory()->staff($adminLevel)->create([
    'name' => $name,
    'email' => $email,
    'password' => $password,
]);

$user->staffProfile->departments()->attach($department->id, [
    'is_primary' => $isPrimary,
]);

$user->fresh('staffProfile.departments');
```

## 4. Assign more departments or change an existing account

Use `syncWithoutDetaching()` to add or update memberships without removing the account's other department assignments.

```php
// Add secondary memberships. Replace the codes as needed.
$extraDepartments = Department::whereIn('code', ['CS', 'CE'])->pluck('id');

$user->staffProfile->departments()->syncWithoutDetaching(
    $extraDepartments->mapWithKeys(fn ($id) => [$id => ['is_primary' => false]])->all()
);

// Promote one of the account's existing memberships to primary.
$user->staffProfile->departments()->updateExistingPivot($department->id, [
    'is_primary' => true,
]);

// Change the access level of an existing staff account.
$user->staffProfile->update(['admin_level' => 'dept_admin']);
// Or: null for plain staff; 'super_admin' for super admin.
```

An account may have multiple department memberships. Keep one primary membership for a department-admin test account so its test data matches the intended model. The database does not enforce one primary membership per staff account, so verify this yourself after changes.

## 5. Verify, log in, and clean up test data

```php
// Inspect one account after creating or editing it.
$user->fresh('staffProfile.departments')->only(['id', 'name', 'email', 'role']);
$user->staffProfile->only(['id', 'staff_id', 'admin_level']);
$user->staffProfile->departments->map(fn ($department) => [
    'id' => $department->id,
    'code' => $department->code,
    'name' => $department->name,
    'is_primary' => (bool) $department->pivot->is_primary,
]);

// Find an account later by its known email.
$user = User::where('email', 'staff.cs@example.test')->firstOrFail();

// Delete a disposable account. The user, profile, and department memberships cascade together.
$user->delete();
```

Log in through the normal UI or `POST /api/login` using the email and password selected above. The frontend routes plain staff to `/staff`, department admins to `/dept-admin`, and super admins to `/admin`.

Current scope note: staff claim and resolve, primary-department administration, and Super Admin management are implemented through their protected HTTP routes. Super Admin accounts are intentionally not seeded.

## Common failures

| Symptom | Check |
| --- | --- |
| `firstOrFail()` throws | List departments again and use an existing code, name/faculty pair, or ID. |
| Duplicate email error | Choose a new `@example.test` address or find and reuse/delete the existing test account. |
| Login succeeds but no staff queue appears | Confirm `role` is `staff` and the profile is attached to the stage's department. |
| The wrong dashboard opens | Confirm `staff_profiles.admin_level`: `null`, `dept_admin`, or `super_admin`. |
| A staff member cannot claim a stage | They must belong to that stage's department; later stages also require the preceding stage to be approved. |
