<?php

namespace App\Http\Controllers;

use App\Models\Pref_default;
use App\Models\Preference;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PreferenceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, User $user)
    {
        abort_unless($this->canManagePreferencesFor($request->user(), $user->id, $user->firm_id), 403);

        $user_initials = $user->contact->member_initials;

        $defaults = Pref_default::all();    // get all default preferences
        $userprefs = Preference::where('user_id', $user->id)->get();   // get the user preferences

        $added_preference = false;

        // for each default preference, check to see if there's a matching user preference.  If not, add the user preference.
        foreach ($defaults as $default) {
            if ($userprefs->count() > 0) {       // if we have any user preferences, filter the list to look for the default
                $thepref = $userprefs->where('pref_default_id', '=', $default->id);
            } else {
                $thepref = collect([]);
            }      // else, use an empty collection

            if ($thepref->count() == 0) {      // no matching user preference found, so add it
                $newpref = Preference::create([
                    'pref_default_id' => $default->id,
                    'user_id' => $user->id,
                    'firm_id' => $user->firm->id,
                    'name' => $default->name,
                    'prompt' => $default->prompt,
                    'setting' => $default->setting,
                ]);

                $added_preference = true;
            }
        } // end foreach

        if ($added_preference == true) {   // if we added a user preference, get all the user preferences again
            $preferences = Preference::where('user_id', $user->id)->get();
        } else {                            // else, just use the existing user preferences
            $preferences = $userprefs;
        }

        $themePref = Preference::where('user_id', $user->id)->where('name', 'theme')->first();
        $userTheme = $themePref ? $themePref->setting : 'light';

        return Inertia::render('Preferences/Index', [
            'preferences' => $preferences,
            'user_id' => $user->id,
            'user_initials' => $user_initials,
            'user_theme' => $userTheme,
        ]);
    }

    public function eventcolor_update(Request $request)
    {   // dd($request);
        $validated = $request->validate(['user_id' => 'numeric|integer|required',
            'event_bg' => 'string|max:25|required',
            'event_text' => 'string|max:25|required',
        ]);
        if ($this->canManagePreferencesFor($request->user(), (int) $request->user_id)) {

            // get the background color pref and update it
            $thepref = Preference::where('user_id', $request->user_id)
                ->where('name', 'event_bg')
                ->first();
            if ($thepref) {
                $thepref->setting = $request->event_bg;
                $thepref->save();
            } else {

            }

            // get the text color pref and update it
            $thepref = Preference::where('user_id', $request->user_id)
                ->where('name', 'event_text')
                ->first();
            if ($thepref) {
                $thepref->setting = $request->event_text;
                $thepref->save();
            } // end if thepref
        } // end if user
    }

    public function hover_placement_update(Request $request): void
    {
        $validated = $request->validate([
            'user_id' => 'numeric|integer|required',
            'event_hover_placement' => 'required|string|in:upper_right,near_cursor',
        ]);

        if ($this->canManagePreferencesFor($request->user(), (int) $request->user_id)) {
            $thepref = Preference::where('user_id', $request->user_id)
                ->where('name', 'event_hover_placement')
                ->first();

            if ($thepref) {
                $thepref->setting = $request->event_hover_placement;
                $thepref->save();
            }
        }
    }

    public function file_open_update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'numeric|integer|required',
            'file_open_to' => 'string|required|in:correspondence,pleadings,discovery,documents,memos,events,todo,phone,medrecs,medbills,costs,all,info',
            'file_recent_spot' => 'string|required|in:true,false',
        ]);

        if ($this->canManagePreferencesFor($request->user(), (int) $request->user_id)) {
            Preference::where('user_id', $request->user_id)
                ->where('name', 'file_open_to')
                ->update(['setting' => $request->file_open_to]);

            Preference::where('user_id', $request->user_id)
                ->where('name', 'file_recent_spot')
                ->update(['setting' => $request->file_recent_spot]);
        }

        return redirect()->back();
    }

    public function theme_update(Request $request): void
    {
        $validated = $request->validate([
            'user_id' => 'numeric|integer|required',
            'theme' => 'required|string|max:50',
        ]);

        if ($this->canManagePreferencesFor($request->user(), (int) $request->user_id)) {
            Preference::where('user_id', $request->user_id)
                ->where('name', 'theme')
                ->update(['setting' => $request->theme]);
        }
    }

    /**
     * Determine whether the actor may view/manage the target user's preferences:
     * either it is their own account, or they are an Admin in the same firm.
     */
    private function canManagePreferencesFor(User $actor, int $targetUserId, ?int $targetFirmId = null): bool
    {
        if ($actor->id === $targetUserId) {
            return true;
        }

        if (! $actor->isAdmin()) {
            return false;
        }

        $targetFirmId ??= User::where('id', $targetUserId)->value('firm_id');

        return $targetFirmId !== null && $targetFirmId === $actor->firm_id;
    }
} // end class
