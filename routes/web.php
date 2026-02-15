<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Controllers\FileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ViewController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactRoleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\FiletypeController;
use App\Http\Controllers\PreferenceController;

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
    Route::get('/welcome_admin', [WelcomeController::class, 'welcome_admin'])->name('welcome_admin');
    Route::get('/welcome_user', [WelcomeController::class, 'welcome_user'])->name('welcome_user');
    Route::post('/welcomeadmin', [WelcomeController::class, 'postWelcomeAdmin'])->name('postWelcomeAdmin');
    Route::post('/donewelcomeadmin',  [WelcomeController::class, 'doneWelcomeAdmin']);
});

    // all the other routes require auth and welcomed
Route::middleware('auth','welcomed')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/dashboard', function() {           // dashboard route just renders the dashboard without a controller
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::resource('users', UserController::class)->names('users');
    Route::resource('contacts', ContactController::class)->names('contacts');
    Route::resource('files', FileController::class)->names('files');
    Route::resource('files.entries', EntryController::class)->names('entries');
    Route::resource('filetypes', FiletypeController::class)->names('filetypes');
    Route::resource('views', ViewController::class)->names('views');
    Route::resource('folders', FolderController::class)->names('folders');
    Route::resource('folders.entrytypes', EntrytypeController::class)->names('entrytypes');

    // Contact Roles routes
    Route::post('/contact-roles', [ContactRoleController::class, 'store'])->name('contact-roles.store');
    Route::put('/contact-roles/{contactRole}', [ContactRoleController::class, 'update'])->name('contact-roles.update');
    Route::delete('/contact-roles/{contactRole}', [ContactRoleController::class, 'destroy'])->name('contact-roles.destroy');

    Route::get('/users/{user}/preferences', [PreferenceController::class, 'index'])->name('preferences.index');
    Route::post('/preferences/eventcolors', [PreferenceController::class, 'eventcolor_update'])->name('preferences.eventcolors');
    Route::get('/preferences/updateEntrytypes', [PreferenceController::class, 'update_entrytypes']);


    Route::post('/lookup_file', [FileController::class, 'lookup_file'])->name('files.lookup_file');
    Route::post('setDefaultFileType', [FiletypeController::class, 'set_default_type']);
    Route::put('/toggle_read/{entry}', [EntryController::class, 'toggle_read']);

    Route::post('/lookup_contact', [EntryController::class, 'lookup_contact'])->name('entries.lookup_contact');
    Route::post('/contact_add_modal', [ EntryController::class, 'contact_add_modal'])->name('entries.add_modal');
    Route::get('/contact_add_modal', [ EntryController::class, 'contact_add_modal'])->name('entries.add_modal');  // for error passback
    Route::post('/contact_add_modal2', [ EntryController::class, 'contact_add_modal2'])->name('entries.add_modal2');
    Route::get('/contact_add_modal2', [ EntryController::class, 'contact_add_modal2'])->name('entries.add_modal2');  // for error passback
    Route::post('/add_new_entrytype', [EntryController::class, 'add_new_entrytype'])->name('entries.add.entrytype');
    Route::get('/add_new_entrytype', [EntryController::class, 'add_new_entrytype'])->name('entries.add.entrytype');  // for error passback
    Route::post('/new_contact_modal', [ EntryController::class, 'new_contact_modal'])->name('entries.new_contact_modal');

    Route::post('/add_new_event_type', [CalendarController::class, 'add_new_event_type'])->name('calendar.add.event_type');
    Route::get('/add_new_event_type', [CalendarController::class, 'add_new_event_type'])->name('calendar.add.event_type');   // for error passback

    // Route::post('/newfiletype', [CasefiletypeController::class, 'addnewtype'])->name('files.addnewtype');  // Not needed?

    
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index'); 
    Route::post('/calendar', [CalendarController::class, 'store'])->name('calendar.store'); 
    Route::post('/lookup_file4cal', [CalendarController::class, 'lookup_file'])->name('calendar.lookup_file');

    
    Route::post('/get_events', [CalendarController::class, 'get_events'])->name('calendar.get_events');
    Route::get('/get_events', [CalendarController::class, 'get_events']);
    Route::post('/move_event', [CalendarController::class, 'move_event'])->name('calendar.move_event');
    Route::post('/resize_event', [CalendarController::class, 'resize_event'])->name('calendar.resize_event');
    Route::post('/event_placement', [CalendarController::class, 'event_placement'])->name('calendar.event_placement');

    
    Route::get('/adminmenu', [ AdminController::class, 'menu'])->name('adminmenu');

});

require __DIR__.'/auth.php';
