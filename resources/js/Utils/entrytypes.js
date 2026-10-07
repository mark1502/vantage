/**
 * Helpers for entrytype / event-type lists. Each type is { id, folder_id, name, faux_deleted }.
 * Deleted (faux_deleted) types stay in the lists so names still display; choosers must hide them.
 */

/** Types that aren't deleted. */
export function activeEntrytypes(types) {
    return (types ?? []).filter(t => !t.faux_deleted);
}

/**
 * Options for a type <select>: the active types, plus the type with id currentId if that one is deleted
 * (so an entry/event that already uses a deleted type still shows it). Each option gets a `label`.
 */
export function selectableEntrytypes(types, currentId = null) {
    return (types ?? [])
        .filter(t => !t.faux_deleted || (currentId !== null && t.id === currentId))
        .map(t => ({ ...t, label: t.faux_deleted ? t.name + ' (deleted)' : t.name }));
}

/** Put a type returned by add_new_entrytype into a list: un-delete it if already there (restored), else insert it sorted by name. Mutates the list. */
export function upsertEntrytype(types, type) {
    const known = types.find(t => t.id === type.id);
    if (known) {
        known.faux_deleted = false;
        return;
    }
    types.push(type);
    types.sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }));
}
