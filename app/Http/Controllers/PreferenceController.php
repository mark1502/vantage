<?php

namespace App\Http\Controllers;

use App\Models\Entrytype;
use App\Models\User;
use Inertia\Inertia;
use App\Models\Preference;
use App\Models\Pref_default;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PreferenceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index( Request $request, User $user )
    {
        $user_initials = $user->contact->member_initials;

        $defaults = Pref_default::all();    // get all default preferences
        $userprefs = Preference::where('user_id', $user->id)->get();   // get the user preferences

        $added_preference = false;

            // for each default preference, check to see if there's a matching user preference.  If not, add the user preference.
        foreach ( $defaults as $default ) {
            if($userprefs->count() > 0) {   // if we have any user preferences, filter the list
                $thepref = $userprefs->where('pref_default_id', '=', $default->id);
            } else $thepref = collect([]);    // else, use an empty collection

            if( $thepref->count() == 0 ) {  // no matching user preference found, so add it
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

        if( $added_preference == true ) {   // if we added a user preference, get all the user preferences again
            $preferences = Preference::where('user_id',$user->id)->get();
        } else {    // else, just use the existing user preferences
            $preferences = $userprefs;
        }

        return Inertia::render( 'Preferences/Index', [  'preferences' => $preferences,
                                                        'user_id' => $user->id,
                                                        'user_initials' => $user_initials
                                                     ] );
    }


    public function eventcolor_update(Request $request)
    {   // dd($request);
        $validated = $request->validate([ 'user_id' => 'numeric|integer|required',
                                          'event_bg' => 'string|max:25|required',
                                          'event_text' => 'string|max:25|required',
                                        ]);
        if( $request->user()->id == $request->user_id || $request->user()->user_type == 'Admin') { // NOTE: admin needs further test for correct firm

                // get the background color pref and update it
            $thepref = Preference::where('user_id', $request->user_id)
                                   ->where('name','event_bg')
                                   ->first();
            if( $thepref ) {
                $thepref->setting = $request->event_bg;
                $thepref->save();
            } else {
                
            }

                // get the text color pref and update it
            $thepref = Preference::where('user_id', $request->user_id)
                                    ->where('name','event_text')
                                    ->first();
            if( $thepref ) {
                $thepref->setting = $request->event_text;
                $thepref->save();
            } // end if thepref
        } // end if user
    }

    // just used this to update entrytypes of the firm to be sure it has all of the defaults
    public function update_entrytypes(Request $request)
    {
        $user = auth::user();
        $defaultTypes = Entrytype::where('firm_id', 1)->get();

        foreach($defaultTypes as $defaultType ) {
            $firmtype = Entrytype::where('firm_id', $user->firm_id)->where('name', $defaultType->name)->first();
            if( !$firmtype ) {
                $newtype = new Entrytype;
                $newtype->firm_id = $user->firm_id;
                $newtype->folder_id = $defaultType->folder_id;
                $newtype->name = $defaultType->name;
                $newtype->save();
            }
        }

        dd('Done!');
    }

} // end class
