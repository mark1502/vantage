# WelcomeAdmin.vue - Disable Next Button Until Attorney Added

## Problem
On step 3 (`state.count == 3`), non-attorney users are told they must add an attorney before completing registration, but the "Next" button was not disabled — allowing them to proceed without adding one.

## Solution
Added an `attorneyAdded` tracking flag and a computed property to control the Next button's disabled state.

## Changes (resources/js/Pages/WelcomeAdmin.vue)

1. **Added `attorneyAdded: false`** to the `state` reactive object.
2. **Added `canProceedFromStep3` computed property** — returns `true` if the registering user's role is "Attorney" (`form2.firm_role === 'Attorney'`) OR if `state.attorneyAdded` is `true`.
3. **Set `state.attorneyAdded = true`** in `submitAddUser()`'s `onSuccess` callback, so the flag is set only after a successful attorney submission.
4. **Added `:disabled="!canProceedFromStep3"`** to the Next button shown when `state.count == 3 && state.addUser != 1`.

## Behavior
- If the registering user selected "Attorney" as their role in step 2, the Next button is enabled immediately on step 3.
- If the registering user is not an attorney, the Next button is disabled until they successfully add an attorney via the "Add Attorney" form.
