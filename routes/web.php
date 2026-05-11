<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactRoleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\EntrytypeController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FiletypeController;
use App\Http\Controllers\FirmController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecentFileController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ViewController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // dd('here');
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// welcome routes do not require welcomed middleware, or they wouldn't work
Route::middleware('auth')->group(function () {
    Route::get('/welcome_user', [WelcomeController::class, 'welcome_user'])->name('welcome_user');
    Route::get('/welcome_admin', [WelcomeController::class, 'welcome_admin'])->name('welcome_admin');
    Route::post('/welcome_admin', [WelcomeController::class, 'postWelcomeAdmin'])->name('postWelcomeAdmin');
    Route::post('/donewelcomeadmin', [WelcomeController::class, 'doneWelcomeAdmin']);
});

// all the other routes require auth and welcomed
Route::middleware('auth', 'welcomed')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
    Route::get('/dashboard', function () {           // dashboard route just renders the dashboard without a controller
        return Inertia::render('Dashboard');
    })->name('dashboard');
    */

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('users', UserController::class)->names('users');
    Route::resource('contacts', ContactController::class)->names('contacts');
    Route::patch('/contacts/{contact}/restore', [ContactController::class, 'restore'])->name('contacts.restore');
    Route::resource('files', FileController::class)->names('files');
    Route::resource('files.entries', EntryController::class)->names('entries');
    Route::resource('filetypes', FiletypeController::class)->names('filetypes');
    Route::resource('views', ViewController::class)->names('views');
    Route::resource('folders', FolderController::class)->names('folders');
    Route::get('/entrytypes', [EntrytypeController::class, 'index'])->name('entrytypes.index');
    Route::post('/entrytypes', [EntrytypeController::class, 'store'])->name('entrytypes.store');
    Route::put('/entrytypes/{entrytype}', [EntrytypeController::class, 'update'])->name('entrytypes.update');
    Route::delete('/entrytypes/{entrytype}', [EntrytypeController::class, 'destroy'])->name('entrytypes.destroy');
    Route::patch('/entrytypes/{entrytype}/restore', [EntrytypeController::class, 'restore'])->name('entrytypes.restore');

    // Contact Roles routes
    Route::get('/contact-role-ids/{file}', [ContactRoleController::class, 'getContactRoleIds'])->name('contact-roles.ids');
    Route::post('/contact-roles', [ContactRoleController::class, 'store'])->name('contact-roles.store');
    Route::put('/contact-roles/{contactRole}', [ContactRoleController::class, 'update'])->name('contact-roles.update');
    Route::delete('/contact-roles/{contactRole}', [ContactRoleController::class, 'destroy'])->name('contact-roles.destroy');

    Route::get('/users/{user}/preferences', [PreferenceController::class, 'index'])->name('preferences.index');
    Route::post('/preferences/eventcolors', [PreferenceController::class, 'eventcolor_update'])->name('preferences.eventcolors');
    Route::post('/preferences/hover_placement', [PreferenceController::class, 'hover_placement_update'])->name('preferences.hover_placement');
    Route::post('/preferences/file_open', [PreferenceController::class, 'file_open_update'])->name('preferences.file_open');
    Route::post('/preferences/theme', [PreferenceController::class, 'theme_update'])->name('preferences.theme');
    Route::get('/preferences/updateEntrytypes', [PreferenceController::class, 'update_entrytypes']);

    Route::get('/recent-files', [RecentFileController::class, 'index'])->name('recent-files.index');
    Route::post('/lookup_file', [FileController::class, 'lookup_file'])->name('files.lookup_file');
    Route::post('setDefaultFileType', [FiletypeController::class, 'set_default_type']);
    Route::put('/toggle_read/{entry}', [EntryController::class, 'toggle_read']);

    Route::post('/lookup_contact', [EntryController::class, 'lookup_contact'])->name('entries.lookup_contact');
    Route::post('/contact_add_modal', [EntryController::class, 'contact_add_modal'])->name('entries.add_modal');
    Route::get('/contact_add_modal', [EntryController::class, 'contact_add_modal'])->name('entries.add_modal');  // for error passback
    Route::post('/contact_add_modal2', [EntryController::class, 'contact_add_modal2'])->name('entries.add_modal2');
    Route::get('/contact_add_modal2', [EntryController::class, 'contact_add_modal2'])->name('entries.add_modal2');  // for error passback
    Route::post('/add_new_entrytype', [EntryController::class, 'add_new_entrytype'])->name('entries.add.entrytype');
    Route::get('/add_new_entrytype', [EntryController::class, 'add_new_entrytype'])->name('entries.add.entrytype');  // for error passback
    Route::post('/new_contact_modal', [EntryController::class, 'new_contact_modal'])->name('entries.new_contact_modal');

    Route::post('/add_new_event_type', [CalendarController::class, 'add_new_event_type'])->name('calendar.add.event_type');
    Route::get('/add_new_event_type', [CalendarController::class, 'add_new_event_type'])->name('calendar.add.event_type');   // for error passback

    // Route::post('/newfiletype', [CasefiletypeController::class, 'addnewtype'])->name('files.addnewtype');  // Not needed?

    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::post('/calendar', [CalendarController::class, 'store'])->name('calendar.store');
    Route::post('/lookup_file4cal', [CalendarController::class, 'lookup_file'])->name('calendar.lookup_file');

    Route::post('/get_events', [CalendarController::class, 'get_events'])->name('calendar.get_events');
    Route::get('/get_events', [CalendarController::class, 'get_events']);
    Route::post('/event_placement', [CalendarController::class, 'event_placement'])->name('calendar.event_placement');

    Route::get('/adminmenu', [AdminController::class, 'menu'])->name('adminmenu');

    Route::get('/firm/edit', [FirmController::class, 'edit'])->name('firm.edit');
    Route::put('/firm/update', [FirmController::class, 'update'])->name('firm.update');
    Route::get('/firm/protocol-setup', [FirmController::class, 'protocolSetup'])->name('firm.protocol-setup');
    Route::get('/firm/browse-directory', [FirmController::class, 'browseDirectory'])->name('firm.browse-directory');

    Route::get('/entries/{entry}/document/{filename?}', [EntryController::class, 'serve_document'])->name('entries.document');

    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::get('/subscription/success', [SubscriptionController::class, 'success'])->name('subscription.success');
    Route::get('/subscription/billing-portal', [SubscriptionController::class, 'billingPortal'])->name('subscription.billing-portal');

});

require __DIR__.'/auth.php';
