/**
 * Shared status logic for entries that are expecting a response.
 *
 * Overdue-ness used to be decided by string-comparing the display label
 * ("Awaiting response" vs "Awaiting response (overdue)"), with the producing
 * function copied into three components. Callers that need the decision should
 * use isResponseOverdue(); the label is for display only.
 */

/** True when the expected response date has passed. */
export function isResponseOverdue(date_expected) {
    return new Date() > new Date(date_expected);
}

/** Human-readable status shown on the entry form. */
export function responseStatusLabel(date_expected) {
    return isResponseOverdue(date_expected) ? 'Awaiting response (overdue)' : 'Awaiting response';
}

/**
 * Text color for an entry that is expecting a response. Overdue entries also
 * get extra weight so the signal is not carried by color alone.
 *
 * text-overdue / text-pending are defined in resources/css/app.css and derive
 * from the theme's own error/success tokens, pushed to a readable lightness.
 */
export function statusTextClass(entry) {
    return isResponseOverdue(entry.date_response_expected)
        ? 'text-overdue font-semibold'
        : 'text-pending';
}

/**
 * Reduce a value to its local calendar date as "YYYY-MM-DD".
 *
 * Accepts the raw strings Eloquent sends for uncast columns
 * ("2026-06-08 17:07:00", "2026-06-08") as well as the Date objects the
 * datepicker produces. Returns null when there is no usable date.
 */
function toCalendarDate(value) {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        const month = String(value.getMonth() + 1).padStart(2, '0');
        const day = String(value.getDate()).padStart(2, '0');

        return `${value.getFullYear()}-${month}-${day}`;
    }

    const matched = String(value).match(/^(\d{4}-\d{2}-\d{2})/);

    return matched ? matched[1] : null;
}

/**
 * Whether a response that has already arrived came in after the day it was
 * expected.
 *
 * Compared as calendar dates rather than instants: responses.response_date is a
 * date with no time component, while entries.date_response_expected is a
 * datetime, and the two arrive as strings that `new Date()` resolves in
 * different time frames ("2026-06-08" as UTC midnight, "2026-06-08 17:07:00" as
 * local). Comparing the date portions is timezone-independent and matches how
 * the database compares the same two columns.
 *
 * Returns null when lateness is undecidable — date_response_expected is
 * nullable, and treating a missing date as the epoch would mark every response
 * late.
 */
export function isResponseLate(date_expected, date_received) {
    const expected = toCalendarDate(date_expected);
    const received = toCalendarDate(date_received);

    if (expected === null || received === null) {
        return null;
    }

    return received > expected;
}

/** Text color for one already-received response. Neutral when undecidable. */
export function responseLateClass(date_expected, date_received) {
    const late = isResponseLate(date_expected, date_received);

    if (late === null) {
        return 'text-base-content';
    }
    return late ? 'text-overdue font-semibold' : 'text-pending';
}
