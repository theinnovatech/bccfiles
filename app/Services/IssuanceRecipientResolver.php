<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Resolves the "Received By" employee for an issuance on the server (Revision 5).
 *
 * Resolution order:
 *   1. self      - the authenticated user is the recipient (their account must be linked
 *                  to an active directory employee).
 *   2. directory - an authorised selection from the unit employee directory.
 *
 * The approved-request source is not available because the supply request module was
 * removed from this system; see the hand-off notes.
 *
 * Whatever the client sends as names/units is ignored: name, unit and identifier are always
 * derived here from the directory record.
 */
class IssuanceRecipientResolver
{
    public const SOURCE_DIRECTORY = 'directory';
    public const SOURCE_SELF = 'self';

    public const SOURCES = [self::SOURCE_DIRECTORY, self::SOURCE_SELF];

    /**
     * @return array{employee: Employee, source: string}
     */
    public function resolve(User $user, ?string $source, mixed $employeeId): array
    {
        $source = $source ?: self::SOURCE_DIRECTORY;

        if (! in_array($source, self::SOURCES, true)) {
            throw ValidationException::withMessages([
                'recipient_source' => ['Unsupported recipient source.'],
            ]);
        }

        if ($source === self::SOURCE_SELF) {
            $employee = $user->employee_id
                ? Employee::query()->with('department')->find($user->employee_id)
                : null;

            if (! $employee) {
                throw ValidationException::withMessages([
                    'received_by' => ['Your account is not linked to an employee in the directory, so you cannot receive as yourself.'],
                ]);
            }

            return ['employee' => $this->assertUsable($employee), 'source' => $source];
        }

        if (! is_numeric($employeeId) || (int) $employeeId < 1) {
            throw ValidationException::withMessages([
                'received_by' => ['Select the recipient from the employee directory.'],
            ]);
        }

        $employee = Employee::query()->with('department')->find((int) $employeeId);

        if (! $employee) {
            throw ValidationException::withMessages([
                'received_by' => ['The selected recipient is not in the employee directory.'],
            ]);
        }

        return ['employee' => $this->assertUsable($employee), 'source' => $source];
    }

    private function assertUsable(Employee $employee): Employee
    {
        if (! $employee->is_active) {
            throw ValidationException::withMessages([
                'received_by' => ["{$employee->name} is inactive in the employee directory and cannot receive stock."],
            ]);
        }

        if (! $employee->department_id || ! $employee->department) {
            throw ValidationException::withMessages([
                'received_by' => ["{$employee->name} has no unit in the employee directory. Update the directory first."],
            ]);
        }

        return $employee;
    }
}
