# CampusDesk — Error Handling & Sanitization Convention

**Last reviewed:** 26 October 2026  
**Status:** Active Convention  
**Applicability:** Full-stack (Laravel backend & Vue 3 frontend)

---

## 1. Core Principles

1. **Defense-in-Depth Information Security**:
   * Sensitive infrastructure details, database schemas, table names, hostnames, ports, SQL queries, file system paths, and stack traces must **never** be transmitted in API responses or displayed in the UI.
2. **User-Friendly UI vs. Developer Console**:
   * **In the UI**: Render clean, polite, human-oriented, and actionable messages (e.g. *"Your requests could not be loaded. Please try again."*).
   * **In the Console (`console.error`)**: Always log full technical context (HTTP status, endpoint URL, HTTP method, response payload, and error stack) for debugging in DevTools.
   * **In Backend Logs (`laravel.log`)**: Always record full exception traces with query context via Laravel's `report()` pipeline.
3. **Zero Code Duplication**:
   * Do not write custom error-parsing logic, regex matching, or `err.response?.data?.message` extractions inside components or composables. All error handling must funnel through the centralized utility.

---

## 2. Backend Convention (Laravel 12)

### Global Exception Pipeline (`campusdesk/bootstrap/app.php`)

All unhandled exceptions on API routes (`api/*` or requests expecting JSON) are intercepted by the global exception handler configured in `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions): void {
    // Intercept database queries and PDO exceptions
    $exceptions->render(function (QueryException|PDOException $e, Request $request) {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'A database error occurred while processing your request. Please try again later.',
            ], 500);
        }
    });

    // Intercept unexpected runtime and server throwables
    $exceptions->render(function (Throwable $e, Request $request) {
        if ($request->is('api/*') || $request->expectsJson()) {
            // Allow domain and framework HTTP exceptions to render normally
            if (
                $e instanceof ValidationException ||
                $e instanceof AuthenticationException ||
                $e instanceof AuthorizationException ||
                $e instanceof HttpExceptionInterface
            ) {
                return null;
            }

            return response()->json([
                'message' => 'An unexpected server error occurred. Please try again later.',
            ], 500);
        }
    });
})
```

### Controller & Service Rules

* **Validation Failures (422)**:
  Use Laravel Form Requests (e.g. `StoreRequestRequest`) or `$request->validate()`. Laravel automatically produces:
  ```json
  {
    "message": "The given data was invalid.",
    "errors": {
      "field_name": ["Specific validation error message."]
    }
  }
  ```
* **Domain / Business Rule Violations**:
  Use `abort(statusCode, 'User-facing explanation')` or throw `HttpException` with an intentional message:
  ```php
  // Allowed: Intentional domain message
  abort_if($locked->handled_by === (int) $validated['handled_by'], 422, 'Choose a different staff member.');
  abort_if($used, 409, 'This record is referenced and cannot be deleted.');
  ```
* **Lower-Level Exceptions**:
  **NEVER** return `$e->getMessage()` to the client in a catch block:
  ```php
  // ❌ STRICTLY FORBIDDEN: Leaks internal exception details
  catch (\Exception $e) {
      return response()->json(['message' => $e->getMessage()], 500);
  }

  // ✅ CORRECT: Let the exception bubble up to the global handler or report & rethrow
  catch (\Throwable $e) {
      report($e);
      throw $e;
  }
  ```

---

## 3. Frontend Convention (Vue 3 / TypeScript)

### Centralized Error Utility (`Frontend/src/utils/error.ts`)

Every component, view, and composable must use `parseApiError`:

```ts
import { parseApiError } from '@/utils/error'

try {
  await performAction()
} catch (cause) {
  error.value = parseApiError(cause, 'Unable to perform this action. Please try again.')
}
```

### Function Signature & Behavior

```ts
function parseApiError(
  cause: unknown,
  fallback?: string,
  options?: { logToConsole?: boolean; context?: string }
): string
```

1. **Diagnostic Logging**:
   Calls `console.error('[API Error]', errorDetails)` including HTTP method, URL, status code, and raw response payload.
2. **Technical String Detection (`isTechnicalMessage`)**:
   Automatically detects and masks raw technical patterns:
   - SQL keywords and errors (`SQLSTATE`, `Base table or view not found`, `Table '...' doesn't exist`, `select ... from`, `Duplicate entry`, `foreign key constraint`).
   - PHP / Framework exceptions (`Illuminate\`, `PDOException`, `QueryException`, `ErrorException`, `Stack trace:`, `.php on line \d+`).
   - Raw JSON objects or array dumps (`{...}`, `[...]`).
   - Low-level network codes (`ECONNREFUSED`, `ETIMEDOUT`, `AxiosError`).
3. **Response Classification Matrix**:

| Failure Condition | UI Message Returned | Console Log |
| :--- | :--- | :--- |
| **Network Failure / Offline** | *"Unable to connect to the server. Please check your internet connection."* | Logged |
| **Request Timeout** | *"The request timed out. Please check your connection and try again."* | Logged |
| **Validation Error (422)** | Flatted string of field validation messages (e.g. *"The matricule has already been taken."*) | Logged |
| **Unauthorized (401)** | Server message (if clean) or *"Your session has expired. Please sign in again."* | Logged |
| **Forbidden (403)** | Server message (if clean) or *"You do not have permission to perform this action."* | Logged |
| **Not Found (404)** | Server message (if clean) or *"The requested resource was not found."* | Logged |
| **Conflict (409)** | Safe domain message (e.g. *"This user is referenced by requests."*) or fallback | Logged |
| **Server Error (500+)** | Friendly fallback string (never raw SQL or technical dump) | Full technical details logged |

---

## 4. Anti-Patterns to Avoid

### ❌ Anti-Pattern 1: Displaying `err.message` or `err.response.data.message` Directly
```ts
// ❌ WRONG: Exposes "SQLSTATE[...]" or "Request failed with status code 500" to the UI
errorMessage.value = err instanceof Error ? err.message : 'Failed.'
errorMessage.value = err.response?.data?.message || 'Failed.'

// ✅ CORRECT: Sanitizes technical messages, handles 422/network, and logs to console
errorMessage.value = parseApiError(err, 'Failed to complete action. Please try again.')
```

### ❌ Anti-Pattern 2: Silent Swallowing in `catch {}`
```ts
// ❌ WRONG: Hides network or 500 failures from the developer console
try {
  items.value = await fetchItems()
} catch {
  error.value = 'Items could not be loaded.'
}

// ✅ CORRECT: Logs technical cause to console.error while returning user-friendly UI text
try {
  items.value = await fetchItems()
} catch (cause) {
  error.value = parseApiError(cause, 'Items could not be loaded.')
}
```

### ❌ Anti-Pattern 3: Writing Duplicate Local Message Extractors
```ts
// ❌ WRONG: Re-implementing validation and message extraction in individual components
function errorMessage(error: unknown) {
  if (isAxiosError(error)) {
    return Object.values(error.response?.data?.errors ?? {}).flat().join(' ')
  }
}

// ✅ CORRECT: Use parseApiError from '@/utils/error'
```

---

## 5. Pre-Configured Helpers & Backward Compatibility

Existing composable helpers have been unified to delegate directly to `parseApiError`:

* `studentApiMessage(cause, fallback)` in [`src/composables/useStudentRequests.ts`](file:///c:/Users/samam/OneDrive/Desktop/CampusDesk/Frontend/src/composables/useStudentRequests.ts)
* `message(e, fallback?)` in [`src/composables/admin/shared.ts`](file:///c:/Users/samam/OneDrive/Desktop/CampusDesk/Frontend/src/composables/admin/shared.ts)

When creating new composables or views, import `parseApiError` from `@/utils/error`.

---

## 6. Testing Requirements

* **Backend**:
  Every new API endpoint must have feature tests in `campusdesk/tests/Feature/` verifying that:
  - Valid requests return expected JSON shapes.
  - Invalid requests return 422 with validation errors.
  - Permission denials return 403.
  - Server/database failures do not expose SQL statements or stack traces (see [`tests/Feature/ApiExceptionHandlingTest.php`](file:///c:/Users/samam/OneDrive/Desktop/CampusDesk/campusdesk/tests/Feature/ApiExceptionHandlingTest.php)).
* **Frontend**:
  Every view or composable handling API requests must have unit tests in `Frontend/src/components/__tests__/` verifying that:
  - Error states render the user-friendly message in the alert element (`[role="alert"]`).
  - Validation errors from the API are presented cleanly.
  - Technical and 500 error mocks do not break the UI.
