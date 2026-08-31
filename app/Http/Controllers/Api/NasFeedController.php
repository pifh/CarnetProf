<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogbookEntry;
use App\Models\PersonalBirthday;
use App\Models\Reminder;
use App\Models\Student;
use App\Models\User;
use App\Services\CalendarItemCollector;
use App\Support\CalendarCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Publicly reachable (no Filament panel session) read-only JSON endpoints for
 * an external automation (a NAS running N8N) to pull today's birthdays,
 * schedule and pending actions for automatic printing. Same access-control
 * idiom as CalendarFeedController: an opaque per-teacher token is the only
 * auth, looked up with withoutGlobalScopes() since there's no session —
 * every query below must therefore scope by user_id explicitly,
 * BelongsToTeacher's global scope is a no-op without an authenticated user.
 *
 * Every endpoint accepts the token two ways, registered as two route groups
 * in routes/api.php pointing at the same methods: in the URL path (simplest,
 * works with any HTTP client) or as `Authorization: Bearer <token>` (no
 * secret in the URL/logs) — whichever a given client supports.
 */
class NasFeedController extends Controller
{
    public function birthdays(Request $request, ?string $token = null): JsonResponse
    {
        $user = $this->resolveUser($request, $token);
        $today = Carbon::today();

        $students = Student::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->whereNotNull('birth_date')
            ->whereMonth('birth_date', $today->month)
            ->whereDay('birth_date', $today->day)
            ->with('schoolClass')
            ->get()
            ->map(fn (Student $student) => [
                'type' => 'eleve',
                'name' => $student->full_name,
                'class' => $student->schoolClass?->name,
                'age' => (int) round($student->birth_date->diffInYears($today)),
            ]);

        $personal = PersonalBirthday::query()
            ->where('user_id', $user->id)
            ->whereMonth('date', $today->month)
            ->whereDay('date', $today->day)
            ->get()
            ->map(fn (PersonalBirthday $birthday) => [
                'type' => 'personnel',
                'name' => $birthday->name,
                'age' => (int) round($birthday->date->diffInYears($today)),
                'notes' => $birthday->notes,
            ]);

        $birthdays = $students->concat($personal)->values();

        return response()->json([
            'date' => $today->toDateString(),
            'count' => $birthdays->count(),
            'birthdays' => $birthdays,
        ]);
    }

    public function schedule(Request $request, CalendarItemCollector $collector, ?string $token = null): JsonResponse
    {
        $user = $this->resolveUser($request, $token);
        $today = Carbon::today()->toDateString();

        $items = $collector->forUser($user)
            ->reject(fn ($item) => in_array($item->type, ['anniversaires_eleves', 'anniversaires_personnels'], true))
            ->filter(fn ($item) => in_array($today, $item->datesOccupied(), true))
            ->sortBy(fn ($item) => $item->startsAt->timestamp) // all-day items start at midnight, so they naturally sort first
            ->values()
            ->map(fn ($item) => [
                'type' => $item->type,
                'type_label' => CalendarCategories::label($item->type),
                'title' => $item->title,
                'description' => $item->description,
                'starts_at' => $item->allDay ? null : $item->startsAt->toIso8601String(),
                'ends_at' => $item->allDay || ! $item->endsAt ? null : $item->endsAt->toIso8601String(),
                'all_day' => $item->allDay,
            ]);

        return response()->json([
            'date' => $today,
            'count' => $items->count(),
            'schedule' => $items,
        ]);
    }

    public function reminders(Request $request, ?string $token = null): JsonResponse
    {
        $user = $this->resolveUser($request, $token);

        $reminders = Reminder::query()
            ->where('user_id', $user->id)
            ->where('is_done', false)
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->get()
            ->map(fn (Reminder $reminder) => [
                'title' => $reminder->title,
                'due_date' => $reminder->due_date?->toDateString(),
            ]);

        return response()->json([
            'date' => Carbon::today()->toDateString(),
            'count' => $reminders->count(),
            'reminders' => $reminders,
        ]);
    }

    public function homework(Request $request, ?string $token = null): JsonResponse
    {
        $user = $this->resolveUser($request, $token);
        $today = Carbon::today();

        $homework = LogbookEntry::query()
            ->where('user_id', $user->id)
            ->whereNotNull('homework')
            ->whereDate('date', $today)
            ->with(['schoolClass', 'subject'])
            ->get()
            ->map(fn (LogbookEntry $entry) => [
                'class' => $entry->schoolClass?->name,
                'subject' => $entry->subject?->name,
                'homework' => $entry->homework,
            ]);

        return response()->json([
            'date' => $today->toDateString(),
            'count' => $homework->count(),
            'homework' => $homework,
        ]);
    }

    private function resolveUser(Request $request, ?string $token): User
    {
        $token ??= $request->bearerToken();

        abort_if(blank($token), 401, 'Jeton manquant : passez-le dans l\'URL ou via Authorization: Bearer.');

        return User::withoutGlobalScopes()->where('api_token', $token)->firstOrFail();
    }
}
