# Diagnostic Log Output Schema

Below is an authentic, high-fidelity log segment output showing how the **Laravel Agent-Debugger** packages and prints request contexts to files:

```text
================================================================================
[2026-05-22 10:35:12] REQUEST: POST http://localhost:8000/student/tests/4/submit
IP: 127.0.0.1 | Auth: User #14 (student: john@example.com) | Execution: 114.82ms | Memory Peak: 14.25 MB
Route Action: App\Http\Livewire\Student\Tests\TestRunner@submitAnswers
Referer: http://localhost:8000/student/tests/4/review

⚠️ WARNING: LOCAL ENVIRONMENT CONFIGURATION DRIFT DETECTED!
  - SESSION_DRIVER changed from 'file' to 'redis'
  - QUEUE_CONNECTION changed from 'sync' to 'database'

- INCOMING REQUEST HEADERS:
  * Content-Type: application/json
  * Stripe-Signature: t=1672531199,v1=24ef...

- SESSION BREADCRUMB TRAIL:
  1. [GET 200] /student/dashboard
  2. [GET 200] /student/tests/4/instructions
  3. [GET 200] /student/tests/4/attempt
  4. [GET 200] /student/tests/4/review
  5. [POST 500] /student/tests/4/submit (CRASHED)

- ACTIVE SESSION STATE:
  * test_attempt_id => 82
  * active_step => 5

- AUTHORIZATION GATES & POLICIES:
  * [ALLOWED] view-test (Arguments: App\Models\Test #4)
  * [DENIED] update-test (Arguments: App\Models\Test #4)

- INCOMING REQUEST PAYLOAD:
  {
    "answers": {
      "question_12": "ans_b",
      "question_13": null
    },
    "force_submit": false,
    "secret_token": "[REDACTED]"
  }

- INERTIA STATE SAMPLES:
  * students => [
      { "id": 1, "name": "John Doe", "email": "john@example.com" },
      { "id": 2, "name": "Jane Smith", "email": "jane@example.com" },
      "... [truncated 98 more items; total collection count: 100]"
    ]

- DATABASE TRANSACTION & QUERIES:
  * [0.00ms] DB::beginTransaction()
  * [1.02ms] select * from `users` where `id` = 14 limit 1
  * [2.45ms] select * from `test_attempts` where `user_id` = 14 and `test_id` = 4 limit 1
  * [SLOW QUERY (12.18ms)] update `test_attempts` set `status` = 'submitted', `completed_at` = '2026-05-22 10:35:12' where `id` = 82
  * [0.00ms] DB::rollBack() (Triggered on App\Http\Livewire\Student\Tests\TestRunner@submitAnswers line 102)

  ⚠️ WARNING: N+1 Query Detected! The following query executed 12 times:
  * select * from `questions` where `id` = ? limit 1
  👉 Solution: Eager load relationships (e.g. TestAttempt::with('questions')) in your controller.

- VIEW COMPOSITIONS:
  * components.layouts.student-layout
  * livewire.student.tests.test-runner

- OUTGOING EXTERNAL HTTP REQUESTS:
  * [POST 200 OK] https://api.stripe.com/v1/charges (Duration: 240ms)

- SIDE-EFFECT EVENTS & JOBS:
  * [Event] App\Events\StudentTestSubmitted
  * [Job] App\Jobs\CalculateTestGrades (Dispatched to 'default' queue)

============================================= [BLADE ENGINE SYNTAX CRASH] ======
CRITICAL BLADE COMPILER ERROR:
File: /resources/views/components/book-card.blade.php
Line: 12
Original Line: <p>{{ $book->author->getShortName() }}</p>
Error: Call to a member function getShortName() on null (Authored in controller context)
================================================================================
```
