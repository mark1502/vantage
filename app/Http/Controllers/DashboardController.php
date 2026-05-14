<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\File;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $user_contact = $user->contact;
        $user_contact_id = $user_contact ? $user_contact->id : null;

        // count events for this user
        $eventCount = Entry::where('firm_id', $user->firm_id)
            ->where('folder_id', 6)
            ->where('to_contact_id', $user_contact_id)
            ->whereBetween('date1', [today()->startOfDay(), today()->endOfDay()])
            ->count();
        if ($eventCount == 0) {
            $msg_events = 'You have no events on your calendar today.';
        } elseif ($eventCount == 1) {
            $msg_events = 'You have '.$eventCount.' event on your calendar today.';
        } elseif ($eventCount > 1) {
            $msg_events = 'You have '.$eventCount.' events on your calendar today.';
        }

        // count entries due from this user
        $dueFromCount = Entry::where('firm_id', $user->firm_id)
            ->where('expecting_response', true)
            ->where('to_contact_id', $user_contact_id)
            ->count();
        if ($dueFromCount == 0) {
            $msg_dueFrom = 'There are no entries expecting your response.';
        } elseif ($dueFromCount == 1) {
            $msg_dueFrom = 'There is '.$dueFromCount.' entry expecting your response.';
        } elseif ($dueFromCount > 1) {
            $msg_dueFrom = 'There are '.$dueFromCount.' entries expecting your response.';
        }

        // count entries due to this user
        $dueToCount = Entry::where('firm_id', $user->firm_id)
            ->where('expecting_response', true)
            ->where('from_contact_id', $user_contact_id)
            ->count();
        if ($dueToCount == 0) {
            $msg_dueTo = 'You are not expecting any responses due.';
        } elseif ($dueToCount == 1) {
            $msg_dueTo = 'You are expecting '.$dueToCount.' response that is due.';
        } elseif ($dueToCount > 1) {
            $msg_dueTo = 'You are expecting '.$dueToCount.' responses that are due.';
        }

        // count todo for this user
        $todoCount = Entry::where('firm_id', $user->firm_id)
            ->where('folder_id', 7)
            ->where('from_contact_id', $user_contact_id)
            ->whereNull('date2')
            ->count();
        if ($todoCount == 0) {
            $msg_todo = 'You have no pending To-Do entries.';
        } elseif ($todoCount == 1) {
            $msg_todo = 'You have '.$todoCount.' To-Do entry which has not been completed.';
        } elseif ($todoCount > 1) {
            $msg_todo = 'You have '.$todoCount.' To-Do entries which have not been completed.';
        }

        // count phone for this user
        $phoneCount = Entry::where('firm_id', $user->firm_id)
            ->where('folder_id', 8)
            ->where('to_contact_id', $user_contact_id)
            ->whereNull('date2')
            ->count();
        if ($phoneCount == 1) {
            $msg_phone = 'You have '.$phoneCount.' unread phone message.';
        } else {
            $msg_phone = 'You have '.$phoneCount.' unread phone messages.';
        }

        // count unread memos to this user
        $memoCount = Entry::where('firm_id', $user->firm_id)
            ->where('folder_id', 5)
            ->where('to_contact_id', $user_contact_id)
            ->whereNull('date2')    // unread
            ->count();
        if ($memoCount == 0) {
            $msg_memo = 'You have no unread memos.';
        } elseif ($memoCount == 1) {
            $msg_memo = 'You have '.$memoCount.' memo which you have not read.';
        } elseif ($memoCount > 1) {
            $msg_memo = 'You have '.$memoCount.' memos which you have not read.';
        }

        // starting SOL stuff
        $firmId = $user->firm_id;
        $isAttorney = $user_contact?->firm_role === 'Attorney';  // is this user an attorney? t/f
        $today = today();
        $in90 = today()->copy()->addDays(90);

        $buildSolBuckets = function (?int $attorneyContactId) use ($firmId, $today, $in90): array {
            $base = function () use ($firmId, $attorneyContactId) {
                $q = File::query()
                    ->where('firm_id', $firmId)
                    ->select(['id', 'name', 'date_sol', 'date_filed']);

                if ($attorneyContactId !== null) {
                    $q->whereHas('assignedAttorney', fn ($a) => $a->where('contact_id', $attorneyContactId));
                }

                return $q;
            };

            $next90 = $base()
                ->whereBetween('date_sol', [$today, $in90])
                ->orderBy('date_sol')
                ->get(['id', 'name', 'date_sol', 'date_filed']);

            $next90Unfiled = $next90->whereNull('date_filed')->values();

            $expired = $base()
                ->whereNotNull('date_sol')
                ->where('date_sol', '<', $today)
                ->where(fn ($w) => $w->whereNull('date_filed')->orWhereColumn('date_filed', '>', 'date_sol'))
                ->orderBy('date_sol')
                ->get(['id', 'name', 'date_sol']);

            $unspecified = $base()
                ->whereNull('date_sol')
                ->whereHas('filetype', fn ($f) => $f->where('enable_file_SOL', true))
                ->orderBy('name')
                ->get(['id', 'name', 'date_sol']);

            $project = fn ($collection) => $collection->map(fn ($f) => [
                'name' => $f->name,
                'date_sol' => $f->date_sol,
            ])->values();

            return [
                'next90' => $project($next90),
                'next90_unfiled' => $project($next90Unfiled),
                'expired' => $project($expired),
                'unspecified' => $project($unspecified),
            ];
        };

        $buildSolSummary = function (?int $attorneyContactId) use ($buildSolBuckets): array {
            $buckets = $buildSolBuckets($attorneyContactId);

            return [
                'next90_total' => count($buckets['next90']),
                'next90_unfiled' => count($buckets['next90_unfiled']),
                'expired' => count($buckets['expired']),
                'unspecified' => count($buckets['unspecified']),
                'files' => $buckets,
            ];
        };

        $solSummary = [
            'is_attorney' => $isAttorney,
            'your_files' => $isAttorney ? $buildSolSummary($user_contact_id) : null,
            'all_files' => $buildSolSummary(null),
        ];

        return Inertia::render('Dashboard', [
            'msg_events' => $msg_events,
            'msg_dueFrom' => $msg_dueFrom,
            'msg_dueTo' => $msg_dueTo,
            'msg_todo' => $msg_todo,
            'msg_phone' => $msg_phone,
            'msg_memo' => $msg_memo,
            'member_initials' => $user_contact?->member_initials,
            'user_contact_id' => $user_contact_id,
            'theme_preference' => session('theme_preference'),
            'sol_summary' => $solSummary,
        ]);
    }
}
