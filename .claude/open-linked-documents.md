# Open Linked Documents — Issue & Options

## Problem
When a user clicks "Open Doc" on an entry with a linked document (e.g., .odt), the browser downloads a copy to the Downloads folder. Edits are made to the copy, not the original file. The user needs to edit the document at its original file location.

## Ruled Out
- **Server-side `shell_exec` open**: Only works when user is on the same machine as the server. App will be deployed remotely, so this won't work.
- **Serving a .bat/.command file**: Works technically, but browsers and OS security (SmartScreen, Gatekeeper) show multiple warnings. No clean way to whitelist. Mac equivalent (`.command`) has similar or worse friction.

## Recommended: Custom Protocol Handler (Option 2)
Register a custom URI scheme (e.g., `vantage://`) in the OS so the browser can hand off a file path to a local handler that opens the file directly.

### How it works
1. "Open Doc" button links to `vantage://open/C:/path/to/file.odt`
2. Browser passes the URL to a registered local handler program
3. Handler parses the path and opens the file with the default application
4. File is opened in place — edits save to the original location

### Windows setup
- Add a registry key under `HKEY_CLASSES_ROOT\vantage\shell\open\command`
- Points to a small handler script (.bat, .exe, or PowerShell)
- One-time setup per client machine
- Browser shows "Allow this app?" prompt on first use only

### Mac setup
- Register a custom URL scheme via a small .app bundle with an `Info.plist`
- Or use a Swift/AppleScript wrapper
- macOS may require the app to be signed or user-approved once

### Pros
- No download step, seamless after initial setup
- Works from any browser
- File opens at original location

### Cons
- Requires one-time client-side setup (registry entry or app install)
- Need to build/distribute the handler for each OS
- Browser confirmation prompt on first use

### Next Steps
- Determine target OS platforms (Windows only? Windows + Mac?)
- Build a minimal handler script/app
- Create an installer or setup instructions for client machines
- Update the "Open Doc" button to use `vantage://` links instead of file downloads
