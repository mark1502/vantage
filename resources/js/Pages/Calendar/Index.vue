<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from "@/Components/InputError.vue";
import { reactive, ref, computed, watch, onMounted, onUnmounted, nextTick, inject } from "vue";
import { Head, Link, useForm, router } from '@inertiajs/vue3';
// import  _debounce  from 'lodash/debounce';

import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

import '@/../../resources/css/fullcalendar-theme.css';

// import VueDatePicker from '@vuepic/vue-datepicker';
// import '@vuepic/vue-datepicker/dist/main.css'
import axios from 'axios';

const lookup = reactive({
    file: false,
    file_isChosen: false,
    file_chosen_name: "",
    file_list: Object,
})

lookup.file_list = {data: []};

const related_file = reactive({
    name: '',
    id: null,
    initial_name: '',
    initial_id: null,
});

const show_filters = ref(true);
const fullCalendar = ref(null);
let calApi = null;

const state = reactive({
    weekdaysOnly: false,
    extractedContent: '',
    eventContent: null,
    includeDueDates: false,
});

const props = defineProps({
    'firm_members': Object,
    'event_types': Object,
    'bg_colors': Object,
    'text_colors': Object,
    'new_event_type': Object,
});

const matching = reactive({ event_types: Object });

const calendarform_title = ref('Add New Event');

const calendar_form = useForm({
    formtype: "calendar",
    action: "",
    file_id: null,
    folder_id: 6,
    entry_id: null,
    entrytype_id: 33, // meeting, for now
    from_contact_id: null,
    date1: "",
    date2: "",
    note: "",
    allDay: false,
    fileSpecific: true,
    display_date_string: '',
});

let hold_calendar_form = {};

const entrytype_form = useForm({
    name: '',
    id: null,
    folder_id: null,
    isChosen: false,
    chosenName: '',
    lookup: false,
});

const calendarFor = reactive({
    initials: '****',
    id: 1,
});

const tooltip = reactive({
    class: '',
    timespan: '',
    text: '',
    file_name: '',
    visibility: false,
});

const tooltipStyles = reactive({
    color: '',
    backgroundColor: '',
});

const confirm_dialog = reactive({
    heading: 'Confirm Changes',
    statement: '',
    question: '',
    answer: '',
});

const cal_errors = reactive({
    file_id: '',
    folder_id: '',
    entrytype_id: '',
    from_contact_id: '',
});

const calendarOptions = reactive({
    // timezone: 'UTC',
    plugins: [
        dayGridPlugin,
        timeGridPlugin,
        interactionPlugin,
    ],
    headerToolbar: {
        start: 'prevYear,nextYear prev,next today',
        center: 'title',
        end: 'dayGridMonth,timeGridWeek,timeGridDay'
    },

    initialView: 'timeGridWeek',

    events: '/get_events?user1=' + calendarFor.id + '&include_due=' + state.includeDueDates,  // Here is the events url to get the calendar data

    editable: true,
    height: 'auto',
    fixedWeekCount: false,
    navLinks: true,
    slotMinTime: '07:00:00',

    dateClick:   function (eventInfo) { click_date(eventInfo); },
    eventClick:  function (eventInfo) { click_event(eventInfo); },
    eventDrop:   function (eventInfo) { event_placement(eventInfo, 'move'); },
    eventResize: function (eventInfo) { event_placement(eventInfo, 'resize'); },

    eventMouseEnter: function (mouseEnterInfo) {
        // console.log(mouseEnterInfo);

        let starting = new Date(mouseEnterInfo.event.startStr);
        let hours = starting.getHours();
        let mins = starting.getMinutes();
        if( hours > 12 ) hours -= 12;
        // let starting_time = hours < 10 ? '0' + hours + ':' : hours + ':';
        let starting_time = hours  + ':';
        starting_time += mins < 10 ? '0' + mins : mins;


        let ending = new Date(mouseEnterInfo.event.endStr);
        hours = ending.getHours();
        mins = ending.getMinutes();
        if( hours > 12 ) hours -= 12;
        // let ending_time = hours < 10 ? '0' + hours + ':' : hours + ':';
        let ending_time = hours + ':';
        ending_time += mins < 10 ? '0' + mins : mins;

        tooltip.timespan = starting_time + ' - ' + ending_time;


        tooltip.visibility = true;
        tooltip.text = mouseEnterInfo.event.title;
        tooltip.file_name = 'File: ';
        tooltip.file_name += mouseEnterInfo.event.extendedProps.file_id == '1' ? 'Not File Related' : mouseEnterInfo.event.extendedProps.file_name;

        tooltipStyles.color = mouseEnterInfo.event.textColor;
        tooltipStyles.backgroundColor = mouseEnterInfo.event.backgroundColor;
        
    },
    
    eventMouseLeave: function (mouseEnterInfo) {
        tooltip.visibility = false;
        tooltip.timespan = '';
        tooltip.text = '';
        tooltip.file_name = '';
    },    
}); // end calendarOptions



function submit_test_1() {  // on submit, first test if the event firm member was changed, if so, then confirm the change
    if( calendar_form.action != 'add' && calendar_form.from_contact_id != hold_calendar_form.from_contact_id ) {    // if the event_for user changed (and not adding new entry)
        let current_initials = findFirmMemberInitials( calendar_form.from_contact_id );     // get initials of the currently selected memebr
        let starting_initials = findFirmMemberInitials( hold_calendar_form.from_contact_id ) // get initials of the firm member at the start (when the form opened)

        confirm_dialog.heading = 'Confirm: Changed Event User';
        confirm_dialog.statement = 'This event was for user (' + starting_initials +'), but has been changed to user (' + current_initials + ').';
        confirm_dialog.question = 'Do you want this event to be for user (' + current_initials +')?';

        display_modal( 'confirm_event_for', true );
    } else {
        submit_test_2();    // no user change, so test for file change
    }

}


function submit_test_2() {  // on submit, test if the file changed.  If so, then confirm the change
    display_modal( 'confirm_event_for', false );

    confirm_dialog.heading = 'Confirm: Changed Event File';

    if( calendar_form.action != 'add' && (calendar_form.fileSpecific != hold_calendar_form.fileSpecific || calendar_form.file_id != hold_calendar_form.file_id) ) {
        if( calendar_form.fileSpecific != hold_calendar_form.fileSpecific ) {
            if( hold_calendar_form.fileSpecific == "false" ) { // initially, the event was not file related
                confirm_dialog.statement = 'Initially, this event was \"Not File Related\", but it will be changed to relate to file \"' + related_file.name +'\".';
                confirm_dialog.question = 'Do you want this event to be related to file \"' + related_file.name + '\"?';
            } else { // initially, was related to a file
                confirm_dialog.statement = 'This event was initially for file \"' + related_file.initial_name +'\", but it will be changed to be \"Not File Related\".';
                confirm_dialog.question = 'Do you want this event to become \"Not File Related\".';
            }
        }
        else if( calendar_form.file_id != hold_calendar_form.file_id ){
            confirm_dialog.statement = 'This event was intially for file \"' + related_file.initial_name + '\", but the file has been changed to \"' + related_file.name +'\".'
            confirm_dialog.question = 'Do you want this event to be for file \"' + related_file.name +'\"?'
        }

        display_modal( 'confirm_file_change', true);
    }
    else {  // no change of file, so submit the form
        submit_calendarform();
    }

}


function delete_event() {
    display_modal( 'confirm_delete_event', false);
    calendar_form.action = 'delete';
    submit_calendarform();
}


function submit_calendarform() {
    display_modal( 'confirm_file_change', false);


    if( calendar_form.fileSpecific != "true" ) {    // if not file specific, set id to 1 and clear the related file values
        calendar_form.file_id = 1;
        related_file.id = null;
        related_file.name = '';
        related_file.initial_id = null;
        related_file.initial_name = '';
    }

    clear_cal_errors();  // clear any form errors before submitting

    axios.post('/calendar', calendar_form )       // lookup search and list the response data
            .then(function (response) {
                clear_calendarform();
                calApi.refetchEvents();
            })
            .catch(function (error) {
                // console.log(error);
                // console.log(error.response.data.errors.file_id);

                Object.entries(error.response.data.errors).forEach(([key, value]) => {
                    cal_errors[key] = value[0];
                });

                //cal_errors.file_id = error.response.data.errors.file_id[0];
            });

} // end function
    

function refresh_calendar() {
    calendarOptions.events = '/get_events' + '?user1=' + calendarFor.id + '&include_due=' + state.includeDueDates;
}


function display_calendarform(setting) {    // function to display or close the calendar form
    if( setting == true) {
        document.getElementById('calendarform_modal').showModal();
    } else if( setting == false ) {
        document.getElementById('calendarform_modal').close();
    }

}


function display_modal( which_modal, setting = null ) {
    let modal_name = '';

    switch ( which_modal ) {
        case 'confirm_event_for':
        case 'confirm_file_change':
        case 'entrytype':
        case 'confirm_delete_event':
            modal_name = which_modal + '_modal';
            break;            
    }

    if( modal_name && ( setting == true || setting == 'show' || setting == 'on' ) ) {
        document.getElementById( modal_name ).showModal();
    } else if( modal_name && ( setting == false || setting == 'hide' || setting == 'off' ) ) {
        document.getElementById( modal_name ).close();
    }
}


function clicked_AddTypeButton() {
    entrytype_form.reset();
    display_modal( 'entrytype', true );
}

function clear_calendarform() {
    display_calendarform(false);  // hide contact form

    calendar_form.action = "";
    calendar_form.file_id = null;
    calendar_form.entry_id = null;
    calendar_form.entrytype_id = null;
    calendar_form.from_contact_id = null;
    calendar_form.date1 = "";
    calendar_form.date2 = "";
    calendar_form.note = "";
    calendar_form.allDay = false;
    calendar_form.fileSpecific = "true";
    calendar_form.display_date_string = "";

    related_file.id = null;
    related_file.name = '';
    lookup.file = false;
    lookup.file_chosen_name = '';

    clear_cal_errors();
}

function clear_cal_errors() {
    Object.entries(cal_errors).forEach(([key, value]) => {
        cal_errors[key] = '';
    });
}


function lookup_related_file() {
    if (lookup.file_isChosen == false || lookup.file_chosen_name != related_file.name) {       // name isn't chosen or display doesn't match chosen name
        lookup.file = true;
    }

    if( related_file.name.length > 0 ) {                                                        // if something is entered in related_file.name, then lookup and list the response data
        axios.post('/lookup_file4cal', { search: related_file.name })       
        .then(function (response) {
            lookup.file_list = response.data;
        });
    }
    // debouncedLookup();
}

function clicked_file_list(index) {
    calendar_form.file_id = lookup.file_list.data[index].id;
    related_file.id = lookup.file_list.data[index].id;
    related_file.name = lookup.file_list.data[index].name;

    lookup.file = false;
    lookup.file_isChosen = true;
    lookup.file_chosen_name = related_file.name;
}


function click_date(eventInfo) {
    // console.log(eventInfo.allDay);
    
    clear_calendarform();

    calendarform_title.value = 'Add New Event'

    calendar_form.action = 'add';
    calendar_form.allDay = eventInfo.allDay;
    calendar_form.date1 = eventInfo.dateStr.slice(0, 19).replace('T', ' ');  // cut off the time offset and replace the 'T' with a ' '

    var enddate = new Date();
    enddate = eventInfo.date;
    enddate.setUTCHours(enddate.getHours() + 1);

    if( eventInfo.allDay != true  /* eventInfo.view.type != 'dayGridMonth' */ ) {    // if not a Month grid or not an allday event, then set the datetime display and the end date
        calendar_form.display_date_string = convertDateTimeToLocal(eventInfo.dateStr);
        calendar_form.date2 = enddate.toISOString().slice(0, 19).replace('T', ' ');
    }
    else {                                          // else (allday), just set the date display (without time)
        calendar_form.display_date_string = convertDateToLocal(eventInfo.dateStr) + ' (all day)';
    }

    if( calendarFor.id != 1 ) {
        calendar_form.from_contact_id = calendarFor.id;
    }
    

    nextTick(() => {
        display_calendarform(true);
    });
    
}


function click_event(eventInfo) {
    clear_calendarform();
    calendarform_title.value = 'Edit an Event'
    calendar_form.action = 'edit';
    calendar_form.entry_id = eventInfo.event.id;

    calendar_form.from_contact_id = eventInfo.event.extendedProps.event_for;
    calendar_form.entrytype_id    = eventInfo.event.extendedProps.entrytype_id;
    calendar_form.note            = eventInfo.event.extendedProps.note;

    calendar_form.file_id = eventInfo.event.extendedProps.file_id;

    if( calendar_form.file_id == 1 ) {
        calendar_form.fileSpecific = "false";
        related_file.id = 1;
        related_file.name = '';
        related_file.initial_id = 1;
        related_file.initial_name = '';
    }
    else {
        calendar_form.fileSpecific = "true";
        related_file.id = calendar_form.file_id;
        related_file.name = eventInfo.event.extendedProps.file_name;
        related_file.initial_id = calendar_form.file_id;
        related_file.initial_name = eventInfo.event.extendedProps.file_name;
    }

    calendar_form.allDay = eventInfo.event.allDay;
    calendar_form.date1 = eventInfo.event.startStr.slice(0, 19).replace('T', ' '); // cut off the time offset and replace the 'T' with a ' '
    calendar_form.date2 = eventInfo.event.endStr ? eventInfo.event.endStr.slice(0, 19).replace('T', ' ') : ''; // cut off the time offset and replace the 'T' with a ' '

    if (eventInfo.event.allDay == true) {
        calendar_form.display_date_string = eventInfo.event.start.toLocaleDateString() + ' (all day)';
    }
    else {
        calendar_form.display_date_string = eventInfo.event.start.toLocaleDateString() + ' @ ' + eventInfo.event.start.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
        if (eventInfo.event.end) {
            calendar_form.display_date_string += ' - ' + eventInfo.event.end.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
        }
    }

    hold_calendar_form = { ...calendar_form};  // clone a copy of the calendar form

    display_calendarform(true);
}


function event_placement(eventInfo, ptype) {
    calendar_form.action = ptype;   // assign the action/placement type
    calendar_form.entry_id = eventInfo.event.id;
    calendar_form.allDay = eventInfo.event.allDay;
    if( eventInfo.event.allDay == true ) {
        calendar_form.date1 = eventInfo.event.startStr;
        calendar_form.date2 = eventInfo.event.endStr;
    } else {
        calendar_form.date1 = eventInfo.event.startStr.slice(0, 19).replace('T', ' '); // cut off the time offset and replace the 'T' with a ' '
        calendar_form.date2 = eventInfo.event.endStr ? eventInfo.event.endStr.slice(0, 19).replace('T', ' ') : ""; // if endStr, do the same with date2, otherwise ""
    }

    calendar_form.post('/event_placement', {
        preserveState: true,
        onSuccess: () => {
            calApi.refetchEvents();
        }
    });
}


function convertDateTimeToLocal(datetimeString) {
    const localDate = new Date(datetimeString);
    const localDateString = localDate.toLocaleDateString();
    const localTimeString = localDate.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
    return `${localDateString} @ ${localTimeString}`;
}

function convertDateToLocal(dt) {
    return dt.slice(5, 7) + '/' + dt.slice(8, 10) + '/' + dt.slice(0, 4);
}

const keypress_handler = (e) => {
    switch (e.key) {
        // case 'Escape':
        //     e.preventDefault();
        //     if (document.getElementById('calendarform_modal').checked == true) {
        //         clear_calendarform();
        //     }
        //     break;
    }
}


// const debouncedLookup = debounce(() => {
//   axios.post('/lookup_file4cal', { search: related_file.name })
//     .then(response => {
//       lookup.file_list = response.data;
//     })
//     .catch(error => {
//       console.error('Error fetching file list:', error);
//     });
// }, 300); // Adjust the debounce delay as needed

/*
const formatDate = (date, shortened=false) => {
  let sendback = new Intl.DateTimeFormat("en-US", {
    hour: "numeric",
    minute: "numeric",
    hour12: true,
  }).format(new Date(date));
  if( shortened == true ) {
    return sendback.substring(0, sendback.length - 2);
  }
  else return sendback;
};

function formatTooltip(event) {
    let textback = "";
    // textback += formatDate(event.start) + " - " + formatDate(event.end);
    textback +=  event.title;
    if(event.extendedProps.file_id != 1) {
        textback += ' --> File name: ' + event.extendedProps.file_name;
    }
    return textback;
} 
*/

function findFirmMemberInitials( lookup_id ) {
    let idx = props.firm_members.findIndex( (element) => element.id == lookup_id);  // find the index of firm member matching the id
    return props.firm_members[idx].member_initials;                                 // return that members initials
}


function lookup_entrytype() {
    matching.event_types = props.event_types.filter( function (e) {
        if( e.name.toLowerCase().includes( entrytype_form.name.toLowerCase() ) && this.count < 6 ) {  // set the test and limit the filtered list to 6 entries
            this.count ++;
            return true;
        }
        else return false;
    }, {count: 0} );
};

function clicked_matchingEntrytype(index) {
    entrytype_form.name = matching.event_types[index].name;
    entrytype_form.id = matching.event_types[index].id;
    entrytype_form.isChosen = true;
    entrytype_form.chosenName = entrytype_form.name;    
}


function clicked_entrytypeModal_button(clicked_button) {
    if (clicked_button == 'ok') {
        if(entrytype_form.isChosen == true && entrytype_form.name == entrytype_form.chosenName) {
            calendar_form.entrytype_id = entrytype_form.id;
            display_modal('entrytype', false);
        }
        else {
            entrytype_form.folder_id = 6;
            entrytype_form.post('/add_new_event_type', { only: ['event_types', 'new_event_type'], preserveState: true,
            onSuccess: () => {
                calendar_form.entrytype_id = props.new_event_type.id;
                display_modal('entrytype', false);
             },
            onError: (errors) => {
                console.log(errors);
            }});

        }
    }
    else if (clicked_button == 'cancel') {
        entrytype_form.reset();
        display_modal('entrytype', false);
    }
}

onMounted(() => {
    calApi = fullCalendar.value.getApi();  // assign fullcalendar api
    document.addEventListener('keydown', keypress_handler);
});

onUnmounted(() => document.removeEventListener('keydown', keypress_handler));

</script>

<template>
    <Head title="Calendar" />

    <AuthenticatedLayout>

        <template #header>
            <div class="flex align-baseline">
                <div class="w-2/3 ml-3 text-base-content">
                    <label for="calendarFor" class="ml-6 mr-3 font-semibold text-xl">Calendar events for:</label>
                    <select v-model="calendarFor.id" class=" border rounded-md p-1 bg-base-100" id="calendarFor" @change="refresh_calendar()">
                        <option value="1">****</option>
                        <option v-for="firm_member, index in props.firm_members" :key="firm_member.id" :value="firm_member.id">
                            {{ firm_member.member_initials }}
                        </option>
                    </select>
                    <label for="includeDueDates" class="ml-8">
                        <input type="checkbox" v-model="state.includeDueDates" @change="refresh_calendar()" id="includeDueDates">
                        Include Due Dates
                    </label>
                    <label for="weekdaysOnly" class="w-48 ml-10">
                        <input type="checkbox" v-model="state.weekdaysOnly" id="weekdaysOnly" @click="calendarOptions.weekends = state.weekdaysOnly" />
                        - Weekdays Only
                    </label>

                </div>
                <div class="w-1/3 text-right pr-12 relative">
                    <!-- Fade Slide
                    <Transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 -translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-2"
                    >
                    -->
                    <!-- Scale effect
                    <Transition
                        enter-active-class="transition ease-out duration-200 transform"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-150 transform"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                    -->
                    <!--
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="transform -translate-y-4 opacity-0"
                        enter-to-class="transform translate-y-0 opacity-100"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="transform translate-y-0 opacity-100"
                        leave-to-class="transform -translate-y-4 opacity-0"
                    >
                    -->
                    <Transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 -translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-2"
                    >
                        <div 
                            v-if="tooltip.visibility"
                            class="absolute top-full right-0 shadow-lg border rounded-lg p-4 z-50 mt-2"
                            style="min-width: 300px;"
                            :style="tooltipStyles"
                        >
                            <div class="text-left">
                                <div class="font-bold mb-2">Event Information:</div>
                                <div class="mt-2 text-xs">
                                    {{ tooltip.timespan }}
                                </div>
                                <div class="mt-2 text-sm">
                                    {{ tooltip.text }}
                                </div>
                                <div class="my-2 text-xs">
                                    {{ tooltip.file_name }}
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>

        </template>

        <div class="py-3">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden sm:rounded-lg" id="ContactScreen" name="ContactScreen">
                    <div class="flex">
                        <!-- <div v-if="show_filters" id="left_side" class="min-w-48 m-1 border"> -->
                        <!-- The follwing div is hidden, but would show a filter if set to true, but not used -->
                        <div v-if="false" id="left_side" class="min-w-48 m-1 border"> 
                            <!-- <div class="flex mt-8">
                                <label for="calendarFor" class="ml-3 mr-4 font-bold text-lg">Calendar for:</label>
                                <select v-model="calendarFor.id" class=" border rounded-md p-1" id="calendarFor"
                                    @change="refresh_calendar()">
                                    <option value="1">****</option>
                                    <option v-for="firm_member, index in props.firm_members" :key="firm_member.id"
                                        :value="firm_member.id">
                                        {{ firm_member.member_initials }}
                                    </option>
                                </select>
                            </div> -->
                            <div class="flex mt-8 ml-6">
                                <input type="checkbox" v-model="state.weekdaysOnly" id="weekdaysOnly"
                                    @click="calendarOptions.weekends = state.weekdaysOnly" />
                                <label for="weekdaysOnly" class="w-48 ml-1">- Weekdays Only</label>
                            </div>
                            <p v-show="state.weekdaysOnly == true" class="ml-6 mr-2 mt-1 text-xs text-red-700">Please note: Weekend calendar entries are NOT displayed!</p>
                            <div class="mt-40 ml-4 text-blue-900 font-bold" :class="tooltip.visibility">Event Information:
                            </div>
                            <div class="border" :class="tooltip.visibility" :style="tooltipStyles">
                                <div class="mt-2 ml-4 w-52 text-xs">
                                    {{ tooltip.timespan }}
                                </div>
                                <div class="mt-2 ml-4 w-52 text-sm">
                                    {{ tooltip.text }}
                                </div>
                                <div class="my-2 ml-4 w-52 text-xs">
                                    {{ tooltip.file_name }}
                                </div>
                            </div>
                        </div>
                        <div id="right_side" class="">
                            <div class="p-4 text-gray-900">
                                <FullCalendar ref='fullCalendar' :options="calendarOptions" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <!-- Modal - Calendar Form -->
        <dialog id="calendarform_modal" class="modal">
            <div class="modal-box w-11/12 max-w-3xl h-[548px]">
                <h3 class="font-bold text-2xl text-center text-blue-800">{{ calendarform_title }}</h3>
                <!-- START of This Entry Form -->
                <form id="entry_form" @submit.prevent="submit_ThisEventForm" class="max-w-5xl mx-auto mt-1">

                    <div class="flex place-items-baseline mt-4">
                        <label for="" class="mr-9 font-semibold text-sm">Event Date:</label>
                        <p>{{ calendar_form.display_date_string }}</p>
                        <!-- <div class="flex items-center ml-12">
                            <input type="checkbox" v-model="calendar_form.allDay" id="check_allDay" value="true"
                                @change="setAllDay()">
                            <label for="check_allDay" class="ml-1">- All Day</label>
                        </div> -->
                    </div>

                    <!-- Event for droplist -->
                    <div class="flex place-items-baseline mt-4">
                        <label for="select_eventFor" class="mr-12 font-semibold text-sm">Event for:</label>
                        <select v-model="calendar_form.from_contact_id" id="select_eventFor" class="border rounded-md p-1 bg-white" :disabled="calendarFor.id != 1">
                            <!-- <option value="">Select User</option> -->
                            <option v-for="firm_member, index in props.firm_members" :key="firm_member.id" :value="firm_member.id">
                                {{ firm_member.member_initials }}
                            </option>
                        </select>
                    </div>
                    <InputError class="mt-2 ml-28" :message="cal_errors.from_contact_id" />

                    <!-- Entrytype Row -->
                    <div class="flex place-items-baseline mt-4">
                        <label class="text-sm font-semibold w-28">
                            Event Type:
                        </label>
                        <select v-model="calendar_form.entrytype_id" id="entry_entrytype_select"
                            class="w-72 p-2 border font-normal text-sm text-gray-900 bg-white disabled:text-gray-900 disabled:font-normal">
                            <option v-for="event_type, index in props.event_types" :key="event_type.id"
                                :value="event_type.id">
                                {{ event_type.name }}
                            </option>
                        </select>
                        <button type="button" class="btn btn-xs btn-outline text-xs font-semibold w-32 ml-8"
                            @click="clicked_AddTypeButton()">
                            Add New Type
                        </button>
                    </div>
                    <InputError class="mt-2 ml-28" :message="cal_errors.entrytype_id" />

                    <!-- Note Row -->
                    <div class="flex place-items-baseline mt-4">
                        <label class="text-sm font-semibold w-28">
                            Event Information:
                        </label>
                        <textarea v-model="calendar_form.note" id="calendar_note" rows="3"
                            class="w-[470px] border disabled:border-0 text-sm resize-none py-0 px-2 leading-normal" />
                    </div>


                    <!-- File Related Row -->
                    <div class="mt-4 border p-4 w-[720px]">
                        <div class="flex place-items-baseline">
                            <input type="radio" id="fileSpecific" v-model="calendar_form.fileSpecific" value="true" />
                            <label for="fileSpecific" class="ml-1"> - File Related</label>
                        </div>


                        <div v-show="calendar_form.fileSpecific == 'true'" id="related_file_div" class="flex items-baseline ml-12 mt-1">
                            <label for="entry_from" class="text-sm font-semibold">File:</label>
                            <input v-model="related_file.name" id="display_related_file" @input="lookup_related_file()"
                                autocomplete="off" class="ml-3 input input-bordered input-sm rounded-sm w-96 disabled:bg-gray-50" />
                        </div>
                        <InputError class="mt-2 ml-24" :message="cal_errors.file_id" />

                        <table v-show="lookup.file == true && related_file.name && lookup.file_list.data"
                            class="mt-0 ml-10 border border-gray-400 suggestion-table bg-white w-80">
                            <tbody>
                                <tr v-for="file, index in lookup.file_list.data" :key="file.id" @click="clicked_file_list(index)">
                                    <td class="pl-4 py-1 text-sm hover:bg-gray-200 hover:cursor-default">
                                        {{ file.name }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <table v-show="lookup.file == true && related_file.name && lookup.file_list.data.length == 0"
                            class="mt-0 ml-10 border border-red-400 suggestion-table bg-white w-80">
                            <tbody>
                                <tr>
                                    <td class="pl-4 py-1 text-sm">
                                        File Name Not Found
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="flex pt-4">
                            <input type="radio" id="not_fileSpecific" v-model="calendar_form.fileSpecific" value="false" />
                            <label for="not_fileSpecific" class="ml-1"> - Not File Related</label>
                        </div>

                    </div>

                </form>

                <div class="mt-10">
                    <a class="btn btn-primary w-28 ml-[248px] mr-10" @click="submit_test_1()">Ok</a>
                    <a class="btn btn-primary mr-36" @click="clear_calendarform(false)">Cancel</a>
                    <a v-if="calendar_form.action !== 'add'" class="btn btn-error btn-outline" @click="display_modal('confirm_delete_event', true)">Delete</a>
                </div>
            </div>
        </dialog>


        <!-- Modal - confirm event for change -->
        <dialog id="confirm_event_for_modal" class="modal">
            <div class="modal-box z-50">
                <h3 class="font-bold text-2xl text-center">{{ confirm_dialog.heading }}</h3>
                <p class="text-lg mt-4">{{ confirm_dialog.statement }}</p>
                <p class="text-lg mt-4">{{ confirm_dialog.question }}</p>
                <div class="modal-action justify-center mt-12">
                    <form method="dialog">
                        <a class="btn mr-10 w-28" @click="submit_test_2()">Yes</a>
                        <a class="btn" @click="display_modal( 'confirm_event_for', false)">No</a>
                    </form>
                </div>
            </div>
        </dialog>        


        <!-- Modal - confirm > file change -->
        <dialog id="confirm_file_change_modal" class="modal">
            <div class="modal-box z-50">
                <h3 class="font-bold text-2xl text-center">{{ confirm_dialog.heading }}</h3>
                <p class="text-base mt-4">{{ confirm_dialog.statement }}</p>
                <p class="text-base mt-4">{{ confirm_dialog.question }}</p>
                <div class="modal-action justify-center mt-12">
                    <form method="dialog">
                        <a class="btn mr-10 w-28" @click="submit_calendarform()">Yes</a>
                        <a class="btn" @click="display_modal( 'confirm_file_change', false )">No</a>
                    </form>
                </div>
            </div>
        </dialog>        

        <!-- Modal - confirm > delete event -->
        <dialog id="confirm_delete_event_modal" class="modal">
            <div class="modal-box z-50">
                <h3 class="font-bold text-2xl text-center">Confirm Deletion</h3>
                <!-- <p class="text-base mt-4">{{ confirm_dialog.statement }}</p> -->
                <p class="text-lg mt-3">Do you want to delete this event?</p>
                <div class="modal-action justify-center mt-8">
                    <form method="dialog">
                        <a class="btn btn-sm btn-primary btn-outline mr-10 w-28" @click="delete_event()">Yes</a>
                        <a class="btn btn-sm btn-primary btn-outline" @click="display_modal( 'confirm_delete_event', false )">No</a>
                    </form>
                </div>
            </div>
        </dialog>        



        <!-- Modal - add new event type -->
        <dialog id="entrytype_modal" class="modal">
            <div class="modal-box w-11/12 max-w-3xl z-50">
                <h3 class="font-bold text-2xl text-center">Add New Event Type</h3>
                <form>
                    <div class="flex mt-8 items-baseline">
                        <label for="type_name" class="text-xl">
                            Event Type:
                        </label>
                        <input v-model="entrytype_form.name" id="type_name" name="type_name" @input="lookup_entrytype()"
                        class="input input-bordered ml-4 w-[500px]" autocomplete="off" />
                    </div>
                    <table v-if="matching.event_types.length > 0 && entrytype_form.name.length > 0 && entrytype_form.isChosen == false" class="mt-2 ml-32 border w-80">
                        <tr v-for="entrytype, index in matching.event_types" :key="entrytype.id" @click="clicked_matchingEntrytype(index)">
                            <td class="pl-4 py-1 text-sm hover:bg-gray-200 hover:cursor-default">
                                {{ entrytype.name }}
                            </td>
                        </tr>
                    </table>

                </form>
                <div class="modal-action justify-center mt-12">
                    <button type="button" class="btn btn-primary mr-10 w-28" @click="clicked_entrytypeModal_button('ok')">Ok</button>
                    <button type="button" class="btn btn-primary" @click="display_modal( 'entrytype', false )">Cancel</button>
                </div>

            </div>
        </dialog>        

    </AuthenticatedLayout>
</template>
<style>
/*
.vdtp .dp__input {
    height: 48px;
    width: 252px;
    border-radius: 4px;
    border-color: lightgray;
}

.vdtp-disabled .dp__input {
    height: 48px;
    width: 252px;
    border-radius: 4px;
    border: 0;
}

.vdtp_sm .dp__input {
    height: 32px;
    width: 180px;
    border-radius: 6px;
    border-color: lightgray;
    font-size: 14px;
}

.vdtp-disabled_sm .dp__input {
    height: 32px;
    width: 180px;
    border-radius: 6px;
    border: 0;
    font-size: 14px;
}
*/
.dp__main {
    width: 200px;
}

input[type="text"]:disabled {
    background: #FAFAFA;
}

select:disabled {
    background: #FAFAFA;
}

textarea:disabled {
    background: #FAFAFA;
}

#name:disabled {
    background: #FAFAFA;
}

#contact_id:disabled {
    background: #FAFAFA;
}

#summary:disabled {
    background: #FAFAFA;
}
/*
.my-datepicker .dp__input {
    height: 48px;
    width: 252px;
    border-radius: 8px;
    border-color: lightgray;
}
*/
.lookupContainer {
    position: relative;
}

.autocomplete-suggestions {
    position: absolute;
    z-index: 100;
    background-color: #fff;
    border: 1px solid #ccc;
    list-style: none;
    left: 50px;
    right: 0;
    padding: 2;
    margin: 0;
}

.suggestion-table {
    position: absolute;
    z-index: 100;
    left: 105px;
}

</style>