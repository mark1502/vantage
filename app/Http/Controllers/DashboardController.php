<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $user_contact = $user->contact;
        $user_contact_id = $user_contact ? $user_contact->id : null;

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

        $memoCount = Entry::where('firm_id', $user->firm_id)
            ->where('folder_id', 5)
            ->where('to_contact_id', $user_contact_id)
            ->whereNull('date2')
            ->count();
        if ($memoCount == 0) {
            $msg_memo = 'You have no unread memos.';
        } elseif ($memoCount == 1) {
            $msg_memo = 'You have '.$memoCount.' memo which you have not read.';
        } elseif ($memoCount > 1) {
            $msg_memo = 'You have '.$memoCount.' memos which you have not read.';
        }

        return Inertia::render('Dashboard', [
            'msg_events' => $msg_events,
            'msg_dueFrom' => $msg_dueFrom,
            'msg_dueTo' => $msg_dueTo,
            'msg_todo' => $msg_todo,
            'msg_phone' => $msg_phone,
            'msg_memo' => $msg_memo,
            'theme_preference' => session('theme_preference'),
        ]);
    }
}
