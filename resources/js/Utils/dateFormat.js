/**
 * Shared date/time display formatting. Inputs are server date strings such as
 * '2026-10-07 13:30:00', '2026-10-07', or FullCalendar ISO strings such as '2026-10-07T13:30:00-04:00'.
 */

/** 'h:mmam' / 'h:mmpm' from the HH:MM at positions 11–15. '' if there is no time part. */
export function formatTime(dt) {
    if (!dt) return '';
    const str = dt.toString();
    if (str.length < 16) return '';
    let hours = parseInt(str.slice(11, 13), 10);
    const minutes = str.slice(14, 16);
    const ap = hours >= 12 ? 'pm' : 'am';
    if (hours > 12) hours -= 12;
    if (hours === 0) hours = 12;
    return hours + ':' + minutes + ap;
}

/** 'MM/DD/YY', or 'MM/DD/YY, h:mmam' when inputTime is truthy, or 'MM/DD/YY (all day)' when allDay is also truthy. */
export function formatDate(dt, inputTime = false, allDay = false) {
    if (dt === null || dt === undefined || dt === '') return '';
    const str = dt.toString();
    const theDate = str.slice(5, 7) + '/' + str.slice(8, 10) + '/' + str.slice(2, 4);
    if (!inputTime) return theDate;
    if (allDay) return theDate + ' (all day)';
    return theDate + ', ' + formatTime(str);
}

/** 'MM-DD-YYYY' (used by the Files index). */
export function formatDateLong(dt) {
    if (dt === null || dt === undefined || dt === '') return '';
    const str = dt.toString();
    return str.slice(5, 7) + '-' + str.slice(8, 10) + '-' + str.slice(0, 4);
}
