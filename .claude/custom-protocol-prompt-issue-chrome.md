# Chrome/Edge Protocol Handler Prompt Issue

## Problem
When a user clicks "Open Doc" on Entries/Index, Chrome and Edge show a confirmation prompt every time the `vantage://` protocol is invoked. There is no "Always allow" checkbox in these browsers (unlike Firefox, which offers one).

## Root Cause
Chromium-based browsers intentionally suppress the "Always allow" option for custom protocol handlers by default as a security measure.

## Solution
Chrome and Edge support a policy called **`AutoLaunchProtocolsFromOrigins`** that skips the prompt for specific protocols from specific origins.

### Registry entries needed (adjust origin to match production URL):

```reg
[HKEY_LOCAL_MACHINE\SOFTWARE\Policies\Google\Chrome]
"AutoLaunchProtocolsFromOrigins"="[{\"protocol\":\"vantage\",\"allowed_origins\":[\"https://your-production-url.com\"]}]"

[HKEY_LOCAL_MACHINE\SOFTWARE\Policies\Microsoft\Edge]
"AutoLaunchProtocolsFromOrigins"="[{\"protocol\":\"vantage\",\"allowed_origins\":[\"https://your-production-url.com\"]}]"
```

Use `*` for `allowed_origins` to allow from any origin.

### Notes
- This could be added to `vantage-protocol.reg` so it installs alongside the protocol handler.
- Firefox already works — it offers a "Remember my choice" checkbox natively.
- The `HKEY_LOCAL_MACHINE` path requires admin rights. `HKEY_CURRENT_USER\SOFTWARE\Policies\...` may also work for per-user installs.

## References
- [AutoLaunchProtocolsFromOrigins policy docs](https://admx.help/?Category=Chrome&Policy=Google.Policies.Chrome::AutoLaunchProtocolsFromOrigins)
- [Bypassing AppProtocol Prompts](https://textslashplain.com/2020/02/20/bypassing-appprotocol-prompts/)
