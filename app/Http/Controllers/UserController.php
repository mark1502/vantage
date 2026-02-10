<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $show = $request->query('show');

        $users = DB::table('users')
        ->where('users.firm_id', $request->user()->firm_id)    // users from this firm
        // ->where('users.id', '<>', $request->user()->id)        // but not this user
        ->join('contacts','users.id', '=', 'contacts.user_id',) // include the users info from the contacts file
        ->select('users.id', 'users.name','users.email','users.user_type','contacts.title','contacts.last_name', 'contacts.middle_name','contacts.first_name',
                    'contacts.srjr','contacts.firm_role','contacts.member_initials', 'contacts.display_name', 'contacts.display_last_first' )
        ->paginate($show ? $show : 10);

        return Inertia::render('Users/Index', compact('users'));        
    }


    public function create()
    {
        return inertia::render('Users/Create');
    }


    public function store(Request $request)
    {
        $user = $request->user();

        $verified = $request->validate(
            [   'title' => ['required', Rule::in(['Mr.', 'Ms.', 'Mrs.', 'Miss', 'Dr.', 'Hon.', 'Co.'])],
                'first_name' => 'required|max:255',
                'middle_name' => 'nullable|max:255',
                'last_name' => 'required|max:255',
                'srjr' => 'nullable|max:255',
                'member_initials' => ['required', 'max:4', Rule::unique('contacts')->where('firm_id', $user->firm_id)],
                'email' => ['required', 'email', 'max:255', Rule::unique('users')],
                'firm_role' => ['required', Rule::in(['Attorney', 'Paralegal', 'Clerical'])],
                'user_type' => ['required', Rule::in(['Standard', 'Admin'])],
                'work_phone' => 'nullable|max:255',
                'cell_phone' => 'nullable|max:255',
                'home_phone' => 'nullable|max:255',
                'password' => 'required|confirmed|max:255|min:8',
                'password_confirmation' => 'required|max:255|min:8',

                'display_name' => ['required', 'max:255', Rule::unique('contacts')->where('firm_id', $user->firm_id)],
                'display_last_first' => 'nullable|max:255',
            ],
            [
                'display_name' => 'This name is taken by another user in your firm.',
                'member_initials' => 'These initials are taken by another user in your firm.',
            ]);

            $aUser = new User;
            $aUser->name = $verified['display_name'];
            $aUser->email = $verified['email'];
            $aUser->firm_id = $user->firm_id;       // this user's firm id (from the user making the request)
            $aUser->user_type = $verified['user_type'];
            $aUser->password = Hash::make($verified['password']);
            $aUser->save();

            $contact = new Contact;

            $contact->is_firm_member = true;
            $contact->user_id = $aUser->id;         // this contact's user id
            $contact->firm_id = $user->firm_id;     // this contact's firm id (from the user making the request)
            $contact->member_initials = $verified['member_initials'];
            $contact->firm_role = $verified['firm_role'];
            $contact->email = $verified['email'];

            $contact->title = $request->title;
            $contact->first_name = $request->first_name;
            $contact->middle_name = $request->middle_name;
            $contact->last_name = $request->last_name;
            $contact->srjr = $request->srjr;
            $contact->work_phone = $request->work_phone;
            $contact->cell_phone = $request->cell_phone;
            $contact->home_phone = $request->home_phone;

            $contact->display_name = $request->display_name;
            $contact->display_last_first = $request->display_last_first;

            $contact->save();

            return redirect('/users?page=' . $request->current_page . '&show=' . $request->show);
    }




    public function edit(User $user)
    {
        $contact = Contact::where('user_id',$user->id)->first();

        return Inertia::render('Users/Edit', [
            'contact' => [
                'title' => $contact->title,
                'last_name' => $contact->last_name,
                'first_name' => $contact->first_name,
                'middle_name' => $contact->middle_name,                
                'srjr' => $contact->srjr,
                'member_initials' => $contact->member_initials,
                'firm_role' => $contact->firm_role,
                'home_phone' => $contact->home_phone,
                'work_phone' => $contact->work_phone,
                'cell_phone' => $contact->cell_phone,
            ],
            'user' => [
                'user_type' => $user->user_type,
                'email' => $user->email,
                'id' => $user->id,
            ]
        ]);        
    }


    public function update(Request $request, User $user)
    {
        $contact = Contact::where('user_id',$user->id)
            ->where('firm_id', $user->firm_id)
            ->firstOrFail();

        $verified3 = $request->validate(
            [
                'title' => ['required', Rule::in(['Mr.', 'Ms.', 'Mrs.', 'Miss', 'Dr.', 'Hon.'])],
                'first_name' => 'required|max:255',
                'middle_name' => 'nullable|max:255',
                'last_name' => 'required|max:255',
                'srjr' => 'nullable|max:255',
                'member_initials' => ['required', 'max:4', Rule::unique('contacts')->where('firm_id', $user->firm_id)->ignore($contact->id)],
                'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
                'firm_role' => ['required', Rule::in(['Attorney', 'Paralegal', 'Clerical'])],
                'user_type' => ['required', Rule::in(['Standard', 'Admin'])],
                'work_phone' => 'nullable|max:255',
                'home_phone' => 'nullable|max:255',
                'cell_phone' => 'nullable|max:255',
                'display_name' => ['required', 'max:255', Rule::unique('contacts')->where('firm_id', $user->firm_id)->ignore($contact->id)],
                'display_last_first' => 'nullable|max:255',
            ],
            [
                'display_name' => 'This name is taken by another user in your firm.',
                'member_initials' => 'These initials are taken by another user in your firm.',
            ]
        );

        if($request->change_password == true) {
            $verified_pw = $request->validate(
                [
                 'password' => 'required|confirmed|max:255|min:8',
                 'password_confirmation' => 'required|max:255|min:8',
                ]
            );
        }

        // Confirm there's still an admin
        if($user->user_type == 'Admin' && $request->user_type != 'Admin') {     // was this user and admin before, but not now requested
            $otherAdmins = User::where('firm_id', $user->firm_id)               // are there other admins for this firm?
                ->where('user_type','Admin')        
                ->where('id', '<>', $user->id)
                ->first();

            if(!$otherAdmins) {         // no other admins, so create a validation error
                $verify_admin = $request->validate(
                    [ 'user_type' => 'max:1'],
                    [ 'user_type' => 'At least 1 Admin is required for each firm' ]
                );
            }
        }

        if( $request->change_email == true ) {          // did the name or email change?
            $user->email = $request->email;
        }

        if($request->change_password == true) {         // was the password changed?
            $user->password = Hash::make($verified_pw['password']);
        }

        $user->name = $request->display_name;
        $user->user_type = $request->user_type;
        $user->save();

        // now, update the users contact record
        $contact->title = $request->title;
        $contact->first_name = $request->first_name;
        $contact->middle_name = $request->middle_name;
        $contact->last_name = $request->last_name;
        $contact->srjr = $request->srjr;

        $contact->display_name = $request->display_name;
        $contact->display_last_first = $request->display_last_first;

        $contact->member_initials = $request->member_initials;
        $contact->email = $request->email;
        $contact->firm_role = $request->firm_role;
        $contact->home_phone = $request->home_phone;
        $contact->work_phone = $request->work_phone;
        $contact->cell_phone = $request->cell_phone;

    
        $contact->save();

        return redirect('/users?page=' . $request->current_page . '&show=' . $request->show);

    } // end function update

} // end class
