function formatDate(dt, inputTime = false, allDay = false) {
    if (!dt) return '';
    dt = dt.toString();
    if (inputTime) {
        const theDate = dt.slice(5, 7) + '/' + dt.slice(8, 10) + '/' + dt.slice(2, 4);
        let theHour = parseInt(dt.slice(11, 13));
        const theMinutes = dt.slice(14, 16);
        let ap = 'am';
        if (theHour > 12) { theHour -= 12; ap = 'pm'; }
        if (!allDay) return theDate + ', ' + theHour + ':' + theMinutes + ap;
        return theDate + ' (all day)';
    }
    return dt.slice(5, 7) + '/' + dt.slice(8, 10) + '/' + dt.slice(2, 4);
}

// Width style as percentage string — used inline via :style="{ width: col.width }"
// Narrow columns (dates, amounts) get fixed-ish %, text columns expand to fill.
export const columns = {
    date1: {
        key: 'date1',
        label: (folder) => folder.date1_prompt,
        getValue: (entry, props) => formatDate(entry.date1, props.folders[entry.folder_id - 1].input_time, entry.all_day),
        width: '25%',
    },
    entrytype: {
        key: 'entrytype',
        label: (folder) => folder.entrytype_prompt ?? 'Type',
        getValue: (entry, props) => {
            const folder = props.folders[entry.folder_id - 1];
            const et = folder.entrytypes.find(t => t.id === entry.entrytype_id);
            return et ? et.name : '';
        },
        width: '25%',
    },
    from: {
        key: 'from',
        label: (folder) => folder.from_prompt,
        getValue: (entry, props) => {
            const contact = props.file_contacts.find(c => c.id === entry.from_contact_id);
            return contact ? contact.display_last_first : '';
        },
        width: '25%',
        hasLinkedDoc: true,
    },
    to: {
        key: 'to',
        label: (folder) => folder.to_prompt ?? 'To',
        getValue: (entry, props) => {
            const contact = props.file_contacts.find(c => c.id === entry.to_contact_id);
            return contact ? contact.display_last_first : '';
        },
        width: '25%',
    },
    date2: {
        key: 'date2',
        label: (folder) => folder.date2_prompt ?? 'Date 2',
        getValue: (entry) => formatDate(entry.date2),
        width: '25%',
    },
    note: {
        key: 'note',
        label: (folder) => folder.note_prompt ?? 'Note',
        getValue: (entry) => entry.note ?? '',
        width: '25%',
    },
    amount: {
        key: 'amount',
        label: (folder) => folder.amount_prompt ?? 'Amount',
        getValue: (entry) => entry.amount ? '$' + parseFloat(entry.amount).toFixed(2) : '',
        width: '20%',
    },
    response: {
        key: 'response',
        label: () => 'Response Due',
        getValue: (entry) => {
            if (!entry.expecting_response) return '';
            return formatDate(entry.date_response_expected);
        },
        width: '25%',
    },
};

const formats = [
    {
        key: 'standard',
        label: 'Standard Format',
        columns: ['date1', 'entrytype', 'from'],
        availableWhen: () => true,
    },
    {
        key: 'contacts',
        label: 'Contacts Format',
        columns: ['date1', 'from', 'to'],
        availableWhen: (folder) => !folder.hide_to_prompt,
    },
    {
        key: 'detailed',
        label: 'Detailed Format',
        columns: ['date1', 'entrytype', 'from', 'to'],
        availableWhen: (folder) => !folder.hide_to_prompt,
    },
    {
        key: 'with_notes',
        label: 'With Notes Format',
        getColumns: (folder) => folder.hide_entrytype_prompt ? ['date1', 'from', 'note'] : ['date1', 'entrytype', 'from', 'note'],
        availableWhen: () => true,
    },
    {
        key: 'amounts',
        label: 'Amounts Format',
        getColumns: (folder) => folder.hide_entrytype_prompt ? ['date1', 'from', 'amount'] : ['date1', 'entrytype', 'from', 'amount'],
        availableWhen: (folder) => !folder.hide_amount_prompt,
    },
    {
        key: 'dates',
        label: 'Dates Format',
        getColumns: (folder) => folder.hide_entrytype_prompt ? ['date1', 'date2', 'from'] : ['date1', 'date2', 'entrytype', 'from'],
        availableWhen: (folder) => !folder.hide_date2_prompt,
    },
    {
        key: 'response',
        label: 'Response Due Format',
        getColumns: (folder) => folder.hide_entrytype_prompt ? ['date1', 'from', 'response'] : ['date1', 'entrytype', 'from', 'response'],
        availableWhen: (folder) => !folder.hide_responseexpected_prompt,
    },
];

export function getAvailableFormats(folder) {
    if (!folder) return [formats[0]];
    return formats.filter(f => f.availableWhen(folder));
}

export function getColumns(formatKey, folder) {
    const format = formats.find(f => f.key === formatKey) || formats[0];
    const colKeys = format.getColumns ? format.getColumns(folder) : format.columns;
    return colKeys.map(key => columns[key]);
}
