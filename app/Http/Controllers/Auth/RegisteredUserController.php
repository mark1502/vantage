<?php

namespace App\Http\Controllers\Auth;

use App\Models\Firm;
use App\Models\User;
use App\Models\Role;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Filetype;
use App\Models\Entrytype;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Auth\Events\Registered;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([                    // validate request, including unique firm name and email ( for firm & user )
            'firm_name' => 'required|string|max:100|unique:App\Models\Firm,name',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:App\Models\User,email|unique:App\Models\Firm,email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

            // Create the New Law Firm
        $new_firm = new Firm;
        $new_firm->name = $request->firm_name;
        $new_firm->email = $request->email;
        $new_firm->save();

            // Create base casefiletypes for this law firm
        $base_filetypes = Filetype::where( 'firm_id', 1 )->orderBy('name')->get();  // get base filetypes from firm 1

        foreach ( $base_filetypes as $base_filetype ) {                                 // for each base type, create a copy for this firm
            $new_filetype = new Filetype;

            $new_filetype->firm_id = $new_firm->id;                                     // use the new_firm id for each file type

            $new_filetype->name = $base_filetype->name;
            $new_filetype->has_correspondence = $base_filetype->has_correspondence;
            $new_filetype->has_pleadings = $base_filetype->has_pleadings;
            $new_filetype->has_discovery = $base_filetype->has_discovery;
            $new_filetype->has_documents = $base_filetype->has_documents;
            $new_filetype->has_memos = $base_filetype->has_memos;
            $new_filetype->has_events = $base_filetype->has_events;
            $new_filetype->has_todo = $base_filetype->has_todo;
            $new_filetype->has_phone = $base_filetype->has_phone;
            $new_filetype->has_medrecs = $base_filetype->has_medrecs;
            $new_filetype->has_medbills = $base_filetype->has_medbills;
            $new_filetype->has_costs = $base_filetype->has_costs;
            $new_filetype->enable_file_SOL = $base_filetype->enable_file_SOL;
            $new_filetype->set_as_default = $base_filetype->set_as_default;

            $new_filetype->save();
        }

            // Create base entrytypes for this law firm
        $base_entrytypes = Entrytype::where( 'firm_id', 1 )->get();

        foreach ( $base_entrytypes as $base_entrytype ) {
            $new_entrytype = new Entrytype;                             // create a new copy for the new firm
            $new_entrytype->firm_id = $new_firm->id;                    // use new_firm id for each type
            $new_entrytype->name = $base_entrytype->name;
            $new_entrytype->folder_id = $base_entrytype->folder_id;
            $new_entrytype->save();
        }

            // Create base roles for this law firm
        $base_roles = Role::where( 'firm_id', 1 )->get();

        foreach ( $base_roles as $base_role ) {
            $new_role = new Role;
            $new_role->firm_id = $new_firm->id;
            $new_role->name = $base_role->name;
            $new_role->save();
        }

            // now, create the new user and make an 'Admin' user for the new firm
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => 'Admin', // Firm Administrator
            'firm_id' => $new_firm->id,
        ]);

        //
        // Note: SKIP this mailer issue for now
        // event(new Registered($user));
        //

        Auth::login($user);

        return redirect('/dashboard');
    }
}
