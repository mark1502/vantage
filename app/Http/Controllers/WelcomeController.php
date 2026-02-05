<?php

namespace App\Http\Controllers;

use App\Models\Firm;
use App\Models\User;
use Inertia\Inertia;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class WelcomeController extends Controller
{
    public function welcome_admin( Request $request ) {
        $vals = $this->getWelcomeVals();
        return Inertia::render('WelcomeAdmin', $vals);
    }


    public function welcome_user( Request $request ) {
        dd('welcome_user');
    }

    public function postWelcomeAdmin(Request $request)
    {
        $user = $request->user();

        $firm = Firm::findOrFail($user->firm_id);  // find the logged in user->firm_id or fail

            // if 'firm' form request, then verify and save the firm information
        if ($request['formtype'] == 'firm') {
            $verified = $request->validate([
                'name' => 'required|max:255',
                'address' => 'required|max:255',
                'phone' => 'required|max:30',
            ]);

            $firm->name = $verified['name'];
            $firm->address = $verified['address'];
            $firm->phone = $verified['phone'];
            $firm->save();
        }
            // else if user form request, validate and save the user's info (into the contacts table)
            // note: initials and diplsay name must be unique (within the firm)
            // also note display name and display_last_first are not displayed on the form, but are created client-side
        else if ($request['formtype'] == 'user') {

            $verified2 = $request->validate(
                [
                    'title' => ['required', Rule::in(['Mr.', 'Ms.', 'Mrs.', 'Miss', 'Dr.', 'Hon.'])],
                    'first_name' => 'required|max:255',
                    'middle_name' => 'nullable|max:255',
                    'last_name' => 'required|max:255',
                    'srjr' => 'nullable|max:255',
                    'firm_role' => ['required', Rule::in(['Attorney', 'Paralegal', 'Clerical'])],
                    'member_initials' => ['required', 'max:4', Rule::unique('contacts')->where('firm_id', $user->firm_id)->where('user_id', '<>', $user->id)],
                    'work_phone' => 'nullable|max:255',
                    'home_phone' => 'nullable|max:255',
                    'cell_phone' => 'nullable|max:255',
                    'display_name' => ['required', Rule::unique('contacts')->where('firm_id', $user->firm_id)->where('user_id', '<>', $user->id),],
                    'display_last_first' => 'max:255',
                ],
                [
                    'display_name' => 'This name is taken by another user in your firm.',
                    'member_initials' => 'These initials are taken by another user in your firm.',
                ]
            );

            $contact = Contact::firstOrNew(['user_id' => $user->id]);  // find this user's entry or create one

            $contact->title = $verified2['title'];
            $contact->first_name = $verified2['first_name'];
            $contact->middle_name = array_key_exists('middle_name', $verified2) ? $verified2['middle_name'] : ''; //nullable, so check if key exists
            $contact->last_name = $verified2['last_name'];
            $contact->srjr = array_key_exists('srjr', $verified2) ? $verified2['srjr'] : '';
            $contact->firm_role = $verified2['firm_role'];
            $contact->member_initials = $verified2['member_initials'];
            $contact->work_phone = array_key_exists('work_phone', $verified2) ? $verified2['work_phone'] : '';
            $contact->home_phone = array_key_exists('home_phone', $verified2) ? $verified2['home_phone'] : '';
            $contact->cell_phone = array_key_exists('cell_phone', $verified2) ? $verified2['cell_phone'] : '';
            $contact->display_name = $verified2['display_name'];
            $contact->display_last_first = $verified2['display_last_first'];
                // now add firm and user info
            $contact->is_firm_member = true;    // is a firm member
            $contact->user_id = $user->id;      // the firm member's user_id
            $contact->email = $user->email;     // copy the user email to the contact
            $contact->firm_id = $firm->id;      // the firmm_id

            $contact->save();
        } // end elseif formtype = user
            // else, if the admin is adding another user
        else if ($request['formtype'] == 'addUser') {

            $verified3 = $request->validate(
                [
                    'title' => ['required', Rule::in(['Mr.', 'Ms.', 'Mrs.', 'Miss', 'Dr.', 'Hon.'])],
                    'first_name' => 'required|max:255',
                    'middle_name' => 'nullable|max:255',
                    'last_name' => 'required|max:255',
                    'srjr' => 'nullable|max:255',
                    'member_initials' => ['required', 'max:4', Rule::unique('contacts')->where('firm_id', $user->firm_id)],
                    'email' => 'required|email|max:255|unique:users',
                    'firm_role' => ['required', Rule::in(['Attorney', 'Paralegal', 'Clerical'])],
                    'user_type' => ['required', Rule::in(['Standard', 'Admin'])],
                    'work_phone' => 'nullable|max:255',
                    'home_phone' => 'nullable|max:255',
                    'cell_phone' => 'nullable|max:255',
                    'password' => 'required|confirmed|max:255|min:8',
                    'password_confirmation' => 'required|max:255|min:8',

                    'display_name' => ['required', 'max:255', Rule::unique('contacts')->where('firm_id', $user->firm_id)],
                    'display_last_first' => 'nullable|max:255',
                ],
                [
                    'display_name' => 'This name is taken by another user in your firm.',
                    'member_initials' => 'These initials are taken by another user in your firm.',
                ]
            );

            $aUser = new User;
            $aUser->name = $verified3['display_name'];
            $aUser->email = $verified3['email'];
            $aUser->firm_id = $user->firm_id;
            $aUser->user_type = $verified3['user_type'];
            $aUser->password = Hash::make($verified3['password']);
            $aUser->save();

            $aContact = new Contact;
            $aContact->title = $verified3['title'];
            $aContact->first_name = $verified3['first_name'];
            $aContact->middle_name = array_key_exists('middle_name', $verified3) && $verified3['middle_name'] ? $verified3['middle_name'] : '';
            $aContact->last_name = $verified3['last_name'];
            $aContact->srjr = array_key_exists('srjr', $verified3) && $verified3['srjr'] ? ' ' . $verified3['srjr'] : '';
            $aContact->member_initials = $verified3['member_initials'];
            $aContact->email = $verified3['email'];
            $aContact->is_firm_member = true;
            $aContact->firm_id = $user->firm_id;
            $aContact->user_id = $aUser->id;
            $aContact->firm_role = $verified3['firm_role'];
            $aContact->display_name = $verified3['display_name'];
            $aContact->display_last_first = $verified3['display_last_first'];
            $aContact->save();
        }
        else if ($request['formtype'] == 'editUser') {

            $verified3 = $request->validate(
                [
                    'title' => ['required', Rule::in(['Mr.', 'Ms.', 'Mrs.', 'Miss', 'Dr.', 'Hon.'])],
                    'first_name' => 'required|max:255',
                    'middle_name' => 'nullable|max:255',
                    'last_name' => 'required|max:255',
                    'srjr' => 'nullable|max:255',
                    'member_initials' => ['required', 'max:4'],
                    'email' => 'required|email|max:255',
                    'firm_role' => ['required', Rule::in(['Attorney', 'Paralegal', 'Clerical'])],
                    'user_type' => ['required', Rule::in(['Standard', 'Admin'])],
                    'work_phone' => 'nullable|max:255',
                    'home_phone' => 'nullable|max:255',
                    'cell_phone' => 'nullable|max:255',
                    'display_name' => 'required|max:255',
                    'display_last_first' => 'required|max:255',
                ],
                [
                    'display_name' => 'This name is taken by another user in your firm.',
                    'member_initials' => 'These initials are taken by another user in your firm.',
                ]
            );
                
            if( $request['change_password'] == true ) {     // if the password was changed, validate the passwords
                $verified_pass = $request->validate(
                    [
                        'password' => 'required|confirmed|max:255|min:8',
                        'password_confirmation' => 'required|max:255|min:8',
                    ]
                );
            }

            $foundUser = User::where('email', $verified3['email'])->firstOrFail();
            $foundContact = Contact::where('user_id', $foundUser->id)->firstOrFail();

            $conflict_Initials = Contact::where('member_initials', $verified3['member_initials'])
                ->where('firm_id', $foundUser->firm_id)
                ->where('user_id', '<>', $foundUser->id)
                ->first();

            $conflict_DisplayName = Contact::where('display_name', $verified3['display_name'])
                ->where('firm_id', $foundUser->firm_id)
                ->where('user_id', '<>', $foundUser->id)
                ->first();

            $conflicted = false;

            if ($conflict_Initials) {
                $ruleval['member_initials'] = 'max:1';
                $messval['member_initials'] =  'These initials are taken by another user in the firm.';
                $conflicted = true;
            }

            if ($conflict_DisplayName) {
                $ruleval['display_name'] = 'max:1';
                $messval['display_name'] =  'This user name is taken by another user in the firm.';
                $conflicted = true;
            }

            if ($conflicted) {
                $verified4 = $request->validate($ruleval, $messval);
            }

            $foundContact->title = $verified3['title'];
            $foundContact->first_name = $verified3['first_name'];
            $foundContact->middle_name = array_key_exists('middle_name', $verified3) ? $verified3['middle_name'] : ''; //nullable, so check if key exists
            $foundContact->last_name = $verified3['last_name'];
            $foundContact->srjr = array_key_exists('srjr', $verified3) ? $verified3['srjr'] : '';
            $foundContact->member_initials = $verified3['member_initials'];
            $foundContact->firm_role = $verified3['firm_role'];
            // $foundContact->user_type = $verified3['user_type'];
            $foundContact->display_name = $verified3['display_name'];
            $foundContact->display_last_first = $verified3['display_last_first'];

            $foundContact->save();

            $foundUser->name = $verified3['display_name'];
            if($request['change_password'] == true) {
                $foundUser->password = Hash::make($verified_pass['password']);
            }
            $foundUser->user_type = $request->user_type;
            $foundUser->save();
        }

        $vals2send = $this->getWelcomeVals();    // get the values (an array) to send to the vue

        return Inertia::render('WelcomeAdmin', $vals2send);
    } // end postWelcomeAdmin


    public function doneWelcomeAdmin() {

        $user = User::findOrFail(Auth::id());

        $user->welcomed = true;
        $user->save();

        return Inertia::render('Dashboard');
    }


    public function getWelcomeVals() {
        $user = Auth::user();
        $firm = Firm::where( 'id', $user->firm_id )->first();
        $contact = Contact::where( 'user_id', $user->id )->first();

        $vals = [];
        $vals['firm_name'] = $firm->name;
        $vals['firm_address'] = $firm->address;
        $vals['firm_phone'] = $firm->phone;
        $vals['user_email'] = $user->email;
        $vals['contact_title'] = $contact ? $contact->title : '';
        $vals['contact_first_name'] = $contact ? $contact->first_name : '';
        $vals['contact_middle_name'] = $contact ? $contact->middle_name : '';
        $vals['contact_last_name'] = $contact ? $contact->last_name : '';
        $vals['contact_srjr'] = $contact ? $contact->srjr : '';
        $vals['contact_firm_role'] = $contact ? $contact->firm_role : '';
        $vals['contact_user_type'] = $contact ? $contact->user_type : '';
        $vals['contact_member_initials'] = $contact ? $contact->member_initials : '';
        $vals['contact_address'] = $contact ? $contact->address : '';
        $vals['contact_work_phone'] = $contact ? $contact->work_phone : '';
        $vals['contact_home_phone'] = $contact ? $contact->home_phone : '';
        $vals['contact_cell_phone'] = $contact ? $contact->cell_phone : '';
    
        $vals['contact_display_name'] = $contact ? $contact->display_name : '';
        $vals['contact_display_last_first'] = $contact ? $contact->display_last_first : '';

        return $vals;
    }


}
